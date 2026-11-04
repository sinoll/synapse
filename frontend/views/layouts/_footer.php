<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use common\models\Post;
use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$typeIcons = ['blog' => 'bi-journal-text', 'moment' => 'bi-chat-square-text', 'images' => 'bi-images', 'video' => 'bi-camera-reels'];
?>
<footer id="footer" class="site-footer">
    <div class="container">
        <div class="site-footer-cols">
            <div class="site-footer-brand">
                <span class="site-footer-logo">拾光</span>
                <p class="site-footer-tagline">拾取生活的时光</p>
                <p class="site-footer-desc">技术笔记、生活随想，以及值得留下来的瞬间。</p>
                <?= Html::a('<i class="bi bi-info-circle" aria-hidden="true"></i><span>关于拾光</span>', ['/site/about'], ['class' => 'site-footer-cta', 'encode' => false]) ?>
            </div>

            <nav class="site-footer-group" aria-label="内容类型">
                <h2 class="site-footer-title">内容</h2>
                <ul class="site-footer-list">
                    <li><?= Html::a('<span>全部</span>', ['/site/index'], ['encode' => false]) ?></li>
                    <?php foreach (Post::typeDefinitions() as $key => $definition): ?>
                        <li>
                            <?= Html::a(
                                '<i class="bi ' . $typeIcons[$key] . '" aria-hidden="true"></i><span>' . Html::encode($definition['label']) . '</span>',
                                ['/site/index', 'type' => $key],
                                ['encode' => false],
                            ) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <nav class="site-footer-group" aria-label="站点地图">
                <h2 class="site-footer-title">浏览</h2>
                <ul class="site-footer-list">
                    <li><?= Html::a('<i class="bi bi-diagram-3" aria-hidden="true"></i><span>分类总览</span>', ['/site/cate'], ['encode' => false]) ?></li>
                    <li><?= Html::a('<i class="bi bi-tags" aria-hidden="true"></i><span>标签云</span>', ['/site/tag'], ['encode' => false]) ?></li>
                    <li><?= Html::a('<i class="bi bi-calendar3" aria-hidden="true"></i><span>时间归档</span>', ['/site/archive'], ['encode' => false]) ?></li>
                    <li><?= Html::a('<i class="bi bi-search" aria-hidden="true"></i><span>搜索</span>', ['/search/index'], ['encode' => false]) ?></li>
                </ul>
            </nav>

            <nav class="site-footer-group" aria-label="账号与收藏">
                <h2 class="site-footer-title"><?php echo $identity === null ? '访问' : '我的' ?></h2>
                <ul class="site-footer-list">
                    <?php if ($identity === null): ?>
                        <li><?= Html::a('<span>登录</span>', ['/users/login'], ['encode' => false]) ?></li>
                        <li><?= Html::a('<span>注册</span>', ['/users/signup'], ['encode' => false]) ?></li>
                    <?php else: ?>
                        <li><?= Html::a('<span>我的内容</span>', ['/users/index'], ['encode' => false]) ?></li>
                        <li><?= Html::a('<span>我的收藏</span>', ['/users/favorite'], ['encode' => false]) ?></li>
                        <li><?= Html::a('<span>我的点赞</span>', ['/users/like'], ['encode' => false]) ?></li>
                        <?php if ($identity->isAdministrator()): ?>
                            <li><?= Html::a('<span>系统管理</span>', ['/manage/index'], ['encode' => false]) ?></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>

        <div class="site-footer-bottom">
            <span class="site-footer-copy">&copy; 2010 - <?= date('Y') ?> 家庭版权</span>
            <span class="site-footer-slogan">家庭的数字资产</span>
        </div>
    </div>
</footer>
