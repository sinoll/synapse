<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\db\ActiveRecord $model */
/** @var common\models\Post $post */
/** @var string $label */
/** @var string[] $fields */
/** @var string $type */
/** @var string $recipientUsernames */
/** @var bool $isUpdate */

use common\models\Post;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isUpdate = $isUpdate ?? false;
$this->title = ($isUpdate ? '编辑' : '发布') . $label;
$labels = [
    'title' => '标题', 'summary' => '摘要', 'content' => '正文',
    'description' => '描述', 'video_path' => '视频文件路径', 'cover_path' => '封面图片路径',
    'file_path' => '文件路径', 'original_name' => '原始文件名', 'mime_type' => 'MIME 类型', 'size_bytes' => '文件大小（字节）',
    'alt_text' => '图片说明', 'duration_seconds' => '时长（秒）', 'image_paths' => '图片路径（JSON）',
];
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];
if ($type === 'file') {
    $visibilityOptions[Post::VISIBILITY_SELECTED_USERS] = '指定用户可见';
}
?>
<div class="content-form mx-auto py-4" style="max-width: 800px">
    <h1><?= Html::encode($this->title) ?></h1>
    <?php $form = ActiveForm::begin(); ?>
    <?php foreach ($fields as $field): ?>
        <?php if (in_array($field, ['content', 'description', 'summary', 'image_paths'], true)): ?>
            <?= $form->field($model, $field)->label($labels[$field] ?? $field)->textarea(['rows' => $field === 'content' || $field === 'description' ? 6 : 3]) ?>
        <?php else: ?>
            <?= $form->field($model, $field)->label($labels[$field] ?? $field)->textInput(['maxlength' => true]) ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <?= $form->field($post, 'visibility')->dropDownList($visibilityOptions) ?>
    <?php if ($type === 'file'): ?>
        <div class="text-body-secondary small mb-3">文件路径请填写已部署的资源路径；此表单目前不上传文件。</div>
        <?= Html::label('授权用户名（用逗号、空格或换行分隔）', 'recipient_usernames', ['class' => 'form-label']) ?>
        <?= Html::textInput('recipient_usernames', $recipientUsernames, [
            'id' => 'recipient_usernames',
            'class' => 'form-control mb-3',
            'autocomplete' => 'off',
            'placeholder' => '仅“指定用户可见”时需要填写',
        ]) ?>
    <?php elseif ($type === 'video' || $type === 'images'): ?>
        <p class="text-body-secondary">请填写已部署的资源路径；此表单目前不上传文件。</p>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <?= Html::submitButton($isUpdate ? '保存修改' : '发布', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('取消', $isUpdate ? ['view', 'id' => $post->id] : ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
