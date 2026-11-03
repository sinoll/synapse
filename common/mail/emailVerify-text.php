<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $verifyLink */
?>
<?= $user->displayName() ?>，你好：

感谢你在 <?= Yii::$app->name ?> 注册账户。打开下面的链接确认这个邮箱归你所有（7 天内有效）：

<?= $verifyLink ?>

确认邮箱不是登录的前提，但完成后我们才能通过邮件帮你找回密码、通知账号异常。
