<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Image $model */
/** @var common\models\Post $post */
/** @var bool $isUpdate */
/** @var int[] $selectedImageIds */
/** @var string[] $selectedTagNames */
/** @var string|null $tagError */

use common\models\Post;
use common\models\Tag;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isUpdate = $isUpdate ?? false;
$selectedImageIds = $selectedImageIds ?? [];
$selectedTagNames = $selectedTagNames ?? ($isUpdate
    ? array_map(static fn (Tag $tag): string => $tag->name, $post->tags)
    : []);
$tagError = $tagError ?? null;
$availableTagNames = Tag::find()->select(['name'])->orderBy(['name' => SORT_ASC])->limit(100)->column();
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];
$this->title = $isUpdate ? '编辑图片' : '发布图片';

$this->registerCss(<<<'CSS'
.content-compose { max-width: 980px; margin-inline: auto; }
.content-compose-mark { display: grid; width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; place-items: center; border-radius: 1rem; color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .1); font-size: 1.5rem; font-weight: 700; }
.content-compose-card { border-radius: 1rem; }
.content-compose-card .card-body { padding: clamp(1rem, 4vw, 2.25rem); }
.image-collection-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .75rem; }
.image-collection-preview:empty { display: none; }
.image-collection-preview figure { margin: 0; overflow: hidden; border: 1px solid var(--bs-border-color); border-radius: .75rem; background: var(--bs-tertiary-bg); }
.image-collection-preview img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.image-collection-preview figcaption { padding: .4rem .55rem; overflow: hidden; color: var(--bs-secondary-color); font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }
.image-collection-delete-option { display: flex; align-items: center; gap: .4rem; padding: 0 .55rem .55rem; color: var(--bs-danger); font-size: .8rem; cursor: pointer; }
.image-collection-preview figure.image-marked-for-removal { border-color: var(--bs-danger); opacity: .55; }
.content-tag-picker { margin: 1.25rem 0; }
.content-tag-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .65rem; }
.content-tag-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border: 1px solid rgba(var(--bs-primary-rgb), .2); border-radius: 999px; color: var(--bs-primary-text-emphasis); background: rgba(var(--bs-primary-rgb), .08); }
.content-tag-chip button { padding: 0; border: 0; color: inherit; background: transparent; font-size: 1.1rem; line-height: 1; }
@media (max-width: 575.98px) {
    .content-compose { padding-left: .25rem; padding-right: .25rem; }
    .content-compose-actions > .btn { flex: 1 1 100%; }
}
CSS);

