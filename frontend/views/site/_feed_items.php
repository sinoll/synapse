<?php

declare(strict_types=1);

/** @var common\models\Post[] $posts */

use common\models\Post;
use yii\helpers\Html;

$typeLabels = Post::TYPE_LABELS;
$detailRoutes = Post::TYPE_DETAIL_ROUTES;

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
        $thumbnail = (string) preg_replace('#^/uploads/blog/\d{4}/#', '/uploads/thumb/blog/', $source);
        $images[] = $thumbnail !== $source && is_file($webRoot . $thumbnail) ? $thumbnail : $source;
        if (count($images) === 9) {
            break;
        }
    }

    return $images;
};

foreach ($posts as $post):
    $preview = '';
    $momentImages = [];
    if ($post->type === Post::TYPE_BLOG && $post->blog !== null) {
        $preview = (string) ($post->blog->summary ?: $post->blog->content);
    } elseif ($post->type === Post::TYPE_MOMENT && $post->moment !== null) {
        $preview = (string) $post->moment->content;
        $momentImages = $momentFeedImages($preview);
    } elseif ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null) {
        $preview = (string) $post->shortVideo->description;
    }
    $preview = trim(preg_replace('/\s+/u', ' ', strip_tags($preview)) ?? '');
    if (mb_strlen($preview) > 150) {
        $preview = mb_substr($preview, 0, 150) . '…';
    }
    $detailRoute = $detailRoutes[$post->type] ?? '/site/view';
    $author = $post->user?->nickname ?: $post->user?->username ?: '用户';
    $avatar = $post->user?->avatar;
    $avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
    ?>
    <article class="home-feed-card card mb-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
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
            <h2 class="h4 mb-2">
                <?php if ($post->type === Post::TYPE_MOMENT): ?>
                    <?= Html::encode($post->title ?: '动态') ?>
                <?php else: ?>
                    <?= Html::a(Html::encode($post->title), [$detailRoute, 'id' => $post->id], ['class' => 'home-feed-title']) ?>
                <?php endif; ?>
            </h2>
            <?php if ($preview !== ''): ?><p class="home-feed-preview text-body-secondary mb-3"><?= Html::encode($preview) ?></p><?php endif; ?>
            <?php if ($momentImages !== []): ?>
                <div class="home-feed-images mb-3">
                    <?php foreach ($momentImages as $momentImage): ?>
                        <img src="<?= Html::encode($momentImage) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($post->type === Post::TYPE_IMAGES && $post->images !== []): ?>
                <div class="home-feed-images mb-3">
                    <?php foreach (array_slice($post->images, 0, 9) as $image): ?>
                        <img src="<?= Html::encode($image->file_path) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null): ?>
                <video class="home-feed-video mb-3" controls preload="metadata" playsinline<?php if ($post->shortVideo->cover_path): ?> poster="<?= Html::encode($post->shortVideo->cover_path) ?>"<?php endif; ?>>
                    <source src="<?= Html::encode($post->shortVideo->video_path) ?>">
                    您的浏览器不支持视频播放。
                </video>
            <?php endif; ?>
            <?php if (!empty($post->tags)): ?>
                <div class="home-feed-tags mt-2">
                    <?php foreach ($post->tags as $tag): ?>
                        <?= Html::a(Html::encode($tag->name), ['/site/tag', 'slug' => $tag->slug], ['class' => 'badge text-bg-light me-1 text-decoration-none']) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3">
                <?php if ($post->type === Post::TYPE_IMAGES): ?><span class="small text-body-secondary"><?= count($post->images) ?> 张图片</span><?php else: ?><span></span><?php endif; ?>
                <?= Html::a('查看详情 →', [$detailRoute, 'id' => $post->id], ['class' => 'home-feed-more']) ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
