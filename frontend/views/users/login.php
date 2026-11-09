<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \common\models\LoginForm $model */

use common\models\SiteSetting;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '登录账户';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = '登录拾光账户，继续发布和浏览内容。';
$this->params['meta_keywords'] = '登录, 账户, 拾光';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
?>
<div class="site-login d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card">
        <div class="row g-0 justify-content-center">

        <!-- Form panel -->
            <div class="col-md-7">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">输入账户信息继续</p>
                    </div>

                    <?= $this->render('_flash') ?>

                    <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

                    <div class="mb-3">
                        <?= $form->field($model, 'username', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128100;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => '用户名',
                                'autofocus' => true,
                            ],
                        ])->textInput()->label('用户名', $labelOptions) ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'password', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128274;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => '密码',
                                'autocomplete' => 'current-password',
                            ],
                        ])->passwordInput()->label('密码', $labelOptions) ?>
                    </div>

                    <div class="mb-4">
                        <?= $form->field($model, 'rememberMe')->checkbox(['label' => '记住我']) ?>
                    </div>

                    <div class="d-grid">
                        <?= Html::submitButton(
                            '登录',
                            [
                                'class' => 'btn login-btn btn-lg rounded-3 text-white',
                                'name' => 'login-button',
                            ],
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                    <div class="text-body-secondary text-center mt-3 small">
                        <?php if (SiteSetting::signupEnabled()): ?>
                            <?= Html::a('注册新账户', ['/users/signup']) ?>
                            ·
                        <?php endif; ?>
                        <?= Html::a('忘记密码', ['/users/request-password-reset']) ?>
                        ·
                        <?= Html::a('重发确认邮件', ['/users/resend-verification-email']) ?>
                    </div>

                    <p class="text-body-secondary text-center small mb-0">
                        超过 <?= max(1, (int) round(((int) Yii::$app->params['user.loginCodeAfterInactivity']) / 86400)) ?> 天未登录的账户，
                        登录时会向注册邮箱发送验证码确认是本人。
                    </p>

                </div>
            </div>

        </div>
    </div>
</div>
