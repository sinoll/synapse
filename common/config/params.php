<?php

declare(strict_types=1);

use common\models\Post;
use common\models\ShortVideo;

return [
    'user.passwordResetTokenExpire' => 3600,
    'user.emailVerifyTokenExpire' => 604800,
    'user.passwordMinLength' => 8,
    // 站点发信地址，上线前在 params-local.php 覆盖成真实域名下的地址，否则邮件基本必然进垃圾箱。
    'mailSupportEmail' => 'noreply@example.com',
    // Windows 下 PHP 的 mail() 只能走 php.ini 指定的 SMTP；这两项用于在 params-local.php
    // 里覆盖成免鉴权的本机转发器（如 papercut/smtp4dev），留空则沿用 php.ini。
    'mailSmtpHost' => '',
    'mailSmtpPort' => '',
    // 规范站点地址。留空时从当前请求推断；生产建议显式配置，
    // 避免伪造 Host 头时把带重置令牌的外链发往攻击者域名。
    'siteBaseUrl' => '',
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
