<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\ShortVideo $model */
/** @var common\models\Post $post */
/** @var bool $isUpdate */
/** @var string|null $tagError */

use common\models\Post;
use common\models\ShortVideo;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isUpdate = (bool) ($isUpdate ?? false);
$tagError = $tagError ?? null;
// A failed create leaves the pending placeholder in the model, so only edit
// views may point the previews at stored media.
$existingVideoPath = $isUpdate ? (string) $model->video_path : '';
$existingCoverPath = $isUpdate ? (string) $model->cover_path : '';
$visibilityOptions = [
    Post::VISIBILITY_PRIVATE => '仅自己可见',
    Post::VISIBILITY_REGISTERED_USERS => '登录用户可见',
    Post::VISIBILITY_PUBLIC => '公开',
];
$user = Yii::$app->user->identity;
$author = $user?->nickname ?: $user?->username ?: '我';
$avatar = $user?->avatar;
$avatarInitial = mb_strtoupper(mb_substr($author, 0, 1));
$videoInputId = Html::getInputId($model, 'video_path');
$coverInputId = Html::getInputId($model, 'cover_path');
$this->title = $isUpdate ? '编辑短视频' : '发布短视频';

$this->registerCss(<<<'CSS'
.video-compose { max-width: 680px; margin-inline: auto; }
.video-compose-card { border-radius: 1.25rem; }
.video-compose-main { min-width: 0; }
.video-compose-avatar { width: 3rem; height: 3rem; flex: 0 0 3rem; border-radius: 50%; object-fit: cover; }
.video-compose-avatar-fallback { display: grid; place-items: center; background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); font-weight: 700; }
.video-input { padding: .3rem 0; border: 0; border-bottom: 1px solid transparent; border-radius: 0; background: transparent; box-shadow: none; font-size: 1.25rem; font-weight: 600; }
.video-input:focus { border-bottom-color: var(--bs-primary); background: transparent; box-shadow: none; }
.video-input::placeholder, .video-textarea::placeholder { color: var(--bs-secondary-color); font-weight: 400; }
.video-input.is-valid, .video-input.is-invalid,
.video-textarea.is-valid, .video-textarea.is-invalid { padding-right: 0; background-image: none; }
.video-input.is-valid, .video-textarea.is-valid,
.video-input.is-valid:focus, .video-textarea.is-valid:focus { border-color: transparent; box-shadow: none; }
.video-input.is-invalid, .video-textarea.is-invalid { border-bottom-color: var(--bs-form-invalid-border-color); }
.video-textarea { min-height: 5.5rem; padding: .3rem 0; border: 0; background: transparent; box-shadow: none; resize: none; font-size: 1rem; line-height: 1.8; }
.video-textarea:focus { background: transparent; box-shadow: none; }
.video-drop { display: flex; flex-direction: column; align-items: center; gap: .2rem; margin-top: .75rem; padding: 1.4rem 1rem; border: 1px dashed var(--bs-border-color); border-radius: .9rem; color: var(--bs-secondary-color); text-align: center; cursor: pointer; transition: border-color .15s ease, background-color .15s ease, color .15s ease; }
.video-drop:hover { border-color: var(--bs-primary); color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .04); }
.video-drop-icon { font-size: 1.6rem; }
.video-drop-name { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.video-preview { display: block; width: 100%; max-height: 340px; margin-top: .75rem; border-radius: .9rem; background: #000; }
.video-compose-footer { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: 1rem; padding-top: .9rem; border-top: 1px solid var(--bs-border-color); }
.video-tool { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; padding: 0; border: 0; border-radius: 50%; color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .08); font-size: 1.1rem; }
.video-tool:hover, .video-tool:focus-visible { background: rgba(var(--bs-primary-rgb), .16); }
.video-cover-thumb { width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem; border-radius: .5rem; object-fit: cover; }
.video-visibility { width: auto; max-width: 9.5rem; border-color: transparent; background-color: transparent; color: var(--bs-secondary-color); font-size: .875rem; }
.video-publish { border-radius: 999px; padding-inline: 1.6rem; }
@media (max-width: 575.98px) {
    .video-compose { padding-left: .25rem; padding-right: .25rem; }
    .video-compose-avatar { width: 2.5rem; height: 2.5rem; flex-basis: 2.5rem; }
    .video-input { font-size: 1.1rem; }
}
CSS);

