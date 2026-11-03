<?php

declare(strict_types=1);

/** @var common\models\Post[] $posts */

use common\models\Post;
use frontend\widgets\FavoriteButton;
use frontend\widgets\LikeButton;
use yii\helpers\Html;

$typeLabels = Post::TYPE_LABELS;
$detailRoutes = Post::TYPE_DETAIL_ROUTES;
$featured = $featured ?? true;
$keyword = $keyword ?? '';

/** 返回已转义的 HTML，关键词用 <mark> 包裹，可直接输出或交给 Html::a()。 */
$highlight = static function (string $text) use ($keyword): string {
    $encoded = Html::encode($text);
    if ($keyword === '') {
        return $encoded;
    }

    $pattern = '/(' . preg_quote(Html::encode($keyword), '/') . ')/iu';

    return preg_replace($pattern, '<mark>$1</mark>', $encoded) ?? $encoded;
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
        $images[] = $thumbnail !== $source && is_file($webRoot . $thumbnail) ? $thumbnail : $source;
        if (count($images) === 9) {
            break;
        }
    }

    return $images;
};

foreach ($posts as $index => $post):
    $preview = '';
    $momentImages = [];
    $blogCover = null;
    if ($post->type === Post::TYPE_BLOG && $post->blog !== null) {
        $preview = (string) ($post->blog->summary ?: $post->blog->content);
        $blogCover = '/images/default-cover.png';
        $thumb = trim((string) $post->blog->thumb);
        if ($thumb !== '') {
            $thumbPath = '/' . ltrim($thumb, '/');
            if (is_file(Yii::getAlias('@frontend/web') . $thumbPath)) {
                $blogCover = $thumbPath;
            }
        }
    } elseif ($post->type === Post::TYPE_MOMENT && $post->moment !== null) {
        $preview = (string) $post->moment->content;
        $momentImages = $momentFeedImages($preview);
    } elseif ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null) {
        $preview = (string) $post->shortVideo->description;
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
    $avatar = $post->user?->avatar;
    $avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
    $hasMedia = $momentImages !== []
        || $blogCover !== null
        || ($post->type === Post::TYPE_IMAGES && $post->images !== [])
        || ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null);
    ?>
    <article class="home-feed-card card<?= $featured && $index === 0 ? ' is-featured' : '' ?>">
        <div class="card-body p-4">
            <div class="home-feed-card-header d-flex justify-content-between align-items-start gap-3 mb-3">
                <div class="d-flex align-items-center gap-3 min-width-0">
                    <?php if ($avatar): ?>
                        <img class="home-feed-avatar" src="<?= Html::encode($avatar) ?>" alt="<?= Html::encode($author) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="home-feed-avatar home-feed-avatar-fallback" aria-hidden="true"><?= Html::encode($avatarInitial) ?></span>
                    <?php endif; ?>
                    <div class="min-width-0">
                        <div class="home-feed-author text-truncate"><?= Html::encode($author) ?></div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="home-feed-type"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span>
                            <time class="text-body-secondary small text-nowrap" datetime="<?= Html::encode(date(DATE_ATOM, (int) $post->created_at)) ?>"><?= Yii::$app->formatter->asRelativeTime($post->created_at) ?></time>
                        </div>
                    </div>
                </div>
                <?php if (!empty($post->categories)): ?>
                    <div class="home-feed-categories mt-2">
                        <?php foreach ($post->categories as $cate): ?>
                            <?= Html::a(Html::encode($cate->name), ['/site/cate', 'slug' => $cate->slug], ['class' => 'badge text-bg-primary me-1 text-decoration-none']) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <h2 class="home-feed-card-title h4 mb-2">
                <?php if ($post->type === Post::TYPE_MOMENT): ?>
                    <?= $highlight($post->title ?: '动态') ?>
                <?php else: ?>
                    <?= Html::a($highlight((string) $post->title), [$detailRoute, 'id' => $post->id], ['class' => 'home-feed-title']) ?>
                <?php endif; ?>
            </h2>
            <?php if ($preview !== ''): ?><p class="home-feed-preview text-body-secondary mb-3"><?= $highlight($preview) ?></p><?php endif; ?>
            <?php if ($hasMedia): ?>
                <div class="home-feed-media mb-3">
                    <?php if ($blogCover !== null): ?>
                        <div class="home-feed-blog-cover">
                            <img src="<?= Html::encode($blogCover) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <?php if ($momentImages !== []): ?>
                        <div class="home-feed-images">
                            <?php foreach ($momentImages as $momentImage): ?>
                                <img src="<?= Html::encode($momentImage) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($post->type === Post::TYPE_IMAGES && $post->images !== []): ?>
                        <div class="home-feed-images">
                            <?php foreach (array_slice($post->images, 0, 9) as $image): ?>
                                <img src="<?= Html::encode($image->file_path) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy">
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
            <?php if (!empty($post->tags)): ?>
                <div class="home-feed-tags mt-2">
                    <?php foreach ($post->tags as $tag): ?>
                        <?= Html::a(Html::encode($tag->name), ['/site/tag', 'slug' => $tag->slug], ['class' => 'badge text-bg-light me-1 text-decoration-none']) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="home-feed-card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3">
                <?php if ($post->type === Post::TYPE_IMAGES): ?><span class="small text-body-secondary"><?= count($post->images) ?> 张图片</span><?php else: ?><span></span><?php endif; ?>
                <div class="d-flex align-items-center gap-2">
                    <?= LikeButton::widget(['post' => $post]) ?>
                    <?= FavoriteButton::widget(['postId' => (int) $post->id]) ?>
                    <?= Html::a('阅读更多', [$detailRoute, 'id' => $post->id], ['class' => 'btn btn-sm btn-outline-success']) ?>
                </div>
            </div>
        </div>
    </article>
<?php endforeach; ?>
