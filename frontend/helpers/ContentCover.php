<?php

declare(strict_types=1);

namespace frontend\helpers;

use common\models\Post;

/** 列表卡片共用的一图一摘要取数口径。 */
final class ContentCover
{
    public static function url(Post $post): string
    {
        if ($post->type === Post::TYPE_BLOG && $post->blog !== null && $post->blog->thumb !== null && $post->blog->thumb !== '') {
            return '/' . ltrim((string) $post->blog->thumb, '/');
        }

        if ($post->type === Post::TYPE_IMAGES && $post->images !== []) {
            return '/' . ltrim((string) $post->images[0]->file_path, '/');
        }

        if ($post->type === Post::TYPE_MOMENT && $post->moment !== null && $post->moment->thumb !== null && $post->moment->thumb !== '') {
            return '/' . ltrim((string) $post->moment->thumb, '/');
        }

        return '/images/default-cover.png';
    }

    public static function summary(Post $post): string
    {
        $raw = '';
        if ($post->type === Post::TYPE_BLOG && $post->blog !== null) {
            $raw = (string) ($post->blog->summary ?: $post->blog->content);
        } elseif ($post->type === Post::TYPE_MOMENT && $post->moment !== null) {
            $raw = (string) $post->moment->content;
        } elseif ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null) {
            $raw = (string) $post->shortVideo->description;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');

        return mb_strlen($text) > 120 ? mb_substr($text, 0, 120) . '…' : $text;
    }
}
