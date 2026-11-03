<?php

declare(strict_types=1);

/** @var common\models\Post[] $posts */

use common\models\Post;
use common\models\Tag;
use frontend\assets\ImageViewerAsset;
use frontend\widgets\FavoriteButton;
use frontend\widgets\FollowButton;
use frontend\widgets\LikeButton;
use yii\helpers\Html;
use yii\helpers\Url;

ImageViewerAsset::register($this);

$typeLabels = Post::TYPE_LABELS;
$detailRoutes = Post::TYPE_DETAIL_ROUTES;
$keyword = $keyword ?? '';

$previewSource = static function (Post $post): string {
    if ($post->type === Post::TYPE_BLOG && $post->blog !== null) {
        return (string) ($post->blog->summary ?: $post->blog->content);
    }
    if ($post->type === Post::TYPE_MOMENT && $post->moment !== null) {
        return (string) $post->moment->content;
    }
    if ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null) {
        return (string) $post->shortVideo->description;
    }

    return '';
};

/* 先收集整页文案里的 #话题# 写法，再一次查库拿到已存在的标签；正文渲染时只链已入库的标签。 */
$hashtagPattern = '/#([^#\s<>][^#<>]{0,30})#/u';
$hashtagNames = [];
foreach ($posts as $post) {
    foreach ([$post->title, $previewSource($post)] as $raw) {
        if (preg_match_all($hashtagPattern, strip_tags((string) $raw), $matches)) {
            foreach ($matches[1] as $name) {
                $hashtagNames[trim($name)] = true;
            }
        }
    }
}
$hashtagSlugs = [];
if ($hashtagNames !== []) {
    foreach (Tag::find()->select(['name', 'slug'])->where(['name' => array_keys($hashtagNames)])->all() as $tag) {
        $hashtagSlugs[$tag->name] = (string) $tag->slug;
    }
}

/** 返回已转义的 HTML，关键词用 <mark> 包裹；$linkHashtags 时 #标签# 变成话题链接。标题本身已是链接时不能再嵌 <a>。 */
$highlight = static function (string $text, bool $linkHashtags = true) use ($keyword, $hashtagPattern, $hashtagSlugs): string {
    $encoded = Html::encode($text);
    if ($keyword !== '') {
        $pattern = '/(' . preg_quote(Html::encode($keyword), '/') . ')/iu';
        $encoded = preg_replace($pattern, '<mark>$1</mark>', $encoded) ?? $encoded;
    }
    if (!$linkHashtags || $hashtagSlugs === []) {
        return $encoded;
    }

    return preg_replace_callback($hashtagPattern, static function (array $matches) use ($hashtagSlugs): string {
        $name = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        if (!isset($hashtagSlugs[$name])) {
            return $matches[0];
        }

        return '<a class="home-feed-hashtag" href="' . Html::encode(Url::to(['/site/tag', 'slug' => $hashtagSlugs[$name]])) . '">' . $matches[0] . '</a>';
    }, $encoded) ?? $encoded;
};

$momentFeedImages = static function (string $html): array {
    if (!preg_match_all('#<img\b[^>]*\bsrc=["\']([^"\']+)["\']#i', $html, $matches)) {
        return [];
    }

    $webRoot = Yii::getAlias('@frontend/web');
    $images = [];
    foreach (array_unique($matches[1]) as $source) {
        if (!str_starts_with($source, '/uploads/')) {
            continue;
        }
        $thumbnail = (string) preg_replace('#^/uploads/moment/(\d{4})/(\d{2})/#', '/uploads/thumb/moment/$1/$2/', $source);
        if ($thumbnail === $source) {
            $thumbnail = (string) preg_replace('#^/uploads/blog/\d{4}/#', '/uploads/thumb/blog/', $source);
        }
        $images[] = [
            'thumb' => $thumbnail !== $source && is_file($webRoot . $thumbnail) ? $thumbnail : $source,
            'full' => $source,
        ];
        if (count($images) === 9) {
            break;
        }
    }

    return $images;
};

