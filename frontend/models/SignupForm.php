<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use common\services\MailSenderInterface;
use frontend\components\MailMessage;
use Yii;
use yii\base\Model;
use yii\db\IntegrityException;

/**
 * Signup form
 */
class SignupForm extends Model
{
    use PasswordRulesTrait;

    public string $username = '';
    public string $nickname = '';
    public string $email = '';
    public string $password = '';
    public string $confirmPassword = '';

    /** 与角色/系统账号重名的用户名一律拒绝，避免冒充管理员。 */
    private const RESERVED_USERNAMES = [
        'admin', 'administrator', 'root', 'system', 'server', 'guest',
        'moderator', 'support', 'service', 'postmaster', 'webmaster', 'default',
    ];

    private User|null $_createdUser = null;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['username', 'nickname', 'email', 'password', 'confirmPassword'], 'trim'],
            ['username', 'required'],
            ['username', 'string', 'min' => 2, 'max' => 24],
            [
                'username',
                'match',
                'pattern' => '/^[\p{L}\p{N}_.\-]+$/u',
                'message' => '用户名只能包含中英文、数字以及点、下划线、短横线。',
            ],
            ['username', 'validateReservedUsername'],
            ['username', 'unique', 'targetClass' => User::class, 'message' => '该用户名已被占用。'],
            ['nickname', 'string', 'max' => 80],

            ['email', 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'targetClass' => User::class, 'message' => '该邮箱已被注册。'],

            ...$this->passwordRules('password'),
            ['confirmPassword', 'required'],
            [
                'confirmPassword',
                'compare',
                'compareAttribute' => 'password',
                'message' => '两次输入的密码不一致。',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => '用户名',
            'nickname' => '昵称',
            'email' => '邮箱',
            'password' => '密码',
            'confirmPassword' => '确认密码',
        ];
    }

    public function validateReservedUsername(string $attribute): void
    {
        if (in_array(mb_strtolower($this->$attribute), self::RESERVED_USERNAMES, true)) {
            $this->addError($attribute, '该名称不可用，请换一个用户名。');
        }
    }

    /**
     * Signs user up.
     *
     * @return User|null 注册成功的账户，失败或未通过校验返回 null
     */
    public function signup(): User|null
    {
        if (!$this->validate()) {
            return null;
        }

        $user = new User();
        $user->username = $this->username;
        $user->nickname = $this->nickname === '' ? null : $this->nickname;
        $user->email = $this->email;
        // 账户要等用户点击确认邮件里的链接才激活，见 VerifyEmailForm::verifyEmail()。
        $user->status = User::STATUS_INACTIVE;
        // 自助注册者刚自己选过口令，不能再被强制改密；公开入口也永远不给管理权限。
        $user->must_change_password = 0;
        $user->is_admin = 0;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->generateEmailVerificationToken();

        try {
            if (!$user->save(false)) {
                return null;
            }
        } catch (IntegrityException) {
            $this->addError('username', '该用户名或邮箱已被占用。');
            return null;
        }

        $this->_createdUser = $user;

        return $user;
    }

    /**
     * 发送激活邮件（内含一次性确认链接）。发信失败不影响注册结果，账户保持待激活。
     */
    public function sendVerificationEmail(MailSenderInterface $mailSender): bool
    {
        if ($this->_createdUser === null) {
            return false;
        }

        return MailMessage::send(
            $mailSender,
            $this->_createdUser->email,
            '激活你的账户 - ' . Yii::$app->name,
            '@common/mail/emailVerify',
            ['user' => $this->_createdUser, 'verifyLink' => Yii::$app->urlManager->createAbsoluteUrl(
                ['users/verify-email', 'token' => $this->_createdUser->emailVerifySecret],
            )],
        );
    }
}
