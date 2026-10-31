<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array<int,int> $tagCounts */

use yii\bootstrap5\LinkPager;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Blog';
$this->params['breadcrumbs'] = ['Blog'];

$posts = $dataProvider->getModels();
$webRoot = Yii::getAlias('@webroot');
?>
<div class="blog-index py-3 py-md-4">
    <?php if ($posts === []): ?>
        <div class="alert alert-light border">暂时还没有博客文章。</div>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php
            $blog = $post->blog;
            if ($blog === null) {
                continue;
            }

            $detailUrl = ['/blog/view', 'id' => $post->id];
            $author = $post->user?->nickname ?: $post->user?->username ?: '用户';
            $cate = $post->categories[0] ?? null;

            $thumb = trim((string) $blog->thumb);
            $thumbPath = '/' . ltrim($thumb, '/');
            $thumbUrl = $thumb !== '' && is_file($webRoot . $thumbPath) ? $thumbPath : '/images/default-cover.png';

            $excerpt = trim((string) ($blog->summary ?: $blog->content));
            $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags($excerpt)) ?? '');
            if (mb_strlen($excerpt) > 200) {
                $excerpt = mb_substr($excerpt, 0, 200) . '…';
            }
            ?>
            <article class="row g-3 g-md-4 align-items-center border-bottom pb-4 mb-4">
                <div class="col-12 col-md-4 text-center">
                    <a href="<?= Html::encode(Url::to($detailUrl)) ?>">
                        <img class="img-fluid rounded" src="<?= Html::encode($thumbUrl) ?>"
                             alt="<?= Html::encode($post->title) ?>" loading="lazy">
                    </a>
                </div>

                <div class="col-12 col-md-8">
                    <h2 class="h4 fw-bold mb-3">
                        <?= Html::a(Html::encode($post->title), $detailUrl, ['class' => 'text-decoration-none']) ?>
                    </h2>

                    <p class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge text-bg-dark"><?= Html::encode($author) ?></span>
                        <span class="badge text-bg-light">发布于</span>
                        <span class="badge text-bg-dark"><?= Html::encode(date('Y-m-d H:i:s', (int) $post->created_at)) ?></span>
                        <?php if ($cate !== null): ?>
                            <?= Html::a(Html::encode($cate->name), ['/site/cate', 'slug' => $cate->slug], ['class' => 'btn btn-outline-success btn-sm ms-md-3']) ?>
                        <?php endif; ?>
                        <?php foreach ($post->tags as $tag): ?>
                            <?= Html::a(
                                Html::encode($tag->name) . '<span class="badge text-bg-light ms-1">' . ($tagCounts[(int) $tag->id] ?? 0) . '</span>',
                                ['/site/tag', 'slug' => $tag->slug],
                                ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false],
                            ) ?>
                        <?php endforeach; ?>
                    </p>

                    <?php if ($excerpt !== ''): ?>
                        <p class="card-text text-body-secondary mb-3"><?= Html::encode($excerpt) ?></p>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end">
                        <?= Html::a('阅读全文', $detailUrl, ['class' => 'btn btn-sm btn-outline-success']) ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <div class="d-flex justify-content-center mt-4">
            <?= LinkPager::widget([
                'pagination' => $dataProvider->pagination,
                'options' => ['class' => 'pagination mb-0'],
                'maxButtonCount' => 10,
            ]) ?>
        </div>
    <?php endif; ?>
</div>
