<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '修改密码';
?>
<section class="account-flow mx-auto py-4 py-md-5">
    <div class="mb-4">
        <p class="small text-body-secondary text-uppercase mb-1">账户安全</p>
        <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
        <p class="text-body-secondary mb-0">更新用于登录的密码。</p>
    </div>
    <div class="card border-0 shadow-sm"><div class="card-body p-3 p-md-4">
        <?php $form = ActiveForm::begin(['id' => 'change-password-form']); ?>
        <?= $form->field($model, 'currentPassword')->passwordInput(['autocomplete' => 'current-password'])->label('当前密码') ?>
        <?= $form->field($model, 'newPassword')->passwordInput(['autocomplete' => 'new-password'])->label('新密码') ?>
        <?= $form->field($model, 'confirmPassword')->passwordInput(['autocomplete' => 'new-password'])->label('确认新密码') ?>
        <div class="d-grid d-sm-flex justify-content-sm-end mt-4">
            <?= Html::submitButton('保存新密码', ['class' => 'btn btn-primary px-4']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div></div>
</section>