$this->registerJs(<<<'JS'
document.querySelectorAll('[data-image-collection]').forEach((input) => {
    const preview = document.getElementById(input.dataset.preview);
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
    };
    input.addEventListener('change', renderPreview);
    renderPreview();
});
document.querySelectorAll('[data-remove-image]').forEach((checkbox) => {
    const updateMarkedState = () => checkbox.closest('figure')?.classList.toggle('image-marked-for-removal', checkbox.checked);
    checkbox.addEventListener('change', updateMarkedState);
    updateMarkedState();
});
document.querySelectorAll('[data-tag-picker]').forEach((picker) => {
    const input = picker.querySelector('[data-tag-input]');
    const chips = picker.querySelector('[data-tag-chips]');
    const form = picker.closest('form');
    const addTag = (rawName) => {
        const name = rawName.trim().replace(/^#+|#+$/g, '').trim();
        if (!name || [...chips.querySelectorAll('input[name="tags[]"]')].some((hidden) => hidden.value.toLocaleLowerCase() === name.toLocaleLowerCase())) return;
        const chip = document.createElement('span');
        chip.className = 'content-tag-chip';
        const label = document.createElement('span');
        label.textContent = name;
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'tags[]';
        hidden.value = name;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.setAttribute('aria-label', `移除标签 ${name}`);
        remove.addEventListener('click', () => chip.remove());
        chip.append(label, hidden, remove);
        chips.append(chip);
    };
    const addInputTags = () => {
        input.value.split(/[,，]/).forEach(addTag);
        input.value = '';
    };
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addInputTags();
        }
    });
    input.addEventListener('change', addInputTags);
    form.addEventListener('submit', addInputTags);
    chips.querySelectorAll('[data-remove-tag]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.content-tag-chip')?.remove());
    });
});
JS);
?>
<div class="content-compose container-fluid py-3 py-md-5">
    <div class="d-flex align-items-start gap-3 mb-3 mb-md-4">
        <div class="content-compose-mark" aria-hidden="true">▧</div>
        <div>
            <p class="text-uppercase small text-body-secondary mb-1">图片</p>
            <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
            <p class="text-body-secondary mb-0">选择一张或多张图片，上传后即可预览图片集。</p>
        </div>
    </div>

    <div class="card content-compose-card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['options' => ['class' => 'content-compose-form', 'enctype' => 'multipart/form-data']]); ?>

            <?= $form->field($model, 'title')->label('标题')->textInput(['maxlength' => true]) ?>

            <div class="image-collection-field mb-3">
                <?php if ($isUpdate && $post->images !== []): ?>
                    <div class="mb-3" data-current-images>
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
                <?= $form->field($model, 'file_path', [
                    // The model's persisted-image rule requires file_path,
                    // but an edit may legitimately submit without uploads.
                    'enableClientValidation' => !$isUpdate,
                ])->label('选择图片')->fileInput([
                    // PHP only preserves every selected file when a multiple
                    // upload field uses an array-style name.
                    'name' => Html::getInputName($model, 'file_path') . '[]',
                    // Existing images can be edited or removed without uploading
                    // another file, so the browser must not require this input.
                    'required' => false,
                    'class' => 'form-control image-collection-input',
                    'multiple' => true,
                    'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                    'data-image-collection' => '1',
                    'data-preview' => 'image-collection-preview',
                ]) ?>
                <div class="form-text mb-2">
                    <?= $isUpdate ? '新选择的图片会追加到当前图片集；不选择则仅更新图片信息。' : '可同时选择多张图片，支持 JPG、PNG、GIF、WebP；单张不超过 10 MB，最多 50 张。' ?>
                </div>
                <?php if ($isUpdate): ?><p class="small fw-semibold mb-2">新选择图片预览</p><?php endif; ?>
                <div id="image-collection-preview" class="image-collection-preview mb-3" aria-live="polite"></div>
            </div>

            <div class="mb-3">
                <?= $form->field($model, 'alt_text', ['options' => ['class' => 'mb-1']])->label('图片说明')->textInput([
                    'maxlength' => true,
                    'placeholder' => '简要描述图片内容',
                ]) ?>
                <div class="form-text">描述图片内容，便于访问者理解，也有助于无障碍访问。</div>
            </div>

            <div class="content-tag-picker" data-tag-picker>
                <?= Html::label('标签', 'content-tag-input', ['class' => 'form-label fw-semibold']) ?>
                <div class="content-tag-chips" data-tag-chips aria-live="polite">
                    <?php foreach ($selectedTagNames as $tagName): ?>
                        <span class="content-tag-chip">
                            <span><?= Html::encode($tagName) ?></span>
                            <?= Html::hiddenInput('tags[]', $tagName) ?>
                            <button type="button" data-remove-tag aria-label="移除标签 <?= Html::encode($tagName) ?>">×</button>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?= Html::textInput(null, '', [
                    'id' => 'content-tag-input',
                    'class' => 'form-control',
                    'list' => 'images-tag-suggestions',
                    'maxlength' => 50,
                    'autocomplete' => 'off',
                    'placeholder' => '输入标签后按 Enter 添加，也可输入 #话题词#',
                    'data-tag-input' => '1',
                ]) ?>
                <datalist id="images-tag-suggestions">
                    <?php foreach ($availableTagNames as $availableTagName): ?>
                        <option value="<?= Html::encode($availableTagName) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <div class="form-text">可选已有标签或创建新标签；多个标签分别输入并按 Enter，每条内容最多 10 个。</div>
                <?php if ($tagError !== null): ?><div class="text-danger small mt-2"><?= Html::encode($tagError) ?></div><?php endif; ?>
            </div>

            <?= $form->field($post, 'visibility')->label('可见范围')->dropDownList($visibilityOptions) ?>

            <div class="content-compose-actions d-flex flex-column flex-sm-row gap-2 mt-4">
                <?= Html::submitButton($isUpdate ? '保存修改' : '发布内容', ['class' => 'btn btn-primary px-4']) ?>
                <?= Html::a('取消', $isUpdate ? ['/content/view', 'id' => $post->id] : ['/content/index'], ['class' => 'btn btn-outline-secondary px-4']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
