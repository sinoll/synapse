<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array<int, array{post: common\models\Post, reason: string}> $items */
/** @var string $heading */

use common\models\Post;
use yii\helpers\Html;
use yii\helpers\Url;

$typeLabels = Post::TYPE_LABELS;
$detailRoutes = Post::TYPE_DETAIL_ROUTES;
?>
<?php if ($items !== []): ?>
    <section class="content-detail-related" aria-label="<?= Html::encode($heading) ?>">
        <h2 class="content-detail-heading"><?= Html::encode($heading) ?></h2>
        <div class="content-detail-related-list">
            <?php foreach ($items as $item): ?>
                <?php $related = $item['post']; ?>
                <a class="content-detail-related-item" href="<?= Html::encode(Url::to([$detailRoutes[$related->type] ?? '/site/view', 'id' => (int) $related->id])) ?>">
                    <img class="content-detail-related-thumb" src="<?= Html::encode($related->coverUrl()) ?>" alt="" width="88" height="60" loading="lazy">
                    <span class="content-detail-related-body">
                        <span class="content-detail-related-title"><?= Html::encode($related->title ?: '动态') ?></span>
                        <span class="content-detail-related-meta">
                            <?= Html::encode($typeLabels[$related->type] ?? $related->type) ?> · <?= date('Y-m-d', (int) $related->created_at) ?>
                        </span>
                        <span class="content-detail-related-reason"><?= Html::encode($item['reason']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
