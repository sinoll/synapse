<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\base\Model;

class ChangePasswordForm extends Model
{
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $confirmPassword = '';

    public function rules(): array
    {
        return [
            [['currentPassword', 'newPassword', 'confirmPassword'], 'required'],
            ['currentPassword', 'validateCurrentPassword'],
            ['newPassword', 'string', 'min' => Yii::$app->params['user.passwordMinLength']],
            ['confirmPassword', 'compare', 'compareAttribute' => 'newPassword'],
        ];
    }

    public function validateCurrentPassword(string $attribute): void
    {
        $user = Yii::$app->user->identity;
        if ($user === null || !$user->validatePassword($this->$attribute)) {
            $this->addError($attribute, '当前密码不正确。');
        }
    }

    public function changePassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        $user->setPassword($this->newPassword);
        $user->must_change_password = 0;
        $user->generateAuthKey();
        if (!$user->save(false, ['password_hash', 'must_change_password', 'auth_key', 'updated_at'])) {
            return false;
        }

        Yii::$app->user->switchIdentity($user);
        return true;
    }
}
