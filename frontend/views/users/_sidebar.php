<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $active 当前高亮的导航项 key。 */

use yii\helpers\Html;

$user = Yii::$app->user->identity;
$isGuest = $user === null;
$displayName = $isGuest ? '游客' : ($user->nickname ?: $user->username);

$navigation = [
    ['key' => 'index', 'label' => '用户首页', 'description' => '查看账号概览', 'icon' => 'bi-house-door', 'url' => ['/users/index']],
    ['key' => 'message', 'label' => '我的消息', 'description' => '私信与提及提醒', 'icon' => 'bi-envelope', 'url' => ['/users/message']],
    ['key' => 'favorite', 'label' => '我的收藏', 'description' => '回看收藏的内容', 'icon' => 'bi-star', 'url' => ['/users/favorite']],
    ['key' => 'like', 'label' => '我的点赞', 'description' => '回看点过赞的内容', 'icon' => 'bi-heart', 'url' => ['/users/like']],
    ['key' => 'followers', 'label' => '我的粉丝', 'description' => '关注你的人', 'icon' => 'bi-people', 'url' => ['/users/followers']],
    ['key' => 'following', 'label' => '我的关注', 'description' => '你关注的作者', 'icon' => 'bi-person-check', 'url' => ['/users/following']],
    ['key' => 'settings', 'label' => '个人资料设置', 'description' => '修改昵称与头像', 'icon' => 'bi-gear', 'url' => ['/users/settings']],
    ['key' => 'change-password', 'label' => '修改密码', 'description' => '更新登录密码', 'icon' => 'bi-lock', 'url' => ['/users/change-password']],
];
?>
<div class="card moment-side-card border-0 shadow-sm mb-3">
    <div class="moment-profile-cover" aria-hidden="true"></div>
    <div class="card-body text-center moment-profile-body pb-3">
        <?php if (!$isGuest && $user->avatar): ?>
            <img class="moment-avatar moment-avatar-lg mx-auto" src="<?= Html::encode($user->avatar) ?>" alt="<?= Html::encode($displayName) ?>">
        <?php else: ?>
            <span class="moment-avatar moment-avatar-fallback moment-avatar-lg mx-auto" aria-hidden="true"><?= Html::encode(mb_strtoupper(mb_substr($displayName, 0, 1))) ?></span>
        <?php endif; ?>
        <h2 class="h5 mt-2 mb-0"><?= Html::encode($displayName) ?></h2>
        <p class="text-body-secondary small mb-0">个人工作台</p>
        <div class="d-grid gap-2 mt-3">
            <?= Html::a('<i class="bi bi-arrow-left me-1" aria-hidden="true"></i>返回首页', ['/site/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
</div>

<div class="card moment-side-card border-0 shadow-sm mb-3">
    <div class="card-header moment-side-heading">用户中心</div>
    <nav class="list-group list-group-flush moment-nav" aria-label="用户中心导航">
        <?php foreach ($navigation as $item): ?>
            <?php $isActive = $item['key'] === $active; ?>
            <?= Html::a(
                '<i class="bi ' . Html::encode($item['icon']) . ' me-2" aria-hidden="true"></i>'
                . '<span><span class="d-block">' . Html::encode($item['label']) . '</span><small class="text-body-secondary">' . Html::encode($item['description']) . '</small></span>',
                $item['url'],
                [
                    'class' => 'list-group-item list-group-item-action' . ($isActive ? ' active' : ''),
                    'encode' => false,
                    'aria-current' => $isActive ? 'page' : null,
                ]
            ) ?>
        <?php endforeach; ?>
    </nav>
</div>
