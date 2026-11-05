<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $verifyLink */
?>
<?= $user->displayName() ?>，你好：

感谢你在 <?= Yii::$app->name ?> 注册账户。打开下面的链接确认这个邮箱归你所有并激活账户（链接 7 天内有效）：

<?= $verifyLink ?>

激活后才能登录。如果收不到这封邮件，可以在登录页重新发送确认邮件。
