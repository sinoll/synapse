<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $definitions */
/** @var string|null $selectedType */

use common\models\Post;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;

$this->title = '内容';
$typeLabels = [];
foreach ($definitions as $key => $definition) {
    $typeLabels[$definition['postType']] = $definition['label'];
}
?>
<div class="content-index py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="mb-1"><?= Html::encode($this->title) ?></h1>
            <p class="text-body-secondary mb-0">浏览公开内容，或查看你自己的内容。</p>
        </div>
        <?php if (!Yii::$app->user->isGuest): ?>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    发布内容
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($definitions as $key => $definition): ?>
                        <li><?= Html::a('发布' . $definition['label'], ['/content/create', 'type' => $key], ['class' => 'dropdown-item']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="btn-group flex-wrap mb-4" role="group" aria-label="内容类型筛选">
        <?= Html::a('全部', ['/content/index'], ['class' => 'btn btn-outline-secondary' . ($selectedType === null ? ' active' : '')]) ?>
        <?php foreach ($definitions as $key => $definition): ?>
            <?= Html::a($definition['label'], ['/content/index', 'type' => $key], ['class' => 'btn btn-outline-secondary' . ($selectedType === $key ? ' active' : '')]) ?>
        <?php endforeach; ?>
    </div>

    <?php if ($dataProvider->getCount() === 0): ?>
        <div class="alert alert-light border">目前没有可显示的内容。</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($dataProvider->getModels() as $post): ?>
                <?php
                $summary = match ($post->type) {
                    Post::TYPE_BLOG => $post->blog?->summary ?: $post->blog?->content,
                    Post::TYPE_MOMENT => $post->moment?->content,
                    Post::TYPE_SHORT_VIDEO => $post->shortVideo?->description,
                    Post::TYPE_SHARED_FILE => $post->sharedFile?->description,
                    default => ($post->images[0] ?? null)?->alt_text,
                };
                ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <span class="badge text-bg-light"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span>
                                <?php if ($post->status === Post::STATUS_DRAFT): ?>
                                    <span class="badge text-bg-secondary">草稿</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="h5"><?= Html::a(Html::encode($post->title), ['/content/view', 'id' => $post->id], ['class' => 'text-decoration-none']) ?></h2>
                            <?php if ($summary !== null && $summary !== ''): ?>
                                <p class="text-body-secondary"><?= Html::encode(mb_substr((string) $summary, 0, 180)) ?></p>
                            <?php endif; ?>
                            <p class="small text-body-secondary mb-0">
                                <?= Html::encode($post->user?->nickname ?: $post->user?->username ?: '用户') ?>
                                · <?= Yii::$app->formatter->asDatetime($post->created_at) ?>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <?= Html::a('查看详情', ['/content/view', 'id' => $post->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4">
            <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
        </div>
    <?php endif; ?>
</div>
