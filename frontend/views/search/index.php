<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $keyword */
/** @var string|null $selectedType */
/** @var array $definitions */
/** @var yii\data\ActiveDataProvider|null $dataProvider */

use yii\bootstrap5\LinkPager;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = $keyword === '' ? '搜索' : '“' . $keyword . '” 的搜索结果';
$total = $dataProvider === null ? 0 : $dataProvider->getTotalCount();
?>
<div class="search-page py-3 py-md-4">
    <header class="search-masthead mb-4">
        <h4 class="search-heading mb-3">站内搜索</h4>
        <form class="search-form input-group" action="<?= Url::to(['/search/index']) ?>" method="get" role="search">
            <input class="form-control" type="search" name="q" value="<?= Html::encode($keyword) ?>"
                placeholder="输入关键词，搜索标题与正文…" aria-label="站内搜索关键词" maxlength="60" autofocus>
            <?php if ($selectedType !== null): ?>
                <input type="hidden" name="type" value="<?= Html::encode($selectedType) ?>">
            <?php endif; ?>
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>搜索</button>
        </form>
        <div class="btn-group flex-wrap mt-3" role="group" aria-label="内容类型筛选">
            <?= Html::a('全部', ['/search/index', 'q' => $keyword], ['class' => 'btn btn-outline-secondary' . ($selectedType === null ? ' active' : '')]) ?>
            <?php foreach ($definitions as $key => $definition): ?>
                <?= Html::a($definition['label'], ['/search/index', 'q' => $keyword, 'type' => $key], ['class' => 'btn btn-outline-secondary' . ($selectedType === $key ? ' active' : '')]) ?>
            <?php endforeach; ?>
        </div>
    </header>

    <?php if ($keyword === ''): ?>
        <div class="alert alert-light border">可以搜索博客、动态、图集和短视频的标题、正文，以及标签、分类和作者。</div>
    <?php elseif ($total === 0): ?>
        <div class="search-empty card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <p class="search-empty-icon mb-2" aria-hidden="true"><i class="bi bi-search"></i></p>
                <p class="mb-1">没有找到与“<?= Html::encode($keyword) ?>”相关的内容</p>
                <p class="text-body-secondary small mb-0">换个关键词，或者去掉类型筛选再试试。</p>
            </div>
        </div>
    <?php else: ?>
        <p class="search-result-count text-body-secondary mb-3">
            共 <?= $total ?> 条与“<?= Html::encode($keyword) ?>”相关的内容<?= $selectedType === null ? '' : '（' . Html::encode($definitions[$selectedType]['label']) . '）' ?>
        </p>
        <div class="home-feed-list">
            <?= $this->render('@frontend/views/site/_feed_items', [
                'posts' => $dataProvider->getModels(),
                'featured' => false,
                'keyword' => $keyword,
            ]) ?>
        </div>
        <div class="d-flex justify-content-center mt-4">
            <?= LinkPager::widget([
                'pagination' => $dataProvider->pagination,
                'options' => ['class' => 'pagination mb-0'],
                'maxButtonCount' => 10,
            ]) ?>
        </div>
    <?php endif; ?>
</div>
