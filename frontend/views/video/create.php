<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\ShortVideo $model */
/** @var common\models\Post $post */
/** @var string|null $tagError */

$this->title = '发布短视频';

echo $this->render('_composer', [
    'model' => $model,
    'post' => $post,
    'isUpdate' => false,
    'tagError' => $tagError ?? null,
]);
