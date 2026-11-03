<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Image $model */
/** @var common\models\Post $post */
/** @var int[] $selectedImageIds */
/** @var string|null $tagError */

$this->title = '发布图片';

echo $this->render('_composer', [
    'model' => $model,
    'post' => $post,
    'isUpdate' => false,
    'selectedImageIds' => $selectedImageIds ?? [],
    'tagError' => $tagError ?? null,
]);
