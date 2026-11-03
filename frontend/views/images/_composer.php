<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Image $model */
/** @var common\models\Post $post */
/** @var bool $isUpdate */
/** @var int[] $selectedImageIds */
/** @var string|null $tagError */

use common\models\Post;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isUpdate = (bool) ($isUpdate ?? false);
$selectedImageIds = $selectedImageIds ?? [];
$tagError = $tagError ?? null;
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];
$user = Yii::$app->user->identity;
$author = $user?->nickname ?: $user?->username ?: '我';
$avatar = $user?->avatar;
$avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
$imageInputId = Html::getInputId($model, 'file_path');
$captionErrors = array_values(array_filter([
    $model->getFirstError('caption'),
    $model->getFirstError('title'),
    $model->getFirstError('alt_text'),
]));

$this->registerCss(<<<'CSS'
.image-compose { max-width: 680px; margin-inline: auto; }
.image-compose-card { border-radius: 1.25rem; }
.image-compose-main { min-width: 0; }
.image-compose-avatar { width: 3rem; height: 3rem; flex: 0 0 3rem; border-radius: 50%; object-fit: cover; }
.image-compose-avatar-fallback { display: grid; place-items: center; background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); font-weight: 700; }
.image-textarea { min-height: 6.5rem; padding: .3rem 0; border: 0; border-bottom: 1px solid transparent; border-radius: 0; background: transparent; box-shadow: none; resize: none; font-size: 1.05rem; line-height: 1.9; }
.image-textarea:focus { border-bottom-color: var(--bs-primary); background: transparent; box-shadow: none; }
.image-textarea::placeholder { color: var(--bs-secondary-color); font-weight: 400; }
.image-drop { display: flex; flex-direction: column; align-items: center; gap: .2rem; margin-top: .75rem; padding: 1.4rem 1rem; border: 1px dashed var(--bs-border-color); border-radius: .9rem; color: var(--bs-secondary-color); text-align: center; cursor: pointer; transition: border-color .15s ease, background-color .15s ease, color .15s ease; }
.image-drop:hover { border-color: var(--bs-primary); color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .04); }
.image-drop-icon { font-size: 1.6rem; }
.image-drop-name { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.image-collection-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .75rem; margin-top: .75rem; }
.image-collection-preview:empty { display: none; }
.image-collection-preview figure { margin: 0; overflow: hidden; border: 1px solid var(--bs-border-color); border-radius: .75rem; background: var(--bs-tertiary-bg); }
.image-collection-preview img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.image-collection-preview figcaption { padding: .4rem .55rem; overflow: hidden; color: var(--bs-secondary-color); font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }
.image-collection-delete-option { display: flex; align-items: center; gap: .4rem; padding: 0 .55rem .55rem; color: var(--bs-danger); font-size: .8rem; cursor: pointer; }
.image-collection-preview figure.image-marked-for-removal { border-color: var(--bs-danger); opacity: .55; }
.image-compose-footer { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: 1rem; padding-top: .9rem; border-top: 1px solid var(--bs-border-color); }
.image-visibility { width: auto; max-width: 9.5rem; border-color: transparent; background-color: transparent; color: var(--bs-secondary-color); font-size: .875rem; }
.image-publish { border-radius: 999px; padding-inline: 1.6rem; }
@media (max-width: 575.98px) {
    .image-compose { padding-left: .25rem; padding-right: .25rem; }
    .image-compose-avatar { width: 2.5rem; height: 2.5rem; flex-basis: 2.5rem; }
}
CSS);

