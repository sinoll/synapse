<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $code */
/** @var int $minutes */
/** @var string $resetLink */
?>
<div class="login-code">
    <p><?= Html::encode($user->displayName()) ?>，你好：</p>

    <p>你的账户 <strong><?= Html::encode($user->username) ?></strong> 正在 <?= Html::encode(Yii::$app->name) ?> 登录。这个账户已经很久没登录过，本次登录需要邮箱验证码。</p>

    <p>验证码（<?= $minutes ?> 分钟内有效，只用一次）：</p>

    <p style="font-size:1.6rem;font-weight:700;letter-spacing:.35rem;color:#16a34a"><?= Html::encode($code) ?></p>

    <p>把验证码填回登录页即可完成登录。我们不会以任何方式向你索要这个验证码。</p>

    <p>如果这次登录不是你本人发起的，说明密码可能已经泄露，请立刻重置：<?= Html::a('重置密码', $resetLink, ['style' => 'color:#16a34a;']) ?></p>
</div>
