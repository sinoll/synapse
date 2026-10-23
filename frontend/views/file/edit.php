<?php
declare(strict_types=1);
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
/** @var common\models\SharedFile $file */
$this->title = '文件信息';
$this->registerCssFile('@web/css/content-file.css');
?>
<div class="nextcloud-upload-page"><nav class="drive-breadcrumb"><?= Html::a('所有文件', ['/file/index']) ?> <span>›</span> 文件信息</nav><div class="nextcloud-upload-card"><h1>文件信息</h1><p><?= Html::encode($file->original_name) ?></p><?= Html::beginForm(['/file/update', 'id' => $file->id], 'post', ['class' => 'nextcloud-upload-form']) ?><label>显示名称<?= Html::textInput('title', $file->title, ['class' => 'form-control', 'maxlength' => 200, 'required' => true]) ?></label><label>保存到文件夹<?= Html::dropDownList('folder_id', $file->folder_id, ['' => '所有文件'] + ArrayHelper::map($folders, 'id', 'name'), ['class' => 'form-select']) ?></label><label>描述<?= Html::textarea('description', $file->description, ['class' => 'form-control', 'rows' => 4]) ?></label><div class="drive-upload-actions"><?= Html::a('取消', ['/file/index', 'folder' => $file->folder_id], ['class' => 'drive-cancel-button']) ?><?= Html::submitButton('保存', ['class' => 'drive-upload-button']) ?></div><?= Html::endForm() ?></div></div>
