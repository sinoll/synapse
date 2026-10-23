<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = '用户中心';
$user = Yii::$app->user->identity;
$isGuest = $user === null;
$displayName = $isGuest ? '游客' : ($user->nickname ?: $user->username);

$navigation = [
    ['label' => '用户首页', 'description' => '查看账号概览', 'icon' => '⌂', 'url' => ['/users/index'], 'active' => true],
    ['label' => '个人资料设置', 'description' => '修改昵称与头像', 'icon' => '⚙', 'url' => ['/users/settings']],
    ['label' => '修改密码', 'description' => '更新登录密码', 'icon' => '🔒', 'url' => ['/users/change-password']],
];

$publishItems = [
    ['label' => '博客文章', 'type' => 'blog', 'icon' => '✎'],
    ['label' => '图片集', 'type' => 'images', 'icon' => '▧'],
    ['label' => '生活动态', 'type' => 'moment', 'icon' => '●'],
    ['label' => '短视频', 'type' => 'video', 'icon' => '▶'],
    ['label' => '文件分享', 'type' => 'file', 'icon' => '↗'],
];
?>

<div class="user-center py-3 py-md-4">
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3">
            <div class="user-center-sidebar card">
                <div class="user-center-profile">
                    <div class="user-center-avatar" aria-hidden="true"><?= Html::encode(mb_strtoupper(mb_substr($displayName, 0, 1))) ?></div>
                    <div class="min-width-0">
                        <div class="fw-semibold text-truncate"><?= Html::encode($displayName) ?></div>
                        <div class="small text-body-secondary">个人工作台</div>
                    </div>
                </div>
                <nav class="user-center-nav" aria-label="用户中心导航">
                    <?php foreach ($navigation as $item): ?>
                        <?= Html::a(
                            '<span class="user-center-nav-icon" aria-hidden="true">' . Html::encode($item['icon']) . '</span>'
                            . '<span><span class="d-block">' . Html::encode($item['label']) . '</span><small>' . Html::encode($item['description']) . '</small></span>',
                            $item['url'],
                            ['class' => 'user-center-nav-item' . (!empty($item['active']) ? ' active' : '')]
                        ) ?>
                    <?php endforeach; ?>
                </nav>
                <?= Html::a('返回首页', ['/site/index'], ['class' => 'user-center-back-link']) ?>
            </div>
        </aside>

        <section class="col-lg-9" aria-labelledby="user-center-title">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="user-center-eyebrow mb-2">PERSONAL WORKSPACE</p>
                    <h1 id="user-center-title" class="h2 mb-2">欢迎回来，<?= Html::encode($displayName) ?></h1>
                    <p class="text-body-secondary mb-0">在这里管理你的账号设置和内容分享。</p>
                </div>
                <?php if (!$isGuest): ?>
                    <?= Html::a('管理我的内容', ['/content/index'], ['class' => 'btn btn-primary']) ?>
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4"><div class="user-center-stat card h-100"><span class="user-center-stat-icon">◉</span><span class="small text-body-secondary">账号状态</span><strong>正常使用</strong></div></div>
                <div class="col-md-4"><div class="user-center-stat card h-100"><span class="user-center-stat-icon">▤</span><span class="small text-body-secondary">内容管理</span><strong>查看我的发布</strong></div></div>
                <div class="col-md-4"><div class="user-center-stat card h-100"><span class="user-center-stat-icon">✦</span><span class="small text-body-secondary">快速操作</span><strong>开始创作</strong></div></div>
            </div>

            <div class="card user-center-panel">
                <div class="card-body p-4 p-md-5">
                    <div class="mb-4"><h2 class="h4 mb-2">发布内容</h2><p class="text-body-secondary mb-0">选择一种内容类型，开始记录和分享。</p></div>
                    <div class="row g-3">
                        <?php foreach ($publishItems as $item): ?>
                            <div class="col-sm-6 col-xl-4">
                                <?= Html::a(
                                    '<span class="user-center-publish-icon" aria-hidden="true">' . Html::encode($item['icon']) . '</span><span><strong class="d-block">发布' . Html::encode($item['label']) . '</strong><small class="text-body-secondary">创建新的内容</small></span>',
                                    ['/' . $item['type'] . '/create'],
                                    ['class' => 'user-center-publish-item']
                                ) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
