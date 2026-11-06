<?php

declare(strict_types=1);

namespace restful\controllers;

use restful\models\ShortVideo;

/** 短视频接口：/short_video。 */
final class ShortVideoController extends ContentController
{
    protected function resource(): string
    {
        return ShortVideo::class;
    }
}
