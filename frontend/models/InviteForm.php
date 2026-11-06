<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use common\services\InviteToken;
use common\services\MailSenderInterface;
use frontend\components\MailMessage;
use Yii;
use yii\base\Model;

/**
 * 系统管理里的邀请注册表单：管理员填一个邮箱，系统给它发一封带邀请链接的注册邀请。
 *
 * 不建账户、不记邀请：链接里自带上邮箱和过期时间，见 common\services\InviteToken。
 */
class InviteForm extends Model
{
    /** 同一地址两次邀请的最小间隔（秒），防止手抖连点把对方邮箱刷爆。 */
    private const RESEND_COOLDOWN = 60;

    public string $email = '';

    public function rules(): array
    {
        return [
            ['email', 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'validateNotRegistered'],
            ['email', 'validateCooldown'],
        ];
    }

    public function attributeLabels(): array
    {
        return ['email' => '邮箱地址'];
    }

    public function validateNotRegistered(string $attribute): void
    {
        $email = mb_strtolower(trim((string) $this->$attribute));
        if ($email !== '' && User::find()->where(['email' => $email])->exists()) {
            $this->addError($attribute, '该邮箱已经有账户，不需要再邀请。');
        }
    }

    public function validateCooldown(string $attribute): void
    {
        $lastSentAt = Yii::$app->cache->get($this->throttleKey());
        if ($lastSentAt === false) {
            return;
        }
        $waiting = self::RESEND_COOLDOWN - (time() - (int) $lastSentAt);
        if ($waiting > 0) {
            $this->addError($attribute, "已经邀请过这个邮箱，请 {$waiting} 秒后再发。");
        }
    }

    /**
     * 发出邀请信。只有校验通过且投递成功才返回 true，冷却标记也在成功之后才打，
     * 这样 SMTP 故障时管理员修好配置能立刻重发。
     */
    public function send(MailSenderInterface $mailSender): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $email = mb_strtolower(trim($this->email));
        $sent = MailMessage::send(
            $mailSender,
            $email,
            '邀请你注册 ' . Yii::$app->name,
            '@common/mail/inviteSignup',
            [
                'siteName' => Yii::$app->name,
                'inviteLink' => Yii::$app->urlManager->createAbsoluteUrl(
                    ['users/signup', 'invite' => InviteToken::issue($email)],
                ),
                'days' => intdiv((int) Yii::$app->params['user.inviteTokenExpire'], 86400),
            ],
        );
        if ($sent) {
            Yii::$app->cache->set($this->throttleKey(), time(), self::RESEND_COOLDOWN);
        }

        return $sent;
    }

    private function throttleKey(): string
    {
        return 'invite/' . hash('sha256', mb_strtolower(trim($this->email)));
    }
}
