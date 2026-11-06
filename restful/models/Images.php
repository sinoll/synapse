<?php

declare(strict_types=1);

namespace restful\models;

use common\models\Image as ImageRecord;
use common\models\Post;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;

/**
 * 图集：post(type=images) + images 子表多行。
 *
 * file_paths 是「最终清单」而不是增量：顺序即展示顺序（写进 sort_order），
 * 清单里没有的行会被删除，文件在无人引用时才落盘删除。
 */
final class Images extends ApiResource
{
    public static function postType(): string
    {
        return Post::TYPE_IMAGES;
    }

    public static function label(): string
    {
        return '图集';
    }

    protected static function childFields(): array
    {
        return ['caption', 'title', 'alt_text', 'file_paths'];
    }

    /** 一对多，没有 hasOne 关系名可返回；预加载走 images。 */
    protected static function childRelation(): ?string
    {
        return null;
    }

    protected static function hashtagFields(): array
    {
        return ['caption'];
    }

    protected static function typeFields(Post $post, bool $detail): array
    {
        return [
            'caption' => self::captionOf($post),
            'image_count' => count($post->images),
            'images' => array_map(static fn (ImageRecord $image): array => [
                'id' => (int) $image->id,
                'title' => $image->title,
                'alt_text' => $image->alt_text,
                'file_path' => $image->file_path,
                'file_url' => ApiResponder::assetUrl($image->file_path),
                'thumbnail_path' => $image->getThumbnailPath(),
                'thumbnail_url' => ApiResponder::assetUrl($image->getThumbnailPath()),
                'sort_order' => (int) $image->sort_order,
                'created_at' => ApiResponder::timestamp((int) $image->created_at),
            ], $post->images),
        ];
    }

    protected static function persistChild(Post $post, array $payload, bool $isCreate): void
    {
        [$title, $altText] = self::captionParts($payload, $post, $isCreate);
        $paths = self::filePathList($payload, $isCreate);

        if ($paths === null) {
            // 只改文案：把标题与说明广播到既有各行，顺序与图片都不动。
            foreach ($post->images as $image) {
                if ($title !== null) {
                    $image->title = $title;
                }
                if ($altText !== null) {
                    $image->alt_text = $altText;
                }
                if ($title === null && $altText === null) {
                    continue;
                }
                self::saveChildModel($image);
            }

            return;
        }

        $existing = [];
        foreach ($post->images as $image) {
            $existing[(string) $image->file_path] = $image;
        }

        foreach ($paths as $index => $path) {
            $image = $existing[$path] ?? new ImageRecord();
            $image->post_id = (int) $post->id;
            $image->file_path = $path;
            $image->sort_order = $index;
            if ($title !== null) {
                $image->title = $title;
            } elseif ($isCreate) {
                $image->title = (string) $post->title;
            }
            if ($altText !== null) {
                $image->alt_text = $altText;
            } elseif ($isCreate) {
                $image->alt_text = '';
            }
            self::saveChildModel($image);
            unset($existing[$path]);
        }

        // 清单里被去掉的行：路径已经不存在了，文件交给提交后的 purgeUnreferenced() 处理。
        foreach ($existing as $image) {
            if (!$image->delete()) {
                throw new ValidationException(['file_paths' => ['旧图片记录删除失败，请重试。']]);
            }
        }
    }

    protected static function derivedTitle(array $payload): string
    {
        $image = new ImageRecord();
        $image->caption = self::trimmed($payload['caption'] ?? null, 'caption', 2000);
        $image->applyCaption();

        return (string) $image->title;
    }

    protected static function currentMedia(Post $post): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (ImageRecord $image): string => (string) $image->file_path, $post->images)
        )));
    }

    /** 缩略图按约定从源文件名推导、不在库里登记，所以只能在源文件确定被删时跟着一起回收。 */
    protected static function derivedMedia(string $path): array
    {
        return array_filter([ImageRecord::thumbnailForPath($path)]);
    }

    /** @return array{0: string|null, 1: string|null} [标题, 说明]；null 表示这次不改 */
    private static function captionParts(array $payload, Post $post, bool $isCreate): array
    {
        if (array_key_exists('caption', $payload)) {
            $image = new ImageRecord();
            $image->caption = self::trimmed($payload['caption'], 'caption', 2000);
            $image->applyCaption();

            return [(string) $image->title, (string) $image->alt_text];
        }

        $title = array_key_exists('title', $payload) ? self::trimmed($payload['title'], 'title', 200) : null;
        $altText = array_key_exists('alt_text', $payload) ? self::trimmed($payload['alt_text'], 'alt_text', 255) : null;
        if ($isCreate) {
            $title ??= (string) $post->title;
            $altText ??= '';
        }

        return [$title, $altText];
    }

    /** @return string[]|null null 表示本次没提交 file_paths */
    private static function filePathList(array $payload, bool $isCreate): ?array
    {
        if (!array_key_exists('file_paths', $payload)) {
            if ($isCreate) {
                throw new ValidationException(['file_paths' => ['图集至少需要一张图片，请先用 POST /upload 上传后提交 file_paths。']]);
            }

            return null;
        }
        if (!is_array($payload['file_paths']) || $payload['file_paths'] === []) {
            throw new ValidationException(['file_paths' => ['file_paths 必须是非空数组；要清空图片请改为提交 caption 或新建图集。']]);
        }

        $paths = [];
        foreach ($payload['file_paths'] as $path) {
            $validated = self::mediaPath($path, 'file_paths');
            if ($validated !== null) {
                $paths[] = $validated;
            }
        }
        if ($paths === []) {
            throw new ValidationException(['file_paths' => ['file_paths 里没有任何有效路径。']]);
        }

        return array_values(array_unique($paths));
    }

    /** 用已存的标题与说明还原成前台那个合并输入框的内容。 */
    private static function captionOf(Post $post): string
    {
        $first = $post->images[0] ?? null;

        return $first === null ? '' : $first->buildCaption();
    }
}
