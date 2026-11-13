<?php

declare(strict_types=1);

return [
    // 访问令牌有效期。登录响应的 expires_in 与签名覆盖的过期时间共用这一个值。
    'api.tokenExpire' => 7 * 24 * 3600,
    'api.pageSizeDefault' => 20,
    'api.pageSizeMax' => 100,
    'api.assetBaseUrl' => 'http://192.168.8.10',

    // POST /upload 的 kind → 落盘位置与校验口径，逐项对着前台各控制器的现行规则写：
    // 原图 uploads/{kind}/YYYYMM/ 分层、缩略图 uploads/thumb/{kind}/ 平铺，分类图本身就是缩略图所以不分级。
    // 图片大小在应用内限制；视频大小由 PHP upload_max_filesize 与 post_max_size 配置控制。
    'api.uploadKinds' => [
        'blog' => [
            'dir' => '/uploads/blog',
            'dated' => true,
            'thumb' => '/uploads/thumb/blog',
            'thumb_from' => 'image',
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 20 * 1024 * 1024,
        ],
        'moment' => [
            'dir' => '/uploads/moment',
            'dated' => true,
            'thumb' => '/uploads/thumb/moment',
            'thumb_from' => 'image',
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 20 * 1024 * 1024,
        ],
        'images' => [
            'dir' => '/uploads/images',
            'dated' => true,
            'thumb' => '/uploads/thumb/images',
            'thumb_from' => 'image',
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 20 * 1024 * 1024,
        ],
        'cate' => [
            'dir' => '/uploads/thumb/cate',
            'dated' => false,
            'thumb' => null,
            'thumb_from' => null,
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 20 * 1024 * 1024,
        ],
        'video' => [
            'dir' => '/uploads/video',
            'dated' => true,
            'thumb' => '/uploads/thumb/video',
            'thumb_from' => 'video',
            'extensions' => ['mp4', 'webm', 'mov', 'ogv', 'ogg'],
        ],
    ],
];
