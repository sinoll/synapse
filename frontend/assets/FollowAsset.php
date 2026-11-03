<?php

declare(strict_types=1);

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * 关注按钮的样式与交互脚本。
 *
 * 与 LikeAsset 同样依赖 AppAsset 排在 site.css 之后，脚本用事件委托绑定，
 * 无限滚动追加进来的内容片段里的按钮一样生效。
 */
class FollowAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/follow';
    public $css = [
        'css/follow.css',
    ];
    public $js = [
        'js/follow.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}
