<?php

declare(strict_types=1);

namespace common\services;

interface SmsSenderInterface
{
    public function sendVerificationCode(string $phone, string $code): void;
}
