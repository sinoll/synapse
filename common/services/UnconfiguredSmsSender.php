<?php

declare(strict_types=1);

namespace common\services;

use RuntimeException;

/** Replace with a provider adapter before enabling SMS delivery in production. */
class UnconfiguredSmsSender implements SmsSenderInterface
{
    public function sendVerificationCode(string $phone, string $code): void
    {
        throw new RuntimeException('短信服务尚未配置，请先接入短信服务商。');
    }
}
