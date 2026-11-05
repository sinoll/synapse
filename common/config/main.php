<?php

declare(strict_types=1);

return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'smsSender' => [
            'class' => \common\services\UnconfiguredSmsSender::class,
        ],
        // 默认走带鉴权的 SMTP。host/username/password 在 main-local.php 里给全，
        // 缺任一项时 SmtpMailSender 会抛"SMTP 发信服务未配置"，日志里能直接定位；
        // 不要退回 PhpMailSender：Linux 上它依赖本机 MTA，且会静默忽略 SMTP 凭据。
        'mailSender' => [
            'class' => \common\services\SmtpMailSender::class,
        ],
        'ffmpegService' => [
            'class' => \services\FfmpegService::class,
        ],
    ],
];
