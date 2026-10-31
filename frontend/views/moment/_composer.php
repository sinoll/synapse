<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Moment $model */
/** @var common\models\Post $post */
/** @var bool $isUpdate */
/** @var string|null $contentError */
/** @var int $selectedCateId */
/** @var string|null $cateError */

use common\models\Cate;
use common\models\Post;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\HtmlPurifier;

$isUpdate = (bool) ($isUpdate ?? false);
$contentError = $contentError ?? null;
$selectedCateId = (int) ($selectedCateId ?? 0);
$cateError = $cateError ?? null;
$categoryOptions = Cate::find()->select(['name', 'id'])->orderBy(['name' => SORT_ASC])->indexBy('id')->column();

$user = Yii::$app->user->identity;
$author = $user?->nickname ?: $user?->username ?: '我';
$avatar = $user?->avatar;
$avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
$contentInputId = Html::getInputId($model, 'content');
$editorImagesId = Html::getInputId($model, 'editor_images');
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];

$this->registerCss(<<<'CSS'
.moment-compose { max-width: 680px; margin-inline: auto; }
.moment-compose-card { border-radius: 1.25rem; }
.moment-compose-avatar { width: 3rem; height: 3rem; flex: 0 0 3rem; border-radius: 50%; object-fit: cover; }
.moment-compose-avatar-fallback { display: grid; place-items: center; background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); font-weight: 700; }
.moment-editor { min-height: 9.5rem; padding: .15rem 0; overflow-wrap: anywhere; outline: 0; font-size: 1.0625rem; line-height: 1.8; }
.moment-editor:empty::before { content: attr(data-placeholder); color: var(--bs-secondary-color); pointer-events: none; }
.moment-editor[aria-invalid="true"] { outline: 0; }
.moment-editor > :first-child { margin-top: 0; }
.moment-editor > :last-child { margin-bottom: 0; }
.moment-editor img { max-width: 100%; height: auto; margin: .5rem 0; border-radius: .75rem; }
.moment-editor a { color: var(--bs-primary); }
.moment-editor pre { overflow-x: auto; padding: .85rem 1rem; border-radius: .5rem; background: var(--bs-tertiary-bg); font-family: var(--bs-font-monospace); white-space: pre; }
.moment-editor pre code { color: inherit; }
.moment-image-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: .5rem; margin-top: .75rem; }
.moment-image-preview:empty { display: none; }
.moment-image-preview img { width: 100%; height: 96px; object-fit: cover; border-radius: .65rem; }
.moment-compose-footer { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: 1rem; padding-top: .9rem; border-top: 1px solid var(--bs-border-color); }
.moment-tool { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; padding: 0; border: 0; border-radius: 50%; color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .08); font-size: 1.1rem; }
.moment-tool:hover, .moment-tool:focus-visible { background: rgba(var(--bs-primary-rgb), .16); }
.moment-visibility { width: auto; max-width: 9.5rem; border-color: transparent; background-color: transparent; color: var(--bs-secondary-color); font-size: .875rem; }
.moment-counter { min-width: 1.75rem; color: var(--bs-secondary-color); font-size: .8125rem; text-align: right; }
.moment-publish { border-radius: 999px; padding-inline: 1.6rem; }
@media (max-width: 575.98px) {
    .moment-compose { padding-left: .25rem; padding-right: .25rem; }
    .moment-compose-avatar { width: 2.5rem; height: 2.5rem; flex-basis: 2.5rem; }
    .moment-editor { min-height: 7.5rem; font-size: 1rem; }
}
CSS);

