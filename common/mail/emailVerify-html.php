<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $verifyLink */
?>
<div class="verify-email">
    <p><?= Html::encode($user->displayName()) ?>，你好：</p>

    <p>感谢你在 <?= Html::encode(Yii::$app->name) ?> 注册账户。点击下方按钮确认这个邮箱归你所有（7 天内有效）：</p>

    <p><?= Html::a('确认邮箱', $verifyLink, ['style' => 'display:inline-block;padding:.6rem 1.4rem;border-radius:999px;background:#16a34a;color:#fff;text-decoration:none;']) ?></p>

    <p style="word-break:break-all"><?= Html::encode($verifyLink) ?></p>

    <p>确认邮箱不是登录的前提，但完成后我们才能通过邮件帮你找回密码、通知账号异常。</p>
</div>
