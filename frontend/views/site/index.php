<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Content sharing';
$categories = [
    ['blog', 'Blog', 'Long-form posts and articles.'],
    ['images', 'Photo albums', 'Publish image collections.'],
    ['moment', 'Moments', 'Short updates and thoughts.'],
    ['video', 'Short videos', 'Publish short video content.'],
    ['file', 'Shared files', 'Organize and share files.'],
];
?>
<section class="py-5 mb-4">
    <div class="p-5 rounded-4 bg-dark text-white">
        <p class="text-uppercase small opacity-75 mb-2">Share · Create · Discover</p>
        <h1 class="display-5 fw-bold">A home for your content</h1>
        <p class="lead opacity-75 mb-0">Publish posts, moments, videos, albums, and files.</p>
    </div>
</section>
<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h2 class="h3 mb-1">Explore content</h2>
        <p class="text-body-secondary mb-0">Choose a content type to get started.</p>
    </div>
</div>
<div class="row g-3">
    <?php foreach ($categories as [$type, $label, $description]): ?>
        <div class="col-md-6 col-xl-4">
            <article class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="h5"><?= Html::encode($label) ?></h3>
                    <p class="text-body-secondary"><?= Html::encode($description) ?></p>
                    <?= Html::a('Create ' . Html::encode($label), ['/content/create', 'type' => $type], ['class' => 'btn btn-outline-primary']) ?>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>
