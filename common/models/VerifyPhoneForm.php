<?php

declare(strict_types=1);

namespace common\models;

use common\services\SmsSenderInterface;
use Throwable;
use Yii;
use yii\base\Model;

class VerifyPhoneForm extends Model
{
    public string $phone = '';
    public string $code = '';

    public function rules(): array
    {
        return [
            ['phone', 'required'],
            ['phone', 'match', 'pattern' => '/^\+?[0-9]{6,20}$/'],
            ['phone', 'validateUniquePhone'],
            ['code', 'string', 'length' => 6],
        ];
    }

    public function validateUniquePhone(string $attribute): void
    {
        $user = Yii::$app->user->identity;
        if (User::find()->where(['phone' => $this->$attribute])->andWhere(['<>', 'id', $user->id])->exists()) {
            $this->addError($attribute, '该手机号已被其他账户使用。');
        }
    }

    public function sendCode(SmsSenderInterface $smsSender): bool
    {
        if (!$this->validate(['phone'])) {
            return false;
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        if ($user->sms_code_sent_at !== null && $user->sms_code_sent_at > time() - 60) {
            $this->addError('phone', '请稍后再获取验证码。');
            return false;
        }

        $code = (string) random_int(100000, 999999);
        try {
            $smsSender->sendVerificationCode($this->phone, $code);
        } catch (Throwable $exception) {
            $this->addError('phone', $exception->getMessage());
            return false;
        }

        $user->phone = $this->phone;
        $user->phone_verified_at = null;
        $user->sms_code_hash = Yii::$app->security->generatePasswordHash($code);
        $user->sms_code_expires_at = time() + 300;
        $user->sms_code_sent_at = time();
        $user->sms_code_attempts = 0;
        return $user->save(false, [
            'phone', 'phone_verified_at', 'sms_code_hash', 'sms_code_expires_at',
            'sms_code_sent_at', 'sms_code_attempts', 'updated_at',
        ]);
    }

    public function verify(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        if ($user->sms_code_attempts >= 5) {
            $this->addError('code', '验证码尝试次数过多，请重新获取验证码。');
            return false;
        }
        if (
            $user->phone !== $this->phone
            || $user->sms_code_hash === null
            || $user->sms_code_expires_at === null
            || $user->sms_code_expires_at < time()
            || !Yii::$app->security->validatePassword($this->code, $user->sms_code_hash)
        ) {
            $user->sms_code_attempts++;
            $user->save(false, ['sms_code_attempts', 'updated_at']);
            $this->addError('code', '验证码错误或已过期，请重新获取。');
            return false;
        }

        $user->phone_verified_at = time();
        $user->sms_code_hash = null;
        $user->sms_code_expires_at = null;
        $user->sms_code_attempts = 0;
        return $user->save(false, ['phone_verified_at', 'sms_code_hash', 'sms_code_expires_at', 'sms_code_attempts', 'updated_at']);
    }
}
