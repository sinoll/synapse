<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \frontend\models\PasswordResetRequestForm $model */

use common\models\SiteSetting;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '找回密码';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = '通过注册邮箱接收找回密码链接，重新进入拾光账户。';
$this->params['meta_keywords'] = '找回密码, 重置密码, 拾光';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
?>
<div class="site-request-password-reset d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card">
        <div class="row g-0">

            <!-- Brand panel -->
            <div class="col-md-5 d-none d-md-flex login-brand-panel text-white">
                <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                    <div>
                        <h2 class="fw-bold mb-3 login-brand-title">
                            忘记密码了？<br>没关系
                        </h2>
                        <p class="opacity-75 mb-0 login-brand-text">
                            填写注册邮箱，我们会发送一条一次性重置链接，1 小时内有效且只能使用一次。
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form panel -->
            <div class="col-md-7">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">重置链接会发送到你的注册邮箱</p>
                    </div>

                    <?= $this->render('_flash') ?>

                    <?php $form = ActiveForm::begin([
                        'id' => 'request-password-reset-form',
                        'action' => ['/users/request-password-reset'],
                        'method' => 'post',
                    ]); ?>

                    <div class="mb-4">
                        <?= $form->field($model, 'email', [
                            'options' => ['class' => 'mb-0'],
                            'template' => sprintf($htmlIcon, '&#9993;'),
                            'inputOptions' => [
                                'autofocus' => true,
                                'class' => 'form-control',
                                'placeholder' => 'name@example.com',
                                'maxlength' => true,
                                'autocomplete' => 'email',
                            ],
                        ])->textInput()->label('注册邮箱', $labelOptions) ?>
                    </div>

                    <div class="d-grid">
                        <?= Html::submitButton(
                            '发送重置链接',
                            [
                                'class' => 'btn login-btn btn-lg rounded-3 text-white',
                            ],
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                    <p class="text-body-secondary text-center small mt-3 mb-0">
                        为保护账户，无论该邮箱是否注册过，这里的提示都完全一样。
                    </p>

                    <div class="text-body-secondary text-center mt-2 small">
                        想起来了？<?= Html::a('去登录', ['/users/login']) ?>
                        <?php if (SiteSetting::signupEnabled()): ?>
                            ·
                            <?= Html::a('注册新账户', ['/users/signup']) ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
