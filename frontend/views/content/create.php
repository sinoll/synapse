<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\db\ActiveRecord $model */
/** @var common\models\Post $post */
/** @var string $label */
/** @var string[] $fields */
/** @var string $type */
/** @var string $recipientUsernames */
/** @var bool $isUpdate */

use common\models\Post;
use common\models\Tag;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\HtmlPurifier;

$isUpdate = $isUpdate ?? false;
$selectedImageIds = $selectedImageIds ?? [];
$hasRichEditor = in_array($type, ['blog', 'moment'], true);
$hasImageCollection = $type === 'images';
$hasVideoUploader = $type === 'video';
$hasTagPicker = in_array($type, ['blog', 'images', 'moment', 'video'], true);
$selectedTagNames = $selectedTagNames ?? ($isUpdate && $hasTagPicker
    ? array_map(static fn (Tag $tag): string => $tag->name, $post->tags)
    : []);
$tagError = $tagError ?? null;
$availableTagNames = $hasTagPicker
    ? Tag::find()->select(['name'])->orderBy(['name' => SORT_ASC])->limit(100)->column()
    : [];
$pageCopy = [
    'blog' => ['icon' => '✎', 'title' => $isUpdate ? '编辑博客' : '写一篇博客', 'intro' => '整理文章结构，添加摘要，并用格式工具排出清晰易读的正文。'],
    'moment' => ['icon' => '◉', 'title' => $isUpdate ? '编辑动态' : '记录一条动态', 'intro' => '动态标题默认取内容前 8 个字。内容不能为空。'],
    'images' => ['icon' => '▧', 'title' => $isUpdate ? '编辑图片' : '发布图片', 'intro' => '选择一张或多张图片，上传后即可预览图片集。'],
    'video' => ['icon' => '▶', 'title' => $isUpdate ? '编辑短视频' : '发布短视频', 'intro' => '上传视频即可预览，时长自动读取，封面可自定义或使用第一帧。'],
    'file' => ['icon' => '↗', 'title' => $isUpdate ? '编辑文件分享' : '分享一个文件', 'intro' => '补充文件说明并设置可见范围，明确哪些人可以访问。'],
];
$pageCopy += ['other' => ['icon' => '＋', 'title' => ($isUpdate ? '编辑' : '创建') . $label, 'intro' => '填写内容信息并设置合适的可见范围。']];
$copy = $pageCopy[$type] ?? $pageCopy['other'];
$this->title = $copy['title'];
if ($type === 'file') {
    $this->registerCssFile('@web/css/content-file.css');
}
$labels = [
    'title' => '标题', 'summary' => '摘要', 'content' => $type === 'moment' ? '动态内容' : '正文',
    'description' => '描述', 'video_path' => '视频文件路径', 'cover_path' => '封面图片路径',
    'file_path' => '文件路径', 'original_name' => '原始文件名', 'mime_type' => 'MIME 类型', 'size_bytes' => '文件大小（字节）',
    'alt_text' => '图片说明', 'duration_seconds' => '时长（秒）', 'image_paths' => '图片路径（JSON）',
];
$fieldHelp = [
    'summary' => '简要介绍文章内容；列表页会优先展示这段摘要。',
    'file_path' => '选择需要上传的图片文件。',
    'video_path' => '选择需要上传的视频文件。',
    'cover_path' => '可选，选择自定义封面；不选择时自动使用视频第 5 帧。',
    'image_paths' => '可选，使用 JSON 数组填写图片路径。',
    'alt_text' => '描述图片内容，便于访问者理解，也有助于无障碍访问。',
    'duration_seconds' => '填写视频时长，单位为秒。',
    'description' => '用简短文字说明这项内容。',
    'original_name' => '访问者看到的原始文件名。',
    'size_bytes' => '文件大小，单位为字节。',
];
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];
if ($type === 'file') {
    $visibilityOptions[Post::VISIBILITY_SELECTED_USERS] = '指定用户可见';
}

