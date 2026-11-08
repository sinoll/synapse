<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */

use common\models\Comment;
use common\models\PostComment;
use frontend\assets\MentionAsset;
use frontend\controllers\CommentController;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

MentionAsset::register($this);

$identity = Yii::$app->user->identity;
$profileName = $identity !== null ? ($identity->nickname ?: $identity->username) : '';
$profileInitial = $profileName !== '' ? mb_strtoupper(mb_substr($profileName, 0, 1)) : '拾';

$commentTotal = (int) PostComment::find()->where(['post_id' => $post->id])->count();
[$roots, $hasMoreFloors] = PostComment::floorPage((int) $post->id, 1);
$feedback = Yii::$app->session->getFlash(CommentController::FEEDBACK_KEY);

$this->registerCss(<<<'CSS'
.comment-thread { border-radius: var(--radius-lg); }
.comment-thread .card-header { display: flex; align-items: center; gap: .5rem; }
.comment-count { margin-left: auto; color: var(--bs-secondary-color); font-size: .8rem; font-variant-numeric: tabular-nums; }
.comment-composer, .comment-reply-form { display: flex; flex-direction: column; gap: .6rem; }
.comment-reply-form { border-left: 3px solid var(--bs-primary-border-subtle); padding-left: .75rem; margin-top: .5rem; }
.comment-reply-form .form-label { margin-bottom: 0; font-size: .85rem; color: var(--bs-secondary-color); }
.comment-floor { padding: .85rem 0; border-top: 1px solid var(--bs-border-color); }
.comment-floor:first-child { border-top: 0; }
.comment-item { display: flex; gap: .65rem; align-items: flex-start; }
.comment-item-reply { margin-top: .7rem; }
.comment-avatar { width: 2.1rem; height: 2.1rem; flex: 0 0 2.1rem; }
.comment-item-main { flex: 1 1 auto; min-width: 0; }
.comment-item-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; font-size: .85rem; }
.comment-author { font-weight: 700; overflow-wrap: anywhere; }
.comment-time { color: var(--bs-secondary-color); }
.comment-reply-to { margin: .15rem 0 0; font-size: .8rem; color: var(--bs-secondary-color); }
.comment-text { margin: .25rem 0 0; font-size: .95rem; line-height: 1.7; white-space: pre-wrap; overflow-wrap: anywhere; }
.comment-text-hidden { color: var(--bs-secondary-color); font-style: italic; }
.comment-item-actions { display: flex; align-items: center; gap: .75rem; margin-top: .3rem; }
.comment-action { border: 0; background: none; padding: 0; color: var(--bs-secondary-color); font-size: .82rem; font-weight: 600; cursor: pointer; }
.comment-action:hover { color: var(--bs-primary); }
.comment-action-danger:hover { color: var(--bs-danger-text-emphasis); }
.comment-delete-form { display: inline; margin: 0; }
.comment-more { border: 0; background: none; padding: .35rem 0 0; color: var(--bs-primary); font-size: .85rem; font-weight: 600; cursor: pointer; }
.comment-more:hover { text-decoration: underline; }
CSS);

