<?php

declare(strict_types=1);

namespace restful\services;

use common\models\SiteSetting;
use RuntimeException;
use Yii;

/**
 * API 访问令牌的签发与校验。
 *
 * 令牌自包含：base64url(用户 id|过期时间) + HMAC 签名，不落库——与 common\services\InviteToken
 * 同一套路数。代价是签发后无法单独撤销：要立刻作废所有令牌，删掉 site_setting 里的
 * api_token_secret 即可，下次签发会重新生成一份，旧签名全部失效。
 */
final class ApiToken
{
    /** 签名密钥存放的 SiteSetting 键名。 */
    private const SECRET_KEY = 'api_token_secret';

    public static function issue(int $userId): string
    {
        $payload = self::encode($userId . '|' . (time() + self::lifetime()));

        return $payload . '.' . self::sign($payload);
    }

    /** 签名不符、格式非法或已过期返回 null，否则返回令牌所属的用户 id。 */
    public static function verify(string|null $token): int|null
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
        [$userId, $expiresAt] = explode('|', $data, 2);
        if (!ctype_digit($userId) || !ctype_digit($expiresAt) || (int) $expiresAt <= time()) {
            return null;
        }

        return (int) $userId;
    }

    public static function lifetime(): int
    {
        return (int) Yii::$app->params['api.tokenExpire'];
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::key());
    }

    /**
     * 密钥第一次用到时随机生成，之后存在 site_setting 里，签发和校验共用同一份。
     * 写不进库时直接抛错：用空串当密钥等于任何人都能伪造令牌。
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
            throw new RuntimeException('API 令牌密钥初始化失败，请检查 site_setting 表能否写入。');
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
