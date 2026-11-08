<?php

declare(strict_types=1);

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * @ 提及输入联想的样式与脚本。
 *
 * 挂在带 data-mention 属性的 textarea / contenteditable 上，
 * 输入 @ 时向 data-mention-url 拉取候选用户。
 */
class MentionAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/mention';
    public $css = [
        'css/mention.css',
    ];
    public $js = [
        'js/mention.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}