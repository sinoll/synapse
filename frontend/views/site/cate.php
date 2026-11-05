<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Cate|null $cate */
/** @var common\models\Cate[] $cates */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination|null $pagination */

use frontend\helpers\ContentCover;
use frontend\widgets\ContentPager;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = $cate !== null ? '分类：' . $cate->name : '分类总览';

$detailRoutes = common\models\Post::TYPE_DETAIL_ROUTES;
$typeLabels = common\models\Post::TYPE_LABELS;

$heroStyle = '';
if ($cate !== null && $cate->thumb) {
    $heroStyle = 'background-image:linear-gradient(rgba(15,46,34,.86),rgba(15,46,34,.72)),url(\''
        . Html::encode('/' . ltrim((string) $cate->thumb, '/')) . '\');background-size:cover;background-position:center;';
}

$featured = $posts !== [] ? $posts[0] : null;
$featuredMinis = array_slice($posts, 1, 3);
$gridPosts = array_slice($posts, 4);
?>

<div class="browse-page cate-channel py-3 py-md-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-3">
            <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
            <?php if ($cate !== null): ?>
                <li class="breadcrumb-item"><?= Html::a('分类总览', ['/site/cate']) ?></li>
                <li class="breadcrumb-item active" aria-current="page">分类: <?= Html::encode($cate->name) ?></li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page">分类总览</li>
            <?php endif; ?>
        </ol>
    </nav>

    <header class="cate-hero mb-3" style="<?= $heroStyle ?>">
        <h1 class="cate-hero-title mb-1"><?= $cate !== null ? Html::encode($cate->name) : '分类总览' ?></h1>
        <?php if ($cate !== null && $cate->description): ?>
            <p class="cate-hero-desc mb-2"><?= Html::encode($cate->description) ?></p>
        <?php elseif ($cate === null): ?>
            <p class="cate-hero-desc mb-2">任选一个分类，查看该主题下的全部内容。</p>
        <?php endif; ?>
        <?php if ($cate !== null): ?>
            <p class="cate-hero-stat mb-0">
                共 <b><?= (int) ($pagination?->totalCount ?? $cate->postCount ?? count($posts)) ?></b> 篇内容
                <?php if ($pagination !== null && $pagination->getPageCount() > 1): ?>
                    · 当前第 <?= $pagination->getPage() + 1 ?> / <?= $pagination->getPageCount() ?> 页
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if ($cate === null): ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 cate-overview">
            <?php foreach ($cates as $c): ?>
                <div class="col">
                    <a class="cate-overview-card" href="<?= Html::encode(Url::to(['/site/cate', 'slug' => $c->slug])) ?>">
                        <span class="cate-overview-name"><?= Html::encode($c->name) ?></span>
                        <span class="cate-overview-count"><?= (int) $c->postCount ?> 篇</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="cate-switcher mb-4">
            <?php foreach ($cates as $c): ?>
                <?= Html::a(
                    Html::encode($c->name) . ' <small>' . (int) $c->postCount . '</small>',
                    ['/site/cate', 'slug' => $c->slug],
                    ['class' => ($cate !== null && $c->id === $cate->id) ? 'cate-switch is-active' : 'cate-switch']
                ) ?>
            <?php endforeach; ?>
        </div>

        <?php if ($posts === []): ?>
            <div class="alert alert-light border">该分类下暂时还没有内容。</div>
        <?php else: ?>
            <div class="row g-3 g-lg-4 cate-featured mb-4">
                <div class="col-lg-8">
                    <a class="cate-featured-main" href="<?= Html::encode($detailRoutes[$featured->type] ?? '/site/view') . '?id=' . (int) $featured->id ?>">
                        <img class="cate-featured-cover" src="<?= Html::encode(ContentCover::url($featured)) ?>" alt="<?= Html::encode($featured->title) ?>" width="720" height="405">
                        <span class="cate-featured-body">
                            <span class="home-feed-type"><?= Html::encode($typeLabels[$featured->type] ?? $featured->type) ?></span>
                            <span class="cate-featured-title"><?= Html::encode($featured->title ?: '动态') ?></span>
                            <?php if (($s = ContentCover::summary($featured)) !== ''): ?>
                                <span class="cate-featured-summary"><?= Html::encode($s) ?></span>
                            <?php endif; ?>
                            <span class="cate-featured-meta"><?= Html::encode($featured->user?->nickname ?: $featured->user?->username ?: '用户') ?> · <?= Yii::$app->formatter->asRelativeTime($featured->created_at) ?></span>
                        </span>
                    </a>
                </div>
                <div class="col-lg-4">
                    <div class="cate-featured-side">
                        <?php foreach ($featuredMinis as $mini): ?>
                            <a class="cate-featured-mini" href="<?= Html::encode($detailRoutes[$mini->type] ?? '/site/view') . '?id=' . (int) $mini->id ?>">
                                <img class="cate-featured-mini-cover" src="<?= Html::encode(ContentCover::url($mini)) ?>" alt="<?= Html::encode($mini->title) ?>" width="160" height="90" loading="lazy">
                                <span class="cate-featured-mini-body">
                                    <span class="cate-featured-mini-title"><?= Html::encode($mini->title ?: '动态') ?></span>
                                    <span class="cate-featured-meta"><?= Html::encode($typeLabels[$mini->type] ?? $mini->type) ?> · <?= Yii::$app->formatter->asRelativeTime($mini->created_at) ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($gridPosts !== []): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?= $this->render('_cover_items', ['posts' => $gridPosts]) ?>
                </div>
            <?php endif; ?>

            <?= $pagination !== null
                ? ContentPager::widget(['pagination' => $pagination, 'route' => ['/site/cate'], 'params' => ['slug' => $cate->slug]])
                : '' ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
