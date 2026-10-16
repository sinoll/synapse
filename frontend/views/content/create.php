<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\db\ActiveRecord $model */
/** @var string $label */
/** @var string[] $fields */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '发布' . $label;
$labels = [
    'title' => '标题', 'summary' => '摘要', 'content' => '正文',
    'description' => '描述', 'video_path' => '视频文件路径', 'cover_path' => '封面图片路径',
    'file_path' => '文件路径', 'original_name' => '原始文件名', 'mime_type' => 'MIME 类型', 'size_bytes' => '文件大小（字节）',
    'alt_text' => '图片说明',
];
?>
<div class="content-create">
    <h1><?= Html::encode($this->title) ?></h1>
    <?php $form = ActiveForm::begin(); ?>
    <?php foreach ($fields as $field): ?>
        <?= $form->field($model, $field)->label($labels[$field] ?? $field)->textarea(['rows' => $field === 'content' || $field === 'description' ? 6 : 2]) ?>
    <?php endforeach; ?>
    <p class="text-body-secondary">文件与视频上传接口将在文件存储服务接入后启用；当前可以先完善文本内容和数据结构。</p>
    <?= Html::submitButton('发布', ['class' => 'btn btn-primary']) ?>
    <?php ActiveForm::end(); ?>
</div>
