<?php

declare(strict_types=1);

namespace restful\models;

use common\models\User as UserRecord;
use restful\services\AuthMailer;
use Yii;
use yii\base\Model;

/**
 * 注册第一步：确认邮箱格式并发送验证码，不创建用户记录。
 */
final class RegistrationCodeRequestForm extends Model
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
        if (UserRecord::find()->where(['email' => $this->email])->exists()) {
            $this->addError('email', '该邮箱已注册，不能再次申请注册验证码。');

            return false;
        }

        $code = AuthEmailCode::issueForEmail(
            $this->email,
            AuthEmailCode::PURPOSE_EMAIL_VERIFICATION,
        );
        if ($code === null) {
            return null;
        }

        $sent = AuthMailer::sendCode(
            Yii::$app->mailSender,
            $this->email,
            AuthEmailCode::PURPOSE_EMAIL_VERIFICATION,
            $code,
        );
        if (!$sent) {
            AuthEmailCode::discard($this->email, AuthEmailCode::PURPOSE_EMAIL_VERIFICATION);

            return false;
        }

        return true;
    }
}
