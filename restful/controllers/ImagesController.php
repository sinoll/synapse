<?php

declare(strict_types=1);

namespace restful\controllers;

use restful\models\Images;

/** 图集接口：/images。 */
final class ImagesController extends ContentController
{
    protected function resource(): string
    {
        return Images::class;
    }
}
