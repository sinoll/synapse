<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var string $displayTitle */
/** @var array{total: int, byType: array<string, int>} $authorCounts */

use common\models\Post;
use frontend\widgets\FavoriteButton;
use frontend\widgets\FollowButton;
use frontend\widgets\LikeButton;
use frontend\widgets\ViewCount;
use yii\helpers\Html;
use yii\helpers\Url;

$typeLabels = Post::TYPE_LABELS;
$typeRoute = Post::TYPE_CONTROLLERS[$post->type] ?? null;
$primaryCate = $post->categories[0] ?? null;
$author = $post->user;

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
        <?php if ($typeRoute !== null): ?>
            <li class="breadcrumb-item"><?= Html::a(Html::encode($typeLabels[$post->type]), ['/' . $typeRoute . '/index']) ?></li>
        <?php endif; ?>
        <?php if ($primaryCate !== null): ?>
            <li class="breadcrumb-item"><?= Html::a(Html::encode($primaryCate->name), ['/site/cate', 'slug' => $primaryCate->slug]) ?></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?= Html::encode($displayTitle) ?></li>
    </ol>
</nav>

<?php if ($displayTitle !== ''): ?><h1 class="mb-3"><?= Html::encode($displayTitle) ?></h1><?php endif; ?>

<?php if ($author !== null): ?>
    <?php
    $displayName = trim((string) ($author->nickname ?: $author->username)) ?: '用户';
    $avatar = trim((string) $author->avatar);
    $bio = trim((string) $author->bio);
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $joinedAt = $author->created_at > 0 ? date('Y-n', (int) $author->created_at) : null;
    ?>
    <section class="content-detail-author" aria-label="作者">
        <div class="content-detail-author-body">
            <a class="content-detail-author-head" href="<?= Html::encode(Url::to(['/users/profile', 'id' => (int) $author->id])) ?>">
                <?php if ($avatar !== ''): ?>
                    <img class="content-detail-avatar" src="<?= Html::encode($avatar) ?>" alt="" width="44" height="44" loading="lazy">
                <?php else: ?>
                    <span class="content-detail-avatar content-detail-avatar-initial" aria-hidden="true"><?= Html::encode($initial) ?></span>
                <?php endif; ?>
                <span class="content-detail-author-name">
                    <span class="content-detail-author-title"><?= Html::encode($displayName) ?></span>
                    <span class="content-detail-author-sub">
                        <?= $authorCounts['total'] ?> 篇内容<?php if ($joinedAt !== null): ?> · <?= Html::encode($joinedAt) ?> 加入<?php endif; ?>
                    </span>
                </span>
            </a>
            <?php if ($bio !== ''): ?>
                <p class="content-detail-author-bio"><?= Html::encode($bio) ?></p>
            <?php endif; ?>
        </div>
        <div class="content-detail-author-actions">
            <?= FollowButton::widget(['user' => $author, 'showCount' => true]) ?>
            <?= Html::a('查看主页', ['/users/profile', 'id' => (int) $author->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
        </div>
    </section>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-3 text-body-secondary small">
    <span class="home-feed-type"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span>
    <?php if ($author === null): ?>
        <span>用户</span>
        <span>·</span>
    <?php endif; ?>
    <time datetime="<?= Html::encode(date('Y-m-d H:i:s', (int) $post->created_at)) ?>"><?= date('Y-m-d H:i:s', $post->created_at) ?></time>
    <?php if ((int) $post->status === Post::STATUS_DRAFT): ?><span>· 草稿</span><?php endif; ?>
    <?= ViewCount::widget(['post' => $post]) ?>
    <?= LikeButton::widget(['post' => $post]) ?>
    <?= FavoriteButton::widget(['postId' => (int) $post->id]) ?>
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
