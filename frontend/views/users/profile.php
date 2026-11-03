<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User $author */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array{total: int, byType: array<string, int>} $counts */
/** @var string|null $activeType */

use common\models\Post;
use frontend\widgets\ContentPager;
use yii\helpers\Html;

$displayName = trim((string) ($author->nickname ?: $author->username)) ?: '用户';
$this->title = $activeType === null
    ? $displayName . ' 的主页'
    : $displayName . ' 的' . Post::TYPE_LABELS[$activeType];

$posts = $dataProvider->getModels();
$avatar = trim((string) $author->avatar);
$bio = trim((string) $author->bio);
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
$joinedAt = $author->created_at > 0 ? date('Y 年 n 月', (int) $author->created_at) : null;
?>

<div class="author-profile py-3 py-md-4">
    <header class="author-profile-head card border-0 overflow-hidden shadow-sm mb-4">
        <div class="author-profile-banner" aria-hidden="true"></div>
        <div class="card-body author-profile-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <?php if ($avatar !== ''): ?>
                    <img class="author-profile-avatar" src="<?= Html::encode($avatar) ?>" alt="" width="88" height="88">
                <?php else: ?>
                    <span class="author-profile-avatar author-profile-avatar-initial" aria-hidden="true"><?= Html::encode($initial) ?></span>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <h1 class="h4 mb-1"><?= Html::encode($displayName) ?></h1>
                    <?php if ($bio !== ''): ?>
                        <p class="author-profile-bio mb-1"><?= Html::encode($bio) ?></p>
                    <?php endif; ?>
                    <p class="text-body-secondary small mb-0">
                        共发布 <?= (int) $counts['total'] ?> 篇内容<?php if ($joinedAt !== null): ?> · <?= Html::encode($joinedAt) ?> 加入<?php endif; ?>
                    </p>
                </div>
            </div>

            <nav class="author-profile-chips mt-3" aria-label="内容类型筛选">
                <?= Html::a(
                    '全部<small class="author-profile-chip-count">' . (int) $counts['total'] . '</small>',
                    ['/users/profile', 'id' => (int) $author->id],
                    ['class' => 'author-profile-chip' . ($activeType === null ? ' active' : ''), 'encode' => false]
                ) ?>
                <?php foreach (Post::TYPE_LABELS as $type => $label): ?>
                    <?php $typeCount = (int) ($counts['byType'][$type] ?? 0); ?>
                    <?= Html::a(
                        Html::encode($label) . '<small class="author-profile-chip-count">' . $typeCount . '</small>',
                        ['/users/profile', 'id' => (int) $author->id, 'type' => $type],
                        ['class' => 'author-profile-chip' . ($activeType === $type ? ' active' : ''), 'encode' => false]
                    ) ?>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <?php if ($posts === []): ?>
        <div class="alert alert-light border">
            <?php if ($activeType === null): ?>
                这位作者还没有公开发布的内容。
            <?php else: ?>
                这位作者还没有公开发布的<?= Html::encode(Post::TYPE_LABELS[$activeType] ?? $activeType) ?>。
                <?= Html::a('查看全部内容', ['/users/profile', 'id' => (int) $author->id]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?= $this->render('@frontend/views/site/_cover_items', ['posts' => $posts]) ?>
        </div>
    <?php endif; ?>

    <?= ContentPager::widget([
        'pagination' => $dataProvider->getPagination(),
        'route' => ['/users/profile'],
        'params' => ['id' => (int) $author->id, 'type' => $activeType],
    ]) ?>
</div>
