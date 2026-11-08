<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

declare(strict_types=1);

namespace frontend\assets;

use yii\bootstrap5\BootstrapAsset;
use yii\bootstrap5\BootstrapIconAsset;
use yii\web\AssetBundle;
use yii\web\YiiAsset;

/**
 * Main frontend application asset bundle.
 */
class AppAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets';
    public $css = [
        'css/site.css',
        'css/app.css',
    ];
    public $js = [
        'js/app.js',
    ];
    public $publishOptions = [
        'only' => ['css/site.css', 'css/app.css', 'js/app.js'],
    ];
    public $depends = [
        YiiAsset::class,
        BootstrapAsset::class,
        BootstrapIconAsset::class,
        ColorModeAsset::class,
    ];
}
