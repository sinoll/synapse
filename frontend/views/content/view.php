<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var yii\db\ActiveRecord|null $contentModel */
/** @var bool $canEdit */

use common\models\Post;
use yii\helpers\Html;

$this->title = $post->title;
$typeLabels = [
    Post::TYPE_BLOG => '博客',
    Post::TYPE_IMAGES => '图片',
    Post::TYPE_MOMENT => '动态',
    Post::TYPE_SHORT_VIDEO => '短视频',
    Post::TYPE_SHARED_FILE => '文件分享',
];
?>
<article class="content-view mx-auto py-4" style="max-width: 900px">
    <p class="mb-2"><span class="badge text-bg-light"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span></p>
    <h1><?= Html::encode($post->title) ?></h1>
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
            <div class="text-break" style="white-space: pre-wrap"><?= Html::encode($contentModel->content) ?></div>
        <?php elseif ($post->type === Post::TYPE_MOMENT): ?>
            <div class="text-break" style="white-space: pre-wrap"><?= Html::encode($contentModel->content) ?></div>
            <?php if ($contentModel->image_paths): ?>
                <p class="mt-3 mb-1 fw-semibold">图片路径</p>
                <pre class="mb-0"><?= Html::encode((string) $contentModel->image_paths) ?></pre>
            <?php endif; ?>
        <?php elseif ($post->type === Post::TYPE_IMAGES): ?>
            <?php if ($contentModel->alt_text): ?>
                <p><?= Html::encode($contentModel->alt_text) ?></p>
            <?php endif; ?>
            <p class="mb-0"><span class="fw-semibold">图片路径：</span><?= Html::encode($contentModel->file_path) ?></p>
        <?php elseif ($post->type === Post::TYPE_SHORT_VIDEO): ?>
            <?php if ($contentModel->description): ?>
                <div class="text-break mb-3" style="white-space: pre-wrap"><?= Html::encode($contentModel->description) ?></div>
            <?php endif; ?>
            <p class="mb-1"><span class="fw-semibold">视频路径：</span><?= Html::encode($contentModel->video_path) ?></p>
            <?php if ($contentModel->cover_path): ?>
                <p class="mb-1"><span class="fw-semibold">封面路径：</span><?= Html::encode($contentModel->cover_path) ?></p>
            <?php endif; ?>
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
                <dt class="col-sm-3">文件路径</dt>
                <dd class="col-sm-9 text-break"><?= Html::encode($contentModel->file_path) ?></dd>
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
        <?php if ($canEdit): ?>
            <?= Html::a('编辑', ['update', 'id' => $post->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('删除', ['delete', 'id' => $post->id], [
                'class' => 'btn btn-outline-danger',
                'data' => ['method' => 'post', 'confirm' => '确定删除这条内容吗？'],
            ]) ?>
        <?php endif; ?>
    </div>
</article>
