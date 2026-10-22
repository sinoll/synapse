<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = '用户设置';
?>
<section class="account-flow mx-auto py-4 py-md-5">
    <div class="mb-4">
        <p class="small text-body-secondary text-uppercase mb-1">账户安全</p>
        <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
        <p class="text-body-secondary mb-0">更新个人资料与登录密码。</p>
    </div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-3 p-md-4">
        <h2 class="h5 mb-3">个人资料</h2>
        <?php $form = ActiveForm::begin(['id' => 'profile-form', 'options' => ['enctype' => 'multipart/form-data']]); ?>
        <?php $currentAvatar = Yii::$app->user->identity->avatar; ?>
        <div class="mb-3">
            <img id="avatar-preview" src="<?= $currentAvatar ? Html::encode(Url::to($currentAvatar)) : '' ?>" alt="头像预览" class="rounded-circle<?= $currentAvatar ? '' : ' d-none' ?>" style="width: 96px; height: 96px; object-fit: cover">
            <p id="avatar-preview-caption" class="small text-body-secondary mt-2 mb-0"><?= $currentAvatar ? '当前头像' : '尚未设置头像' ?></p>
        </div>
        <?= $form->field($profile, 'nickname')->textInput(['maxlength' => true, 'autocomplete' => 'nickname'])->label('昵称') ?>
        <?= $form->field($profile, 'avatarFile')->fileInput(['accept' => 'image/png,image/jpeg,image/gif,image/webp'])->label('更换头像')->hint('支持 PNG、JPG、GIF、WebP，最大 5 MB。') ?>
        <div class="d-grid d-sm-flex justify-content-sm-end mt-3">
            <?= Html::submitButton('保存资料', ['class' => 'btn btn-primary px-4']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div></div>
    <div class="card border-0 shadow-sm"><div class="card-body p-3 p-md-4">
        <h2 class="h5 mb-3">修改密码</h2>
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
<?php
$this->registerJs(<<<'JS'
const avatarInput = document.getElementById('profileform-avatarfile');
const avatarPreview = document.getElementById('avatar-preview');
const avatarCaption = document.getElementById('avatar-preview-caption');
let selectedAvatarUrl = null;
avatarInput?.addEventListener('change', () => {
    if (selectedAvatarUrl) URL.revokeObjectURL(selectedAvatarUrl);
    const file = avatarInput.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;
    selectedAvatarUrl = URL.createObjectURL(file);
    avatarPreview.src = selectedAvatarUrl;
    avatarPreview.classList.remove('d-none');
    avatarCaption.textContent = '新头像预览';
});
JS
);
?>