$this->registerCss(<<<'CSS'
.content-compose { max-width: 980px; }
.content-compose { margin-inline: auto; }
.content-compose-mark { display: grid; width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; place-items: center; border-radius: 1rem; color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .1); font-size: 1.5rem; font-weight: 700; }
.content-compose-card { border-radius: 1rem; }
.content-compose-card .card-body { padding: clamp(1rem, 4vw, 2.25rem); }
.rich-editor-toolbar { display: flex; flex-wrap: wrap; gap: .4rem; padding: .65rem; border: 1px solid var(--bs-border-color); border-bottom: 0; border-radius: var(--bs-border-radius) var(--bs-border-radius) 0 0; background: var(--bs-tertiary-bg); }
.rich-editor-toolbar .btn { min-width: 2.5rem; }
.rich-editor-surface { min-height: 300px; padding: 1rem; overflow-wrap: anywhere; border: 1px solid var(--bs-border-color); border-radius: 0 0 var(--bs-border-radius) var(--bs-border-radius); background: var(--bs-body-bg); outline: 0; line-height: 1.75; }
.rich-editor-surface:focus { border-color: var(--bs-primary); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .18); }
.rich-editor-surface:empty::before { content: attr(data-placeholder); color: var(--bs-secondary-color); pointer-events: none; }
.rich-editor-surface > :first-child { margin-top: 0; }
.rich-editor-surface > :last-child { margin-bottom: 0; }
.rich-editor-surface pre { overflow-x: auto; padding: .85rem 1rem; border-radius: .5rem; background: var(--bs-tertiary-bg); font-family: var(--bs-font-monospace); white-space: pre; }
.rich-editor-surface pre code { color: inherit; }
.rich-editor-help { font-size: .875rem; }
.rich-editor-image-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .65rem; margin-top: .75rem; }
.rich-editor-image-preview:empty { display: none; }
.rich-editor-image-preview img { width: 100%; height: 100px; object-fit: cover; border-radius: .5rem; }
.rich-content img { max-width: 100%; height: auto; }
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
.video-upload-preview { display: grid; gap: 1rem; grid-template-columns: minmax(0, 2fr) minmax(180px, 1fr); }
.video-upload-preview video, .video-upload-preview img { width: 100%; max-height: 360px; object-fit: contain; border: 1px solid var(--bs-border-color); border-radius: .75rem; background: #191512; }
@media (max-width: 575.98px) { .video-upload-preview { grid-template-columns: 1fr; } }
@media (max-width: 575.98px) {
    .content-compose { padding-left: .25rem; padding-right: .25rem; }
    .rich-editor-surface { min-height: 220px; padding: .75rem; }
    .rich-editor-toolbar { gap: .3rem; padding: .5rem; }
    .rich-editor-toolbar .btn { min-width: 2.25rem; padding: .35rem .55rem; }
    .content-compose-actions > .btn { flex: 1 1 100%; }
}
CSS);

