<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \frontend\models\SignupForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '注册新账户';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = '注册拾光账户，发布博客、图集、动态和短视频。';
$this->params['meta_keywords'] = '注册, 创建账户, 拾光';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
?>
<div class="site-signup d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card">
        <div class="row g-0">

            <!-- Brand panel -->
            <div class="col-md-5 d-none d-md-flex login-brand-panel text-white">
                <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                    <div>
                        <h2 class="fw-bold mb-3 login-brand-title">
                            <b style="font-size: 2rem; color: #ff5e0e;">拾</b>取
                            <br/>生活的<br/>
                            时<b style="font-size: 2rem; color: #ff5e0e;">光</b>
                        </h2>
                        <p class="opacity-75 mb-0 login-brand-text">
                            注册后即可发布博客、图集、动态和短视频，并按关注内容搭建自己的信息流。
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form panel -->
            <div class="col-md-7">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">填写下面的信息即可创建账户</p>
                    </div>

                    <?= $this->render('_flash') ?>

                    <?php $form = ActiveForm::begin(['id' => 'form-signup']); ?>

                    <div class="mb-3">
                        <?= $form->field($model, 'username', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128100;'),
                            'inputOptions' => [
                                'autofocus' => true,
                                'class' => 'form-control',
                                'placeholder' => '用户名',
                                'maxlength' => true,
                            ],
                        ])->textInput()->label('用户名', $labelOptions)->hint('2–24 个字符，可用中英文、数字与点、下划线、短横线。') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'email', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#9993;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'name@example.com',
                                'maxlength' => true,
                                'autocomplete' => 'email',
                            ],
                        ])->textInput()->label('邮箱', $labelOptions)->hint('用于找回密码和接收账号通知，不会公开展示。') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'password', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128274;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => '密码',
                                'autocomplete' => 'new-password',
                            ],
                        ])->passwordInput()->label('密码', $labelOptions)->hint('至少 ' . (int) Yii::$app->params['user.passwordMinLength'] . ' 位，需同时包含字母和数字。') ?>
                    </div>

                    <div class="mb-4">
                        <?= $form->field($model, 'confirmPassword', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#128274;'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => '再次输入密码',
                                'autocomplete' => 'new-password',
                            ],
                        ])->passwordInput()->label('确认密码', $labelOptions) ?>
                    </div>

                    <div class="d-grid">
                        <?= Html::submitButton(
                            '注册',
                            [
                                'class' => 'btn login-btn btn-lg rounded-3 text-white',
                                'name' => 'signup-button',
                            ],
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                    <div class="text-body-secondary text-center mt-3 small">
                        已有账户？<?= Html::a('去登录', ['/users/login']) ?>
                        ·
                        <?= Html::a('收不到确认邮件？', ['/users/resend-verification-email']) ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
