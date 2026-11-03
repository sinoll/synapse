<?php

declare(strict_types=1);

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

return [
    'id' => 'app-frontend',
    'name' => '拾光',
    'charset' => 'utf-8',
    'language' => 'zh-CN',
    'timeZone' => 'Asia/Shanghai',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'siteSettings'],
    'controllerNamespace' => 'frontend\controllers',
    'components' => [
        'siteSettings' => [
            'class' => \common\components\SiteSettingsBootstrap::class,
        ],
        'request' => [
            'csrfParam' => '_csrf-frontend',
        ],
        'user' => [
            'identityClass' => \common\models\User::class,
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-frontend', 'httpOnly' => true],
            'loginUrl' => ['/users/login'],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the frontend
            'name' => 'advanced-frontend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            // 留空时按当前请求推断；配置后邮件里的重置/验证链接一律走规范域名，
            // 不受请求 Host 头影响。
            'hostInfo' => ($params['siteBaseUrl'] ?? '') !== '' ? $params['siteBaseUrl'] : null,
            'rules' => [
                'search' => 'search/index',
                'u/<id:\d+>' => 'users/profile',
            ],
        ],
    ],
    'params' => $params,
];
