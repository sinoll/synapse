<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Cate $cate */
/** @var common\models\Cate[] $cates */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination $pagination */

use frontend\widgets\ContentPager;
use yii\helpers\Html;

$this->title = '分类：' . $cate->name;
?>

<div class="browse-page py-3 py-md-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-3">
            <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
            <li class="breadcrumb-item"><?= Html::a('内容', ['/site/index']) ?></li>
            <li class="breadcrumb-item active" aria-current="page">分类: <?= Html::encode($cate->name) ?></li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="browse-page-title mb-1"><?= Html::encode($cate->name) ?></h1>
            <?php if ($cate->description): ?>
                <p class="text-body-secondary mb-0"><?= Html::encode($cate->description) ?></p>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($cates as $c): ?>
                <?= Html::a(
                    Html::encode($c->name) . ' (' . $c->postCount . ')',
                    ['/site/cate', 'slug' => $c->slug],
                    ['class' => $c->id === $cate->id ? 'btn btn-primary btn-sm' : 'btn btn-outline-primary btn-sm']
                ) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($posts === []): ?>
        <div class="alert alert-light border">该分类下暂时还没有内容。</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?= $this->render('_cover_items', ['posts' => $posts]) ?>
        </div>
    <?php endif; ?>

    <?= ContentPager::widget(['pagination' => $pagination, 'route' => ['/site/cate'], 'params' => ['slug' => $cate->slug]]) ?>
</div>
