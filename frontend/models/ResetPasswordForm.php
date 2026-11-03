<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use yii\base\InvalidArgumentException;
use yii\base\Model;

/**
 * Password reset form
 */
class ResetPasswordForm extends Model
{
    use PasswordRulesTrait;

    public string $password = '';
    public string $confirmPassword = '';

    private User|null $_user = null;

    /**
     * Creates a form model given a token.
     *
     * @param string $token 邮件链接里的明文令牌
     * @param array $config name-value pairs that will be used to initialize the object properties
     * @throws InvalidArgumentException if token is empty or not valid
     */
    public function __construct(string $token, array $config = [])
    {
        if ($token === '') {
            throw new InvalidArgumentException('重置密码链接不能为空。');
        }
        $this->_user = User::findByPasswordResetToken($token);
        if ($this->_user === null) {
            throw new InvalidArgumentException('重置密码链接无效或已过期。');
        }
        parent::__construct($config);
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['confirmPassword', 'trim'],
            ...$this->passwordRules('password', '新密码'),
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
            'password' => '新密码',
            'confirmPassword' => '确认新密码',
        ];
    }

    /**
     * Resets password.
     *
     * @return bool if password was reset.
     */
    public function resetPassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        /** @var User $user */
        $user = $this->_user;
        $user->setPassword($this->password);
        $user->removePasswordResetToken();
        // 换掉 auth_key：其他设备上的“记住我”身份 Cookie 一并作废。
        $user->generateAuthKey();
        $user->must_change_password = 0;

        return $user->save(false, [
            'password_hash', 'password_reset_token', 'auth_key', 'must_change_password', 'updated_at',
        ]);
    }
}
