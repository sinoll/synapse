<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

// 定义下拉子菜单
$identity = Yii::$app->user->identity;
$searchKeyword = Yii::$app->request->get('q');
$searchKeyword = is_string($searchKeyword) ? $searchKeyword : '';
$accountItems = [
    ['label' => '我的内容', 'url' => ['/users/index']],
    ['label' => '我的收藏', 'url' => ['/users/favorite']],
    ['label' => '我的点赞', 'url' => ['/users/like']],
];
if ($identity !== null && $identity->isAdministrator()) {
    $accountItems[] = ['label' => '系统管理', 'url' => ['/manage/index']];
}

$accountItems[] = '<div class="dropdown-divider"></div>';
$accountItems[] = [
    'label' => '退出登录',
    'url' => ['/users/logout'],
    'linkOptions' => [
        'data-method' => 'post',
        'class' => 'logout',
    ],
];

$menuItems = [
    [
        'label' => '关于',
        'url' => ['/site/about'],
    ],    
];

if ($identity === null) {
    $accountMenu = [
        [
            'label' => '登录',
            'url' => ['/users/login'],
        ],
        [
            'label' => '注册',
            'url' => ['/users/signup'],
        ],
    ];
} else {
    $accountMenu = [
        [
            'label' => Html::encode($identity->nickname ?: $identity->username),
            'items' => $accountItems,
            'dropdownOptions' => ['class' => 'dropdown-menu dropdown-menu-end'],
        ],
    ];
}

// 语言选择子菜单
$languageMenu = [
    [
        'label' => '语言',
        'items' => [
            ['label' => '简体中文', 'url' => '#', 'active' => true],
            ['label' => 'English', 'url' => '#'],
        ],
        'dropdownOptions' => ['class' => 'dropdown-menu dropdown-menu-end'],
    ],
];

?>

<header id="header">
    <?php NavBar::begin(
        [
            'brandLabel' => Yii::$app->name,
            'brandUrl' => Yii::$app->homeUrl,
            'options' => ['class' => 'navbar-expand-md navbar-light navbar-warm fixed-top shadow-sm']
        ],
    ) ?>
    <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 me-auto">
        <?= Nav::widget(
            [
                'options' => ['class' => 'navbar-nav'],
                'encodeLabels' => false,
                'activateParents' => true,
                'items' => $menuItems,
            ],
        ) ?>
        <?= Html::beginForm(['/search/index'], 'get', ['class' => 'nav-search input-group input-group-sm', 'role' => 'search']) ?>
            <input class="form-control" type="search" name="q" maxlength="60" autocomplete="off"
                value="<?= Html::encode($searchKeyword) ?>"
                placeholder="搜索..." aria-label="站内搜索关键词">
            <button class="btn btn-primary" type="submit" aria-label="搜索"><i class="bi bi-search" aria-hidden="true"></i></button>
        <?= Html::endForm() ?>
    </div>

    <div class="d-flex flex-column flex-md-row align-items-md-center gap-2">
        <?= Nav::widget(
            [
                'options' => ['class' => 'navbar-nav'],
                'encodeLabels' => false,
                'activateParents' => true,
                'items' => $languageMenu,
            ],
        ) ?>
        <?= Nav::widget(
            [
                'options' => ['class' => 'navbar-nav'],
                'encodeLabels' => false,
                'activateParents' => true,
                'items' => $accountMenu,
            ],
        ) ?>
        <?= Html::button(
            '🌙',
            [
                'id' => 'theme-toggle',
                'class' => 'btn btn-link nav-link fs-5',
                'aria-label' => '切换至深色主题',
            ],
        ) ?>
    </div>
    <?php NavBar::end() ?>
</header>
