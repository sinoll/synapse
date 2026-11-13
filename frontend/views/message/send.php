<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\UserMessage $model */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$this->title = '发送私信';
?>

<div class="py-3 py-md-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-4">发送私信</h1>
            <?php $form = ActiveForm::begin(); ?>
                <?= $form->field($model, 'recipientUsername')->textInput([
                    'maxlength' => true,
                    'autocomplete' => 'username',
                    'placeholder' => '收件人用户名',
                ])->label('收件人用户名') ?>
                <?= $form->field($model, 'content')->textarea([
                    'rows' => 7,
                    'maxlength' => 5000,
                    'placeholder' => '输入私信内容',
                ])->label('消息内容') ?>
                <div class="d-flex gap-2">
                    <?= Html::submitButton('发送', ['class' => 'btn btn-primary']) ?>
                    <?= Html::a('取消', ['/users/message'], ['class' => 'btn btn-outline-secondary']) ?>
                </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
