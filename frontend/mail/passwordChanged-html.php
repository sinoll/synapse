<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $changedAt */
?>
<div class="password-changed">
    <p><?= Html::encode($user->displayName()) ?>，您好：</p>

    <p>你的 <?= Html::encode(Yii::$app->name) ?> 账户密码已于 <?= Html::encode($changedAt) ?> 成功更改。</p>

    <p>如果这是你本人的操作，无需采取其他措施。为保护账户安全，请勿向任何人透露密码。</p>

    <p><strong>如果这不是你本人的操作：</strong>请立即通过登录页面的“忘记密码”功能重置密码，并尽快联系网站管理员。</p>

    <p>出于安全考虑，更改密码后，其他设备上的登录状态可能会失效。</p>

    <p>此邮件为系统自动发送，请勿直接回复。</p>
</div>
