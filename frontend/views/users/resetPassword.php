<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \frontend\models\ResetPasswordForm $model */
/** @var string $token */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '设置新密码';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = '为拾光账户设置新密码。';
$this->params['meta_keywords'] = '重置密码, 新密码, 账户安全, 拾光';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
$token = $token ?? '';
?>
<div class="site-reset-password d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card">
        <div class="row g-0">

            <!-- Brand panel -->
            <div class="col-md-5 d-none d-md-flex login-brand-panel text-white">
                <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                    <div>
                        <?= Html::img(
                            Yii::getAlias('@web/images/yii3_full_white_for_dark.svg'),
                            [
                                'alt' => Yii::$app->name,
                                'class' => 'mb-4',
                                'height' => 40,
                            ],
                        ) ?>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-3 login-brand-title">
                            设置一<br>个新密码
                        </h2>
                        <p class="opacity-75 mb-0 login-brand-text">
                            提交后本次重置链接立即失效，其他设备上的登录状态也会一并退出。
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form panel -->
            <div class="col-md-7">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="d-md-none mb-3">
                            <?= Html::img(
                                Yii::getAlias('@web/images/yii3_full_black_for_light.svg'),
                                [
                                    'alt' => Yii::$app->name,
                                    'class' => 'login-mobile-logo',
                                    'height' => 36,
                                ],
                            ) ?>
                        </div>
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">请为你的账户选择一个新的密码</p>
                    </div>

                    <?= $this->render('_flash') ?>

                    <?php $form = ActiveForm::begin([
                        'id' => 'reset-password-form',
                        'action' => ['/users/reset-password', 'token' => $token],
                        'method' => 'post',
                    ]); ?>
                    <?= Html::hiddenInput('token', $token) ?>

                    <div class="mb-3">
                        <?= $form->field($model, 'password', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128274;'),
                            'inputOptions' => [
                                'autofocus' => true,
                                'class' => 'form-control',
                                'placeholder' => '新密码',
                                'autocomplete' => 'new-password',
                            ],
                        ])->passwordInput()->label('新密码', $labelOptions)->hint('至少 ' . (int) Yii::$app->params['user.passwordMinLength'] . ' 位，需同时包含字母和数字。') ?>
                    </div>

                    <div class="mb-4">
                        <?= $form->field($model, 'confirmPassword', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128274;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => '再次输入新密码',
                                'autocomplete' => 'new-password',
                            ],
                        ])->passwordInput()->label('确认新密码', $labelOptions) ?>
                    </div>

                    <div class="d-grid">
                        <?= Html::submitButton(
                            '保存新密码',
                            [
                                'class' => 'btn login-btn btn-lg rounded-3 text-white',
                            ],
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                    <div class="text-body-secondary text-center mt-3 small">
                        不想继续了？<?= Html::a('返回登录', ['/users/login']) ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
