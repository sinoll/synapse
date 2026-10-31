<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
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