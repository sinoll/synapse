<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '验证手机号';
?>
<div class="site-verify-phone mx-auto py-5" style="max-width: 520px">
    <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
    <p>完成手机号验证后即可使用网站功能。</p>
    <?php $form = ActiveForm::begin(['id' => 'verify-phone-form']); ?>
    <?= $form->field($model, 'phone')->textInput(['placeholder' => '+8613800000000']) ?>
    <?= Html::submitButton('发送验证码', ['class' => 'btn btn-outline-primary', 'name' => 'sendCode', 'value' => '1']) ?>
    <?= $form->field($model, 'code')->textInput(['maxlength' => 6, 'inputmode' => 'numeric']) ?>
    <?= Html::submitButton('验证手机号', ['class' => 'btn btn-primary', 'name' => 'verifyCode', 'value' => '1']) ?>
    <?php ActiveForm::end(); ?>
</div>
