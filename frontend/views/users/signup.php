<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \frontend\models\SignupForm $model */
/** @var bool $signupEnabled */
/** @var string|null $invitedEmail */
/** @var string $inviteToken */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

// 受邀时即使自助注册关着也照旧出表单：邀请链接本身就是管理员给出去的注册授权。
$invitedEmail = is_string($invitedEmail) && $invitedEmail !== '' ? $invitedEmail : null;
$canRegister = $signupEnabled || $invitedEmail !== null;
$this->title = $invitedEmail === null ? '注册新账户' : '接受邀请，创建账户';
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
                        <p class="text-body-secondary small">
                            <?php if ($invitedEmail !== null): ?>
                                管理员已邀请 <strong><?= Html::encode($invitedEmail) ?></strong> 加入本站，填完下面的信息即可创建账户。
                            <?php elseif ($signupEnabled): ?>
                                填写下面的信息创建账户，再点击确认邮件里的链接激活即可登录
                            <?php else: ?>
                                自助注册暂时关闭，请稍后再来
                            <?php endif; ?>
                        </p>
                    </div>

                    <?= $this->render('_flash') ?>

                    <?php if (!$canRegister): ?>
                        <div class="alert alert-warning mb-0" role="alert">
                            <strong class="d-block mb-1">注册暂时关闭</strong>
                            站点管理员已暂停自助注册。已有账户可以在下方直接登录；需要新账户请联系管理员开通。
                        </div>
                    <?php endif; ?>

                    <?php if ($canRegister): ?>
                    <?php $form = ActiveForm::begin([
                        'id' => 'form-signup',
                        // 邀请令牌挂在地址上：邮箱只读挡不住改包，服务端要靠它认出受邀身份。
                        'action' => $inviteToken === '' ? ['signup'] : ['signup', 'invite' => $inviteToken],
                    ]); ?>

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
                            ] + ($invitedEmail === null ? [] : ['readonly' => true]),
                        ])->textInput()->label('邮箱', $labelOptions)->hint($invitedEmail === null
                            ? '用于激活账户、找回密码和接收账号通知，不会公开展示。'
                            : '邀请链接已经锁定这个邮箱，不能改成别的地址。') ?>
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
                    <?php endif; ?>

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
