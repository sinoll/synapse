<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\Comment;
use common\models\Post;
use common\models\PostComment;
use common\models\User;
use restful\components\ApiPagination;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use RuntimeException;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

final class CommentController extends ApiController
{
    protected function publicActions(): array
    {
        return ['index', 'replies'];
    }

    public function actionIndex(): array
    {
        $queryParams = Yii::$app->request->getQueryParams();
        $postId = filter_var($queryParams['post_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($postId === false) {
            throw new ValidationException(['post_id' => ['post_id 必须是正整数。']]);
        }
        [$page, $perPage] = ApiPagination::parse($queryParams);
        $post = $this->findVisiblePost((int) $postId, $this->viewerId());

        $query = PostComment::find()->where(['post_id' => $post->id, 'depth' => 0]);
        $total = (int) (clone $query)->count();
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $roots = $page > $totalPages ? [] : $query
            ->with(['comment.user'])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        $items = [];
        foreach ($roots as $root) {
            [$replies, $hasMoreReplies] = $root->replyPage(1);
            $items[] = self::item($root) + [
                'reply_count' => (int) PostComment::find()->where(['root_comment_id' => $root->id])->count(),
                'replies' => array_map(static fn (PostComment $reply): array => self::item($reply), $replies),
                'has_more_replies' => $hasMoreReplies,
            ];
        }

        return ApiResponder::ok([
            'post_id' => (int) $post->id,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'items' => $items,
        ]);
    }

    public function actionReplies(int $id): array
    {
        $queryParams = Yii::$app->request->getQueryParams();
        [$page, $perPage] = ApiPagination::parse($queryParams);
        $root = PostComment::find()->where(['id' => $id])->with(['post'])->one();
        if ($root === null || !$root->isRoot()) {
            throw new NotFoundHttpException('找不到这条顶级评论。');
        }
        $this->findVisiblePost((int) $root->post_id, $this->viewerId());

        $query = PostComment::find()->where(['root_comment_id' => $root->id]);
        $total = (int) (clone $query)->count();
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $replies = $page > $totalPages ? [] : $query
            ->with(['comment.user', 'parent.comment.user'])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        return ApiResponder::ok([
            'root_id' => (int) $root->id,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'items' => array_map(static fn (PostComment $reply): array => self::item($reply), $replies),
        ]);
    }

    public function actionCreate(): array
    {
        $userId = $this->actorId();
        $payload = $this->payload();
        $postId = filter_var($payload['post_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($postId === false) {
            throw new ValidationException(['post_id' => ['post_id 必须是正整数。']]);
        }

        $post = $this->findVisiblePost((int) $postId, $userId);
        $parentId = $this->parentId($payload);
        $parent = null;
        if ($parentId !== null) {
            $parent = PostComment::findOne($parentId);
            if ($parent === null || (int) $parent->post_id !== (int) $post->id) {
                throw new NotFoundHttpException('要回复的评论不存在。');
            }
            if ((int) $parent->depth >= PostComment::MAX_DEPTH) {
                throw new ValidationException(['parent_id' => ['回复层级已达上限，请在这条顶级评论下继续讨论。']]);
            }
        }

        $content = $payload['content'] ?? null;
        if (!is_string($content)) {
            throw new ValidationException(['content' => ['content 必须是字符串。']]);
        }
        $draft = new Comment(['user_id' => $userId, 'content' => $content]);
        if (!$draft->validate()) {
            throw new ValidationException($draft->getErrors());
        }

        $node = PostComment::publish($post, $userId, (string) $draft->content, $parent);
        $node = PostComment::find()->where(['id' => $node->id])
            ->with(['comment.user', 'parent.comment.user'])->one();
        if ($node === null) {
            throw new RuntimeException('评论保存后无法重新读取。');
        }

        return ApiResponder::ok(self::item($node), '评论已发表。');
    }

    public function actionDelete(int $id): array
    {
        $node = PostComment::find()->where(['id' => $id])->with(['post'])->one();
        if ($node === null) {
            throw new NotFoundHttpException('评论不存在。');
        }
        $this->findVisiblePost((int) $node->post_id, $this->actorId());
        $actor = Yii::$app->user->identity;
        if (!$node->canManage($actor)) {
            throw new ForbiddenHttpException('没有权限删除这条评论。');
        }

        $deleted = $node->removeBy($actor);
        if ($deleted === 0) {
            throw new RuntimeException('评论删除失败。');
        }

        return ApiResponder::ok(['id' => $id, 'deleted_count' => $deleted], '评论已删除。');
    }

    private function findVisiblePost(int $postId, int|null $viewerId): Post
    {
        $query = Post::find()->alias('p')->where(['p.id' => $postId]);
        if ($viewerId === null) {
            $query->andWhere(['p.status' => Post::STATUS_PUBLISHED]);
        } else {
            $query->andWhere([
                'or',
                ['p.status' => Post::STATUS_PUBLISHED],
                ['and', ['p.status' => Post::STATUS_DRAFT], ['p.user_id' => $viewerId]],
            ]);
        }
        Post::applyVisibilityScope($query, $viewerId);
        $post = $query->one();
        if ($post === null) {
            throw new NotFoundHttpException('内容不存在，或你没有查看它的权限。');
        }

        return $post;
    }

    private function parentId(array $payload): int|null
    {
        if (!array_key_exists('parent_id', $payload) || $payload['parent_id'] === null || $payload['parent_id'] === 0 || $payload['parent_id'] === '0') {
            return null;
        }
        $parentId = filter_var($payload['parent_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parentId === false) {
            throw new ValidationException(['parent_id' => ['parent_id 必须是正整数或 null。']]);
        }

        return (int) $parentId;
    }

    private static function item(PostComment $node): array
    {
        $comment = $node->comment;
        $parent = $node->parent_comment_id === null ? null : $node->parent;
        $author = $comment?->user;

        return [
            'id' => (int) $node->id,
            'post_id' => (int) $node->post_id,
            'parent_id' => $node->parent_comment_id === null ? null : (int) $node->parent_comment_id,
            'root_id' => $node->root_comment_id === null ? null : (int) $node->root_comment_id,
            'depth' => (int) $node->depth,
            'content' => $node->isVisible() ? (string) $comment->content : null,
            'status' => (int) ($comment?->status ?? Comment::STATUS_HIDDEN),
            'author' => UsersController::summary($author),
            'reply_to' => $parent === null ? null : UsersController::summary($parent->comment?->user),
            'created_at' => ApiResponder::timestamp((int) $node->created_at),
        ];
    }
}
