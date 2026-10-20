<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = '内容分享';
$isGuest = Yii::$app->user->isGuest;
$categories = [
    ['blog', '博客文章', '记录经验、想法和值得留存的长篇内容。', '✎'],
    ['images', '图片集', '整理并分享图片与相关说明。', '▧'],
    ['moment', '生活动态', '用简短文字记录此刻的状态和灵感。', '◉'],
    ['video', '短视频', '分享视频、封面和补充说明。', '▶'],
    ['file', '文件分享', '整理资源，并按需要设置访问范围。', '↗'],
];
?>
<section class="home-hero py-4 py-lg-5 mb-4 mb-lg-5">
    <div class="hero-warm p-4 p-md-5 rounded-4 text-white shadow-sm">
        <p class="text-uppercase small fw-semibold opacity-75 mb-2">记录 · 分享 · 发现</p>
        <h1 class="display-5 fw-bold mb-3">让每一份内容都有自己的位置</h1>
        <p class="lead opacity-75 mb-4">在这里写文章、记动态、分享图片、视频和文件。</p>        
    </div>
</section>

<div id="content-types" class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-2 mb-3">
    <div>
        <h2 class="h3 mb-1">选择一种内容形式</h2>
        <p class="text-body-secondary mb-0">每种内容都有专属的创建页面和编辑工具。</p>
    </div>
    <?php if (!$isGuest): ?>
        <?= Html::a('进入内容中心', ['/content/index'], ['class' => 'btn btn-outline-primary']) ?>
    <?php endif; ?>
</div>

<div class="row g-3 g-lg-4">
    <?php foreach ($categories as [$type, $label, $description, $icon]): ?>
        <div class="col-md-6 col-xl-4">
            <article class="card content-type-card h-100 shadow-sm">
                <div class="card-body d-flex flex-column p-4">
                    <span class="content-type-icon mb-3" aria-hidden="true"><?= Html::encode($icon) ?></span>
                    <h3 class="h5 mb-2"><?= Html::encode($label) ?></h3>
                    <p class="text-body-secondary flex-grow-1"><?= Html::encode($description) ?></p>
                    <?= Html::a($isGuest ? '登录后创建' : '创建' . $label, $isGuest ? ['/site/login'] : ['/' . $type . '/create'], ['class' => 'btn btn-outline-primary align-self-start']) ?>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>
