<?php

declare(strict_types=1);

$params = array_merge(
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

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
        // 唯一投递路径：带鉴权的 SMTP（symfony/mailer），Windows 与 Linux 行为一致。
        // 值全部来自 params['smtp.*']，凭据只写在 params-local.php，php init 重新生成后
        // 照模板补这几行即可；host/username/password 任一项为空时发信器会直接抛
        // "SMTP 发信服务未配置"，日志 mail 分类里能定位。
        'mailSender' => [
            'class' => \common\services\SmtpMailSender::class,
            'host' => $params['smtp.host'],
            'port' => (int) $params['smtp.port'],
            'encryption' => $params['smtp.encryption'],
            'username' => $params['smtp.username'],
            'password' => $params['smtp.password'],
            'fromEmail' => $params['smtp.fromEmail'],
            'fromName' => $params['smtp.fromName'],
        ],
        'ffmpegService' => [
            'class' => \services\FfmpegService::class,
        ],
    ],
];
