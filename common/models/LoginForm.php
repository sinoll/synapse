<?php

declare(strict_types=1);

namespace common\models;

use yii\base\Model;

/**
 * Login form
 */
class LoginForm extends Model
{
    public string $username = '';
    public string $password = '';
    public bool $rememberMe = true;
    /**
     * 是否要求"很久没登录的账户先过一次邮箱验证码"。默认关闭：后台登录复用本模型，
     * 目前只有前台 users/login 走这一关，由控制器显式打开。
     */
    public bool $emailCodeEnabled = false;
    /** @var User|null 口令正确、但需要先拿邮箱验证码的账户；[[login]] 返回 false 且它非 null 时说明卡在这一步。 */
    public User|null $emailCodeUser = null;
    private User|null $_user = null;
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword(string $attribute, array|null $params): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $user = $this->getUser();

        if (!$user || !$user->validatePassword($this->password)) {
            $this->addError($attribute, 'Incorrect username or password.');

            return;
        }

        // 口令对了但还没点过邮件确认：告诉他该去做什么，而不是继续报"密码错误"。
        // 只在密码校验通过后才暴露这一点，不影响反账号枚举。
        if ($user->status === User::STATUS_INACTIVE) {
            $this->addError('username', '该账户尚未激活，请前往邮箱点击确认链接；收不到邮件可在下方重新发送。');
        }
    }

    /**
     * Logs in a user using the provided username and password.
     *
     * @return bool whether the user is logged in successfully
     */
    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $user = $this->getUser();
        // 开关只在前台打开；走到这里口令已经校验通过，先不发会话，让控制器去发邮箱验证码。
        // 非 null 的 [[emailCodeUser]] 就是"卡在第二步"的信号，与"密码错误"区分开。
        if ($this->emailCodeEnabled && $user->needsLoginEmailCode()) {
            $this->emailCodeUser = $user;

            return false;
        }

        return $user->signIn($this->rememberMe);
    }

    /**
     * Finds user by [[username]]
     *
     * 待激活账户也要查得到，否则无法区分"密码错"和"没激活"；能不能登录仍由 [[validatePassword]] 决定。
     *
     * @return User|null
     */
    protected function getUser(): User|null
    {
        if ($this->_user === null) {
            $this->_user = User::find()
                ->where(['username' => $this->username])
                ->andWhere(['<>', 'status', User::STATUS_DELETED])
                ->one();
        }

        return $this->_user;
    }
}