$this->registerJs(<<<'JS'
document.querySelectorAll('[data-video-form]').forEach((form) => {
    const videoInput = form.querySelector('[data-video-input]');
    const video = form.querySelector('[data-video-preview]');
    const name = form.querySelector('[data-video-name]');
    const coverInput = form.querySelector('[data-cover-input]');
    const cover = form.querySelector('[data-cover-preview]');
    const duration = form.querySelector('[data-video-duration]');
    const type = form.querySelector('[data-video-type]');
    if (!videoInput || !video) {
        return;
    }

    coverInput?.addEventListener('change', () => {
        const file = coverInput.files?.[0];
        if (!file) {
            return;
        }
        const url = URL.createObjectURL(file);
        if (cover) {
            cover.src = url;
            cover.classList.remove('d-none');
        }
        video.poster = url;
    });

    videoInput.addEventListener('change', () => {
        const file = videoInput.files?.[0];
        if (!file) {
            return;
        }
        if (name) {
            name.textContent = file.name;
        }
        if (!coverInput?.files?.length) {
            // A new video invalidates the old cover; the server generates a new one.
            video.removeAttribute('poster');
            if (cover) {
                cover.removeAttribute('src');
                cover.classList.add('d-none');
            }
        }
        video.src = URL.createObjectURL(file);
        video.classList.remove('d-none');
        video.load();
    });

    video.addEventListener('loadedmetadata', () => {
        if (duration) {
            duration.value = Number.isFinite(video.duration) ? String(Math.round(video.duration)) : '0';
        }
        if (type) {
            type.value = video.videoHeight > video.videoWidth
                ? form.dataset.videoTypeVertical
                : form.dataset.videoTypeHorizontal;
        }
    });
});
JS);
?>
<div class="video-compose container py-3 py-md-4">
    <div class="card video-compose-card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <?php $form = ActiveForm::begin([
                'options' => [
                    'class' => 'video-compose-form',
                    'enctype' => 'multipart/form-data',
                    'data-video-form' => '1',
                    'data-video-type-vertical' => ShortVideo::ORIENTATION_VERTICAL,
                    'data-video-type-horizontal' => ShortVideo::ORIENTATION_HORIZONTAL,
                ],
            ]); ?>
            <div class="d-flex gap-3">
                <?php if ($avatar): ?>
                    <img class="video-compose-avatar" src="<?= Html::encode($avatar) ?>" alt="<?= Html::encode($author) ?>">
                <?php else: ?>
                    <span class="video-compose-avatar video-compose-avatar-fallback" aria-hidden="true"><?= Html::encode($avatarInitial) ?></span>
                <?php endif; ?>

                <div class="video-compose-main flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="fw-semibold"><?= $isUpdate ? '编辑短视频' : '发布短视频' ?></span>
                        <span class="text-body-secondary small text-nowrap">输入 #话题# 添加标签</span>
                    </div>

                    <?= $form->field($model, 'title', ['options' => ['class' => 'mb-2']])->textInput([
                        'class' => 'form-control video-input',
                        'maxlength' => true,
                        'placeholder' => '标题',
                        'aria-label' => '标题',
                    ])->label(false) ?>

                    <?= $form->field($model, 'description', ['options' => ['class' => 'mb-1']])->textarea([
                        'class' => 'form-control video-textarea',
                        'rows' => 3,
                        'placeholder' => '说点什么… 输入 #话题# 添加标签',
                        'aria-label' => '视频简介',
                    ])->label(false) ?>

                    <label class="video-drop" for="<?= Html::encode($videoInputId) ?>">
                        <i class="bi bi-camera-video video-drop-icon" aria-hidden="true"></i>
                        <span class="fw-semibold video-drop-name" data-video-name><?= $isUpdate && $existingVideoPath !== '' ? '重新选择视频' : '选择视频文件' ?></span>
                        <span class="small">MP4 / WebM / MOV / OGG · 最大 500 MB</span>
                    </label>
                    <?= $form->field($model, 'video_path', [
                        'options' => ['class' => 'mb-0'],
                        'enableClientValidation' => !$isUpdate,
                    ])->fileInput([
                        'class' => 'd-none',
                        'accept' => 'video/*,.mp4,.webm,.mov,.ogv,.ogg',
                        'data-video-input' => '1',
                    ])->label(false) ?>

                    <video class="video-preview<?= $existingVideoPath !== '' ? '' : ' d-none' ?>" controls playsinline preload="metadata"
                           data-video-preview<?= $existingVideoPath !== '' ? ' src="' . Html::encode($existingVideoPath) . '"' : '' ?><?= $existingCoverPath !== '' ? ' poster="' . Html::encode($existingCoverPath) . '"' : '' ?>></video>

                    <?= $form->field($model, 'duration_seconds', ['options' => ['class' => 'd-none']])->hiddenInput(['data-video-duration' => '1'])->label(false) ?>
                    <?= $form->field($model, 'video_type', ['options' => ['class' => 'd-none']])->hiddenInput(['data-video-type' => '1'])->label(false) ?>

                    <div class="video-compose-footer">
                        <label class="video-tool mb-0" for="<?= Html::encode($coverInputId) ?>" title="自定义封面" aria-label="自定义封面">
                            <i class="bi bi-image" aria-hidden="true"></i>
                        </label>
                        <?= $form->field($model, 'cover_path', ['options' => ['class' => 'mb-0']])->fileInput([
                            'class' => 'd-none',
                            'accept' => 'image/*',
                            'data-cover-input' => '1',
                        ])->label(false) ?>
                        <img class="video-cover-thumb<?= $existingCoverPath !== '' ? '' : ' d-none' ?>" data-cover-preview alt="封面预览"<?= $existingCoverPath !== '' ? ' src="' . Html::encode($existingCoverPath) . '"' : '' ?>>

                        <div class="ms-auto d-flex align-items-center gap-2">
                            <?= Html::dropDownList($post->formName() . '[visibility]', (int) $post->visibility, $visibilityOptions, [
                                'class' => 'form-select form-select-sm video-visibility',
                                'aria-label' => '可见范围',
                            ]) ?>
                            <?= Html::a('取消', $isUpdate ? ['/content/view', 'id' => $post->id] : ['/content/index'], ['class' => 'btn btn-link btn-sm text-body-secondary text-decoration-none px-1']) ?>
                            <?= Html::submitButton($isUpdate ? '保存' : '发布', ['class' => 'btn btn-primary video-publish']) ?>
                        </div>
                    </div>
                    <?php if ($tagError !== null): ?><div class="text-danger small mt-2"><?= Html::encode($tagError) ?></div><?php endif; ?>
                </div>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
