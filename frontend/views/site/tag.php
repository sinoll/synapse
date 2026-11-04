<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Tag|null $tag */
/** @var common\models\Tag[] $tags */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination|null $pagination */

use frontend\widgets\ContentPager;
use yii\helpers\Html;

$this->title = $tag !== null ? '标签：' . $tag->name : '标签云';
?>

<div class="browse-page py-3 py-md-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-3">
            <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
            <?php if ($tag !== null): ?>
                <li class="breadcrumb-item"><?= Html::a('标签云', ['/site/tag']) ?></li>
                <li class="breadcrumb-item active" aria-current="page">标签: <?= Html::encode($tag->name) ?></li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page">标签云</li>
            <?php endif; ?>
        </ol>
    </nav>

    <h1 class="browse-page-title mb-3"><?= $tag !== null ? '# ' . Html::encode($tag->name) : '标签云' ?></h1>
    <?php if ($tag === null): ?>
        <p class="text-body-secondary mb-3">字号按标签下的内容量缩放，点任意标签查看对应内容。</p>
    <?php endif; ?>

    <?php
    // 字号按内容量取对数，避免 45 篇的标签把只有 1 篇的挤出画面。
    $cloudMaxCount = 0;
    foreach ($tags as $t) {
        $cloudMaxCount = max($cloudMaxCount, (int) $t->postCount);
    }
    // 色相按 tag.id 取模固定分配：改名、排序或计数变化都不会让同一个标签换颜色，
    // 15 这一档要和 site.css 里的 .tag-c1 ~ .tag-c15 对齐。
    $paletteSize = 15;
    ?>
    <div class="tag-cloud">
        <?php foreach ($tags as $index => $t): ?>
            <?php $weight = $cloudMaxCount > 1
                ? log(1 + (int) $t->postCount) / log(1 + $cloudMaxCount)
                : 1.0; ?>
            <?= Html::a(
                '<span class="tag-cloud-name">#' . Html::encode($t->name) . '</span>'
                    . '<span class="tag-cloud-count">' . (int) $t->postCount . '</span>',
                ['/site/tag', 'slug' => $t->slug],
                [
                    'class' => 'tag-cloud-item tag-c' . ((int) $t->id % $paletteSize + 1)
                        . (($tag !== null && $t->id === $tag->id) ? ' active' : ''),
                    'style' => '--i:' . $index . ';--w:' . round($weight, 3),
                    'encode' => false,
                ],
            ) ?>
        <?php endforeach; ?>
    </div>

    <?php if ($tag === null): ?>
        <?php /* 总览态只给标签入口，不查内容，避免为一个空列表付一次分页查询。 */ ?>
    <?php elseif ($posts === []): ?>
        <div class="alert alert-light border mt-4">该标签下暂时还没有内容。</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?= $this->render('_cover_items', ['posts' => $posts]) ?>
        </div>
    <?php endif; ?>

    <?= $pagination !== null
        ? ContentPager::widget(['pagination' => $pagination, 'route' => ['/site/tag'], 'params' => ['slug' => $tag->slug]])
        : '' ?>
</div>
