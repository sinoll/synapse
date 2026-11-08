<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = '关于拾光';
$this->params['meta_description'] = '拾光——记录技术与生活的内容站点。';
$this->params['meta_keywords'] = '拾光, 家庭内容中心';
?>
<div class="row justify-content-center">
    <div class="site-about col-12 col-md-10 col-lg-8 py-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
                <li class="breadcrumb-item active" aria-current="page">关于</li>
            </ol>
        </nav>

        <h1 class="display-6 fw-semibold mb-4">关于拾光</h1>

        <p class="lead text-body-secondary mb-4">
            <b>拾光</b>，拾取生活中的光。
            <br />
            这里记录技术笔记、生活随想以及值得留下的瞬间，
            内容涵盖博客、图片、动态与短视频。 
        </p>
        

        <h2 class="h4 mb-3">开发方式</h2>
        <p class="mb-2">
            本站使用
            <a href="#" target="_blank" rel="noopener"><strong>Qoder CN</strong></a>
            作为 AI 编程助手完成开发与维护——从数据库迁移、页面重构到响应式布局调整，
            均由 Qoder 智能体使用 <strong>Qwen3.8-Flash</strong>和<strong>DeepSeek-Flash</strong> 大模型 <strong>免费版</strong> 协作实现。
        </p>        

        <br />
        <h2 class="h4 mb-3">后续</h2>
        <p class="mb-2">
            正在使用
            <a href="#" target="_blank" rel="noopener"><strong>Visual Studio Code</strong></a>
            作为 AI 编程助手完成 <strong>restful</strong>接口 以及 <strong>Android App</strong> 的开发。
        </p>
    </div>
</div>
