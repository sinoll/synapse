<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

// 定义下拉子菜单
$identity = Yii::$app->user->identity;
$accountItems = [
    ['label' => '用户中心', 'url' => ['/users/index']],
    ['label' => '内容管理', 'url' => ['/content/index']],
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
        'label' => '首页',
        'url' => ['/site/index'],
    ],
    [
        'label' => 'Blog',
        'url' => ['/blog/index'],
    ],
    [
        'label' => '动态',
        'url' => ['/moment/index'],
    ],
    [
        'label' => '图集',
        'url' => ['/images/index'],
    ],
    [
        'label' => '短视频',
        'url' => ['/video/index'],
    ],
    
];

if ($identity === null) {
    $accountMenu = [
        [
            'label' => '登录',
            'url' => ['/users/login'],
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

// 关于菜单 放在 语言 左面  和 几个下拉菜单一样靠右对齐
$aboutItems = [
    'label' => '关于',
    'url' => ['/site/about'],
];


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
    <?= Nav::widget(
        [
            'options' => ['class' => 'navbar-nav me-auto'],
            'encodeLabels' => false,
            'activateParents' => true,
            'items' => $menuItems,
        ],
    ) ?>

    <div class="d-flex flex-column flex-md-row align-items-md-center gap-2">
        <?= Nav::widget(
            [
                'options' => ['class' => 'navbar-nav'],
                'encodeLabels' => false,
                'items' => [$aboutItems],
            ],
        ) ?>
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