foreach ($posts as $post):
    $preview = $previewSource($post);
    $momentImages = [];
    $galleryImages = [];
    $blogCover = null;
    $blogCoverViewable = false;
    if ($post->type === Post::TYPE_BLOG && $post->blog !== null) {
        $blogCover = '/images/default-cover.png';
        $thumb = trim((string) $post->blog->thumb);
        if ($thumb !== '') {
            $thumbPath = '/' . ltrim($thumb, '/');
            if (is_file(Yii::getAlias('@frontend/web') . $thumbPath)) {
                // 封面列存的就是上传时生成的缩略图，站点里没有更深的原图，放大用的也是它。
                $blogCover = $thumbPath;
                $blogCoverViewable = true;
            }
        }
    } elseif ($post->type === Post::TYPE_MOMENT && $post->moment !== null) {
        $momentImages = $momentFeedImages($preview);
    } elseif ($post->type === Post::TYPE_IMAGES) {
        foreach (array_slice($post->images, 0, 9) as $image) {
            $full = (string) $image->file_path;
            if ($full === '') {
                continue;
            }
            $galleryImages[] = ['thumb' => (string) $image->thumbnailPath, 'full' => $full];
        }
    }
    $preview = trim(preg_replace('/\s+/u', ' ', strip_tags($preview)) ?? '');
    if ($keyword !== '') {
        // 命中位置太靠后时，把窗口挪到关键词前，否则摘要里看不到匹配的内容。
        $position = mb_stripos($preview, $keyword);
        if ($position !== false && $position > 40) {
            $preview = '…' . mb_substr($preview, $position - 40);
        }
    }
    if (mb_strlen($preview) > 150) {
        $preview = mb_substr($preview, 0, 150) . '…';
    }
    $detailRoute = $detailRoutes[$post->type] ?? '/site/view';
    $author = $post->user?->nickname ?: $post->user?->username ?: '用户';
    $authorId = $post->user?->id !== null ? (int) $post->user->id : null;
    $authorUrl = $authorId !== null ? ['/users/profile', 'id' => $authorId] : null;
    $avatar = $post->user?->avatar;
    $avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
    $hasMedia = $momentImages !== []
        || $blogCover !== null
        || $galleryImages !== []
        || ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null);
    $imageGroup = 'feed-post-' . (int) $post->id;
    $imageCaption = (string) ($post->title ?: '图片');
    /** 缩略图指向原图的链接，交给看图模式脚本接管点击。 */
    $viewLink = static function (array $image) use ($imageGroup, $imageCaption): string {
        return Html::a(
            Html::img($image['thumb'], ['alt' => $imageCaption, 'loading' => 'lazy']),
            $image['full'],
            [
                'data' => ['image-view' => '', 'image-group' => $imageGroup, 'image-caption' => $imageCaption],
                'title' => '点击查看大图',
                'aria-label' => '查看大图：' . $imageCaption,
            ],
        );
    };
    ?>
    <article class="home-feed-card card">
        <div class="card-body p-4">
            <div class="home-feed-card-header d-flex align-items-center gap-3 mb-3">
                <?php if ($avatar): ?>
                    <img class="home-feed-avatar" src="<?= Html::encode($avatar) ?>" alt="<?= Html::encode($author) ?>" loading="lazy">
                <?php else: ?>
                    <span class="home-feed-avatar home-feed-avatar-fallback" aria-hidden="true"><?= Html::encode($avatarInitial) ?></span>
                <?php endif; ?>
                <div class="home-feed-byline d-flex flex-column min-width-0 flex-grow-1">
                    <?php if ($authorUrl !== null): ?>
                        <div class="home-feed-author text-truncate"><?= Html::a(Html::encode($author), $authorUrl, ['class' => 'home-feed-author-link']) ?></div>
                    <?php else: ?>
                        <div class="home-feed-author text-truncate"><?= Html::encode($author) ?></div>
                    <?php endif; ?>
                    <div class="home-feed-meta text-body-secondary">
                        <time datetime="<?= Html::encode(date(DATE_ATOM, (int) $post->created_at)) ?>"><?= Yii::$app->formatter->asRelativeTime($post->created_at) ?></time>
                        <span aria-hidden="true">·</span>
                        <span><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span>
                    </div>
                </div>
                <?php if ($post->user !== null): ?>
                    <div class="home-feed-header-action flex-shrink-0">
                        <?= FollowButton::widget(['user' => $post->user]) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h2 class="home-feed-card-title h5 mb-2">
                <?php if ($post->type === Post::TYPE_MOMENT): ?>
                    <?= $highlight($post->title ?: '动态') ?>
                <?php else: ?>
                    <?= Html::a($highlight((string) $post->title, false), [$detailRoute, 'id' => $post->id], ['class' => 'home-feed-title', 'encode' => false]) ?>
                <?php endif; ?>
            </h2>

            <?php if ($preview !== ''): ?><p class="home-feed-preview text-body-secondary mb-3"><?= $highlight($preview) ?></p><?php endif; ?>
            <?php if ($hasMedia): ?>
                <div class="home-feed-media mb-3">
                    <?php if ($blogCover !== null): ?>
                        <div class="home-feed-blog-cover">
                            <?php if ($blogCoverViewable): ?>
                                <?= $viewLink(['thumb' => $blogCover, 'full' => $blogCover]) ?>
                            <?php else: ?>
                                <img src="<?= Html::encode($blogCover) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($momentImages !== []): ?>
                        <div class="home-feed-images">
                            <?php foreach ($momentImages as $momentImage): ?>
                                <?= $viewLink($momentImage) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($galleryImages !== []): ?>
                        <div class="home-feed-images">
                            <?php foreach ($galleryImages as $galleryImage): ?>
                                <?= $viewLink($galleryImage) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null): ?>
                        <video class="home-feed-video" controls preload="metadata" playsinline<?php if ($post->shortVideo->cover_path): ?> poster="<?= Html::encode($post->shortVideo->cover_path) ?>"<?php endif; ?>>
                            <source src="<?= Html::encode($post->shortVideo->video_path) ?>">
                            您的浏览器不支持视频播放。
                        </video>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="home-feed-card-footer d-flex align-items-center gap-4 flex-wrap">
                <?= LikeButton::widget(['post' => $post]) ?>
                <?= FavoriteButton::widget(['postId' => (int) $post->id]) ?>
                <?php if ($post->type === Post::TYPE_IMAGES): ?>
                    <span class="home-feed-media-count small text-body-secondary"><?= count($post->images) ?> 张图片</span>
                <?php endif; ?>
                <?= Html::a('阅读更多 <i class="bi bi-chevron-right" aria-hidden="true"></i>', [$detailRoute, 'id' => $post->id], ['class' => 'home-feed-readmore ms-auto text-decoration-none', 'encode' => false]) ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
