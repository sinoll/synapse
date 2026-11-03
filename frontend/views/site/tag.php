<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Tag $tag */
/** @var common\models\Tag[] $tags */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination $pagination */

use frontend\widgets\ContentPager;
use yii\helpers\Html;

$this->title = '标签：' . $tag->name;
?>

<div class="browse-page py-3 py-md-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-3">
            <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
            <li class="breadcrumb-item"><?= Html::a('内容', ['/site/index']) ?></li>
            <li class="breadcrumb-item active" aria-current="page">标签: <?= Html::encode($tag->name) ?></li>
        </ol>
    </nav>

    <h1 class="browse-page-title mb-3"># <?= Html::encode($tag->name) ?></h1>

    <div class="tag-cloud">
        <?php foreach ($tags as $t): ?>
            <?= Html::a(
                Html::encode($t->name) . ' (' . $t->postCount . ')',
                ['/site/tag', 'slug' => $t->slug],
                ['class' => $t->id === $tag->id ? 'active' : null]
            ) ?>
        <?php endforeach; ?>
    </div>

    <?php if ($posts === []): ?>
        <div class="alert alert-light border mt-4">该标签下暂时还没有内容。</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?= $this->render('_cover_items', ['posts' => $posts]) ?>
        </div>
    <?php endif; ?>

    <?= ContentPager::widget(['pagination' => $pagination, 'route' => ['/site/tag'], 'params' => ['slug' => $tag->slug]]) ?>
</div>
