<?php

declare(strict_types=1);

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * 分享按钮的样式与交互脚本。
 *
 * 与 LikeAsset 同样依赖 AppAsset 排在 site.css 之后，脚本用事件委托绑定，
 * 无限滚动追加进来的内容片段里的按钮一样生效。
 */
class ShareAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/share';
    public $css = [
        'css/share.css',
    ];
    public $js = [
        'js/share.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}