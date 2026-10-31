<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '验证手机号码';
?>
<section class="account-flow mx-auto py-4 py-md-5">
    <div class="mb-4">
        <p class="small text-body-secondary text-uppercase mb-1">账户安全</p>
        <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
        <p class="text-body-secondary mb-0">验证完成后即可使用网站的全部功能。</p>
    </div>
    <div class="card border-0 shadow-sm"><div class="card-body p-3 p-md-4">
        <?php $form = ActiveForm::begin(['id' => 'verify-phone-form']); ?>
        <?= $form->field($model, 'phone')->textInput(['placeholder' => '+8613800000000', 'autocomplete' => 'tel', 'inputmode' => 'tel'])->label('手机号码') ?>
        <div class="d-grid d-sm-flex justify-content-sm-end mb-4">
            <?= Html::submitButton('发送验证码', ['class' => 'btn btn-outline-primary px-4', 'name' => 'sendCode', 'value' => '1']) ?>
        </div>
        <?= $form->field($model, 'code')->textInput(['maxlength' => 6, 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'placeholder' => '请输入 6 位验证码'])->label('短信验证码') ?>
        <div class="d-grid d-sm-flex justify-content-sm-end mt-4">
            <?= Html::submitButton('完成验证', ['class' => 'btn btn-primary px-4', 'name' => 'verifyCode', 'value' => '1']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div></div>
</section>
