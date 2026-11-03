<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\BlogPost $model */
/** @var common\models\Post $post */
/** @var int $selectedCateId */
/** @var string|null $cateError */
/** @var string[] $selectedTagNames */
/** @var string|null $tagError */

$this->title = '写一篇博客';

echo $this->render('_composer', [
    'model' => $model,
    'post' => $post,
    'isUpdate' => false,
    'selectedCateId' => $selectedCateId ?? 0,
    'cateError' => $cateError ?? null,
    'selectedTagNames' => $selectedTagNames ?? [],
    'tagError' => $tagError ?? null,
]);
