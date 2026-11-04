<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Cate|null $cate */
/** @var common\models\Cate[] $cates */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination|null $pagination */

use frontend\widgets\ContentPager;
use yii\helpers\Html;

$this->title = $cate !== null ? '分类：' . $cate->name : '分类总览';
?>

<div class="browse-page py-3 py-md-4">
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

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="col-md-5">
            <h1 class="browse-page-title mb-1"><?= $cate !== null ? Html::encode($cate->name) : '分类总览' ?></h1>
            <?php if ($cate !== null && $cate->description): ?>
                <p class="text-body-secondary mb-0"><?= Html::encode($cate->description) ?></p>
            <?php elseif ($cate === null): ?>
                <p class="text-body-secondary mb-0">任选一个分类，查看该主题下的全部内容。</p>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2" style="float: right;">
            <?php foreach ($cates as $c): ?>
                <?= Html::a(
                    Html::encode($c->name) . ' (' . $c->postCount . ')',
                    ['/site/cate', 'slug' => $c->slug],
                    ['class' => ($cate !== null && $c->id === $cate->id) ? 'btn btn-primary btn-sm' : 'btn btn-outline-primary btn-sm']
                ) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($cate === null): ?>
        <?php /* 总览态只给分类入口，不查内容，避免为一个空列表付一次分页查询。 */ ?>
    <?php elseif ($posts === []): ?>
        <div class="alert alert-light border">该分类下暂时还没有内容。</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?= $this->render('_cover_items', ['posts' => $posts]) ?>
        </div>
    <?php endif; ?>

    <?= $pagination !== null
        ? ContentPager::widget(['pagination' => $pagination, 'route' => ['/site/cate'], 'params' => ['slug' => $cate->slug]])
        : '' ?>
</div>
