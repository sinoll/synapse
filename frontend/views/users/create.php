<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '添加用户';
?>
<div class="users-create mx-auto py-3 py-md-5" style="max-width: 680px">
    <div class="mb-4">
        <p class="small text-body-secondary text-uppercase mb-1">系统管理</p>
        <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
        <p class="text-body-secondary mb-0">填写用户的基本资料和初始密码。</p>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <?php $form = ActiveForm::begin(['id' => 'create-user-form']); ?>
            <?= $form->field($model, 'username')->textInput(['maxlength' => true, 'autocomplete' => 'username']) ?>
            <?= $form->field($model, 'nickname')->textInput(['maxlength' => true, 'autocomplete' => 'nickname']) ?>
            <?= $form->field($model, 'email')->input('email', ['autocomplete' => 'email']) ?>
            <?= $form->field($model, 'phone')->textInput(['maxlength' => true, 'autocomplete' => 'tel']) ?>
            <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password']) ?>
            <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                <?= Html::submitButton('创建账户', ['class' => 'btn btn-primary px-4']) ?>
                <?= Html::a('返回用户列表', ['index'], ['class' => 'btn btn-outline-secondary px-4']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
