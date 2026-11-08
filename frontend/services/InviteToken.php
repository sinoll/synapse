<?php

declare(strict_types=1);

namespace frontend\services;

use common\models\SiteSetting;
use RuntimeException;
use Yii;

/**
 * 注册邀请令牌的签发与校验。
 *
 * 令牌是自包含的：base64url(邮箱|过期时间) + HMAC 签名，不落库。管理员发邀请前不必先建
 * “待邀请记录”，被邀请人也还没有账户可供关联。签名覆盖了邮箱，所以链接无法换成别人的地址；
 * 一次性则由 SignupForm 的邮箱唯一性天然保证——用掉一次后该邮箱已有账户，同一令牌再提交只会被拒。
 */
final class InviteToken
{
    /** 签名密钥存放的 SiteSetting 键名。 */
    private const SECRET_KEY = 'invite_token_secret';

    public static function issue(string $email): string
    {
        $payload = self::encode(sprintf(
            '%s|%d',
            mb_strtolower(trim($email)),
            time() + (int) Yii::$app->params['user.inviteTokenExpire'],
        ));

        return $payload . '.' . self::sign($payload);
    }

    /** 签名不符、格式非法或已过期返回 null，否则返回被邀请的邮箱（小写）。 */
    public static function verify(string|null $token): string|null
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return null;
        }
        [$payload, $signature] = explode('.', $token, 2);
        if (!preg_match('/^[0-9a-f]{64}$/', $signature) || !hash_equals(self::sign($payload), $signature)) {
            return null;
        }

        $data = self::decode($payload);
        if ($data === null || substr_count($data, '|') !== 1) {
            return null;
        }
        [$email, $expiresAt] = explode('|', $data, 2);
        if ($email === '' || !ctype_digit($expiresAt) || (int) $expiresAt <= time()) {
            return null;
        }

        return $email;
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::key());
    }

    /**
     * 密钥第一次用到时随机生成，之后存在 site_setting 里，签发和校验共用同一份。
     * 写不进库时直接抛错：用空串当密钥等于任何人都能伪造邀请链接。
     */
    private static function key(): string
    {
        $secret = SiteSetting::read(self::SECRET_KEY, '');
        if ($secret === '') {
            SiteSetting::write(self::SECRET_KEY, Yii::$app->security->generateRandomString(32));
            // 重新读而不是直接用刚生成的串：并发首次签发时两个进程都写过，只有库里最终那份能被双方校验。
            $secret = SiteSetting::read(self::SECRET_KEY, '');
        }
        if ($secret === '') {
            throw new RuntimeException('邀请密钥初始化失败，请检查 site_setting 表能否写入。');
        }

        return $secret;
    }

    private static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function decode(string $payload): string|null
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $payload)) {
            return null;
        }
        $data = base64_decode(strtr($payload, '-_', '+/'), true);

        return $data === false ? null : $data;
    }
}