$this->registerJs(<<<'JS'
document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
    const form = editor.closest('form');
    const source = document.getElementById(editor.dataset.source);
    const counter = form.querySelector('[data-moment-counter]');
    const imageInput = form.querySelector('[data-rich-image-input]');
    const imagePreview = form.querySelector('[data-rich-image-preview]');
    let pendingFiles = [];

    const textOf = () => (editor.innerText || '').replace(/\u00a0/g, ' ').trim();
    // 图片占位符不进入编辑器，提交时再追加到正文末尾，由服务端替换为真实图片地址。
    const tokenHtml = () => pendingFiles.length === 0
        ? ''
        : '<p>' + pendingFiles.map((file, index) => `[[EDITOR_IMAGE_${index}]]`).join('') + '</p>';
    const sync = () => {
        source.value = editor.innerHTML + tokenHtml();
        if (counter) {
            counter.textContent = String([...(editor.innerText || '').replace(/\u00a0/g, ' ')].length);
        }
    };
    const hasContent = () => textOf() !== '' || pendingFiles.length > 0 || editor.querySelector('img') !== null;
    const clearEmptyError = () => {
        editor.removeAttribute('aria-invalid');
        form.querySelector('[data-content-required-error]')?.remove();
    };

    form.addEventListener('submit', (event) => {
        sync();
        if (!hasContent()) {
            event.preventDefault();
            editor.setAttribute('aria-invalid', 'true');
            editor.focus();
            let message = form.querySelector('[data-content-required-error]');
            if (!message) {
                message = document.createElement('div');
                message.className = 'text-danger small mt-2';
                message.dataset.contentRequiredError = '1';
                message.textContent = '写点什么再发布吧。';
                editor.insertAdjacentElement('afterend', message);
            }
        }
    });
    editor.addEventListener('input', () => {
        sync();
        if (hasContent()) {
            clearEmptyError();
        }
    });
    sync();

    if (!imageInput || !imagePreview) {
        return;
    }
    imageInput.addEventListener('change', () => {
        pendingFiles = [...imageInput.files];
        imagePreview.replaceChildren(...pendingFiles.map((file) => {
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = file.name;
            image.title = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            image.onload = () => URL.revokeObjectURL(image.src);
            return image;
        }));
        if (pendingFiles.length) {
            clearEmptyError();
        }
        sync();
    });
});
JS);
?>
<div class="moment-compose container py-3 py-md-4">
    <div class="card moment-compose-card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <?php $form = ActiveForm::begin(['options' => ['class' => 'moment-compose-form', 'enctype' => 'multipart/form-data']]); ?>
            <div class="d-flex gap-3">
                <?php if ($avatar): ?>
                    <img class="moment-compose-avatar" src="<?= Html::encode($avatar) ?>" alt="<?= Html::encode($author) ?>">
                <?php else: ?>
                    <span class="moment-compose-avatar moment-compose-avatar-fallback" aria-hidden="true"><?= Html::encode($avatarInitial) ?></span>
                <?php endif; ?>

                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="fw-semibold"><?= $isUpdate ? '编辑动态' : '发布动态' ?></span>
                        <span class="text-body-secondary small text-nowrap">输入 #话题# 添加话题</span>
                    </div>

                    <?php
                    $purifiedContent = HtmlPurifier::process((string) $model->getAttribute('content'), ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title],pre,code']);
                    // 丢弃历史遗留/未成功上传的图片占位符，避免它们被再次提交。
                    $purifiedContent = (string) preg_replace('/\[\[EDITOR_IMAGE_\d+\]\]/', '', $purifiedContent);
                    ?>
                    <textarea id="<?= Html::encode($contentInputId) ?>" name="<?= Html::encode($model->formName()) ?>[content]"
                              class="d-none" aria-hidden="true"><?= $purifiedContent ?></textarea>

                    <div class="moment-editor text-break" contenteditable="true" role="textbox" aria-multiline="true"
                         aria-label="动态内容" aria-required="true" data-rich-editor
                         data-source="<?= Html::encode($contentInputId) ?>"
                         data-placeholder="分享新鲜事……"><?= $purifiedContent ?></div>

                    <?= Html::error($model, 'content', ['class' => 'text-danger small mt-2']) ?>
                    <?php if ($contentError !== null): ?><div class="text-danger small mt-2"><?= Html::encode($contentError) ?></div><?php endif; ?>

                    <div class="moment-image-preview" data-rich-image-preview aria-live="polite"></div>

                    <input type="file" class="d-none" id="<?= Html::encode($editorImagesId) ?>" name="editor_images[]"
                           accept="image/jpeg,image/png,image/gif,image/webp" multiple data-rich-image-input>

                    <div class="moment-compose-footer">
                        <label class="moment-tool mb-0" for="<?= Html::encode($editorImagesId) ?>" title="添加图片" aria-label="添加图片">
                            <i class="bi bi-image" aria-hidden="true"></i>
                        </label>

                        <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-folder2 text-body-secondary small" aria-hidden="true"></i>
                            <?= Html::dropDownList('cate_id', $selectedCateId > 0 ? $selectedCateId : '', $categoryOptions, [
                                'id' => 'moment-cate-select',
                                'class' => 'form-select form-select-sm moment-visibility' . ($cateError !== null ? ' is-invalid' : ''),
                                'aria-label' => '分类',
                                'title' => '分类',
                                'prompt' => '选择分类',
                            ]) ?>
                        </div>

                        <div class="ms-auto d-flex align-items-center gap-2">
                            <?= Html::dropDownList($post->formName() . '[visibility]', (int) $post->visibility, $visibilityOptions, [
                                'class' => 'form-select form-select-sm moment-visibility',
                                'aria-label' => '可见范围',
                            ]) ?>
                            <span class="text-body-secondary small"><span data-moment-counter>0</span>/5000</span>
                            <?= Html::a('取消', $isUpdate ? ['/content/view', 'id' => $post->id] : ['/content/index'], ['class' => 'btn btn-link btn-sm text-body-secondary text-decoration-none px-1']) ?>
                            <?= Html::submitButton($isUpdate ? '保存' : '发布', ['class' => 'btn btn-primary moment-publish']) ?>
                        </div>
                    </div>
                    <?php if ($cateError !== null): ?><div class="text-danger small mt-2"><?= Html::encode($cateError) ?></div><?php endif; ?>
                </div>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
