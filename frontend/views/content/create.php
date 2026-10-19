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
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\HtmlPurifier;

$isUpdate = $isUpdate ?? false;
$hasRichEditor = in_array($type, ['blog', 'moment'], true);
$hasImageCollection = $type === 'images';
$pageCopy = [
    'blog' => ['icon' => '✎', 'title' => $isUpdate ? '编辑博客' : '写一篇博客', 'intro' => '整理文章结构，添加摘要，并用格式工具排出清晰易读的正文。'],
    'moment' => ['icon' => '◉', 'title' => $isUpdate ? '编辑动态' : '记录一条动态', 'intro' => '分享一段近况或想法；标题会根据动态内容自动生成。'],
    'images' => ['icon' => '▧', 'title' => $isUpdate ? '编辑图片' : '发布图片', 'intro' => '选择一张或多张图片，上传后即可预览图片集。'],
    'video' => ['icon' => '▶', 'title' => $isUpdate ? '编辑短视频' : '发布短视频', 'intro' => '填写视频、封面和说明信息，帮助访问者了解这段视频。'],
    'file' => ['icon' => '↗', 'title' => $isUpdate ? '编辑文件分享' : '分享一个文件', 'intro' => '补充文件说明并设置可见范围，明确哪些人可以访问。'],
];
$pageCopy += ['other' => ['icon' => '＋', 'title' => ($isUpdate ? '编辑' : '创建') . $label, 'intro' => '填写内容信息并设置合适的可见范围。']];
$copy = $pageCopy[$type] ?? $pageCopy['other'];
$this->title = $copy['title'];
$labels = [
    'title' => '标题', 'summary' => '摘要', 'content' => $type === 'moment' ? '动态内容' : '正文',
    'description' => '描述', 'video_path' => '视频文件路径', 'cover_path' => '封面图片路径',
    'file_path' => '文件路径', 'original_name' => '原始文件名', 'mime_type' => 'MIME 类型', 'size_bytes' => '文件大小（字节）',
    'alt_text' => '图片说明', 'duration_seconds' => '时长（秒）', 'image_paths' => '图片路径（JSON）',
];
$fieldHelp = [
    'summary' => '简要介绍文章内容；列表页会优先展示这段摘要。',
    'file_path' => '选择需要上传的图片文件。',
    'video_path' => '填写站点可访问的视频文件路径或完整 URL。',
    'cover_path' => '可选，填写视频封面的资源路径或完整 URL。',
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
.rich-editor-help { font-size: .875rem; }
.image-collection-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .75rem; }
.image-collection-preview:empty { display: none; }
.image-collection-preview figure { margin: 0; overflow: hidden; border: 1px solid var(--bs-border-color); border-radius: .75rem; background: var(--bs-tertiary-bg); }
.image-collection-preview img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.image-collection-preview figcaption { padding: .4rem .55rem; overflow: hidden; color: var(--bs-secondary-color); font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }
@media (max-width: 575.98px) {
    .content-compose { padding-left: .25rem; padding-right: .25rem; }
    .rich-editor-surface { min-height: 220px; padding: .75rem; }
    .rich-editor-toolbar { gap: .3rem; padding: .5rem; }
    .rich-editor-toolbar .btn { min-width: 2.25rem; padding: .35rem .55rem; }
    .content-compose-actions > .btn { flex: 1 1 100%; }
}
CSS);

if ($hasRichEditor || $hasImageCollection) {
    $this->registerJs(<<<'JS'
document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
    const source = document.getElementById(editor.dataset.source);
    const form = editor.closest('form');
    const sync = () => { source.value = editor.innerHTML; };
    editor.addEventListener('input', sync);
    editor.closest('.rich-editor-wrap').querySelectorAll('[data-editor-command]').forEach((button) => {
        button.addEventListener('pointerdown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand(button.dataset.editorCommand, false, button.dataset.editorValue || null);
            sync();
        });
    });
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
    input.addEventListener('input', renderPreview);
    renderPreview();
});
JS);
}
?>
<div class="content-compose container-fluid py-3 py-md-5">
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
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-link>插入链接</button>
                        </div>
                        <div class="rich-editor-surface" contenteditable="true" role="textbox" aria-multiline="true"
                             aria-label="<?= Html::encode($labels[$field] ?? $field) ?>"
                             data-rich-editor data-source="<?= Html::encode($inputId) ?>"
                             data-placeholder="<?= $type === 'moment' ? '写下此刻的想法……' : '从这里开始撰写正文……' ?>"><?= HtmlPurifier::process((string) $model->getAttribute($field), ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span']) ?></div>
                        <div class="rich-editor-help text-body-secondary mt-2">支持加粗、斜体、标题、引用、列表和链接；手机上可横向换行使用工具栏。</div>
                    </div>
                <?php elseif ($hasImageCollection && $field === 'file_path'): ?>
                    <?= $form->field($model, $field)->label('选择图片')->fileInput([
                        'class' => 'form-control image-collection-input',
                        'multiple' => true,
                        'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                        'data-image-collection' => '1',
                        'data-preview' => 'image-collection-preview',
                    ]) ?>
                    <div class="form-text mb-2">可同时选择一张或多张图片，支持 JPG、PNG、GIF、WebP，单张不超过 10 MB。</div>
                    <div id="image-collection-preview" class="image-collection-preview mb-3" aria-live="polite"></div>
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

            <?= $form->field($post, 'visibility')->label('可见范围')->dropDownList($visibilityOptions) ?>
            <?php if ($type === 'file'): ?>
                <p class="small text-body-secondary">请填写已部署的资源路径；此表单目前不提供文件上传。</p>
                <?= Html::label('授权用户名（用逗号、空格或换行分隔）', 'recipient_usernames', ['class' => 'form-label']) ?>
                <?= Html::textInput('recipient_usernames', $recipientUsernames, ['id' => 'recipient_usernames', 'class' => 'form-control mb-3', 'autocomplete' => 'off', 'placeholder' => '仅“指定用户可见”时需要填写']) ?>
            <?php elseif ($type === 'video'): ?>
                <p class="small text-body-secondary">请填写已部署的资源路径；此表单目前不提供文件上传。</p>
            <?php endif; ?>

            <div class="content-compose-actions d-flex flex-column flex-sm-row gap-2 mt-4">
                <?= Html::submitButton($isUpdate ? '保存修改' : '发布内容', ['class' => 'btn btn-primary px-4']) ?>
                <?= Html::a('取消', $isUpdate ? ['/content/view', 'id' => $post->id] : ['/content/index'], ['class' => 'btn btn-outline-secondary px-4']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
