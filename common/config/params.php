<?php

declare(strict_types=1);

return [
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 8,
    // 其他已有参数保留
    'ffmpegPath' => PHP_OS_FAMILY === 'Windows'  ? 'A:/greenSoft/ffmpeg/bin/ffmpeg.exe'  : '/usr/bin/ffmpeg',
];
