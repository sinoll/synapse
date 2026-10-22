<?php

declare(strict_types=1);

use yii\bootstrap5\Html;

/** @var string $activeSection */
$items = [
    ['id' => 'site-name', 'label' => '站点名称', 'description' => '修改站点显示名称', 'icon' => '⌂'],
    ['id' => 'users', 'label' => '用户管理', 'description' => '查看和添加用户', 'icon' => '♙'],
    ['id' => 'node', 'label' => '开启节点', 'description' => '管理内容节点状态', 'icon' => '◉'],
];
?>
<div class="manage-sidebar card border-0 shadow-sm">
    <div class="manage-sidebar-heading">
        <span class="small text-body-secondary text-uppercase">控制面板</span>
        <strong class="d-block mt-1">系统管理</strong>
    </div>
    <nav class="manage-nav" aria-label="系统管理菜单">
        <?php foreach ($items as $item): ?>
            <?= Html::a(
                '<span class="manage-nav-icon" aria-hidden="true">' . Html::encode($item['icon']) . '</span>'
                . '<span><span class="d-block fw-semibold">' . Html::encode($item['label']) . '</span>'
                . '<small>' . Html::encode($item['description']) . '</small></span>',
                ['/manage/index', 'section' => $item['id']],
                ['class' => 'manage-nav-item' . ($activeSection === $item['id'] ? ' active' : '')],
            ) ?>
        <?php endforeach; ?>
    </nav>
    <?= Html::a('返回站点', ['/site/index'], ['class' => 'manage-sidebar-back']) ?>
</div>
