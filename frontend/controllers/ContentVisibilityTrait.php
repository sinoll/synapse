<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use common\models\Tag;
use Yii;
use yii\web\NotFoundHttpException;

trait ContentVisibilityTrait
{
    protected function findContentPost(int $id): Post
    {
        $post = Post::findOne($id);
        if ($post === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $post;
    }

    protected function findTypedContentPost(int $id, string $type): Post
    {
        $post = $this->findContentPost($id);
        if ((string) $post->type !== $type) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $post;
    }

    /** 按 slug 定位列表页的标签筛选条件，slug 为空表示不过滤。 */
    protected function findTagBySlug(?string $slug): ?Tag
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $tag = Tag::find()->where(['slug' => $slug])->one();
        if ($tag === null) {
            throw new NotFoundHttpException('找不到该标签。');
        }

        return $tag;
    }

    protected function canViewContent(Post $post): bool
    {
        if ((int) $post->status !== Post::STATUS_PUBLISHED) {
            return false;
        }
        if (Yii::$app->user->isGuest) {
            return (int) $post->visibility === Post::VISIBILITY_PUBLIC;
        }
        $userId = (int) Yii::$app->user->id;
        if ((int) $post->user_id === $userId) {
            return true;
        }
        if ((int) $post->visibility === Post::VISIBILITY_PUBLIC) {
            return true;
        }
        if ((int) $post->visibility === Post::VISIBILITY_REGISTERED_USERS) {
            return true;
        }

        return false;
    }
}