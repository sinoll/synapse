<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array $definitions */
/** @var array $typeCounts */
/** @var string|null $activeType */

use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$profileName = $identity !== null ? ($identity->nickname ?: $identity->username) : '';
$profileInitial = $profileName !== '' ? mb_strtoupper(mb_substr($profileName, 0, 1)) : '拾';
?>
<div class="home-rail-card home-rail-user">
    <?php if ($identity?->avatar): ?>
        <img class="home-rail-avatar" src="<?= Html::encode($identity->avatar) ?>" alt="<?= Html::encode($profileName) ?>">
    <?php else: ?>
        <span class="home-rail-avatar home-rail-avatar-fallback" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
    <?php endif; ?>
    <?php if ($identity === null): ?>
        <div class="home-rail-user-text">
            <strong>欢迎来到<?= Html::encode(Yii::$app->name) ?></strong>
            <span class="text-body-secondary">登录后即可分享内容</span>
        </div>
        <?= Html::a('登录', ['/users/login'], ['class' => 'btn btn-primary btn-sm w-100 mt-1']) ?>
    <?php else: ?>
        <strong class="home-rail-name text-truncate"><?= Html::encode($profileName) ?></strong>
        <div class="home-rail-actions d-grid gap-1 w-100 mt-1">
            <div class="dropdown">
                <button class="btn btn-primary btn-sm w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">发布内容</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($definitions as $key => $definition): ?>
                        <li><?= Html::a('发布' . $definition['label'], ['/' . $key . '/create'], ['class' => 'dropdown-item']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?= Html::a('我的内容', ['/users/index'], ['class' => 'home-rail-btn']) ?>
        </div>
    <?php endif; ?>
</div>

<?php
$typeIcons = ['blog' => 'bi-journal-text', 'moment' => 'bi-chat-square-text', 'images' => 'bi-images', 'video' => 'bi-camera-reels'];
?>
<nav class="home-rail-card" aria-label="内容分类">    
    <ul class="home-rail-list">
        <li>
            <?= Html::a(
                '<i class="bi bi-collection" aria-hidden="true"></i><span class="home-rail-label">全部</span><span class="home-rail-count">' . array_sum($typeCounts) . '</span>',
                ['/'],
                ['class' => 'home-rail-link' . ($activeType === null ? ' active' : ''), 'encode' => false, 'aria-current' => $activeType === null ? 'page' : null],
            ) ?>
        </li>
        <?php foreach ($definitions as $key => $definition): ?>
            <li>
                <?= Html::a(
                    '<i class="bi ' . $typeIcons[$key] . '" aria-hidden="true"></i><span class="home-rail-label">' . Html::encode($definition['label']) . '</span><span class="home-rail-count">' . (int) ($typeCounts[$definition['postType']] ?? 0) . '</span>',
                    ['/', 'type' => $key],
                    ['class' => 'home-rail-link' . ($activeType === $key ? ' active' : ''), 'encode' => false, 'aria-current' => $activeType === $key ? 'page' : null],
                ) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
