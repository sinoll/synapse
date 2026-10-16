<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use Yii;
use yii\base\Model;

class CreateUserForm extends Model
{
    public string $username = '';
    public string $email = '';
    public string $phone = '';
    public string $nickname = '';
    public string $password = '';

    public function rules(): array
    {
        return [
            [['username', 'email', 'password'], 'required'],
            ['username', 'trim'],
            ['username', 'string', 'min' => 2, 'max' => 255],
            ['username', 'unique', 'targetClass' => User::class],
            ['email', 'trim'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'targetClass' => User::class],
            ['phone', 'string', 'max' => 32],
            ['phone', 'unique', 'targetClass' => User::class],
            ['nickname', 'string', 'max' => 80],
            ['password', 'string', 'min' => Yii::$app->params['user.passwordMinLength']],
        ];
    }

    public function create(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $user = new User();
        $user->username = $this->username;
        $user->email = $this->email;
        $user->phone = $this->phone === '' ? null : $this->phone;
        $user->nickname = $this->nickname === '' ? null : $this->nickname;
        $user->status = User::STATUS_ACTIVE;
        $user->must_change_password = 1;
        $user->is_admin = 0;
        $user->setPassword($this->password);
        $user->generateAuthKey();

        return $user->save();
    }
}
