<?php

declare(strict_types=1);

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * 收藏按钮的样式与交互脚本。
 *
 * 依赖 AppAsset 排在 site.css 之后，方便覆盖卡片默认样式；脚本用事件委托绑定，
 * 所以无限滚动追加进来的内容片段不需要重新注册。
 */
class FavoriteAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/favorite';
    public $css = [
        'css/favorite.css',
    ];
    public $js = [
        'js/favorite.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}
