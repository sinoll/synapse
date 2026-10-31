<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var common\models\Image[] $images */

use yii\helpers\Html;

$this->title = $post->title;
$galleryDescription = trim((string) ($images[0]->alt_text ?? ''));
$webroot = Yii::getAlias('@webroot');
$formatFileSize = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
};
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-11 col-xxl-10">
        <article class="content-view content-view-images py-3 py-md-4">
            <?= $this->render('@frontend/views/site/_view_header', ['post' => $post, 'displayTitle' => $post->title]) ?>

            <?php if ($galleryDescription !== ''): ?>
                <p class="text-center text-body-secondary mb-4"><?= Html::encode($galleryDescription) ?></p>
            <?php endif; ?>

            <div class="image-gallery d-flex flex-column gap-4">
                <?php foreach ($images as $image): ?>
                    <?php
                    $imagePath = (string) $image->file_path;
                    $imageAlt = (string) ($image->alt_text ?: $post->title);
                    $imageTitle = trim((string) $image->title);
                    $imageMeta = [];
                    $imageFile = $webroot . '/' . ltrim($imagePath, '/');
                    if ($imagePath !== '' && is_file($imageFile)) {
                        $imageSize = @getimagesize($imageFile);
                        if (is_array($imageSize)) {
                            $imageMeta[] = $imageSize[0] . ' × ' . $imageSize[1];
                        }
                        $imageBytes = filesize($imageFile);
                        if ($imageBytes !== false) {
                            $imageMeta[] = $formatFileSize($imageBytes);
                        }
                    }
                    $imageMetaText = implode(' · ', $imageMeta);
                    ?>
                    <figure class="mb-0">
                        <a href="<?= Html::encode($imagePath) ?>"
                           class="d-block"
                           data-image-preview
                           data-bs-toggle="modal"
                           data-bs-target="#imagePreviewModal"
                           data-image-src="<?= Html::encode($imagePath) ?>"
                           data-image-alt="<?= Html::encode($imageAlt) ?>"
                           data-image-title="<?= Html::encode($imageTitle !== '' ? $imageTitle : $this->title) ?>"
                           data-image-name="<?= Html::encode(basename($imagePath)) ?>"
                           data-image-meta="<?= Html::encode($imageMetaText) ?>">
                            <img src="<?= Html::encode($imagePath) ?>"
                                 alt="<?= Html::encode($imageAlt) ?>"
                                 class="img-fluid w-100 rounded shadow-sm" loading="lazy">
                        </a>
                        <br />
                    </figure>
                <?php endforeach; ?>
            </div>

            <p class="text-center text-body-secondary small mt-4 mb-0">共 <?= count($images) ?> 张图片</p>
        </article>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="imagePreviewModalTitle">查看原图</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagePreviewModalImage" class="img-fluid rounded" alt="">
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-body-secondary" id="imagePreviewModalMeta"></span>
                <a class="btn btn-primary" id="imagePreviewModalDownload" href="#" download>下载原图</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<?php $this->registerJs(<<<'JS'
document.querySelectorAll('[data-image-preview]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        const image = document.getElementById('imagePreviewModalImage');
        const download = document.getElementById('imagePreviewModalDownload');
        const title = document.getElementById('imagePreviewModalTitle');
        const meta = document.getElementById('imagePreviewModalMeta');

        image.src = trigger.dataset.imageSrc;
        image.alt = trigger.dataset.imageAlt || '';
        download.href = trigger.dataset.imageSrc;
        download.setAttribute('download', trigger.dataset.imageName || '');
        title.textContent = trigger.dataset.imageTitle || '查看原图';
        meta.textContent = trigger.dataset.imageMeta || '';
        meta.classList.toggle('d-none', !trigger.dataset.imageMeta);
    });
});
JS); ?>
