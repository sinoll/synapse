<?php

declare(strict_types=1);

namespace restful\models;

use common\models\User as UserRecord;
use RuntimeException;
use Yii;
use yii\base\Component;

/**
 * 将 API 邮件验证码哈希和尝试次数存入应用缓存，不创建验证码数据表。
 */
final class AuthEmailCode extends Component
{
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    private const CODE_LIFETIME = 600;
    private const SEND_COOLDOWN = 60;
    private const MAX_ATTEMPTS = 3;

    /**
     * 签发新验证码；冷却期内返回 null。
     */
    public static function issue(int $userId, string $purpose): string|null
    {
        self::assertPurpose($purpose);
        $user = UserRecord::findOne($userId);
        if ($user === null) {
            throw new RuntimeException('验证码对应的用户不存在。');
        }

        return self::issueForEmail($user->email, $purpose, $userId);
    }

    /**
     * 未注册邮箱的注册验证码及已注册用户验证码统一按邮箱寻址。
     */
    public static function issueForEmail(string $email, string $purpose, int|null $userId = null): string|null
    {
        self::assertPurpose($purpose);
        $emailKey = self::emailKey($email);
        $cooldownKey = self::cooldownKey($emailKey, $purpose);
        if (!Yii::$app->cache->add($cooldownKey, time(), self::SEND_COOLDOWN)) {
            if (Yii::$app->cache->get($cooldownKey) !== false) {
                return null;
            }
            throw new RuntimeException('验证码频率限制缓存不可用。');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $record = [
            'user_id' => $userId,
            'code_hash' => Yii::$app->security->generatePasswordHash($code),
            'expires_at' => time() + self::CODE_LIFETIME,
            'attempts' => 0,
        ];
        if (!Yii::$app->cache->set(self::codeKey($emailKey, $purpose), $record, self::CODE_LIFETIME)) {
            Yii::$app->cache->delete($cooldownKey);
            throw new RuntimeException('验证码缓存保存失败。');
        }

        return $code;
    }

    /**
     * 校验并消费验证码。每个错误验证码都会计数，达到上限后立即失效。
     */
    public static function consume(int $userId, string $purpose, string $code): bool
    {
        self::assertPurpose($purpose);
        $user = UserRecord::findOne($userId);
        if ($user === null) {
            return false;
        }

        return self::consumeForEmail($user->email, $purpose, $code, $userId);
    }

    public static function consumeForEmail(
        string $email,
        string $purpose,
        string $code,
        int|null $expectedUserId = null,
    ): bool {
        self::assertPurpose($purpose);
        $emailKey = self::emailKey($email);
        $cache = Yii::$app->cache;
        $key = self::codeKey($emailKey, $purpose);
        $lockKey = $key . '/lock';
        $lockToken = Yii::$app->security->generateRandomString();
        if (!$cache->add($lockKey, $lockToken, 10)) {
            if ($cache->get($lockKey) !== false) {
                throw new RuntimeException('验证码正在校验，请稍后重试。');
            }
            throw new RuntimeException('验证码校验锁不可用。');
        }

        try {
            $record = $cache->get($key);
            if (!is_array($record) || (int) ($record['expires_at'] ?? 0) <= time()) {
                $cache->delete($key);

                return false;
            }
            if (
                $expectedUserId !== null
                && (int) ($record['user_id'] ?? 0) !== $expectedUserId
            ) {
                return false;
            }
            if ((int) ($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
                $cache->delete($key);

                return false;
            }
            if (!Yii::$app->security->validatePassword($code, (string) ($record['code_hash'] ?? ''))) {
                $record['attempts']++;
                if ($record['attempts'] >= self::MAX_ATTEMPTS) {
                    $cache->delete($key);
                } elseif (!$cache->set($key, $record, max(1, (int) $record['expires_at'] - time()))) {
                    throw new RuntimeException('验证码尝试次数保存失败。');
                }

                return false;
            }
            if (!$cache->delete($key)) {
                throw new RuntimeException('已使用验证码清理失败。');
            }

            return true;
        } finally {
            if ($cache->get($lockKey) === $lockToken) {
                $cache->delete($lockKey);
            }
        }
    }

    public static function discard(string $email, string $purpose): void
    {
        self::assertPurpose($purpose);
        $emailKey = self::emailKey($email);
        Yii::$app->cache->delete(self::codeKey($emailKey, $purpose));
        Yii::$app->cache->delete(self::cooldownKey($emailKey, $purpose));
    }

    private static function codeKey(string $emailKey, string $purpose): string
    {
        return 'api-auth-code/' . $purpose . '/' . $emailKey;
    }

    private static function cooldownKey(string $emailKey, string $purpose): string
    {
        return 'api-auth-cooldown/' . $purpose . '/' . $emailKey;
    }

    private static function emailKey(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    private static function assertPurpose(string $purpose): void
    {
        if (!in_array($purpose, [self::PURPOSE_EMAIL_VERIFICATION, self::PURPOSE_PASSWORD_RESET], true)) {
            throw new \InvalidArgumentException('不支持的验证码用途。');
        }
    }
}
