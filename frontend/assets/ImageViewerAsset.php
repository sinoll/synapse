<?php

declare(strict_types=1);

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * 信息流图片的「看图模式」：点击缩略图全屏看大图，可左右切换、点击放大。
 *
 * 脚本用事件委托绑定，无限滚动追加进来的卡片里的图片一样生效；遮罩由脚本在
 * 首次点击时才插入 body，视图不需要预留占位标签。
 */
final class ImageViewerAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/imageviewer';
    public $css = [
        'css/imageviewer.css',
    ];
    public $js = [
        'js/imageviewer.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}
