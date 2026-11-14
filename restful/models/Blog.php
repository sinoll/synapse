<?php

declare(strict_types=1);

namespace restful\models;

use common\models\BlogPost;
use common\models\Cate;
use common\models\Post;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;

/** 博客：post(type=blog) + blog 子表，可选单值分类。 */
final class Blog extends ApiResource
{
    public static function postType(): string
    {
        return Post::TYPE_BLOG;
    }

    public static function label(): string
    {
        return '博客';
    }

    protected static function childFields(): array
    {
        return ['summary', 'content', 'thumb'];
    }

    protected static function childRelation(): ?string
    {
        return 'blog';
    }

    /** 分类只有博客用，且是单值。 */
    protected static function supportsCategory(): bool
    {
        return true;
    }

    /** 前台博客的标签只认显式 tags[]，不解析正文里的 #话题#，这里保持一致。 */
    protected static function hashtagFields(): array
    {
        return [];
    }

    protected static function typeFields(Post $post, bool $detail): array
    {
        $blog = $post->blog;

        return [
            'summary' => $blog?->summary,
            'thumb_url' => ApiResponder::assetUrl($blog?->thumb),
            'content' => $detail ? $blog?->content : null,
            'categories' => array_map(
                static fn (Cate $cate): array => ['id' => (int) $cate->id, 'name' => $cate->name, 'slug' => $cate->slug],
                $post->categories
            ),
        ];
    }

    protected static function persistChild(Post $post, array $payload, bool $isCreate): void
    {
        $blog = $isCreate ? new BlogPost() : ($post->blog ?? new BlogPost());
        $blog->post_id = (int) $post->id;
        // blog.title 非空且要与 post.title 同源；post.title 已由 titleFor() 定稿。
        $blog->title = (string) $post->title;

        if (array_key_exists('summary', $payload)) {
            $blog->summary = self::trimmed($payload['summary'], 'summary', 500);
        }
        if (array_key_exists('content', $payload)) {
            $blog->content = self::trimmed($payload['content'], 'content');
        } elseif ($isCreate) {
            throw new ValidationException(['content' => ['博客正文 content 不能为空。']]);
        }
        if (array_key_exists('thumb', $payload)) {
            $blog->thumb = self::mediaArg($payload, 'thumb');
        } elseif ($isCreate) {
            // 与前台一致：没单独给封面时，用正文里第一张本站插图的缩略图。
            $blog->thumb = self::thumbnailFromContent((string) $blog->content);
        }

        self::saveChildModel($blog);
    }

    protected static function derivedTitle(array $payload): string
    {
        return self::plainText((string) ($payload['content'] ?? ''), 200) ?: '博客';
    }

    protected static function currentMedia(Post $post): array
    {
        return array_values(array_filter([(string) ($post->blog?->thumb ?? '')]));
    }

    /** thumb 里存的多半是源图，同名的那张缩略图只活在磁盘上，得跟着源文件一起回收。 */
    protected static function derivedMedia(string $path): array
    {
        return array_filter([self::blogThumbnailForPath($path)]);
    }
}
