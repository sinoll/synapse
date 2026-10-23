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
$typeLabels = [
    Post::TYPE_BLOG => '博客',
    Post::TYPE_IMAGES => '图片',
    Post::TYPE_MOMENT => '动态',
    Post::TYPE_SHORT_VIDEO => '短视频',
    Post::TYPE_SHARED_FILE => '文件分享',
];
$typeControllers = [
    Post::TYPE_BLOG => 'blog',
    Post::TYPE_IMAGES => 'images',
    Post::TYPE_MOMENT => 'moment',
    Post::TYPE_SHORT_VIDEO => 'video',
    Post::TYPE_SHARED_FILE => 'file',
];
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
            <?php if ($contentModel->image_paths): ?>
                <p class="mt-3 mb-1 fw-semibold">图片路径</p>
                <pre class="mb-0"><?= Html::encode((string) $contentModel->image_paths) ?></pre>
            <?php endif; ?>
        <?php elseif ($post->type === Post::TYPE_IMAGES): ?>
            <?php if ($contentModel->alt_text): ?>
                <p><?= Html::encode($contentModel->alt_text) ?></p>
            <?php endif; ?>
            <div class="image-collection-view row g-3">
                <?php foreach ($post->images as $image): ?>
                    <figure class="col-12 col-sm-6 col-lg-4 mb-0">
                        <img src="<?= Html::encode($image->file_path) ?>" alt="<?= Html::encode($image->alt_text ?: $post->title) ?>" class="img-fluid rounded shadow-sm" loading="lazy">
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
        <?php else: ?>
            <?php if ($contentModel->description): ?>
                <div class="text-break mb-3" style="white-space: pre-wrap"><?= Html::encode($contentModel->description) ?></div>
            <?php endif; ?>
            <dl class="row mb-0">
                <dt class="col-sm-3">文件名</dt>
                <dd class="col-sm-9"><?= Html::encode($contentModel->original_name) ?></dd>
                <dt class="col-sm-3">下载</dt>
                <dd class="col-sm-9"><?= Html::a('下载文件', ['/content/download', 'id' => $post->id], ['class' => 'btn btn-primary btn-sm']) ?></dd>
                <?php if ($contentModel->mime_type): ?>
                    <dt class="col-sm-3">类型</dt>
                    <dd class="col-sm-9"><?= Html::encode($contentModel->mime_type) ?></dd>
                <?php endif; ?>
                <dt class="col-sm-3">大小</dt>
                <dd class="col-sm-9"><?= Yii::$app->formatter->asShortSize($contentModel->size_bytes) ?></dd>
            </dl>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <?= Html::a('返回内容列表', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
        <?php if ($canEdit && $typeController !== null): ?>
            <?= Html::a('编辑', ['/' . $typeController . '/update', 'id' => $post->type === Post::TYPE_SHARED_FILE ? $post->sharedFile->id : $post->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('删除', ['/' . $typeController . '/delete', 'id' => $post->type === Post::TYPE_SHARED_FILE ? $post->sharedFile->id : $post->id], [
                'class' => 'btn btn-outline-danger',
                'data' => ['method' => 'post', 'confirm' => '确定删除这条内容吗？'],
            ]) ?>
        <?php endif; ?>
    </div>
</article>
