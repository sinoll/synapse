<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

$items = [
    [
        'label' => '首页',
        'url' => ['/site/index'],
    ],        
    [
        'label' => '系统管理',
        'url' => ['/manage/index'],
        'visible' => !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdministrator(),
    ],
    [
        'label' => '用户中心',
        'url' => ['/users/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => '文件',
        'url' => ['/file/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => '登录',
        'url' => ['/users/login'],
        'visible' => Yii::$app->user->isGuest,
    ],
    [
        'label' => '退出登录（' . Html::encode(Yii::$app->user->identity?->username) . '）',
        'url' => ['/users/logout'],
        'linkOptions' => [
            'data-method' => 'post',
            'class' => 'logout',
        ],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => '关于',
        'url' => ['/site/about'],
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
            'items' => $items,
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
    <?php NavBar::end() ?>
</header>