$this->registerJs(<<<'JS'
document.querySelectorAll('[data-image-collection]').forEach((input) => {
    const preview = document.getElementById(input.dataset.preview);
    const name = document.querySelector('[data-image-name]');
    const renderPreview = () => {
        const files = [...input.files];
        preview.replaceChildren(...files.map((file, index) => {
            const figure = document.createElement('figure');
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = `图片 ${index + 1}`;
            image.loading = 'lazy';
            image.onload = () => URL.revokeObjectURL(image.src);
            const caption = document.createElement('figcaption');
            caption.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
            figure.append(image, caption);
            return figure;
        }));
        if (name) {
            name.textContent = files.length > 0
                ? (files.length === 1 ? files[0].name : `已选择 ${files.length} 张图片`)
                : name.dataset.emptyText;
        }
    };
    input.addEventListener('change', renderPreview);
    renderPreview();
});
document.querySelectorAll('[data-remove-image]').forEach((checkbox) => {
    const updateMarkedState = () => checkbox.closest('figure')?.classList.toggle('image-marked-for-removal', checkbox.checked);
    checkbox.addEventListener('change', updateMarkedState);
    updateMarkedState();
});
JS);
?>
<div class="image-compose container py-3 py-md-4">
    <div class="card image-compose-card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <?php $form = ActiveForm::begin([
                'options' => ['class' => 'image-compose-form', 'enctype' => 'multipart/form-data'],
                'enableClientValidation' => !$isUpdate,
            ]); ?>
            <div class="d-flex gap-3">
                <?php if ($avatar): ?>
                    <img class="image-compose-avatar" src="<?= Html::encode($avatar) ?>" alt="<?= Html::encode($author) ?>">
                <?php else: ?>
                    <span class="image-compose-avatar image-compose-avatar-fallback" aria-hidden="true"><?= Html::encode($avatarInitial) ?></span>
                <?php endif; ?>

                <div class="image-compose-main flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="fw-semibold"><?= $isUpdate ? '编辑图集' : '发布图集' ?></span>
                        <span class="text-body-secondary small text-nowrap">输入 #话题# 添加标签</span>
                    </div>

                    <?= Html::textArea($model->formName() . '[caption]', (string) $model->caption, [
                        'id' => 'image-caption',
                        'class' => 'form-control image-textarea',
                        'rows' => 4,
                        'placeholder' => "图片标题（第一行）\n补充图片说明… 输入 #话题# 添加标签",
                        'aria-label' => '图片标题与说明',
                    ]) ?>
                    <div class="form-text">第一行作为标题，其余内容作为图片说明；#话题# 会自动识别为标签。</div>
                    <?php foreach ($captionErrors as $captionError): ?>
                        <div class="text-danger small mt-1"><?= Html::encode($captionError) ?></div>
                    <?php endforeach; ?>
                    <?php if ($tagError !== null): ?>
                        <div class="text-danger small mt-1"><?= Html::encode($tagError) ?></div>
                    <?php endif; ?>

                    <?php if ($isUpdate && $post->images !== []): ?>
                        <div class="mt-3">
                            <p class="small fw-semibold mb-2">当前图片</p>
                            <div class="image-collection-preview">
                                <?php foreach ($post->images as $currentImage): ?>
                                    <figure>
                                        <img src="<?= Html::encode($currentImage->file_path) ?>" alt="<?= Html::encode($currentImage->alt_text ?: $post->title) ?>" loading="lazy">
                                        <figcaption><?= Html::encode(basename((string) $currentImage->file_path)) ?></figcaption>
                                        <label class="image-collection-delete-option">
                                            <?= Html::checkbox('remove_image_ids[]', in_array((int) $currentImage->id, $selectedImageIds, true), [
                                                'value' => (int) $currentImage->id,
                                                'data-remove-image' => '1',
                                            ]) ?>
                                            删除此图
                                        </label>
                                    </figure>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <label class="image-drop" for="<?= Html::encode($imageInputId) ?>">
                        <i class="bi bi-images image-drop-icon" aria-hidden="true"></i>
                        <span class="fw-semibold image-drop-name" data-image-name
                              data-empty-text="<?= Html::encode($isUpdate ? '追加图片' : '选择图片') ?>">
                            <?= Html::encode($isUpdate ? '追加图片' : '选择图片') ?>
                        </span>
                        <span class="small">JPG / PNG / GIF / WebP · 单张最大 10 MB · 可多选</span>
                    </label>
                    <?= $form->field($model, 'file_path', ['options' => ['class' => 'mb-0']])->fileInput([
                        // PHP only preserves every selected file when a multiple
                        // upload field uses an array-style name.
                        'name' => Html::getInputName($model, 'file_path') . '[]',
                        'class' => 'd-none',
                        'required' => false,
                        'multiple' => true,
                        'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                        'data-image-collection' => '1',
                        'data-preview' => 'image-collection-preview',
                    ])->label(false) ?>
                    <div id="image-collection-preview" class="image-collection-preview" aria-live="polite"></div>
                    <?php if ($model->getFirstError('file_path') !== null): ?>
                        <div class="text-danger small mt-1"><?= Html::encode($model->getFirstError('file_path')) ?></div>
                    <?php endif; ?>

                    <div class="image-compose-footer">
                        <div class="ms-auto d-flex align-items-center gap-2">
                            <?= Html::dropDownList($post->formName() . '[visibility]', (int) $post->visibility, $visibilityOptions, [
                                'class' => 'form-select form-select-sm image-visibility',
                                'aria-label' => '可见范围',
                            ]) ?>
                            <?= Html::a('取消', $isUpdate ? ['/images/view', 'id' => $post->id] : ['/users/index'], ['class' => 'btn btn-link btn-sm text-body-secondary text-decoration-none px-1']) ?>
                            <?= Html::submitButton($isUpdate ? '保存' : '发布', ['class' => 'btn btn-primary image-publish']) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
