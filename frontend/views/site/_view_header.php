<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var string $displayTitle */

use common\models\Post;
use yii\helpers\Html;

$typeLabels = Post::TYPE_LABELS;
$primaryCate = $post->categories[0] ?? null;

$this->registerCss(<<<'CSS'
.content-view .breadcrumb a { color: var(--bs-link-color); text-decoration: none; }
.content-view .breadcrumb a:hover { color: var(--bs-link-hover-color); text-decoration: underline; }
.content-view .rich-content img { max-width: 100%; height: auto; }
.content-view .rich-content pre { overflow-x: auto; max-width: 100%; margin: 1rem 0; padding: 1rem 1.1rem; border: 1px solid var(--bs-border-color); border-radius: .65rem; background: var(--bs-tertiary-bg); color: var(--bs-body-color); font-family: var(--bs-font-monospace); font-size: .9em; line-height: 1.6; white-space: pre; }
.content-view .rich-content pre code { padding: 0; background: transparent; color: inherit; font: inherit; white-space: inherit; }
.content-view .content-view-summary { margin: 0 0 1.5rem; padding: .9rem 1.2rem; border-left: 4px solid var(--bs-primary); border-radius: 0 .5rem .5rem 0; background: var(--bs-primary-bg-subtle); color: var(--bs-body-color); line-height: 1.8; }
CSS);
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
        <?php if ($primaryCate !== null): ?>
            <li class="breadcrumb-item"><?= Html::a(Html::encode($primaryCate->name), ['/site/cate', 'slug' => $primaryCate->slug]) ?></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?= Html::encode($displayTitle) ?></li>
    </ol>
</nav>

<?php if ($displayTitle !== ''): ?><h1 class="mb-3"><?= Html::encode($displayTitle) ?></h1><?php endif; ?>

<div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-3 text-body-secondary small">
    <span class="home-feed-type"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span>
    <span><?= Html::encode($post->user?->nickname ?: $post->user?->username ?: '用户') ?></span>
    <span>·</span>
    <time datetime="<?= Html::encode(date(DATE_ATOM, (int) $post->created_at)) ?>"><?= Yii::$app->formatter->asDatetime($post->created_at) ?></time>
    <?php if ((int) $post->status === Post::STATUS_DRAFT): ?><span>· 草稿</span><?php endif; ?>
</div>

<?php if (!empty($post->categories) || !empty($post->tags)): ?>
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
        <?php foreach ($post->categories as $cate): ?>
            <?= Html::a(Html::encode($cate->name), ['/site/cate', 'slug' => $cate->slug], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        <?php endforeach; ?>
        <?php foreach ($post->tags as $tag): ?>
            <?= Html::a(Html::encode($tag->name), ['/site/tag', 'slug' => $tag->slug], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
