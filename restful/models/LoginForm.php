<?php

declare(strict_types=1);

namespace restful\models;

use common\models\User;
use yii\base\Model;

/**
 * REST 登录凭据校验，不建立网站会话或写入 Cookie。
 */
final class LoginForm extends Model
{
    public string $username = '';
    public string $password = '';

    private User|null $_user = null;

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            ['password', 'validatePassword'],
        ];
    }

    public function validatePassword(string $attribute, array|null $params): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $user = $this->getUser();
        if ($user === null || !$user->validatePassword($this->password)) {
            $this->addError($attribute, '用户名或密码不正确。');

            return;
        }

        if ($user->status === User::STATUS_INACTIVE) {
            $this->addError('username', '该账户尚未激活。');
        }
    }

    public function validateCredentials(): User|null
    {
        if (!$this->validate()) {
            return null;
        }

        return $this->getUser();
    }

    private function getUser(): User|null
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
