<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var common\models\ShortVideo $video */

use yii\helpers\Html;

$this->title = $post->title;

$this->registerCss(<<<'CSS'
.content-view-video .video-stage { display: flex; justify-content: center; }
.content-view-video .video-stage video { display: block; width: auto; max-width: 100%; max-height: 80vh; background: #000; }
CSS);

$isVertical = $video->isVertical();

$durationLabel = null;
if ($video->duration_seconds !== null) {
    $durationSeconds = max(0, (int) $video->duration_seconds);
    $durationLabel = sprintf('%d:%02d', intdiv($durationSeconds, 60), $durationSeconds % 60);
}
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xxl-9">
        <article class="content-view content-view-video py-3 py-md-4">
            <?= $this->render('@frontend/views/site/_view_header', ['post' => $post, 'displayTitle' => $post->title]) ?>

            <?php if ($isVertical): ?>
                <div class="video-stage mb-4">
                    <video class="rounded-4 shadow-sm" controls playsinline preload="metadata"
                           <?php if ($video->cover_path): ?>poster="<?= Html::encode($video->cover_path) ?>"<?php endif; ?>>
                        <source src="<?= Html::encode($video->video_path) ?>">
                        您的浏览器不支持视频播放。
                    </video>
                </div>
            <?php else: ?>
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-sm bg-dark mb-4">
                    <video controls playsinline preload="metadata"
                           <?php if ($video->cover_path): ?>poster="<?= Html::encode($video->cover_path) ?>"<?php endif; ?>>
                        <source src="<?= Html::encode($video->video_path) ?>">
                        您的浏览器不支持视频播放。
                    </video>
                </div>
            <?php endif; ?>

            <?php if (trim((string) $video->description) !== ''): ?>
                <div class="text-break mb-3" style="white-space: pre-wrap"><?= Html::encode($video->description) ?></div>
            <?php endif; ?>

            <?php if ($durationLabel !== null): ?>
                <p class="text-body-secondary small mb-0">时长：<?= Html::encode($durationLabel) ?></p>
            <?php endif; ?>

            <?php if ($video->videoTypeLabel !== null): ?>
                <p class="text-body-secondary small mb-0">方向：<?= Html::encode($video->videoTypeLabel) ?></p>
            <?php endif; ?>
        </article>
    </div>
</div>
