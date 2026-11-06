<?php

declare(strict_types=1);

namespace restful\controllers;

use restful\models\Moment;

/** 动态接口：/moment。 */
final class MomentController extends ContentController
{
    protected function resource(): string
    {
        return Moment::class;
    }
}
