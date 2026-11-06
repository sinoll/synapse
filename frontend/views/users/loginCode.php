<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \frontend\models\LoginCodeForm $model */
/** @var \common\models\User $user */

use frontend\models\LoginCodeForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '安全验证';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = '长时间未登录的账户需要填写邮箱验证码后才能继续登录。';
$this->params['meta_keywords'] = '登录, 验证码, 邮箱, 账户安全';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
$days = max(1, (int) round(((int) Yii::$app->params['user.loginCodeAfterInactivity']) / 86400));
$minutes = LoginCodeForm::lifetimeMinutes();
$resendIn = LoginCodeForm::resendIn();
$expiresIn = LoginCodeForm::expiresIn();
?>
<div class="site-login d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card">
        <div class="row g-0 justify-content-center">

        <!-- Form panel -->
            <div class="col-md-7">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">这个账户已超过 <?= $days ?> 天没有登录，为确认是本人，请再填一次邮箱验证码</p>
                    </div>

                    <?= $this->render('_flash') ?>

                    <div class="alert alert-secondary small mb-4">
                        验证码已发送到注册邮箱
                        <span class="fw-semibold"><?= Html::encode($user->maskedEmail()) ?></span>，
                        <?= $minutes ?> 分钟内有效<?= $expiresIn > 0 ? sprintf('（还剩 %d 分 %d 秒）', intdiv($expiresIn, 60), $expiresIn % 60) : '' ?>。
                    </div>

                    <?php $form = ActiveForm::begin(['id' => 'login-code-form']); ?>

                    <div class="mb-4">
                        <?= $form->field($model, 'code', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#9993;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => '6 位验证码',
                                'maxlength' => 6,
                                'inputmode' => 'numeric',
                                'autocomplete' => 'one-time-code',
                                'autofocus' => true,
                            ],
                        ])->textInput()
                            ->hint('验证码只用于这次登录，不要转给别人。')
                            ->label('邮箱验证码', $labelOptions) ?>
                    </div>

                    <div class="d-grid">
                        <?= Html::submitButton(
                            '完成登录',
                            [
                                'class' => 'btn login-btn btn-lg rounded-3 text-white',
                                'name' => 'verify',
                            ],
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                    <div class="d-grid mt-3">
                        <?= Html::beginForm(['login-code']) ?>
                        <?= Html::submitButton(
                            $resendIn > 0 ? "重新发送验证码（{$resendIn}s）" : '重新发送验证码',
                            [
                                'class' => 'btn btn-outline-secondary btn-sm rounded-3',
                                'name' => 'resend',
                                'value' => '1',
                                'data-countdown' => $resendIn,
                                'data-label' => '重新发送验证码',
                                'disabled' => $resendIn > 0,
                            ],
                        ) ?>
                        <?= Html::endForm() ?>
                    </div>

                    <div class="text-body-secondary text-center mt-3 small">
                        <?= Html::a('换个账户登录', ['/users/login']) ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
<?php
// 重发倒计时只跟着这一次渲染走：归零后把按钮放开，省得页面看着能点却要点第二下。
$this->registerJs(<<<'JS'
(function () {
    var button = document.querySelector('[data-countdown]');
    if (!button) {
        return;
    }
    var left = parseInt(button.getAttribute('data-countdown'), 10);
    var label = button.getAttribute('data-label');
    if (!(left > 0)) {
        return;
    }
    var timer = setInterval(function () {
        left -= 1;
        if (left <= 0) {
            clearInterval(timer);
            button.disabled = false;
            button.textContent = label;
            return;
        }
        button.textContent = label + '（' + left + 's）';
    }, 1000);
})();
JS);
