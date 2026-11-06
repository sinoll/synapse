<?php

declare(strict_types=1);

namespace restful\controllers;

use restful\models\Blog;

/** 博客接口：/blog。 */
final class BlogController extends ContentController
{
    protected function resource(): string
    {
        return Blog::class;
    }
}
