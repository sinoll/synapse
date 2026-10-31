<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var yii\db\ActiveRecord|null $contentModel */
/** @var bool $canEdit */

use common\models\Post;
use yii\helpers\Html;
use yii\helpers\HtmlPurifier;

$this->registerCss(<<<'CSS'
.rich-content pre { overflow-x: auto; max-width: 100%; margin: 1rem 0; padding: 1rem 1.1rem; border: 1px solid var(--bs-border-color); border-radius: .65rem; background: var(--bs-tertiary-bg); color: var(--bs-body-color); font-family: var(--bs-font-monospace); font-size: .9em; line-height: 1.6; white-space: pre; }
.rich-content pre code { padding: 0; background: transparent; color: inherit; font: inherit; white-space: inherit; }
CSS);

$displayTitle = $post->title ?: ($post->type === Post::TYPE_MOMENT ? '动态' : '');
$this->title = $displayTitle;
$typeLabels = Post::TYPE_LABELS;
$typeControllers = Post::TYPE_CONTROLLERS;
$typeController = $typeControllers[$post->type] ?? null;
?>
<article class="content-view mx-auto py-4" style="max-width: 900px">
    <p class="mb-2"><span class="badge text-bg-light"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span></p>
    <?php if ($displayTitle !== ''): ?><h1><?= Html::encode($displayTitle) ?></h1><?php endif; ?>
    <p class="text-body-secondary">
        <?= Html::encode($post->user?->nickname ?: $post->user?->username ?: '用户') ?>
        · <?= Yii::$app->formatter->asDatetime($post->created_at) ?>
        <?php if ($post->status === Post::STATUS_DRAFT): ?> · 草稿<?php endif; ?>
    </p>

    <div class="border-top border-bottom py-4 my-4">
        <?php if ($contentModel === null): ?>
            <p class="text-body-secondary mb-0">该内容的详细信息暂不可用。</p>
        <?php elseif ($post->type === Post::TYPE_BLOG): ?>
            <?php if ($contentModel->summary !== null && $contentModel->summary !== ''): ?>
                <p class="lead"><?= Html::encode($contentModel->summary) ?></p>
            <?php endif; ?>
            <div class="rich-content text-break"><?= HtmlPurifier::process((string) $contentModel->content, ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title],pre,code']) ?></div>
        <?php elseif ($post->type === Post::TYPE_MOMENT): ?>
            <div class="rich-content text-break"><?= HtmlPurifier::process((string) $contentModel->content, ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title]']) ?></div>
        <?php elseif ($post->type === Post::TYPE_IMAGES): ?>
            <?php if ($contentModel->alt_text): ?>
                <p><?= Html::encode($contentModel->alt_text) ?></p>
            <?php endif; ?>
            <div class="image-collection-view d-flex flex-column gap-4">
                <?php foreach ($post->images as $image): ?>
                    <figure class="mb-0">
                        <img src="<?= Html::encode($image->file_path) ?>" alt="<?= Html::encode($image->alt_text ?: $post->title) ?>" class="img-fluid w-100 rounded shadow-sm" loading="lazy">
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php elseif ($post->type === Post::TYPE_SHORT_VIDEO): ?>
            <?php if ($contentModel->description): ?>
                <div class="text-break mb-3" style="white-space: pre-wrap"><?= Html::encode($contentModel->description) ?></div>
            <?php endif; ?>
            <video class="w-100 rounded shadow-sm mb-3" controls playsinline preload="metadata"
                   src="<?= Html::encode($contentModel->video_path) ?>"
                   <?php if ($contentModel->cover_path): ?>poster="<?= Html::encode($contentModel->cover_path) ?>"<?php endif; ?>></video>
            <?php if ($contentModel->duration_seconds !== null): ?>
                <p class="mb-0"><span class="fw-semibold">时长：</span><?= Html::encode((string) $contentModel->duration_seconds) ?> 秒</p>
            <?php endif; ?>
            <?php if ($contentModel->videoTypeLabel !== null): ?>
                <p class="mb-0"><span class="fw-semibold">方向：</span><?= Html::encode($contentModel->videoTypeLabel) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <?= Html::a('返回内容列表', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
        <?php if ($canEdit && $typeController !== null): ?>
            <?= Html::a('编辑', ['/' . $typeController . '/update', 'id' => $post->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('删除', ['/' . $typeController . '/delete', 'id' => $post->id], [
                'class' => 'btn btn-outline-danger',
                'data' => ['method' => 'post', 'confirm' => '确定删除这条内容吗？'],
            ]) ?>
        <?php endif; ?>
    </div>
</article>
