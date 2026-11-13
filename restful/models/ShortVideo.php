<?php

declare(strict_types=1);

namespace restful\models;

use common\models\Post;
use common\models\ShortVideo as ShortVideoRecord;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use Yii;

/**
 * 短视频：post(type=short_video) + short_video 子表一行。
 *
 * 前台是「上传视频文件、没给封面就抽第 5 帧」，API 走 POST /upload 先把文件和
 * 抽好的封面落盘，这里只收路径，因此封面可以为空（列表与卡片都按无封面降级渲染）。
 */
final class ShortVideo extends ApiResource
{
    public static function postType(): string
    {
        return Post::TYPE_SHORT_VIDEO;
    }

    public static function label(): string
    {
        return '短视频';
    }

    protected static function childFields(): array
    {
        return ['description', 'video_path', 'thumbnail_path', 'cover_path', 'duration_seconds', 'video_type'];
    }

    protected static function childRelation(): ?string
    {
        return 'shortVideo';
    }

    /** 前台从标题和简介里取 #话题#，正文是上传的文件，没有可解析的富文本。 */
    protected static function hashtagFields(): array
    {
        return ['title', 'description'];
    }

    protected static function typeFields(Post $post, bool $detail): array
    {
        $video = $post->shortVideo;

        return [
            'description' => $detail ? $video?->description : null,
            'video_path' => $video?->video_path,
            'video_url' => ApiResponder::assetUrl($video?->video_path),
            'cover_path' => $video?->cover_path,
            'cover_url' => ApiResponder::assetUrl($video?->cover_path),
            'duration_seconds' => $video === null ? null : (int) $video->duration_seconds,
            'video_type' => $video === null ? null : (int) $video->video_type,
            'video_type_label' => $video?->getVideoTypeLabel(),
            'is_vertical' => $video?->isVertical(),
        ];
    }

    protected static function persistChild(Post $post, array $payload, bool $isCreate): void
    {
        $video = $isCreate ? new ShortVideoRecord() : ($post->shortVideo ?? new ShortVideoRecord());
        $video->post_id = (int) $post->id;
        // short_video.title 非空且与 post.title 同源；post.title 已由 titleFor() 定稿。
        $video->title = (string) $post->title;

        if (array_key_exists('description', $payload)) {
            $video->description = self::trimmed($payload['description'], 'description');
        }
        if (array_key_exists('video_path', $payload)) {
            // video_path 列非空、rules 里也必填，所以只在拿到真路径时覆盖；
            // 想换视频就提交新的 /upload 返回的路径，提交空串视为不改。
            $video->video_path = self::mediaArg($payload, 'video_path', $isCreate) ?? $video->video_path;
        } elseif ($isCreate) {
            throw new ValidationException(['video_path' => ['短视频必须带上 video_path，请先用 POST /upload 上传视频。']]);
        }
        if (array_key_exists('thumbnail_path', $payload) && array_key_exists('cover_path', $payload)) {
            throw new ValidationException(['thumbnail_path' => ['thumbnail_path 与 cover_path 不能同时提交，请使用 thumbnail_path。']]);
        }
        if (array_key_exists('thumbnail_path', $payload)) {
            // The public write field follows /upload; the existing database and read field remain cover_path.
            $video->cover_path = self::mediaArg($payload, 'thumbnail_path');
        } elseif (array_key_exists('cover_path', $payload)) {
            $video->cover_path = self::mediaArg($payload, 'cover_path');
        }
        if (array_key_exists('duration_seconds', $payload)) {
            $video->duration_seconds = self::intArg($payload['duration_seconds'], 'duration_seconds', 0);
        }
        if (array_key_exists('video_type', $payload)) {
            $video->video_type = self::videoTypeArg($payload['video_type']);
        }

        self::saveChildModel($video);
    }

    protected static function derivedTitle(array $payload): string
    {
        $description = self::plainText((string) ($payload['description'] ?? ''), 200);

        return $description !== '' ? $description : '短视频';
    }

    protected static function currentMedia(Post $post): array
    {
        $video = $post->shortVideo;

        return array_values(array_filter([
            (string) ($video?->video_path ?? ''),
            (string) ($video?->cover_path ?? ''),
        ]));
    }

    /** 上传视频时顺带抽的那一帧封面没进库（cover_path 可能留空），删源片时一起清掉。 */
    protected static function derivedMedia(string $path): array
    {
        if (preg_match('#^/uploads/video/\d{6}/([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', '/' . ltrim($path, '/'), $match) !== 1) {
            return [];
        }

        $thumbnail = '/uploads/thumb/video/' . pathinfo($match[1], PATHINFO_FILENAME) . '.jpg';

        return is_file(self::mediaAbsolutePath($thumbnail)) ? [$thumbnail] : [];
    }

    private static function videoTypeArg(mixed $value): int
    {
        $options = ShortVideoRecord::videoTypeOptions();
        $type = self::intOrNull($value);
        if ($type === null || !array_key_exists($type, $options)) {
            throw new ValidationException(['video_type' => [
                'video_type 只能是 ' . implode('、', array_map(
                    static fn (int $key, string $name): string => "{$key}（{$name}）",
                    array_keys($options),
                    $options
                )) . '。',
            ]]);
        }

        return $type;
    }
}