$createAction = Url::to(['/comment/create']);
?>
<section class="comment-thread card moment-side-card border-0 shadow-sm mt-4" id="comments"
         data-feed-url="<?= Html::encode(Url::to(['/comment/feed', 'id' => (int) $post->id, 'page' => '__PAGE__'])) ?>"
         data-replies-url="<?= Html::encode(Url::to(['/comment/replies', 'id' => '__ID__', 'page' => '__PAGE__'])) ?>"
         data-next-page="2"
         aria-label="评论">
    <div class="card-header moment-side-heading">
        <span>评论</span>
        <span class="comment-count"><?= $commentTotal > 0 ? $commentTotal : '' ?></span>
    </div>

    <div class="card-body p-3">
        <?php if (is_array($feedback) && isset($feedback['message'])): ?>
            <div class="alert alert-<?= $feedback['type'] === 'error' ? 'danger' : 'success' ?> py-2 small" role="status">
                <?= Html::encode((string) $feedback['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($identity === null): ?>
            <p class="text-body-secondary small mb-0">
                <?= Html::a('登录', ['/users/login']) ?>后可以发表评论与回复。
            </p>
        <?php else: ?>
            <?php $form = ActiveForm::begin(['action' => $createAction, 'options' => ['class' => 'comment-composer'], 'fieldConfig' => ['template' => "{input}"]]); ?>
            <?= Html::hiddenInput('post_id', (int) $post->id) ?>
            <?= Html::hiddenInput('parent_id', 0) ?>
            <div class="d-flex gap-2 align-items-start">
                <?php if ($identity->avatar): ?>
                    <img class="moment-avatar comment-avatar" src="<?= Html::encode($identity->avatar) ?>" alt="<?= Html::encode($profileName) ?>">
                <?php else: ?>
                    <span class="moment-avatar moment-avatar-fallback comment-avatar" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                <?php endif; ?>
                <?= Html::textarea('content', '', [
                    'class' => 'form-control',
                    'rows' => 2,
                    'maxlength' => Comment::MAX_LENGTH,
                    'placeholder' => '友善地说两句… @ 可提及用户',
                    'aria-label' => '评论内容',
                    'data-mention' => '',
                    'data-mention-url' => Url::to(['/users/suggest']),
                ]) ?>
            </div>
            <div class="d-flex justify-content-end">
                <?= Html::submitButton('发表评论', ['class' => 'btn btn-primary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>

            <?php // 全站只渲染一份回复表单，点「回复」时由脚本移动到对应评论下方。 ?>
            <div class="comment-reply-form d-none" data-comment-reply-form>
                <?php $replyForm = ActiveForm::begin(['action' => $createAction, 'options' => ['class' => 'd-flex flex-column gap-2']]); ?>
                <?= Html::hiddenInput('post_id', (int) $post->id) ?>
                <?= Html::hiddenInput('parent_id', 0, ['data' => ['reply-parent' => '']]) ?>
                <span class="comment-reply-target" data-reply-target></span>
                <?= Html::textarea('content', '', [
                    'class' => 'form-control form-control-sm',
                    'rows' => 2,
                    'maxlength' => Comment::MAX_LENGTH,
                    'placeholder' => '回复内容…',
                    'aria-label' => '回复内容',
                    'data-mention' => '',
                    'data-mention-url' => Url::to(['/users/suggest']),
                ]) ?>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-comment-action="reply-cancel">取消</button>
                    <?= Html::submitButton('回复', ['class' => 'btn btn-primary btn-sm']) ?>
                </div>
                <?php ActiveForm::end(); ?>
            </div>
        <?php endif; ?>

        <div data-comment-floors>
            <?php foreach ($roots as $root): ?>
                <?= $this->render('@frontend/views/comment/_floor', ['root' => $root]) ?>
            <?php endforeach; ?>
        </div>

        <?php if ($roots === []): ?>
            <p class="text-body-secondary small mb-0 mt-3">还没有评论，来做第一个发言的人。</p>
        <?php endif; ?>

        <?php if ($hasMoreFloors): ?>
            <div class="text-center pt-2">
                <button type="button" class="comment-more" data-comment-action="more-floors">加载更多评论</button>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
$this->registerJs(<<<'JS'
(function () {
    const thread = document.getElementById('comments');
    if (!thread) return;

    const floors = thread.querySelector('[data-comment-floors]');
    const replyForm = thread.querySelector('[data-comment-reply-form]');
    const replyParentInput = replyForm ? replyForm.querySelector('[data-reply-parent]') : null;
    const replyTarget = replyForm ? replyForm.querySelector('[data-reply-target]') : null;
    let activeSlot = null;
    let loading = false;

    const urlWith = (template, replacements) => Object.keys(replacements).reduce(
        (url, key) => url.replace(key, encodeURIComponent(replacements[key])), template);

    const closeReplyForm = function () {
        if (!replyForm) return;
        replyForm.classList.add('d-none');
        if (replyParentInput) replyParentInput.value = '0';
        if (replyTarget) replyTarget.textContent = '';
        if (activeSlot) activeSlot.replaceChildren();
        activeSlot = null;
    };

    const openReplyForm = function (button) {
        if (!replyForm || !replyParentInput) return;
        const item = button.closest('[data-comment-node]');
        const slot = item ? item.querySelector('.comment-item-main > .comment-reply-slot') : null;
        if (!slot) return;
        if (activeSlot && activeSlot !== slot) activeSlot.replaceChildren();
        slot.appendChild(replyForm);
        activeSlot = slot;
        replyParentInput.value = button.dataset.node;
        if (replyTarget) replyTarget.textContent = '回复 @' + (button.dataset.name || '');
        replyForm.classList.remove('d-none');
        const textarea = replyForm.querySelector('textarea');
        if (textarea) textarea.focus();
    };

    // 复用既有列表翻页约定：后端返回 JSON 里的 HTML 片段，前端只做插入。
    const appendPage = async function (button, url, isFloorPage) {
        if (loading) return;
        loading = true;
        button.disabled = true;
        button.textContent = '加载中…';
        try {
            const response = await fetch(url, {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            });
            if (!response.ok) throw new Error('comment page request failed');
            const result = await response.json();

            if (isFloorPage) {
                floors.insertAdjacentHTML('beforeend', result.html || '');
                thread.dataset.nextPage = String(Number(thread.dataset.nextPage) + 1);
                if (!result.hasMore) {
                    button.remove();
                    return;
                }
                button.textContent = '加载更多评论';
            } else {
                const container = thread.querySelector('[data-comment-floor="' + button.dataset.node + '"] [data-floor-replies]');
                if (container) container.insertAdjacentHTML('beforeend', result.html || '');
                button.dataset.nextPage = String(Number(button.dataset.nextPage || 2) + 1);
                if (!result.hasMore) {
                    button.remove();
                    return;
                }
                button.textContent = '继续加载回复';
            }
            button.disabled = false;
        } catch (error) {
            button.disabled = false;
            button.textContent = '加载失败，点击重试';
        } finally {
            loading = false;
        }
    };

    thread.addEventListener('click', function (event) {
        const button = event.target.closest('[data-comment-action]');
        if (!button) return;
        const action = button.dataset.commentAction;

        if (action === 'reply') {
            openReplyForm(button);
            return;
        }
        if (action === 'reply-cancel') {
            closeReplyForm();
            return;
        }
        if (action === 'more-replies') {
            appendPage(button, urlWith(thread.dataset.repliesUrl,
                {__ID__: button.dataset.node, __PAGE__: button.dataset.nextPage || '2'}), false);
            return;
        }
        if (action === 'more-floors') {
            appendPage(button, urlWith(thread.dataset.feedUrl, {__PAGE__: thread.dataset.nextPage}), true);
        }
    });
})();
JS);
