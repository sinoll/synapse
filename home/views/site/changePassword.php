<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '首次登录：修改密码';
?>
<div class="site-change-password mx-auto py-5" style="max-width: 520px">
    <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
    <p>首次登录前请先修改默认密码。</p>
    <?php $form = ActiveForm::begin(['id' => 'change-password-form']); ?>
    <?= $form->field($model, 'currentPassword')->passwordInput() ?>
    <?= $form->field($model, 'newPassword')->passwordInput() ?>
    <?= $form->field($model, 'confirmPassword')->passwordInput() ?>
    <?= Html::submitButton('保存新密码', ['class' => 'btn btn-primary']) ?>
    <?php ActiveForm::end(); ?>
</div>
