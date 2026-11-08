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

use common\components\RichText;
use common\models\Cate;
use common\models\Post;
use common\models\Tag;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

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
$alignments = [
    'left' => ['左对齐', 'bi-text-left'],
    'center' => ['居中', 'bi-text-center'],
    'right' => ['右对齐', 'bi-text-right'],
    'justify' => ['两端对齐', 'bi-justify'],
];
// 档位写入 span 的 font-size（em 相对正文），避免 execCommand 的 1-7 号 <font size>。
$fontSizes = ['0.85em' => '小', '1em' => '常规', '1.25em' => '大', '1.5em' => '特大'];
// 12 色相 × 4 明度（HSL，饱和度 72%）：按 深→浓→中→浅 分行、按色相环分列。
// 浅行在白底上对比偏低，深色模式下深行同理——色板保留完整光谱，由作者自行取舍。
$textColors = [
    '' => '默认颜色',
    // 深（L=30%）
    '#841515' => '红·深',
    '#844d15' => '橙·深',
    '#848415' => '黄·深',
    '#4d8415' => '草绿·深',
    '#158415' => '绿·深',
    '#15844d' => '青绿·深',
    '#158484' => '青·深',
    '#154d84' => '天蓝·深',
    '#151584' => '蓝·深',
    '#4d1584' => '靛紫·深',
    '#841584' => '紫红·深',
    '#84154d' => '玫红·深',
    // 浓（L=45%）
    '#c52020' => '红·浓',
    '#c57320' => '橙·浓',
    '#c5c520' => '黄·浓',
    '#73c520' => '草绿·浓',
    '#20c520' => '绿·浓',
    '#20c573' => '青绿·浓',
    '#20c5c5' => '青·浓',
    '#2073c5' => '天蓝·浓',
    '#2020c5' => '蓝·浓',
    '#7320c5' => '靛紫·浓',
    '#c520c5' => '紫红·浓',
    '#c52073' => '玫红·浓',
    // 中（L=60%）
    '#e25050' => '红·中',
    '#e29950' => '橙·中',
    '#e2e250' => '黄·中',
    '#99e250' => '草绿·中',
    '#50e250' => '绿·中',
    '#50e299' => '青绿·中',
    '#50e2e2' => '青·中',
    '#5099e2' => '天蓝·中',
    '#5050e2' => '蓝·中',
    '#9950e2' => '靛紫·中',
    '#e250e2' => '紫红·中',
    '#e25099' => '玫红·中',
    // 浅（L=75%）
    '#ed9191' => '红·浅',
    '#edbf91' => '橙·浅',
    '#eded91' => '黄·浅',
    '#bfed91' => '草绿·浅',
    '#91ed91' => '绿·浅',
    '#91edbf' => '青绿·浅',
    '#91eded' => '青·浅',
    '#91bfed' => '天蓝·浅',
    '#9191ed' => '蓝·浅',
    '#bf91ed' => '靛紫·浅',
    '#ed91ed' => '紫红·浅',
    '#ed91bf' => '玫红·浅',
];

