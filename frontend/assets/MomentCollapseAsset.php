<?php

declare(strict_types=1);

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * 动态卡片的「展开 / 收起」：正文默认折叠成几行，超出才出现开关，微博那种读法。
 *
 * 折叠状态由脚本测量后加类，所以禁用 JS 时正文完整展示；卡片是无限滚动追加进来的，
 * 除了事件委托还要 MutationObserver 补测新卡片，窗口尺寸变化时重新测量。
 */
final class MomentCollapseAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/momentcollapse';
    public $js = [
        'js/momentcollapse.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}
