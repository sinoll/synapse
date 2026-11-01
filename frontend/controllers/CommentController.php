<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Comment;
use common\models\Post;
use common\models\PostComment;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/** 详情页评论区：发表、回复、删除，以及楼层与楼中楼的翻页。 */
class CommentController extends Controller
{
    use ContentVisibilityTrait;

    /** 评论区专用的提示键，避免和其它动作的 flash 混在一起。 */
    public const FEEDBACK_KEY = 'comment-feedback';

    public function getViewPath(): string
    {
        return Yii::getAlias('@frontend/views/comment');
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::$app->user->identity;
        if ($user !== null && $user->must_change_password) {
            Yii::$app->response->redirect(['/users/change-password']);
            return false;
        }

        return true;
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['create', 'delete'],
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['create' => ['POST'], 'delete' => ['POST']],
            ],
        ];
    }

    public function actionCreate(): Response
    {
        $request = Yii::$app->request;
        $post = $this->findCommentablePost((int) $request->post('post_id'));
        $userId = (int) Yii::$app->user->id;

        $parent = $this->findReplyTarget($post, $request->post('parent_id'));
        if ($parent !== null && (int) $parent->depth >= PostComment::MAX_DEPTH) {
            return $this->feedback('error', '回复层级已达上限，请在这条顶级评论下继续讨论。', $post);
        }

        // 先按正文规则校验，好把具体原因回显给发起人；写库仍由 publish() 在一个事务里完成。
        $draft = new Comment(['user_id' => $userId, 'content' => (string) $request->post('content')]);
        if (!$draft->validate()) {
            return $this->feedback('error', $draft->getFirstError('content') ?: '评论发送失败，请重试。', $post);
        }

        PostComment::publish($post, $userId, (string) $draft->content, $parent);

        return $this->feedback('success', '评论已发表。', $post);
    }

    public function actionDelete(): Response
    {
        $node = PostComment::findOne((int) Yii::$app->request->post('id'));
        if ($node === null || $node->post === null || !$this->canViewContent($node->post)) {
            throw new NotFoundHttpException('评论不存在。');
        }

        $identity = Yii::$app->user->identity;
        if (!$node->canManage($identity)) {
            throw new ForbiddenHttpException('没有权限删除这条评论。');
        }

        // 作者删自己的评论只摘本条，别人在它下面的回复留在楼层里；版务删除才清空整棵子树。
        if ($node->removeBy($identity) === 0) {
            return $this->feedback('error', '评论删除失败，请重试。', $node->post);
        }

        return $this->feedback('success', '评论已删除。', $node->post);
    }

    /** 下一页楼层，返回 JSON 里的 HTML 片段。 */
    public function actionFeed(int $id, int $page = 1): Response
    {
        $post = $this->findCommentablePost($id);
        [$roots, $hasMore] = PostComment::floorPage((int) $post->id, max(1, $page));

        $html = '';
        foreach ($roots as $root) {
            $html .= $this->renderPartial('_floor', ['root' => $root]);
        }

        return $this->asJson(['html' => $html, 'hasMore' => $hasMore]);
    }

    /** 某个楼层的下一页回复，返回 JSON 里的 HTML 片段。 */
    public function actionReplies(int $id, int $page = 1): Response
    {
        $root = PostComment::findOne($id);
        if ($root === null || !$root->isRoot() || !$this->canViewContent($root->post)) {
            throw new NotFoundHttpException('评论不存在。');
        }

        [$replies, $hasMore] = $root->replyPage(max(1, $page));

        return $this->asJson([
            'html' => $this->renderPartial('_replies', ['replies' => $replies]),
            'hasMore' => $hasMore,
        ]);
    }

    private function findCommentablePost(int $postId): Post
    {
        $post = $postId > 0 ? Post::findOne($postId) : null;
        if ($post === null || !$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $post;
    }

    /** 被回复的节点必须属于同一条内容，否则视为不存在。 */
    private function findReplyTarget(Post $post, mixed $parentId): ?PostComment
    {
        $parentId = (int) $parentId;
        if ($parentId === 0) {
            return null;
        }

        $parent = PostComment::findOne($parentId);
        if ($parent === null || (int) $parent->post_id !== (int) $post->id) {
            throw new NotFoundHttpException('要回复的评论不存在。');
        }

        return $parent;
    }

    private function feedback(string $type, string $message, Post $post): Response
    {
        Yii::$app->session->setFlash(self::FEEDBACK_KEY, ['type' => $type, 'message' => $message]);

        return $this->redirect($this->detailRoute($post));
    }

    /** 回到详情页并停在评论区，跳转地址全部由服务端按内容类型推导。 */
    private function detailRoute(Post $post): array
    {
        $route = Post::TYPE_DETAIL_ROUTES[$post->type] ?? null;
        if ($route === null) {
            return ['/site/index'];
        }

        return [$route, 'id' => $post->id, '#' => 'comments'];
    }
}
