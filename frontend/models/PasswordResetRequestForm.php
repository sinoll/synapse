<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use common\services\MailSenderInterface;
use frontend\components\MailMessage;
use Yii;
use yii\base\Model;

/**
 * Password reset request form
 */
class PasswordResetRequestForm extends Model
{
    public string $email = '';

    /** 同一邮箱两次申请的最小间隔（秒），压住邮件轰炸和令牌刷量。 */
    private const RESEND_COOLDOWN = 60;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['email', 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return ['email' => '邮箱'];
    }

    /**
     * Sends an email with a link, for resetting the password.
     *
     * 账号不存在、或还在冷却期内时同样返回 true：调用方据此显示统一的成功提示，
     * 只有服务端确实没发出去才返回 false，因此错误信息不会泄露账号是否存在。
     */
    public function sendEmail(MailSenderInterface $mailSender): bool
    {
        $user = User::find()
            ->where(['email' => $this->email, 'status' => User::STATUS_ACTIVE])
            ->one();
        if ($user === null) {
            return true;
        }

        $throttleKey = 'password-reset/' . hash('sha256', mb_strtolower($this->email));
        if (Yii::$app->cache->get($throttleKey) !== false) {
            return true;
        }

        // 总是重签，旧链接随之失效，一个账户不会同时挂着多个可用令牌。
        $token = $user->generatePasswordResetToken();
        if (!$user->save(false, ['password_reset_token', 'updated_at'])) {
            return false;
        }
        Yii::$app->cache->set($throttleKey, 1, self::RESEND_COOLDOWN);

        $sent = MailMessage::send(
            $mailSender,
            $user->email,
            '重置你的密码 - ' . Yii::$app->name,
            '@common/mail/passwordResetToken',
            [
                'user' => $user,
                'resetLink' => Yii::$app->urlManager->createAbsoluteUrl(
                    ['users/reset-password', 'token' => $token],
                ),
            ],
        );
        if (!$sent) {
            Yii::$app->cache->delete($throttleKey);
        }

        return $sent;
    }
}
