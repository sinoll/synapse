<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Moment $model */
/** @var common\models\Post $post */
/** @var string|null $contentError */

$this->title = '编辑动态';

echo $this->render('_composer', [
    'model' => $model,
    'post' => $post,
    'isUpdate' => true,
    'contentError' => $contentError ?? null,
]);
