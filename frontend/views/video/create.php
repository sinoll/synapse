<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\ShortVideo $model */
/** @var common\models\Post $post */
/** @var bool $isUpdate */
/** @var string[] $selectedTagNames */
/** @var string|null $tagError */

use common\models\Post;
use common\models\ShortVideo;
use common\models\Tag;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isUpdate = $isUpdate ?? false;
$selectedTagNames = $selectedTagNames ?? ($isUpdate
    ? array_map(static fn (Tag $tag): string => $tag->name, $post->tags)
    : []);
$tagError = $tagError ?? null;
$existingVideoPath = $isUpdate ? (string) $model->video_path : '';
$existingCoverPath = $isUpdate ? (string) $model->cover_path : '';
$availableTagNames = Tag::find()->select(['name'])->orderBy(['name' => SORT_ASC])->limit(100)->column();
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];
$this->title = $isUpdate ? '编辑短视频' : '发布短视频';

$this->registerCss(<<<'CSS'
.content-compose { max-width: 980px; margin-inline: auto; }
.content-compose-mark { display: grid; width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; place-items: center; border-radius: 1rem; color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .1); font-size: 1.5rem; font-weight: 700; }
.content-compose-card { border-radius: 1rem; }
.content-compose-card .card-body { padding: clamp(1rem, 4vw, 2.25rem); }
.content-tag-picker { margin: 1.25rem 0; }
.content-tag-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .65rem; }
.content-tag-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border: 1px solid rgba(var(--bs-primary-rgb), .2); border-radius: 999px; color: var(--bs-primary-text-emphasis); background: rgba(var(--bs-primary-rgb), .08); }
.content-tag-chip button { padding: 0; border: 0; color: inherit; background: transparent; font-size: 1.1rem; line-height: 1; }
.video-upload-preview { display: grid; gap: 1rem; grid-template-columns: minmax(0, 2fr) minmax(180px, 1fr); }
.video-upload-preview video, .video-upload-preview img { width: 100%; max-height: 360px; object-fit: contain; border: 1px solid var(--bs-border-color); border-radius: .75rem; background: #191512; }
@media (max-width: 575.98px) {
    .video-upload-preview { grid-template-columns: 1fr; }
    .content-compose { padding-left: .25rem; padding-right: .25rem; }
    .content-compose-actions > .btn { flex: 1 1 100%; }
}
CSS);

$this->registerJs(<<<'JS'
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
?>
<div class="content-compose container-fluid py-3 py-md-5">
    <div class="d-flex align-items-start gap-3 mb-3 mb-md-4">
        <div class="content-compose-mark" aria-hidden="true">▶</div>
        <div>
            <p class="text-uppercase small text-body-secondary mb-1">短视频</p>
            <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
            <p class="text-body-secondary mb-0">上传视频即可预览，时长自动读取，封面可自定义或使用第一帧。</p>
        </div>
    </div>

    <div class="card content-compose-card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['options' => ['class' => 'content-compose-form', 'enctype' => 'multipart/form-data']]); ?>

            <?= $form->field($model, 'title')->label('标题')->textInput(['maxlength' => true]) ?>
            <div class="mb-3">
                <?= $form->field($model, 'description', ['options' => ['class' => 'mb-1']])->label('描述')->textarea([
                    'rows' => 5,
                ]) ?>
                <div class="form-text">用简短文字说明这项内容。</div>
            </div>

            <?= $form->field($model, 'video_path', [
                // Editing keeps the stored file; a required client rule would
                // block saving when no replacement video is chosen.
                'enableClientValidation' => !$isUpdate,
            ])->label('选择视频')->fileInput([
                'class' => 'form-control',
                'accept' => 'video/*,.mp4,.webm,.mov,.ogv,.ogg',
                'data-video-input' => '1',
            ]) ?>
            <div class="form-text mb-2">
                支持 MP4、WebM、MOV、OGG，最大 500 MB。选择后可立即预览。
                <?= $isUpdate ? '重新选择视频会替换当前视频，并删除旧的视频文件和旧封面缩略图；不选择则保留当前视频。' : '' ?>
            </div>
            <div class="video-upload-preview mb-4">
                <video class="<?= $existingVideoPath !== '' ? '' : 'd-none' ?>" controls playsinline
                       data-video-preview src="<?= Html::encode($existingVideoPath) ?>"></video>
                <div>
                    <p class="small fw-semibold mb-2">封面预览</p>
                    <img class="<?= $existingCoverPath !== '' ? '' : 'd-none' ?>" alt="视频封面预览"
                         data-cover-preview src="<?= Html::encode($existingCoverPath) ?>">
                </div>
            </div>

            <?= $form->field($model, 'cover_path')->label('自定义封面（可选）')->fileInput([
                'class' => 'form-control',
                'accept' => 'image/*',
                'data-cover-input' => '1',
            ]) ?>
            <div class="form-text mb-3">
                <?= $isUpdate
                    ? '重新选择封面会替换当前封面；上传新视频且不选择封面时，自动提取新视频第 5 帧作为封面，旧封面会被删除。'
                    : '不选择自定义封面时，保存后自动提取视频第 5 帧作为封面。' ?>
            </div>

            <?= $form->field($model, 'duration_seconds')->hiddenInput(['data-video-duration' => '1'])->label(false) ?>

            <?= $form->field($model, 'video_type')->label('视频方向')->dropDownList(ShortVideo::videoTypeOptions()) ?>

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
                    'list' => 'video-tag-suggestions',
                    'maxlength' => 50,
                    'autocomplete' => 'off',
                    'placeholder' => '输入标签后按 Enter 添加，也可输入 #话题词#',
                    'data-tag-input' => '1',
                ]) ?>
                <datalist id="video-tag-suggestions">
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
