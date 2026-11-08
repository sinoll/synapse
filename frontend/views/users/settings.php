<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = '个人资料设置';
$user = Yii::$app->user->identity;
$displayName = $user->nickname ?: $user->username;
$navigation = [
    ['label' => '用户首页', 'description' => '查看账号概览', 'icon' => '⌂', 'url' => ['/users/index']],
    ['label' => '个人资料设置', 'description' => '修改昵称、手机号与头像', 'icon' => '⚙', 'url' => ['/users/settings'], 'active' => true],
    ['label' => '修改密码', 'description' => '更新登录密码', 'icon' => '🔒', 'url' => ['/users/change-password']],
];
$currentAvatar = Yii::$app->user->identity->avatar;
?>
<div class="user-center py-3 py-md-4">
    <div class="row g-4 align-items-start">
        <aside class="col-12 col-lg-3">
            <?= $this->render('_sidebar', ['active' => 'settings']) ?>
        </aside>

        <section class="col-lg-9" aria-labelledby="user-center-title">
            
            <div class="mb-4">
                <p class="small text-body-secondary text-uppercase mb-1">账户设置</p>
                <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
                <p class="text-body-secondary mb-0">更新你的昵称、手机号和头像。</p>
            </div>
            <div class="card border-0 shadow-sm"><div class="card-body p-3 p-md-4">
                <?php $form = ActiveForm::begin(['id' => 'profile-form', 'options' => ['enctype' => 'multipart/form-data']]); ?>
                <div class="mb-3">
                    <img id="avatar-preview" src="<?= $currentAvatar ? Html::encode(Url::to($currentAvatar)) : '' ?>" alt="头像预览" width="96" height="96" class="rounded-circle object-fit-cover<?= $currentAvatar ? '' : ' d-none' ?>">
                    <p id="avatar-preview-caption" class="small text-body-secondary mt-2 mb-0"><?= $currentAvatar ? '当前头像' : '尚未设置头像' ?></p>
                </div>
                <?= $form->field($profile, 'nickname')->textInput(['maxlength' => true, 'autocomplete' => 'nickname'])->label('昵称') ?>
                <?= $form->field($profile, 'phone')->textInput(['maxlength' => 32, 'autocomplete' => 'tel', 'inputmode' => 'tel'])->label('手机号') ?>
                <?= $form->field($profile, 'avatarFile')->fileInput(['accept' => 'image/png,image/jpeg,image/gif,image/webp'])->label('更换头像')->hint('支持 PNG、JPG、GIF、WebP，最大 5 MB。') ?>
                <div class="d-grid d-sm-flex justify-content-sm-end mt-3">
                    <?= Html::submitButton('保存资料', ['class' => 'btn btn-primary px-4']) ?>
                </div>
                <?php ActiveForm::end(); ?>
            </div></div>
            

            
        </section>
    </div>
</div>
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
