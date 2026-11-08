<?php

declare(strict_types=1);

namespace restful\models;

use common\models\User as UserRecord;
use RuntimeException;
use Yii;
use yii\base\Model;

/**
 * REST 找回密码第二步：校验邮箱验证码并设置新密码。
 */
final class PasswordResetForm extends Model
{
    public string $email = '';
    public string $code = '';
    public string $password = '';
    public string $confirmPassword = '';

    public function rules(): array
    {
        $minLength = (int) Yii::$app->params['user.passwordMinLength'];

        return [
            [['email', 'code'], 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['code', 'required'],
            ['code', 'match', 'pattern' => '/^\d{6}$/', 'message' => '验证码必须是 6 位数字。'],
            ['password', 'required'],
            ['password', 'string', 'min' => $minLength, 'max' => 64],
            [
                'password',
                'match',
                'pattern' => '/^(?=.*[A-Za-z])(?=.*\d).+$/s',
                'message' => '新密码至少 ' . $minLength . ' 位，且需同时包含字母和数字。',
            ],
            ['confirmPassword', 'trim'],
            ['confirmPassword', 'required'],
            [
                'confirmPassword',
                'compare',
                'compareAttribute' => 'password',
                'message' => '两次输入的密码不一致。',
            ],
        ];
    }

    public function resetPassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = UserRecord::find()
                ->where(['email' => $this->email, 'status' => UserRecord::STATUS_ACTIVE])
                ->one();
            if ($user === null) {
                $transaction->commit();
                $this->addError('code', '验证码错误、已过期或已失效。');

                return false;
            }
            $codeValid = AuthEmailCode::consume(
                (int) $user->id,
                AuthEmailCode::PURPOSE_PASSWORD_RESET,
                $this->code,
            );
            if (!$codeValid) {
                $transaction->commit();
                $this->addError('code', '验证码错误、已过期或已失效。');

                return false;
            }

            $user->setPassword($this->password);
            $user->removePasswordResetToken();
            $user->generateAuthKey();
            $user->must_change_password = 0;
            $attributes = [
                'password_hash',
                'password_reset_token',
                'auth_key',
                'must_change_password',
                'updated_at',
            ];
            if (!$user->save(false, $attributes)) {
                throw new RuntimeException('密码重置保存失败。');
            }

            $transaction->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }
}
