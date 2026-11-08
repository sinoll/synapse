<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $siteName */
/** @var string $inviteLink */
/** @var int $days */
?>
你好：

<?= $siteName ?> 的管理员邀请你注册一个账户。打开下面的链接开始（链接 <?= $days ?> 天内有效，只能用来注册这一个账户）：

<?= $inviteLink ?>


打开链接后邮箱已经替你填好，只要设置用户名和密码；即使本站暂时关闭了自助注册，这个邀请链接也照样能用。注册完成后按页面提示再点击确认邮件里的链接，账户就激活了。

如果你不感兴趣，忽略这封邮件即可，我们不会再打扰你。
