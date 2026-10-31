<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\BlogPost $model */
/** @var common\models\Post $post */
/** @var bool $isUpdate */
/** @var int $selectedCateId */
/** @var string|null $cateError */
/** @var string[] $selectedTagNames */
/** @var string|null $tagError */

use common\models\Cate;
use common\models\Post;
use common\models\Tag;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\HtmlPurifier;

$isUpdate = $isUpdate ?? false;
$selectedCateId = (int) ($selectedCateId ?? ($isUpdate ? (int) ($post->categories[0]->id ?? 0) : 0));
$selectedTagNames = $selectedTagNames ?? ($isUpdate
    ? array_map(static fn (Tag $tag): string => $tag->name, $post->tags)
    : []);
$cateError = $cateError ?? null;
$tagError = $tagError ?? null;
$categoryOptions = [];
foreach (Cate::find()->orderBy(['name' => SORT_ASC])->all() as $cate) {
    $categoryOptions[(int) $cate->id] = $cate->name;
}
$availableTagNames = Tag::find()->select(['name'])->orderBy(['name' => SORT_ASC])->limit(100)->column();
$editorImagesId = Html::getInputId($model, 'editor_images');
$contentInputId = Html::getInputId($model, 'content');
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];
$this->title = $isUpdate ? '编辑博客' : '写一篇博客';

$this->registerCss(<<<'CSS'
.content-compose { max-width: 980px; margin-inline: auto; }
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
.content-tag-picker { margin: 1.25rem 0; }
.content-tag-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .65rem; }
.content-tag-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border: 1px solid rgba(var(--bs-primary-rgb), .2); border-radius: 999px; color: var(--bs-primary-text-emphasis); background: rgba(var(--bs-primary-rgb), .08); }
.content-tag-chip button { padding: 0; border: 0; color: inherit; background: transparent; font-size: 1.1rem; line-height: 1; }
@media (max-width: 575.98px) {
    .content-compose { padding-left: .25rem; padding-right: .25rem; }
    .rich-editor-surface { min-height: 220px; padding: .75rem; }
    .rich-editor-toolbar { gap: .3rem; padding: .5rem; }
    .rich-editor-toolbar .btn { min-width: 2.25rem; padding: .35rem .55rem; }
    .content-compose-actions > .btn { flex: 1 1 100%; }
}
CSS);

$this->registerJs(<<<'JS'
document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
    const source = document.getElementById(editor.dataset.source);
    const form = editor.closest('form');
    const sync = () => { source.value = editor.innerHTML; };
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
        <div class="content-compose-mark" aria-hidden="true">✎</div>
        <div>
            <p class="text-uppercase small text-body-secondary mb-1">博客</p>
            <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
            <p class="text-body-secondary mb-0">整理文章结构，添加摘要，并用格式工具排出清晰易读的正文。</p>
        </div>
    </div>

    <div class="card content-compose-card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['options' => ['class' => 'content-compose-form', 'enctype' => 'multipart/form-data']]); ?>

            <?= $form->field($model, 'title')->label('标题')->textInput(['maxlength' => true]) ?>
            <div class="mb-3">
                <?= $form->field($model, 'summary', ['options' => ['class' => 'mb-1']])->label('摘要')->textarea([
                    'rows' => 3,
                    'placeholder' => '用一两句话概括文章重点',
                ]) ?>
                <div class="form-text">简要介绍文章内容；列表页会优先展示这段摘要。</div>
            </div>

            <div class="rich-editor-wrap mb-4">
                <?= $form->field($model, 'content', ['options' => ['class' => 'mb-0'], 'enableClientValidation' => false])
                    ->textarea(['id' => $contentInputId, 'class' => 'form-control rich-editor-source d-none', 'rows' => 8, 'required' => false])
                    ->label('正文', ['class' => 'form-label fw-semibold']) ?>
                <div class="rich-editor-toolbar" role="toolbar" aria-label="正文格式">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="bold" aria-label="加粗"><strong>B</strong></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="italic" aria-label="斜体"><em>I</em></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="underline" aria-label="下划线"><u>U</u></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="formatBlock" data-editor-value="h2">标题</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="formatBlock" data-editor-value="blockquote">引用</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="insertUnorderedList">项目符号</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-command="insertOrderedList">编号</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-code>代码块</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-link>插入链接</button>
                    <label class="btn btn-sm btn-outline-primary mb-0" for="<?= Html::encode($editorImagesId) ?>">插入图片</label>
                </div>
                <input type="file" class="d-none" id="<?= Html::encode($editorImagesId) ?>" name="editor_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-rich-image-input>
                <div class="form-text mt-2">可一次选择单张或多张图片（每张不超过 10 MB）；图片会插入正文光标位置。</div>
                <div class="rich-editor-image-preview" data-rich-image-preview aria-live="polite"></div>
                <div class="rich-editor-surface" contenteditable="true" role="textbox" aria-multiline="true"
                     aria-label="正文"
                     data-rich-editor data-source="<?= Html::encode($contentInputId) ?>"
                     data-placeholder="从这里开始撰写正文……"><?= HtmlPurifier::process((string) $model->getAttribute('content'), ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title],pre,code']) ?></div>
                <div class="rich-editor-help text-body-secondary mt-2">支持加粗、斜体、标题、引用、列表和链接；手机上可横向换行使用工具栏。</div>
            </div>

            <div class="mb-4">
                <?= Html::label('分类', 'content-cate-select', ['class' => 'form-label fw-semibold']) ?>
                <?= Html::dropDownList('cate_id', $selectedCateId > 0 ? $selectedCateId : '', $categoryOptions, [
                    'id' => 'content-cate-select',
                    'class' => 'form-select' . ($cateError !== null ? ' is-invalid' : ''),
                    'prompt' => '请选择分类',
                    'required' => true,
                ]) ?>
                <?php if ($cateError !== null): ?><div class="text-danger small mt-2"><?= Html::encode($cateError) ?></div><?php endif; ?>
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
                    'list' => 'blog-tag-suggestions',
                    'maxlength' => 50,
                    'autocomplete' => 'off',
                    'placeholder' => '输入标签后按 Enter 添加，也可输入 #话题词#',
                    'data-tag-input' => '1',
                ]) ?>
                <datalist id="blog-tag-suggestions">
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
