<?php

declare(strict_types=1);

// common 的参数必须先并进来：API 复用 common\models 的 AR 模型，而 ShortVideo::videoTypeOptions()、
// user.loginCodeAfterInactivity 一类的取值只定义在 common/config/params.php。缺了它，
// 短视频的 video_type 会「in 空数组」校验不过，读参数的地方还会直接报 undefined index。
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

// 四类内容同构：post 主表 + 类型子表，所以路由一次生成，别手写四遍。
// short_video 的控制器名按 Yii 的连字符约定是 short-video，对外 URL 保持表名 short_video。
$resourceRules = [];
foreach (['blog' => 'blog', 'moment' => 'moment', 'images' => 'images', 'short_video' => 'short-video'] as $urlPath => $controller) {
    $resourceRules += [
        "GET $urlPath" => "$controller/index",
        "POST $urlPath" => "$controller/create",
        "GET $urlPath/<id:\d+>" => "$controller/view",
        "PUT $urlPath/<id:\d+>" => "$controller/update",
        "PATCH $urlPath/<id:\d+>" => "$controller/update",
        "DELETE $urlPath/<id:\d+>" => "$controller/delete",
        "POST $urlPath/<id:\d+>/like" => "$controller/like",
    ];
}

return [
    'id' => 'api',
    'name' => '拾光',
    'charset' => 'utf-8',
    'language' => 'zh-CN',
    'timeZone' => 'Asia/Shanghai',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'restful\controllers',
    'bootstrap' => ['log', 'siteSettings'],
    'components' => [
        'siteSettings' => [
            'class' => \common\components\SiteSettingsBootstrap::class,
        ],
        'request' => [
            // 无状态 Bearer 令牌，不经 Cookie 认证，因此不存在 CSRF 面；开着只会让 POST 全变 400。
            'enableCsrfValidation' => false,
            // 不配这个解析器，application/json 的请求体会被当成表单丢弃：PUT/PATCH 收不到任何字段。
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
        ],
        // 异常的默认输出是 {name,message,code,status,type,stack-trace}，和 {code,message,data} 是两套形状；
        // 换掉渲染方法，错误出口和业务出口才真的走同一个封装。
        'errorHandler' => [
            'class' => \restful\components\ApiErrorHandler::class,
        ],
        'user' => [
            'identityClass' => \common\models\User::class,
            // 每次请求由 BearerAuth 重新解析身份，不写会话也不发身份 Cookie。
            'enableSession' => false,
            'enableAutoLogin' => false,
            'loginUrl' => null,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/api.log',
                ],
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            // 严格解析：没写进 rules 的路径一律 404，不给控制器开出计划外的访问面。
            // 注意属性名是 enableStrictParsing，写成 enableStrictMode 会在初始化时抛 UnknownPropertyException。
            'enableStrictParsing' => true,
            'rules' => $resourceRules + [
                'POST auth/register/code' => 'auth/register-code',
                'POST auth/register' => 'auth/register',
                'POST auth/login' => 'auth/login',
                'POST auth/forgot-password' => 'auth/forgot-password',
                'POST auth/reset-password' => 'auth/reset-password',
                'POST auth/logout' => 'auth/logout',
                'GET auth/me' => 'auth/me',
                'POST upload' => 'upload/create',
                '' => 'site/index',
            ],
        ],
    ],
    'params' => $params,
];
