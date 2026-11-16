<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\Post;
use common\services\PostSearch;
use restful\components\ApiPagination;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use restful\models\ApiResource;
use restful\models\Blog;
use restful\models\Images;
use restful\models\Moment;
use restful\models\ShortVideo;
use Yii;

final class SearchController extends ApiController
{
    private const RESOURCES = [
        Post::TYPE_BLOG => Blog::class,
        Post::TYPE_MOMENT => Moment::class,
        Post::TYPE_IMAGES => Images::class,
        Post::TYPE_SHORT_VIDEO => ShortVideo::class,
    ];

    protected function publicActions(): array
    {
        return ['index'];
    }

    public function actionIndex(): array
    {
        $params = Yii::$app->request->getQueryParams();
        $q = $params['q'] ?? '';
        if (!is_string($q)) {
            throw new ValidationException(['q' => ['q 必须是字符串。']]);
        }
        $keyword = PostSearch::keyword($q);
        $type = $params['type'] ?? '';
        if (!is_string($type) || ($type !== '' && !isset(self::RESOURCES[$type]) && $type !== 'video')) {
            throw new ValidationException(['type' => ['type 只能是 blog、moment、images 或 short_video。']]);
        }
        $type = $type === 'video' ? Post::TYPE_SHORT_VIDEO : $type;
        [$page, $perPage] = ApiPagination::parse($params);

        $viewerId = $this->viewerId();
        $total = 0;
        $posts = [];
        if ($keyword !== '') {
            $query = PostSearch::query($keyword, $type === '' ? null : $type, $viewerId);
            $query->andWhere(['p.type' => array_keys(self::RESOURCES)]);
            $total = (int) (clone $query)->count();
            if ($page <= (int) ceil($total / $perPage)) {
                $posts = $query->offset(($page - 1) * $perPage)->limit($perPage)->all();
            }
        }
        $likedIds = ApiResource::likedIds($posts, $viewerId);
        $items = array_map(static function (Post $post) use ($viewerId, $likedIds): array {
            $resource = self::RESOURCES[$post->type];

            return $resource::item($post, $viewerId, false, in_array((int) $post->id, $likedIds, true));
        }, $posts);

        return ApiResponder::ok([
            'q' => $keyword,
            'type' => $type === '' ? null : $type,
            'authenticated' => $viewerId !== null,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            'items' => $items,
        ]);
    }
}
