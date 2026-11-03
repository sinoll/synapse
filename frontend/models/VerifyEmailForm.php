<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use yii\base\InvalidArgumentException;
use yii\base\Model;

/**
 * 邮箱确认：消费邮件链接里的一次性令牌。
 */
class VerifyEmailForm extends Model
{
    private User|null $_user = null;

    /**
     * @throws InvalidArgumentException 令牌为空、无效或已过期
     */
    public function __construct(string $token, array $config = [])
    {
        if ($token === '') {
            throw new InvalidArgumentException('邮箱验证链接不能为空。');
        }

        $this->_user = User::findByVerificationToken($token);

        if ($this->_user === null) {
            throw new InvalidArgumentException('邮箱验证链接无效或已过期。');
        }

        parent::__construct($config);
    }

    /**
     * Verify email.
     *
     * @return User|null 验证成功的账户，保存失败返回 null
     */
    public function verifyEmail(): User|null
    {
        /** @var User $user */
        $user = $this->_user;
        $user->removeEmailVerificationToken();
        if ($user->status === User::STATUS_INACTIVE) {
            $user->status = User::STATUS_ACTIVE;
        }

        return $user->save(false, ['verification_token', 'status', 'updated_at']) ? $user : null;
    }
}
