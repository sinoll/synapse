<?php

declare(strict_types=1);

use common\models\Post;
use common\models\ShortVideo;

return [
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 8,
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