$this->registerCss(<<<'CSS'
.content-compose { max-width: 980px; margin-inline: auto; }
.content-compose-mark { display: grid; width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; place-items: center; border-radius: 1rem; color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .1); font-size: 1.5rem; font-weight: 700; }
.content-compose-card { border-radius: 1rem; }
.content-compose-card .card-body { padding: clamp(1rem, 4vw, 2.25rem); }
.rich-editor-toolbar { position: sticky; top: var(--rich-editor-sticky-top, 4.25rem); z-index: 1020; display: flex; flex-wrap: wrap; gap: .4rem; padding: .65rem; border: 1px solid var(--bs-border-color); border-bottom: 0; border-radius: var(--bs-border-radius) var(--bs-border-radius) 0 0; background: var(--bs-tertiary-bg); box-shadow: 0 .35rem .75rem rgba(var(--bs-body-color-rgb), .10); }
.rich-editor-toolbar .btn { min-width: 2.5rem; }
.rich-editor-divider { width: 1px; align-self: stretch; margin: .1rem .25rem; background: var(--bs-border-color); }
.rich-editor-group { display: flex; flex-wrap: nowrap; align-items: center; gap: .3rem; }
.rich-editor-group .form-select { width: auto; }
.rich-editor-color { position: relative; }
.rich-editor-swatches { position: absolute; top: calc(100% + .4rem); left: 0; z-index: 1030; display: grid; grid-template-columns: repeat(12, 1.7rem); gap: .4rem; width: max-content; max-width: calc(100vw - 1.5rem); max-height: calc(100vh - 5rem); overflow: auto; padding: .55rem; border: 1px solid var(--bs-border-color); border-radius: .6rem; background: var(--bs-body-bg); box-shadow: 0 .5rem 1.1rem rgba(var(--bs-body-color-rgb), .16); }
.rich-editor-swatches--align-end { left: auto; right: 0; }
.rich-editor-swatches--drop-up { top: auto; bottom: calc(100% + .4rem); }
.rich-editor-swatches[hidden] { display: none; }
.rich-editor-swatch { position: relative; width: 1.7rem; height: 1.7rem; padding: 0; border: 1px solid var(--bs-border-color); border-radius: .4rem; cursor: pointer; }
.rich-editor-swatch:hover { transform: scale(1.08); }
.rich-editor-swatch-reset { grid-column: 1 / -1; justify-self: start; color: var(--bs-body-color); background: var(--bs-tertiary-bg); font-size: .8rem; font-weight: 700; }
.rich-editor-swatch-reset::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top right, transparent calc(50% - .6px), var(--bs-danger) calc(50% - .6px), var(--bs-danger) calc(50% + .6px), transparent calc(50% + .6px)); }
.rich-editor-surface { min-height: 300px; padding: 1rem; overflow-wrap: anywhere; border: 1px solid var(--bs-border-color); border-radius: 0 0 var(--bs-border-radius) var(--bs-border-radius); background: var(--bs-body-bg); outline: 0; line-height: 1.75; }
.rich-editor-surface > p, .rich-editor-surface > div { text-indent: 2em; }
.rich-editor-surface > p:has(img, video), .rich-editor-surface > div:has(img, video) { text-indent: 0; }
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
@media (max-width: 767.98px) {
    .rich-editor-swatches { grid-template-columns: repeat(8, 1.7rem); }
}
@media (max-width: 575.98px) {
    .content-compose { padding-left: .25rem; padding-right: .25rem; }
    .rich-editor-surface { min-height: 220px; padding: .75rem; }
    .rich-editor-toolbar { gap: .3rem; padding: .5rem; }
    .rich-editor-toolbar .btn { min-width: 2.25rem; padding: .35rem .55rem; }
    .rich-editor-divider { display: none; }
    .rich-editor-swatches { grid-template-columns: repeat(6, 1.7rem); gap: .35rem; }
    .content-compose-actions > .btn { flex: 1 1 100%; }
}
CSS);

