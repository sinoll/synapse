<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Moment $model */
/** @var common\models\Post $post */
/** @var string|null $contentError */
/** @var int $selectedCateId */
/** @var string|null $cateError */

$this->title = '发布动态';

echo $this->render('_composer', [
    'model' => $model,
    'post' => $post,
    'isUpdate' => false,
    'contentError' => $contentError ?? null,
    'selectedCateId' => $selectedCateId ?? 0,
    'cateError' => $cateError ?? null,
]);
