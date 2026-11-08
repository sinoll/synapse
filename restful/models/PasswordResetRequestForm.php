<?php

declare(strict_types=1);

namespace restful\models;

use common\models\User as UserRecord;
use restful\services\AuthMailer;
use Yii;
use yii\base\Model;

/**
 * REST 找回密码第一步：检查账户状态并向可用账户发送验证码。
 */
final class PasswordResetRequestForm extends Model
{
    public string $email = '';

    public function rules(): array
    {
        return [
            ['email', 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
        ];
    }

    public function sendCode(): bool|null
    {
        $user = UserRecord::find()
            ->where(['email' => $this->email])
            ->one();
        if ($user === null) {
            $this->addError('email', '该邮箱尚未注册。');

            return false;
        }
        if ((int) $user->status !== UserRecord::STATUS_ACTIVE) {
            $this->addError('email', '该邮箱对应的账户未激活或已停用，无法重置密码。');

            return false;
        }

        if ($user->email === '') {
            $this->addError('email', '该账户未设置有效邮箱，无法重置密码。');

            return false;
        }

        $code = AuthEmailCode::issue((int) $user->id, AuthEmailCode::PURPOSE_PASSWORD_RESET);
        if ($code === null) {
            return null;
        }

        $sent = AuthMailer::sendCode(
            Yii::$app->mailSender,
            $user->email,
            AuthEmailCode::PURPOSE_PASSWORD_RESET,
            $code,
        );
        if (!$sent) {
            AuthEmailCode::discard($user->email, AuthEmailCode::PURPOSE_PASSWORD_RESET);

            return false;
        }

        return true;
    }
}
