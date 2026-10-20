<?php

declare(strict_types=1);

namespace frontend\controllers;

use yii\web\Response;

class BlogController extends ContentController
{
    public function actionCreate(?string $type = null): string|Response
    {
        return $this->createContent('blog');
    }

    public function actionUpdate(int $id): string|Response
    {
        return $this->updateContent($id);
    }

    public function actionDelete(int $id): Response
    {
        return $this->deleteContent($id);
    }
}
