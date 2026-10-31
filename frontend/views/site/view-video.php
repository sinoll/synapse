<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var common\models\ShortVideo $video */
/** @var common\models\Post[] $relatedVideos */
/** @var common\models\Post|null $previousVideo */
/** @var common\models\Post|null $nextVideo */

use common\models\Post;
use yii\helpers\Html;
use yii\helpers\Url;

$displayTitle = $post->title ?: '短视频';
$this->title = $displayTitle;

$isVertical = $video->isVertical();
$author = $post->user;
$authorName = $author?->nickname ?: $author?->username ?: '用户';
$authorInitial = mb_strtoupper(mb_substr($authorName, 0, 1));
$cover = trim((string) $video->cover_path);
$coverUrl = $cover !== '' ? $cover : '/images/default-cover.png';
$description = trim((string) $video->description);
$publishedAt = Yii::$app->formatter->asDatetime($post->created_at);
$canManage = !Yii::$app->user->isGuest && (int) $post->user_id === (int) Yii::$app->user->id;

$durationLabel = null;
if ($video->duration_seconds !== null) {
    $seconds = max(0, (int) $video->duration_seconds);
    $durationLabel = sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

$relatedVideos = $relatedVideos ?? [];
$previousVideo = $previousVideo ?? null;
$nextVideo = $nextVideo ?? null;

$durationOf = static function (?Post $item): ?string {
    $itemDuration = $item?->shortVideo?->duration_seconds;
    if ($itemDuration === null) {
        return null;
    }
    $itemSeconds = max(0, (int) $itemDuration);

    return sprintf('%d:%02d', intdiv($itemSeconds, 60), $itemSeconds % 60);
};
?>
<div class="video-page">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
            <li class="breadcrumb-item"><?= Html::a('短视频', ['/video/index']) ?></li>
            <li class="breadcrumb-item active" aria-current="page"><?= Html::encode($displayTitle) ?></li>
        </ol>
    </nav>

    <?php if ($isVertical): ?>
        <div class="video-shorts">
            <div class="video-shorts-stage">
                <div class="video-shorts-player" data-short-player>
                    <video autoplay muted loop playsinline preload="metadata"
                           poster="<?= Html::encode($coverUrl) ?>" data-short-video
                           aria-label="<?= Html::encode($displayTitle) ?>">
                        <source src="<?= Html::encode($video->video_path) ?>">
                        您的浏览器不支持视频播放。
                    </video>
                    <div class="video-shorts-overlay">
                        <div class="video-shorts-author">
                            <?php if ($author?->avatar): ?>
                                <img class="video-shorts-avatar" src="<?= Html::encode($author->avatar) ?>" alt="<?= Html::encode($authorName) ?>">
                            <?php else: ?>
                                <span class="video-shorts-avatar video-shorts-avatar-fallback" aria-hidden="true"><?= Html::encode($authorInitial) ?></span>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="video-shorts-author-name"><?= Html::encode($authorName) ?></div>
                                <div class="video-shorts-author-meta"><?= Html::encode($publishedAt) ?></div>
                            </div>
                        </div>
                        <div class="video-shorts-title"><?= Html::encode($displayTitle) ?></div>
                        <?php if ($description !== ''): ?>
                            <p class="video-shorts-desc"><?= Html::encode($description) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="video-shorts-rail" aria-label="视频操作">
                    <?php if ($previousVideo !== null): ?>
                        <?= Html::a(
                            '<i class="bi bi-chevron-up" aria-hidden="true"></i><span>上一条</span>',
                            ['/video/view', 'id' => $previousVideo->id],
                            ['class' => 'video-shorts-action', 'encode' => false, 'aria-label' => '上一条视频：' . $previousVideo->title],
                        ) ?>
                    <?php else: ?>
                        <span class="video-shorts-action is-disabled" aria-disabled="true"><i class="bi bi-chevron-up" aria-hidden="true"></i><span>上一条</span></span>
                    <?php endif; ?>
                    <?php if ($nextVideo !== null): ?>
                        <?= Html::a(
                            '<i class="bi bi-chevron-down" aria-hidden="true"></i><span>下一条</span>',
                            ['/video/view', 'id' => $nextVideo->id],
                            ['class' => 'video-shorts-action', 'encode' => false, 'aria-label' => '下一条视频：' . $nextVideo->title],
                        ) ?>
                    <?php else: ?>
                        <span class="video-shorts-action is-disabled" aria-disabled="true"><i class="bi bi-chevron-down" aria-hidden="true"></i><span>下一条</span></span>
                    <?php endif; ?>
                    <button type="button" class="video-shorts-action" data-short-toggle aria-label="暂停">
                        <i class="bi bi-pause-fill" aria-hidden="true"></i><span>暂停</span>
                    </button>
                    <button type="button" class="video-shorts-action" data-short-mute aria-label="开启声音">
                        <i class="bi bi-volume-mute-fill" aria-hidden="true"></i><span>静音</span>
                    </button>
                    <button type="button" class="video-shorts-action" data-video-share>
                        <i class="bi bi-share-fill" aria-hidden="true"></i><span data-share-label>分享</span>
                    </button>
                    <?= Html::a(
                        '<i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i><span>频道</span>',
                        ['/video/index'],
                        ['class' => 'video-shorts-action', 'encode' => false],
                    ) ?>
                </div>
            </div>

            <div class="video-shorts-footer">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="video-meta-chip"><i class="bi bi-phone me-1" aria-hidden="true"></i>竖屏 9:16</span>
                    <?php if ($durationLabel !== null): ?>
                        <span class="video-meta-chip"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= Html::encode($durationLabel) ?></span>
                    <?php endif; ?>
                    <span class="video-meta-chip"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= Html::encode($publishedAt) ?></span>
                    <?php if ($canManage): ?>
                        <?= Html::a('编辑视频', ['/video/update', 'id' => $post->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($post->tags)): ?>
                    <div class="video-tag-list mt-3">
                        <?php foreach ($post->tags as $tag): ?>
                            <?= Html::a('#' . Html::encode($tag->name), ['/site/tag', 'slug' => $tag->slug], ['class' => 'video-tag-chip']) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="video-watch-player ratio ratio-16x9">
                    <video controls playsinline preload="metadata"
                           poster="<?= Html::encode($coverUrl) ?>"
                           aria-label="<?= Html::encode($displayTitle) ?>">
                        <source src="<?= Html::encode($video->video_path) ?>">
                        您的浏览器不支持视频播放。
                    </video>
                </div>

                <h1 class="video-watch-title"><?= Html::encode($displayTitle) ?></h1>

                <div class="video-watch-meta d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($author?->avatar): ?>
                            <img class="video-watch-avatar" src="<?= Html::encode($author->avatar) ?>" alt="<?= Html::encode($authorName) ?>">
                        <?php else: ?>
                            <span class="video-watch-avatar video-watch-avatar-fallback" aria-hidden="true"><?= Html::encode($authorInitial) ?></span>
                        <?php endif; ?>
                        <div>
                            <div class="video-watch-author"><?= Html::encode($authorName) ?></div>
                            <div class="video-watch-sub">
                                发布于 <?= Html::encode($publishedAt) ?>
                                <?php if ($durationLabel !== null): ?> · <?= Html::encode($durationLabel) ?><?php endif; ?>
                                · 横屏
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-video-share>
                            <i class="bi bi-share me-1" aria-hidden="true"></i><span data-share-label>分享</span>
                        </button>
                        <?= Html::a('<i class="bi bi-grid me-1" aria-hidden="true"></i>视频频道', ['/video/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
                        <?php if ($canManage): ?>
                            <?= Html::a('<i class="bi bi-pencil-square me-1" aria-hidden="true"></i>编辑', ['/video/update', 'id' => $post->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="video-watch-description">
                    <p class="video-watch-description-text mb-0"><?= Html::encode($description !== '' ? $description : '这个视频还没有简介。') ?></p>
                    <?php if (!empty($post->tags)): ?>
                        <div class="video-tag-list mt-3">
                            <?php foreach ($post->tags as $tag): ?>
                                <?= Html::a('#' . Html::encode($tag->name), ['/site/tag', 'slug' => $tag->slug], ['class' => 'video-tag-chip']) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="col-12 col-xl-4">
                <h2 class="video-related-heading">接下来</h2>
                <?php if ($relatedVideos === []): ?>
                    <p class="small text-body-secondary mb-0">暂无更多视频。</p>
                <?php else: ?>
                    <div class="video-related-list">
                        <?php foreach ($relatedVideos as $related): ?>
                            <?php
                            $relatedVideo = $related->shortVideo;
                            if ($relatedVideo === null) {
                                continue;
                            }
                            $relatedTitle = $related->title ?: '短视频';
                            $relatedCover = trim((string) $relatedVideo->cover_path);
                            $relatedCoverUrl = $relatedCover !== '' ? $relatedCover : '/images/default-cover.png';
                            $relatedAuthor = $related->user?->nickname ?: $related->user?->username ?: '用户';
                            $relatedDuration = $durationOf($related);
                            ?>
                            <a class="video-related-item" href="<?= Html::encode(Url::to(['/video/view', 'id' => $related->id])) ?>">
                                <div class="video-related-thumb">
                                    <img src="<?= Html::encode($relatedCoverUrl) ?>" alt="<?= Html::encode($relatedTitle) ?>" loading="lazy">
                                    <?php if ($relatedVideo->isVertical()): ?>
                                        <span class="video-related-orientation">竖屏</span>
                                    <?php endif; ?>
                                    <?php if ($relatedDuration !== null): ?>
                                        <span class="video-related-duration"><?= Html::encode($relatedDuration) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="video-related-title"><?= Html::encode($relatedTitle) ?></div>
                                    <div class="video-related-meta"><?= Html::encode($relatedAuthor) ?> · <?= Html::encode(Yii::$app->formatter->asRelativeTime((int) $related->created_at)) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php
$this->registerJs(<<<'JS'
document.querySelectorAll('[data-video-share]').forEach(function (button) {
    button.addEventListener('click', function () {
        const label = button.querySelector('[data-share-label]');
        const done = function () {
            if (!label) return;
            const original = label.textContent;
            label.textContent = '已复制';
            window.setTimeout(function () { label.textContent = original; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(window.location.href).then(done).catch(function () {});
            return;
        }
        const helper = document.createElement('textarea');
        helper.value = window.location.href;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild(helper);
        helper.select();
        try { document.execCommand('copy'); done(); } catch (error) {}
        helper.remove();
    });
});

(function () {
    const video = document.querySelector('[data-short-video]');
    const toggle = document.querySelector('[data-short-toggle]');
    const mute = document.querySelector('[data-short-mute]');
    if (!video) return;

    const playIcon = toggle ? toggle.querySelector('.bi') : null;
    const playLabel = toggle ? toggle.querySelector('span') : null;
    const muteIcon = mute ? mute.querySelector('.bi') : null;
    const muteLabel = mute ? mute.querySelector('span') : null;

    const syncPlay = function () {
        if (!toggle) return;
        const playing = !video.paused && !video.ended;
        if (playIcon) playIcon.className = 'bi ' + (playing ? 'bi-pause-fill' : 'bi-play-fill');
        if (playLabel) playLabel.textContent = playing ? '暂停' : '播放';
        toggle.setAttribute('aria-label', playing ? '暂停' : '播放');
    };
    const syncMute = function () {
        if (!mute) return;
        if (muteIcon) muteIcon.className = 'bi ' + (video.muted ? 'bi-volume-mute-fill' : 'bi-volume-up-fill');
        if (muteLabel) muteLabel.textContent = video.muted ? '静音' : '有声';
        mute.setAttribute('aria-label', video.muted ? '开启声音' : '静音');
    };
    const togglePlay = function () {
        if (video.paused) {
            video.play().catch(function () {});
        } else {
            video.pause();
        }
    };

    video.addEventListener('click', togglePlay);
    if (toggle) toggle.addEventListener('click', togglePlay);
    if (mute) mute.addEventListener('click', function () {
        video.muted = !video.muted;
        if (!video.muted) video.volume = 1;
        syncMute();
    });
    video.addEventListener('play', syncPlay);
    video.addEventListener('pause', syncPlay);
    video.addEventListener('ended', syncPlay);
    syncPlay();
    syncMute();
})();
JS);
