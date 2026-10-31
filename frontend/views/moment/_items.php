<?php

declare(strict_types=1);

/** @var common\models\Post[] $posts */

use common\models\Post;
use common\models\Tag;
use yii\helpers\Html;
use yii\helpers\HtmlPurifier;
use yii\helpers\Url;

$webRoot = Yii::getAlias('@webroot');

$momentContentImages = static function (string $html) use ($webRoot): array {
    if (!preg_match_all('#<img\b[^>]*\bsrc=["\']([^"\']+)["\']#i', $html, $matches)) {
        return [];
    }

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

$visibilityLabels = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
];

foreach ($posts as $post):
    $moment = $post->moment;
    if ($moment === null) {
        continue;
    }

    $detailUrl = ['/moment/view', 'id' => $post->id];
    $author = $post->user?->nickname ?: $post->user?->username ?: '用户';
    $avatar = $post->user?->avatar;
    $avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
    $cate = $post->categories[0] ?? null;
    $title = (string) ($post->title ?: '动态');
    $visibilityLabel = $visibilityLabels[(int) $post->visibility] ?? null;

    $contentHtml = HtmlPurifier::process((string) $moment->content, [
        'HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title]',
    ]);
    $inlineHashtags = Tag::extractHashtags($contentHtml);
    $contentHtml = Tag::linkifyHashtags($contentHtml);
    $images = $momentContentImages($contentHtml);
    $textHtml = (string) preg_replace('#<img\b[^>]*>#i', '', $contentHtml);
    $textHtml = (string) preg_replace('#<p>\s*(?:<br\s*/?>)?\s*</p>#i', '', $textHtml);
    $textHtml = trim($textHtml);
    $plainText = trim(html_entity_decode(strip_tags($textHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $hasText = $plainText !== '';
    ?>
    <article class="card moment-card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
            <header class="d-flex align-items-start gap-3">
                <?php if ($avatar): ?>
                    <img class="moment-avatar" src="<?= Html::encode($avatar) ?>" alt="<?= Html::encode($author) ?>" loading="lazy">
                <?php else: ?>
                    <span class="moment-avatar moment-avatar-fallback" aria-hidden="true"><?= Html::encode($avatarInitial) ?></span>
                <?php endif; ?>
                <div class="flex-grow-1 min-width-0">
                    <div class="moment-author text-truncate"><?= Html::encode($author) ?></div>
                    <div class="d-flex flex-wrap align-items-center gap-2 small text-body-secondary">
                        <time datetime="<?= Html::encode(date(DATE_ATOM, (int) $post->created_at)) ?>"><?= Yii::$app->formatter->asRelativeTime($post->created_at) ?></time>
                        <?php if ($cate !== null): ?>
                            <span>·</span>
                            <?= Html::a(Html::encode($cate->name), ['/site/cate', 'slug' => $cate->slug], ['class' => 'moment-meta-link']) ?>
                        <?php endif; ?>
                        <?php if ($visibilityLabel !== null): ?>
                            <span class="badge rounded-pill text-bg-warning"><?= Html::encode($visibilityLabel) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="moment-more" href="<?= Html::encode(Url::to($detailUrl)) ?>" aria-label="查看动态详情">
                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                </a>
            </header>

            <?php if ($hasText): ?>
                <div class="moment-card-text text-break" data-moment-text><?= $textHtml ?></div>
            <?php endif; ?>

            <?php if ($images !== []): ?>
                <div class="moment-images" data-count="<?= count($images) ?>" role="list">
                    <?php foreach ($images as $image): ?>
                        <a class="moment-image" role="listitem" href="<?= Html::encode(Url::to($detailUrl)) ?>">
                            <img src="<?= Html::encode($image) ?>" alt="<?= Html::encode($title) ?>" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <footer class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <div class="d-flex flex-wrap gap-1 flex-grow-1">
                    <?php if ($inlineHashtags === []): ?>
                        <?php foreach ($post->tags as $tag): ?>
                            <?= Html::a('#' . $tag->name, ['/site/tag', 'slug' => $tag->slug], ['class' => 'moment-tag']) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-link moment-action p-0 text-decoration-none" data-moment-copy="<?= Html::encode(Url::to($detailUrl, true)) ?>">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i><span>复制链接</span>
                    </button>
                    <a class="moment-action" href="<?= Html::encode(Url::to($detailUrl)) ?>">
                        查看详情<i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                </div>
            </footer>
        </div>
    </article>
<?php endforeach; ?>
