<?php

declare(strict_types=1);

namespace restful\models;

use common\models\User as UserRecord;
use restful\services\AuthMailer;
use RuntimeException;
use Yii;
use yii\base\Model;
use yii\db\IntegrityException;

/**
 * REST 注册第二步：消费邮箱验证码并直接创建已激活账户。
 */
final class RegistrationForm extends Model
{
    public string $username = '';
    public string $nickname = '';
    public string $email = '';
    public string $code = '';
    public string $password = '';
    public string $confirmPassword = '';

    private const RESERVED_USERNAMES = [
        'admin', 'administrator', 'root', 'system', 'server', 'guest',
        'moderator', 'support', 'service', 'postmaster', 'webmaster', 'default',
    ];

    public function rules(): array
    {
        $minLength = (int) Yii::$app->params['user.passwordMinLength'];

        return [
            [['username', 'nickname', 'email', 'code', 'password', 'confirmPassword'], 'trim'],
            ['username', 'required'],
            ['username', 'string', 'min' => 2, 'max' => 24],
            [
                'username',
                'match',
                'pattern' => '/^[\p{L}\p{N}_.\-]+$/u',
                'message' => '用户名只能包含中英文、数字以及点、下划线、短横线。',
            ],
            ['username', 'validateReservedUsername'],
            ['username', 'unique', 'targetClass' => UserRecord::class, 'message' => '该用户名已被占用。'],
            ['nickname', 'string', 'max' => 80],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'targetClass' => UserRecord::class, 'message' => '该邮箱已被注册。'],
            ['code', 'required'],
            ['code', 'match', 'pattern' => '/^\d{6}$/', 'message' => '验证码必须是 6 位数字。'],
            ['password', 'required'],
            ['password', 'string', 'min' => $minLength, 'max' => 64],
            [
                'password',
                'match',
                'pattern' => '/^(?=.*[A-Za-z])(?=.*\d).+$/s',
                'message' => '密码至少 ' . $minLength . ' 位，且需同时包含字母和数字。',
            ],
            ['confirmPassword', 'required'],
            [
                'confirmPassword',
                'compare',
                'compareAttribute' => 'password',
                'message' => '两次输入的密码不一致。',
            ],
        ];
    }

    public function validateReservedUsername(string $attribute): void
    {
        if (in_array(mb_strtolower($this->$attribute), self::RESERVED_USERNAMES, true)) {
            $this->addError($attribute, '该名称不可用，请换一个用户名。');
        }
    }

    public function register(): UserRecord|null
    {
        if (!$this->validate()) {
            return null;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = new UserRecord();
            $user->username = $this->username;
            $user->nickname = $this->nickname === '' ? null : $this->nickname;
            $user->email = $this->email;
            $user->status = UserRecord::STATUS_ACTIVE;
            $user->must_change_password = 0;
            $user->is_admin = 0;
            $user->setPassword($this->password);
            $user->generateAuthKey();
            if (!$user->save(false)) {
                throw new RuntimeException('注册账户保存失败。');
            }

            if (!AuthEmailCode::consumeForEmail(
                $this->email,
                AuthEmailCode::PURPOSE_EMAIL_VERIFICATION,
                $this->code,
            )) {
                $transaction->rollBack();
                $this->addError('code', '验证码错误、已过期或已失效。');

                return null;
            }

            $transaction->commit();
        } catch (IntegrityException) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            $this->addError('username', '该用户名或邮箱已被占用。');

            return null;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }

        return $user;
    }
}
