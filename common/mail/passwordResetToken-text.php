<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $resetLink */
?>
<?= $user->displayName() ?>，你好：

我们收到了重置你的密码的请求。打开下面的链接设置新密码（<?= (int) Yii::$app->params['user.passwordResetTokenExpire'] / 60 ?> 分钟内有效，且只能使用一次）：

<?= $resetLink ?>

如果这不是你本人的操作，请忽略这封邮件，你的密码不会被修改。
