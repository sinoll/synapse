<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \frontend\models\ResendVerificationEmailForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = '重发确认邮件';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = '重新发送拾光账户的邮箱确认邮件。';
$this->params['meta_keywords'] = '邮箱验证, 重发确认邮件, 拾光';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
?>
<div class="site-resend-verification-email d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card">
        <div class="row g-0">

            <!-- Brand panel -->
            <div class="col-md-5 d-none d-md-flex login-brand-panel text-white">
                <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                    <div>
                        <h2 class="fw-bold mb-3 login-brand-title">
                            确认你的<br>邮箱
                        </h2>
                        <p class="opacity-75 mb-0 login-brand-text">
                            我们会重新发送一封确认邮件。账户要点击邮件里的确认链接激活后才能登录。
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form panel -->
            <div class="col-md-7">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">填写注册邮箱以接收新的确认链接</p>
                    </div>

                    <?= $this->render('_flash') ?>

                    <?php $form = ActiveForm::begin([
                        'id' => 'resend-verification-email-form',
                        'action' => ['/users/resend-verification-email'],
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
                            '重新发送',
                            [
                                'class' => 'btn login-btn btn-lg rounded-3 text-white',
                            ],
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>

                    <p class="text-body-secondary text-center small mt-3 mb-0">
                        为保护账户，无论该邮箱是否需要确认，这里的提示都完全一样。
                    </p>

                    <div class="text-body-secondary text-center mt-2 small">
                        <?= Html::a('去登录', ['/users/login']) ?>
                        ·
                        <?= Html::a('找回密码', ['/users/request-password-reset']) ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
