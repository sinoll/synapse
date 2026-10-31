<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var bool $vertical */

use yii\helpers\Html;
use yii\helpers\Url;

$video = $post->shortVideo;
if ($video === null) {
    return;
}

$detailUrl = ['/video/view', 'id' => $post->id];
$authorName = $post->user?->nickname ?: $post->user?->username ?: '用户';
$authorInitial = mb_strtoupper(mb_substr($authorName, 0, 1));
$cover = trim((string) $video->cover_path);
$coverUrl = $cover !== '' ? $cover : '/images/default-cover.png';

$durationLabel = null;
if ($video->duration_seconds !== null) {
    $seconds = max(0, (int) $video->duration_seconds);
    $durationLabel = sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}
$publishedLabel = Yii::$app->formatter->asRelativeTime((int) $post->created_at);
?>
<article class="video-lib-card<?= $vertical ? ' video-lib-card--vertical' : '' ?>">
    <a class="video-lib-thumb" href="<?= Html::encode(Url::to($detailUrl)) ?>">
        <img src="<?= Html::encode($coverUrl) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy">
        <?php if ($durationLabel !== null): ?>
            <span class="video-lib-duration"><?= Html::encode($durationLabel) ?></span>
        <?php endif; ?>
        <span class="video-lib-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
    </a>
    <div class="video-lib-body">
        <?php if (!$vertical): ?>
            <?php if ($post->user?->avatar): ?>
                <img class="video-lib-avatar" src="<?= Html::encode($post->user->avatar) ?>" alt="<?= Html::encode($authorName) ?>" loading="lazy">
            <?php else: ?>
                <span class="video-lib-avatar video-lib-avatar-fallback" aria-hidden="true"><?= Html::encode($authorInitial) ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <div class="min-w-0">
            <a class="video-lib-title" href="<?= Html::encode(Url::to($detailUrl)) ?>"><?= Html::encode($post->title) ?></a>
            <div class="video-lib-meta text-truncate"><?= Html::encode($authorName) ?> · <?= Html::encode($publishedLabel) ?></div>
        </div>
    </div>
</article>
