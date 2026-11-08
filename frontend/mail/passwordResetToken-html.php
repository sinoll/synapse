<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $resetLink */
?>
<div class="password-reset">
    <p><?= Html::encode($user->displayName()) ?>，你好：</p>

    <p>我们收到了重置你的密码的请求。点击下方按钮或链接设置新密码（<?= (int) Yii::$app->params['user.passwordResetTokenExpire'] / 60 ?> 分钟内有效，且只能使用一次）：</p>

    <p><?= Html::a('重置密码', $resetLink, ['style' => 'display:inline-block;padding:.6rem 1.4rem;border-radius:999px;background:#16a34a;color:#fff;text-decoration:none;']) ?></p>

    <p style="word-break:break-all"><?= Html::encode($resetLink) ?></p>

    <p>如果这不是你本人的操作，请忽略这封邮件，你的密码不会被修改。</p>
</div>
