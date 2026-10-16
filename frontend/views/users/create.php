<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '添加用户';
?>
<div class="users-create mx-auto py-4" style="max-width: 600px">
    <h1><?= Html::encode($this->title) ?></h1>
    <?php $form = ActiveForm::begin(['id' => 'create-user-form']); ?>
    <?= $form->field($model, 'username')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'nickname')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'email')->input('email') ?>
    <?= $form->field($model, 'phone')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'password')->passwordInput() ?>
    <?= Html::submitButton('创建账户', ['class' => 'btn btn-primary']) ?>
    <?php ActiveForm::end(); ?>
</div>
