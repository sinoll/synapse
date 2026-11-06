<?php

declare(strict_types=1);

namespace restful\models;

use common\models\Moment as MomentRecord;
use common\models\Post;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;

/**
 * 动态：post(type=moment) + moment 子表。
 *
 * 图片以内联 <img> 存在 content 里，不占 images 子表；话题标签从正文的 #xxx# 抽取。
 */
final class Moment extends ApiResource
{
    /** 前台把动态标题截到 16 个字，这里取创建口径。 */
    private const TITLE_LIMIT = 16;

    public static function postType(): string
    {
        return Post::TYPE_MOMENT;
    }

    public static function label(): string
    {
        return '动态';
    }

    protected static function childFields(): array
    {
        return ['content', 'thumb'];
    }

    protected static function childRelation(): ?string
    {
        return 'moment';
    }

    protected static function hashtagFields(): array
    {
        return ['content'];
    }

    protected static function typeFields(Post $post, bool $detail): array
    {
        $moment = $post->moment;

        return [
            'content' => $detail ? $moment?->content : null,
            'text' => self::plainText((string) ($moment?->content ?? ''), 500),
            'thumb' => $moment?->thumb,
            'thumb_url' => ApiResponder::assetUrl($moment?->thumb),
        ];
    }

    protected static function persistChild(Post $post, array $payload, bool $isCreate): void
    {
        $moment = $isCreate ? new MomentRecord() : ($post->moment ?? new MomentRecord());
        $moment->post_id = (int) $post->id;
        // 话题标签留在标题里，和前台一致：标题就是正文的截断。
        $moment->title = (string) $post->title;

        if (array_key_exists('content', $payload)) {
            $moment->content = self::trimmed($payload['content'], 'content');
        } elseif ($isCreate) {
            throw new ValidationException(['content' => ['动态内容 content 不能为空。']]);
        }
        if (array_key_exists('thumb', $payload)) {
            $moment->thumb = self::mediaArg($payload, 'thumb');
        } elseif ($isCreate) {
            // 前台的封面本来就是从正文第一张插图推出来的，接口新建时按同一口径补上。
            $moment->thumb = self::thumbnailFromContent((string) $moment->content);
        }

        self::saveChildModel($moment);
    }

    protected static function derivedTitle(array $payload): string
    {
        return self::plainText((string) ($payload['content'] ?? ''), self::TITLE_LIMIT) ?: '动态';
    }

    protected static function currentMedia(Post $post): array
    {
        return array_values(array_filter([(string) ($post->moment?->thumb ?? '')]));
    }

    /** 与博客同理：thumb 存源图时，磁盘上那张同名缩略图没有登记在库里，要跟着源文件回收。 */
    protected static function derivedMedia(string $path): array
    {
        return array_filter([self::blogThumbnailForPath($path)]);
    }
}
