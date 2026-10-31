<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = '关于拾光';
$this->params['meta_description'] = '拾光——记录技术与生活的个人内容站点。';
$this->params['meta_keywords'] = '拾光, 博客, yii2, php';
?>
<div class="site-about mx-auto py-4" style="max-width: 760px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
            <li class="breadcrumb-item active" aria-current="page">关于</li>
        </ol>
    </nav>

    <h1 class="display-6 fw-semibold mb-4">关于拾光</h1>

    <p class="lead text-body-secondary mb-4">
        拾光，拾取生活中的光。这里记录技术笔记、生活随想与值得留下的瞬间，
        内容涵盖博客、图片、动态与短视频。
    </p>

    <h2 class="h4 mb-3">技术与工具</h2>
    <ul class="mb-4">
        <li>后端框架：<a href="https://www.yiiframework.com/" target="_blank" rel="noopener">Yii 2</a>（PHP 高级模板，前端 / 后台 / 控制台多应用架构）</li>
        <li>前端样式：<a href="https://getbootstrap.cn/" target="_blank" rel="noopener">Bootstrap 5</a> 响应式布局</li>
        <li>数据存储：MySQL，多态内容模型（post 主表 + 各类型子表 + 标签 / 分类关联）</li>
        <li>历史内容：由旧博客系统数据迁移导入（文章、标签、分类）</li>
    </ul>

    <h2 class="h4 mb-3">开发方式</h2>
    <p class="mb-2">
        本站使用
        <a href="https://qoder.com.cn/" target="_blank" rel="noopener"><strong>Qoder CN</strong></a>
        作为 AI 编程助手完成开发与维护——从数据库迁移、页面重构到响应式布局调整，
        均由 Qoder 智能体在本地开发环境中协作实现。
    </p>
    <p class="text-body-secondary mb-4">
        Qoder CN 是面向开发者的智能编程工具，支持多端协作与上下文感知的代码生成，让个人项目也能保持高效的迭代节奏。
    </p>

    <?= Html::a('返回首页，看看内容 →', ['/site/index'], ['class' => 'btn btn-primary']) ?>
</div>
