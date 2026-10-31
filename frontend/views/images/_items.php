<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post[] $posts */

use yii\helpers\Html;
use yii\helpers\Url;

foreach ($posts as $post):
    $detailUrl = ['/images/view', 'id' => $post->id];
    $images = $post->images;
    $cover = $images[0] ?? null;
    $imageCount = count($images);
    $title = trim((string) $post->title) !== '' ? (string) $post->title : '未命名图集';
    $author = $post->user?->nickname ?: $post->user?->username ?: '用户';
    $avatar = $post->user?->avatar;
    $avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
    ?>
    <div class="col">
        <article class="gallery-card">
            <a class="gallery-thumb" href="<?= Html::encode(Url::to($detailUrl)) ?>" tabindex="-1" aria-hidden="true">
                <?php if ($cover !== null): ?>
                    <img src="<?= Html::encode($cover->thumbnailPath) ?>"
                         alt="<?= Html::encode($cover->alt_text ?: $title) ?>"
                         loading="lazy">
                <?php else: ?>
                    <span class="gallery-thumb-empty"><i class="bi bi-images" aria-hidden="true"></i></span>
                <?php endif; ?>
                <?php if ($imageCount > 1): ?>
                    <span class="gallery-count"><i class="bi bi-images" aria-hidden="true"></i><?= (int) $imageCount ?></span>
                <?php endif; ?>
            </a>
            <div class="gallery-body">
                <a class="gallery-title" href="<?= Html::encode(Url::to($detailUrl)) ?>"><?= Html::encode($title) ?></a>
                <div class="gallery-meta">
                    <?php if ($avatar): ?>
                        <img class="gallery-meta-avatar" src="<?= Html::encode($avatar) ?>" alt="<?= Html::encode($author) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="gallery-meta-avatar gallery-meta-avatar-fallback" aria-hidden="true"><?= Html::encode($avatarInitial) ?></span>
                    <?php endif; ?>
                    <span class="gallery-meta-author"><?= Html::encode($author) ?></span>
                    <span class="gallery-meta-sep" aria-hidden="true">·</span>
                    <time datetime="<?= Html::encode(date(DATE_ATOM, (int) $post->created_at)) ?>"><?= Yii::$app->formatter->asRelativeTime((int) $post->created_at) ?></time>
                </div>
            </div>
        </article>
    </div>
<?php endforeach; ?>
