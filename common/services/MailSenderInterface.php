<?php

declare(strict_types=1);

namespace common\services;

interface MailSenderInterface
{
    /**
     * @throws \RuntimeException 投递失败时必须抛出，调用方据此向用户显示可重试提示。
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody): void;
}
