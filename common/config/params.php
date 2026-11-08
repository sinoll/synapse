<?php

declare(strict_types=1);

use common\models\Post;
use common\models\ShortVideo;

return [
    'user.passwordResetTokenExpire' => 3600,
    // 3天
    'user.emailVerifyTokenExpire' => 259200,
    // 管理员发出的注册邀请链接有效期，7 天。见 frontend\services\InviteToken。
    'user.inviteTokenExpire' => 604800,
    'user.passwordMinLength' => 8,
    // 长时间未登录的账户再登录时要过一次邮箱验证码（只在前台 users/login 生效）。
    // 阈值按"从未记录过登录时间以外"的间隔算，存量账户第一次登录放行，见 User::needsLoginEmailCode()。
    // 7天
    'user.loginCodeAfterInactivity' => 604800,
    // 邮箱验证码有效期，10 分钟。
    'user.loginCodeExpire' => 600,
    // 发信通道：唯一的投递路径，直连带鉴权的 SMTP，不依赖本机 mail() 或 MTA。
    // 各机器只覆盖需要变的键（host/username/password/fromEmail），写在 params-local.php：
    // 该文件 git 忽略、且 php init 会重新生成，所以 environments/* 模板里留了同名占位。
    // encryption: ssl=465 隐式 TLS；tls=587 明文连上再 STARTTLS；留空=不加密，只用于本机联调。
    // smtp.fromEmail 必须与 smtp.username 是同一个地址：163/QQ 之类公共邮箱会校验 MAIL FROM，不一致直接 550 退回。
    'smtp.host' => '',
    'smtp.port' => 465,
    'smtp.encryption' => 'ssl',
    'smtp.username' => '',
    'smtp.password' => '',
    'smtp.fromEmail' => '',
    'smtp.fromName' => '',
    // 规范站点地址。留空时从当前请求推断；生产建议显式配置，
    // 避免伪造 Host 头时把带重置令牌的外链发往攻击者域名。
    'siteBaseUrl' => 'http://localhost',
    // 内容类型，取值以 Post::TYPE_* 常量为唯一来源。
    'content.type' => [
        1 => Post::TYPE_BLOG,
        2 => Post::TYPE_IMAGES,
        3 => Post::TYPE_MOMENT,
        4 => Post::TYPE_SHORT_VIDEO,
    ],
    // 视频方向，对应 short_video.video_type 字段。
    'video.type' => [
        ShortVideo::ORIENTATION_HORIZONTAL => 'Horizontal',
        ShortVideo::ORIENTATION_VERTICAL => 'Vertical',
    ],
    // 其他已有参数保留
    'ffmpegPath' => PHP_OS_FAMILY === 'Windows'  ? 'A:/greenSoft/ffmpeg/bin/ffmpeg.exe'  : '/usr/bin/ffmpeg',
];