$this->registerJs(<<<'JS'
const setEditorStickyTop = () => {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        document.documentElement.style.setProperty('--rich-editor-sticky-top', `${navbar.getBoundingClientRect().height + 8}px`);
    }
};
setEditorStickyTop();
window.addEventListener('resize', setEditorStickyTop);

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
    const wrap = editor.closest('.rich-editor-wrap');
    // execCommand 的 foreColor/fontSize/justify* 会写出 <font> 和 align 属性，净化后会被丢弃，
    // 所以这三类格式直接改 DOM，产出白名单内的 style。
    const currentRange = () => {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) return null;
        const range = selection.getRangeAt(0);
        return editor.contains(range.commonAncestorContainer) ? range : null;
    };
    const selectNode = (node) => {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(node);
        selection?.removeAllRanges();
        selection?.addRange(range);
    };
    const unwrapSpan = (span) => {
        const parent = span.parentNode;
        if (!parent) return;
        while (span.firstChild) parent.insertBefore(span.firstChild, span);
        parent.removeChild(span);
    };
    const styleSelection = (property, value) => {
        const range = currentRange();
        if (range === null) return;
        if (value !== '') {
            if (range.collapsed) return;
            const span = document.createElement('span');
            try {
                range.surroundContents(span);
            } catch {
                span.appendChild(range.extractContents());
                range.insertNode(span);
            }
            span.style.setProperty(property, value);
            selectNode(span);
            return;
        }
        editor.querySelectorAll('span').forEach((span) => {
            if (!range.intersectsNode(span)) return;
            span.style.removeProperty(property);
            if (span.style.length === 0) span.removeAttribute('style');
            if (span.attributes.length === 0) unwrapSpan(span);
        });
    };
    const alignBlocks = (value) => {
        const range = currentRange();
        if (range === null) return;
        const blockTags = new Set(['P', 'DIV', 'H2', 'H3', 'BLOCKQUOTE', 'LI', 'UL', 'OL', 'PRE']);
        const nearestBlock = (node) => {
            while (node && node !== editor) {
                if (node.nodeType === 1 && blockTags.has(node.tagName)) return node;
                node = node.parentNode;
            }
            return null;
        };
        const blocks = new Set();
        [nearestBlock(range.startContainer), nearestBlock(range.endContainer)].forEach((block) => block && blocks.add(block));
        [...editor.children].forEach((child) => {
            if (range.intersectsNode(child)) blocks.add(child);
        });
        blocks.forEach((block) => {
            if (value === '') block.style.removeProperty('text-align');
            else block.style.setProperty('text-align', value);
        });
    };
    wrap.querySelectorAll('[data-editor-align]').forEach((button) => {
        button.addEventListener('pointerdown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            editor.focus();
            alignBlocks(button.dataset.editorAlign || '');
            sync();
        });
    });
    const sizeSelect = wrap.querySelector('[data-editor-size]');
    sizeSelect?.addEventListener('change', () => {
        const value = sizeSelect.value;
        sizeSelect.value = '';
        if (value === '') return;
        editor.focus();
        styleSelection('font-size', value);
        sync();
    });
    const colorToggle = wrap.querySelector('[data-editor-color-toggle]');
    const colorPanel = wrap.querySelector('[data-editor-color-panel]');
    if (colorToggle && colorPanel) {
        const closeColorPanel = () => {
            colorPanel.hidden = true;
            colorToggle.setAttribute('aria-expanded', 'false');
        };
        colorToggle.addEventListener('pointerdown', (event) => event.preventDefault());
        colorToggle.addEventListener('click', () => {
            const open = colorPanel.hidden;
            colorPanel.hidden = !open;
            colorToggle.setAttribute('aria-expanded', String(open));
            if (!open) return;
            const panelRect = colorPanel.getBoundingClientRect();
            const toggleRect = colorToggle.getBoundingClientRect();
            const roomBelow = window.innerHeight - toggleRect.bottom;
            const roomAbove = toggleRect.top;
            colorPanel.classList.toggle('rich-editor-swatches--align-end', panelRect.right > window.innerWidth - 8);
            colorPanel.classList.toggle('rich-editor-swatches--drop-up',
                roomBelow < panelRect.height + 8 && roomAbove > roomBelow);
        });
        colorPanel.querySelectorAll('[data-editor-color]').forEach((button) => {
            button.addEventListener('pointerdown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                editor.focus();
                styleSelection('color', button.dataset.editorColor || '');
                closeColorPanel();
                sync();
            });
        });
        document.addEventListener('click', (event) => {
            if (colorPanel.hidden || event.composedPath().some((node) => node === colorToggle || node === colorPanel)) return;
            closeColorPanel();
        });
    }
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
            <h1 class="h2 mb-2"><?= $isUpdate ? '编辑博客' : '写一篇博客' ?></h1>
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
                    <span class="rich-editor-divider" aria-hidden="true"></span>
                    <div class="rich-editor-group" role="group" aria-label="文字对齐">
                        <?php foreach ($alignments as $alignValue => [$alignLabel, $alignIcon]): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-align="<?= Html::encode($alignValue) ?>"
                                    title="<?= Html::encode($alignLabel) ?>" aria-label="<?= Html::encode($alignLabel) ?>">
                                <i class="bi <?= Html::encode($alignIcon) ?>" aria-hidden="true"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="rich-editor-group" role="group" aria-label="字号">
                        <?= Html::dropDownList(null, '', $fontSizes, [
                            'class' => 'form-select form-select-sm',
                            'data-editor-size' => '1',
                            'aria-label' => '字号',
                            'prompt' => '字号',
                        ]) ?>
                    </div>
                    <div class="rich-editor-group rich-editor-color">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-color-toggle
                                aria-haspopup="true" aria-expanded="false" title="文字颜色" aria-label="文字颜色">
                            <i class="bi bi-palette" aria-hidden="true"></i>
                        </button>
                        <div class="rich-editor-swatches" data-editor-color-panel hidden>
                            <?php foreach ($textColors as $colorValue => $colorLabel): ?>
                                <button type="button" class="rich-editor-swatch<?= $colorValue === '' ? ' rich-editor-swatch-reset' : '' ?>"
                                        data-editor-color="<?= Html::encode($colorValue) ?>"
                                        title="<?= Html::encode($colorLabel) ?>" aria-label="<?= Html::encode($colorLabel) ?>"
                                        <?php if ($colorValue !== ''): ?>style="background: <?= Html::encode($colorValue) ?>"<?php endif; ?>></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <label class="btn btn-sm btn-outline-primary mb-0" for="<?= Html::encode($editorImagesId) ?>">插入图片</label>
                </div>
                <input type="file" class="d-none" id="<?= Html::encode($editorImagesId) ?>" name="editor_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-rich-image-input>
                <div class="form-text mt-2">可一次选择单张或多张图片（每张不超过 10 MB）；图片会插入正文光标位置。</div>
                <div class="rich-editor-image-preview" data-rich-image-preview aria-live="polite"></div>
                <div class="rich-editor-surface" contenteditable="true" role="textbox" aria-multiline="true"
                     aria-label="正文"
                     data-rich-editor data-source="<?= Html::encode($contentInputId) ?>"
                     data-mention data-mention-url="<?= Html::encode(Url::to(['/users/suggest'])) ?>"
                     data-placeholder="从这里开始撰写正文……"><?= RichText::purify((string) $model->getAttribute('content')) ?></div>
                <div class="rich-editor-help text-body-secondary mt-2">支持加粗、斜体、标题、引用、列表、链接，以及段落对齐、字号和文字颜色；工具栏在正文滚动时吸附在顶栏下方，随时可用。</div>
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
                <?= Html::a('取消', $isUpdate ? ['/blog/view', 'id' => $post->id] : ['/users/index'], ['class' => 'btn btn-outline-secondary px-4']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
