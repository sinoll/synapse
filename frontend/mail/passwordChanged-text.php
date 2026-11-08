<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $changedAt */
?>
<?= $user->displayName() ?>，您好：

你的 <?= Yii::$app->name ?> 账户密码已于 <?= $changedAt ?> 成功更改。

如果这是你本人的操作，无需采取其他措施。为保护账户安全，请勿向任何人透露密码。

如果这不是你本人的操作，请立即通过登录页面的“忘记密码”功能重置密码，并尽快联系网站管理员。

出于安全考虑，更改密码后，其他设备上的登录状态可能会失效。

此邮件为系统自动发送，请勿直接回复。
