<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\Post;
use common\models\PostFavorite;
use restful\components\ApiPagination;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use restful\models\ApiResource;
use restful\models\Blog;
use restful\models\Images;
use restful\models\Moment;
use restful\models\ShortVideo;
use Yii;
use yii\web\NotFoundHttpException;

final class FavoriteController extends ApiController
{
    private const RESOURCES = [
        Post::TYPE_BLOG => Blog::class,
        Post::TYPE_MOMENT => Moment::class,
        Post::TYPE_IMAGES => Images::class,
        Post::TYPE_SHORT_VIDEO => ShortVideo::class,
    ];

    public function actionIndex(): array
    {
        $userId = $this->actorId();
        $params = Yii::$app->request->getQueryParams();
        [$page, $perPage] = ApiPagination::parse($params);
        $type = $params['type'] ?? '';
        if (!is_string($type) || ($type !== '' && $type !== 'video' && !isset(self::RESOURCES[$type]))) {
            throw new ValidationException(['type' => ['type 只能是 blog、moment、images 或 short_video。']]);
        }
        $type = $type === 'video' ? Post::TYPE_SHORT_VIDEO : $type;

        $query = Post::find()
            ->alias('p')
            ->innerJoin(['pf' => PostFavorite::tableName()], 'pf.post_id = p.id')
            ->select(['p.*', 'favoriteAt' => 'pf.created_at'])
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->where(['pf.user_id' => $userId, 'p.status' => Post::STATUS_PUBLISHED])
            ->andWhere(['p.type' => array_keys(self::RESOURCES)]);
        Post::applyVisibilityScope($query, $userId);
        if ($type !== '') {
            $query->andWhere(['p.type' => $type]);
        }

        $total = (int) (clone $query)->count();
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $posts = $page > $totalPages ? [] : $query
            ->orderBy(['pf.created_at' => SORT_DESC, 'p.id' => SORT_DESC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();
        $likedIds = ApiResource::likedIds($posts, $userId);
        $commentCounts = ApiResource::commentCounts($posts);
        $items = array_map(static function (Post $post) use ($likedIds, $commentCounts, $userId): array {
            $resource = self::RESOURCES[$post->type];

            return $resource::item(
                $post,
                $userId,
                false,
                in_array((int) $post->id, $likedIds, true),
                true,
                $commentCounts[(int) $post->id] ?? 0
            ) + ['favorited_at' => ApiResponder::timestamp((int) $post->favoriteAt)];
        }, $posts);

        return ApiResponder::ok([
            'type' => $type === '' ? null : $type,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'items' => $items,
        ]);
    }

    public function actionToggle(): array
    {
        $postId = $this->payload()['post_id'] ?? null;
        $postId = filter_var($postId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($postId === false) {
            throw new ValidationException(['post_id' => ['post_id 必须是正整数。']]);
        }

        $post = Post::findOne((int) $postId);
        if ($post === null || !isset(self::RESOURCES[$post->type])) {
            throw new NotFoundHttpException('内容不存在，或你没有查看它的权限。');
        }
        $resource = self::RESOURCES[$post->type];

        return ApiResponder::ok($resource::favorite((int) $postId, $this->actorId()));
    }

    public function actionRemove(int $id): array
    {
        $userId = $this->actorId();
        PostFavorite::deleteAll(['user_id' => $userId, 'post_id' => $id]);

        return ApiResponder::ok(['post_id' => $id, 'favorited' => false], '已取消收藏。');
    }
}
