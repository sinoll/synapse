<?php
declare(strict_types=1);
use yii\bootstrap5\Html;
use yii\helpers\Url;
/** @var common\models\SharedFileFolder[] $folders */
/** @var array{maxFileBytes:int,maxFileLabel:string,uploadMax:string,postMax:string,maxFiles:int,uploadsEnabled:bool} $uploadLimits */
$this->title = '上传文件';
$this->registerCssFile('@web/css/content-file.css');
$selectedFolder = Yii::$app->request->get('folder');
?>
<div class="nextcloud-upload-page">
    <nav class="drive-breadcrumb"><?= Html::a('所有文件', ['/file/index']) ?> <span>›</span> 上传文件</nav>
    <div class="nextcloud-upload-card"><h1>上传文件</h1><p>选择文件后上传并设置分享权限。</p>
        <section class="upload-limit-info" aria-label="上传限制">
            <strong>上传限制</strong>
            <ul>
                <li>单个文件最大：<b><?= Html::encode($uploadLimits['maxFileLabel']) ?></b></li>
                <li>PHP 单文件上限（upload_max_filesize）：<?= Html::encode($uploadLimits['uploadMax']) ?></li>
                <li>PHP 单次请求上限（post_max_size）：<?= Html::encode($uploadLimits['postMax']) ?>，包括文件和表单数据</li>
                <li>PHP 每次请求最多接收 <?= (int) $uploadLimits['maxFiles'] ?> 个文件；此页面每次上传 1 个</li>
                <li>支持：PDF、TXT、Markdown、CSV、ZIP/7Z/RAR、Office 文档、JPG/PNG/GIF/WebP、MP3/WAV、MP4/WebM</li>
            </ul>
            <?php if (!$uploadLimits['uploadsEnabled']): ?><p class="upload-limit-error">当前 PHP 配置已禁用文件上传。</p><?php endif; ?>
        </section>
        <?= Html::beginForm(['/file/create'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'nextcloud-upload-form']) ?>
        <label class="drive-dropzone" for="shared-file-upload"><span class="drive-upload-symbol">↑</span><strong>选择文件上传</strong><span>点击选择文件</span><input id="shared-file-upload" type="file" name="upload" required <?= $uploadLimits['uploadsEnabled'] ? '' : 'disabled' ?>></label>
        <div class="drive-picked-file" data-picked-file>尚未选择文件</div>
        <label>文件显示名称<?= Html::textInput('title', '', ['class' => 'form-control', 'maxlength' => 200, 'placeholder' => '默认使用文件名']) ?></label>
        <label>保存到文件夹<?= Html::dropDownList('folder_id', $selectedFolder, ['' => '所有文件'] + \yii\helpers\ArrayHelper::map($folders, 'id', 'name'), ['class' => 'form-select']) ?></label>
        <label>描述<?= Html::textarea('description', '', ['class' => 'form-control', 'rows' => 3, 'maxlength' => 5000]) ?></label>
        <label>默认分享权限<?= Html::dropDownList('visibility', 0, [0 => '仅自己', 1 => '登录用户', 2 => '任何人（公开）', 3 => '指定用户'], ['class' => 'form-select', 'data-upload-visibility' => '1']) ?></label>
        <label data-upload-recipients hidden>指定用户名<?= Html::textInput('recipients', '', ['class' => 'form-control', 'placeholder' => '用逗号、空格或换行分隔']) ?></label>
        <div class="drive-upload-actions"><?= Html::a('取消', ['/file/index'], ['class' => 'drive-cancel-button']) ?><?= Html::submitButton('上传文件', ['class' => 'drive-upload-button', 'disabled' => !$uploadLimits['uploadsEnabled']]) ?></div>
        <?= Html::endForm() ?>
    </div>
</div>
<script>
const fileInput = document.getElementById('shared-file-upload');
fileInput.addEventListener('change', () => { document.querySelector('[data-picked-file]').textContent = fileInput.files[0]?.name || '尚未选择文件'; });
const visibility = document.querySelector('[data-upload-visibility]');
const recipientField = document.querySelector('[data-upload-recipients]');
const syncRecipients = () => { recipientField.hidden = visibility.value !== '3'; };
visibility.addEventListener('change', syncRecipients); syncRecipients();
</script>
