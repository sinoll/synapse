<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User $user */
/** @var string $code */
/** @var int $minutes */
/** @var string $resetLink */
?>
<?= $user->displayName() ?>，你好：

你的账户 <?= $user->username ?> 正在 <?= Yii::$app->name ?> 登录。这个账户已经很久没登录过，本次登录需要邮箱验证码。

验证码（<?= $minutes ?> 分钟内有效，只用一次）：

<?= $code ?>


把验证码填回登录页即可完成登录。我们不会以任何方式向你索要这个验证码。

如果这次登录不是你本人发起的，说明密码可能已经泄露，请立刻重置密码：<?= $resetLink ?>