if ($hasRichEditor || $hasImageCollection || $hasVideoUploader) {
    $this->registerJs(<<<'JS'
document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
    const source = document.getElementById(editor.dataset.source);
    const form = editor.closest('form');
    const sync = () => { source.value = editor.innerHTML; };
    if (editor.dataset.required === 'true') {
        form.addEventListener('submit', (event) => {
            const text = (editor.innerText || '').replace(/\u00a0/g, ' ').trim();
            if (!text) {
                event.preventDefault();
                editor.setAttribute('aria-invalid', 'true');
                editor.focus();
                let message = editor.parentElement.querySelector('[data-content-required-error]');
                if (!message) {
                    message = document.createElement('div');
                    message.className = 'text-danger small mt-2';
                    message.dataset.contentRequiredError = '1';
                    message.textContent = '动态内容不能为空。';
                    editor.insertAdjacentElement('afterend', message);
                }
            }
        });
        editor.addEventListener('input', () => {
            if ((editor.innerText || '').replace(/\u00a0/g, ' ').trim()) {
                editor.removeAttribute('aria-invalid');
                editor.parentElement.querySelector('[data-content-required-error]')?.remove();
            }
        });
    }
    editor.addEventListener('input', sync);
    const imageInput = editor.closest('.rich-editor-wrap').querySelector('[data-rich-image-input]');
    const imagePreview = editor.closest('.rich-editor-wrap').querySelector('[data-rich-image-preview]');
    if (imageInput) {
        let selectedFiles = [];
        imageInput.addEventListener('change', () => {
            const newFiles = [...imageInput.files];
            const firstNewIndex = selectedFiles.length;
            selectedFiles = selectedFiles.concat(newFiles);
            const transfer = new DataTransfer();
            selectedFiles.forEach((file) => transfer.items.add(file));
            imageInput.files = transfer.files;
            imagePreview.replaceChildren(...selectedFiles.map((file) => {
                const image = document.createElement('img');
                image.src = URL.createObjectURL(file);
                image.alt = file.name;
                image.title = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                image.onload = () => URL.revokeObjectURL(image.src);
                return image;
            }));
            editor.focus();
            const selection = window.getSelection();
            let range = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
            if (!range || !editor.contains(range.commonAncestorContainer)) {
                range = document.createRange();
                range.selectNodeContents(editor);
                range.collapse(false);
            }
            newFiles.forEach((file, offset) => {
                const index = firstNewIndex + offset;
                const marker = document.createTextNode(`[[EDITOR_IMAGE_${index}]]`);
                range.insertNode(marker);
                range.setStartAfter(marker);
                range.collapse(true);
            });
            if (selection) { selection.removeAllRanges(); selection.addRange(range); }
            sync();
        });
    }
    editor.closest('.rich-editor-wrap').querySelectorAll('[data-editor-command]').forEach((button) => {
        button.addEventListener('pointerdown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand(button.dataset.editorCommand, false, button.dataset.editorValue || null);
            sync();
        });
    });
    const codeButton = editor.closest('.rich-editor-wrap').querySelector('[data-editor-code]');
    if (codeButton) {
        codeButton.addEventListener('pointerdown', (event) => event.preventDefault());
        codeButton.addEventListener('click', () => {
            editor.focus();
            const selection = window.getSelection();
            const selectedText = selection && selection.rangeCount && editor.contains(selection.getRangeAt(0).commonAncestorContainer)
                ? selection.getRangeAt(0).toString()
                : '';
            const escaped = document.createElement('span');
            escaped.textContent = selectedText || '在此输入代码';
            document.execCommand('insertHTML', false, `<pre><code>${escaped.innerHTML}</code></pre>`);
            sync();
        });
    }
    editor.closest('.rich-editor-wrap').querySelector('[data-editor-link]').addEventListener('pointerdown', (event) => event.preventDefault());
    editor.closest('.rich-editor-wrap').querySelector('[data-editor-link]').addEventListener('click', () => {
        const url = window.prompt('请输入链接地址');
        if (url) {
            editor.focus();
            document.execCommand('createLink', false, url);
            sync();
        }
    });
    form.addEventListener('submit', sync);
});
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
const videoInput = document.querySelector('[data-video-input]');
if (videoInput) {
    const video = document.querySelector('[data-video-preview]');
    const coverInput = document.querySelector('[data-cover-input]');
    const cover = document.querySelector('[data-cover-preview]');
    const duration = document.querySelector('[data-video-duration]');
    const showCover = (url) => { cover.src = url; cover.classList.remove('d-none'); };
    coverInput.addEventListener('change', () => {
        if (coverInput.files.length) showCover(URL.createObjectURL(coverInput.files[0]));
    });
    videoInput.addEventListener('change', () => {
        const file = videoInput.files[0];
        if (!file) return;
        video.src = URL.createObjectURL(file);
        video.classList.remove('d-none');
        video.load();
    });
    video.addEventListener('loadedmetadata', () => {
        duration.value = Number.isFinite(video.duration) ? Math.round(video.duration) : 0;
    });
}
JS);
}
?>
<div class="content-compose container-fluid py-3 py-md-5<?= $type === 'file' ? ' nextcloud-file-page' : '' ?>">
    <div class="d-flex align-items-start gap-3 mb-3 mb-md-4">
        <div class="content-compose-mark" aria-hidden="true"><?= Html::encode($copy['icon']) ?></div>
        <div>
            <p class="text-uppercase small text-body-secondary mb-1"><?= Html::encode($label) ?></p>
            <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
            <p class="text-body-secondary mb-0"><?= Html::encode($copy['intro']) ?></p>
        </div>
    </div>

    <div class="card content-compose-card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['options' => ['class' => 'content-compose-form', 'enctype' => 'multipart/form-data']]); ?>
            <?php foreach ($fields as $field): ?>
                <?php if ($hasRichEditor && $field === 'content'): ?>
                    <?php $inputId = Html::getInputId($model, $field); ?>
                    <div class="rich-editor-wrap mb-4">
                        <?= $form->field($model, $field, ['options' => ['class' => 'mb-0'], 'enableClientValidation' => false])
                            ->textarea(['id' => $inputId, 'class' => 'form-control rich-editor-source d-none', 'rows' => 8, 'required' => false])
                            ->label($labels[$field] ?? $field, ['class' => 'form-label fw-semibold']) ?>
                        <div class="rich-editor-toolbar" role="toolbar" aria-label="正文格式">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="bold" aria-label="加粗"><strong>B</strong></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="italic" aria-label="斜体"><em>I</em></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="underline" aria-label="下划线"><u>U</u></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="formatBlock" data-editor-value="h2">标题</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="formatBlock" data-editor-value="blockquote">引用</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="insertUnorderedList">项目符号</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="insertOrderedList">编号</button>
                            <?php if ($type === 'blog'): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-editor-code>代码块</button><?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-link>插入链接</button>
                            <label class="btn btn-sm btn-outline-primary mb-0" for="<?= Html::getInputId($model, 'editor_images') ?>">插入图片</label>
                        </div>
                        <input type="file" class="d-none" id="<?= Html::getInputId($model, 'editor_images') ?>" name="editor_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-rich-image-input>
                        <div class="form-text mt-2">可一次选择单张或多张图片（每张不超过 10 MB）；图片会插入正文光标位置。</div>
                        <div class="rich-editor-image-preview" data-rich-image-preview aria-live="polite"></div>
                        <div class="rich-editor-surface" contenteditable="true" role="textbox" aria-multiline="true"
                             aria-required="<?= $type === 'moment' ? 'true' : 'false' ?>"
                             data-required="<?= $type === 'moment' ? 'true' : 'false' ?>"
                             aria-label="<?= Html::encode($labels[$field] ?? $field) ?>"
                             data-rich-editor data-source="<?= Html::encode($inputId) ?>"
                             data-placeholder="<?= $type === 'moment' ? '写下此刻的想法……' : '从这里开始撰写正文……' ?>"><?= HtmlPurifier::process((string) $model->getAttribute($field), ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title],pre,code']) ?></div>
                        <div class="rich-editor-help text-body-secondary mt-2">支持加粗、斜体、标题、引用、列表和链接；手机上可横向换行使用工具栏。</div>
                    </div>
                <?php elseif ($hasVideoUploader && $field === 'video_path'): ?>
                    <?= $form->field($model, $field)->label('选择视频')->fileInput([
                        'class' => 'form-control',
                        'accept' => 'video/mp4,video/webm,video/quicktime,video/ogg',
                        'data-video-input' => '1',
                    ]) ?>
                    <div class="form-text mb-2">支持 MP4、WebM、MOV、OGG，最大 500 MB。选择后可立即预览。</div>
                    <div class="video-upload-preview mb-4">
                        <video class="d-none" controls playsinline data-video-preview></video>
                        <div>
                            <p class="small fw-semibold mb-2">封面预览</p>
                            <img class="d-none" alt="视频封面预览" data-cover-preview>
                        </div>
                    </div>
                <?php elseif ($hasVideoUploader && $field === 'cover_path'): ?>
                    <?= $form->field($model, $field)->label('自定义封面（可选）')->fileInput([
                        'class' => 'form-control',
                        'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                        'data-cover-input' => '1',
                    ]) ?>
                    <div class="form-text mb-3">不选择自定义封面时，保存后自动提取视频第 5 帧作为封面。</div>
                <?php elseif ($hasVideoUploader && $field === 'duration_seconds'): ?>
                    <?= $form->field($model, $field)->hiddenInput(['data-video-duration' => '1'])->label(false) ?>
                <?php elseif ($hasImageCollection && $field === 'file_path'): ?>
                    <div class="image-collection-field">
                        <?php if ($isUpdate && $post->type === Post::TYPE_IMAGES && $post->images !== []): ?>
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
                        <?= $form->field($model, $field, [
                            // The model's persisted-image rule requires file_path,
                            // but an edit may legitimately submit without uploads.
                            'enableClientValidation' => !$isUpdate,
                        ])->label('选择图片')->fileInput([
                            // PHP only preserves every selected file when a multiple
                            // upload field uses an array-style name.
                            'name' => Html::getInputName($model, $field) . '[]',
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
                <?php elseif (in_array($field, ['content', 'description', 'summary', 'image_paths'], true)): ?>
                    <?= $form->field($model, $field)->label($labels[$field] ?? $field)->textarea([
                        'rows' => $field === 'summary' ? 3 : 5,
                        'placeholder' => $field === 'summary' ? '用一两句话概括文章重点' : null,
                    ]) ?>
                    <?php if (isset($fieldHelp[$field])): ?><div class="form-text mb-3"><?= Html::encode($fieldHelp[$field]) ?></div><?php endif; ?>
                <?php else: ?>
                    <?= $form->field($model, $field)->label($labels[$field] ?? $field)->textInput([
                        'maxlength' => true,
                        'type' => in_array($field, ['duration_seconds', 'size_bytes'], true) ? 'number' : 'text',
                        'min' => in_array($field, ['duration_seconds', 'size_bytes'], true) ? 0 : null,
                        'placeholder' => match ($field) {
                            'file_path', 'video_path', 'cover_path' => '例如：/uploads/example-file.jpg',
                            'original_name' => '例如：项目资料.pdf',
                            'alt_text' => '简要描述图片内容',
                            default => null,
                        },
                    ]) ?>
                    <?php if (isset($fieldHelp[$field])): ?><div class="form-text mt-n2 mb-3"><?= Html::encode($fieldHelp[$field]) ?></div><?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($hasTagPicker): ?>
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
                        'list' => 'content-tag-suggestions-' . $type,
                        'maxlength' => 50,
                        'autocomplete' => 'off',
                        'placeholder' => '输入标签后按 Enter 添加，也可输入 #话题词#',
                        'data-tag-input' => '1',
                    ]) ?>
                    <datalist id="content-tag-suggestions-<?= Html::encode($type) ?>">
                        <?php foreach ($availableTagNames as $availableTagName): ?>
                            <option value="<?= Html::encode($availableTagName) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">可选已有标签或创建新标签；多个标签分别输入并按 Enter，每条内容最多 10 个。</div>
                    <?php if ($tagError !== null): ?><div class="text-danger small mt-2"><?= Html::encode($tagError) ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <?= $form->field($post, 'visibility')->label('可见范围')->dropDownList($visibilityOptions) ?>
            <?php if ($type === 'file'): ?>
                <p class="small text-body-secondary">请填写已部署的资源路径；此表单目前不提供文件上传。</p>
                <?= Html::label('授权用户名（用逗号、空格或换行分隔）', 'recipient_usernames', ['class' => 'form-label']) ?>
                <?= Html::textInput('recipient_usernames', $recipientUsernames, ['id' => 'recipient_usernames', 'class' => 'form-control mb-3', 'autocomplete' => 'off', 'placeholder' => '仅“指定用户可见”时需要填写']) ?>
            <?php endif; ?>

            <div class="content-compose-actions d-flex flex-column flex-sm-row gap-2 mt-4">
                <?= Html::submitButton($isUpdate ? '保存修改' : '发布内容', ['class' => 'btn btn-primary px-4']) ?>
                <?= Html::a('取消', $isUpdate ? ['/content/view', 'id' => $post->id] : ['/content/index'], ['class' => 'btn btn-outline-secondary px-4']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
