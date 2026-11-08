<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use common\services\MailSenderInterface;
use frontend\components\MailMessage;
use Yii;
use yii\base\Model;

/**
 * 重新发送邮箱确认信。
 */
class ResendVerificationEmailForm extends Model
{
    public string $email = '';

    private const RESEND_COOLDOWN = 60;

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
     * 与找回密码请求同样的反枚举语义：账号不存在或仍在冷却期也返回 true，
     * 只有服务端确实没发出去才返回 false。
     */
    public function sendEmail(MailSenderInterface $mailSender): bool
    {
        $user = User::find()
            ->where(['email' => $this->email, 'status' => User::STATUS_INACTIVE])
            ->one();
        if ($user === null) {
            return true;
        }

        $throttleKey = 'email-verify/' . hash('sha256', mb_strtolower($this->email));
        if (Yii::$app->cache->get($throttleKey) !== false) {
            return true;
        }

        // 重签后旧链接立即失效。
        $token = $user->generateEmailVerificationToken();
        if (!$user->save(false, ['verification_token', 'updated_at'])) {
            return false;
        }
        Yii::$app->cache->set($throttleKey, 1, self::RESEND_COOLDOWN);

        $sent = MailMessage::send(
            $mailSender,
            $user->email,
            '激活你的账户 - ' . Yii::$app->name,
            '@frontend/mail/emailVerify',
            [
                'user' => $user,
                'verifyLink' => Yii::$app->urlManager->createAbsoluteUrl(
                    ['users/verify-email', 'token' => $token],
                ),
            ],
        );
        if (!$sent) {
            Yii::$app->cache->delete($throttleKey);
        }

        return $sent;
    }
}
