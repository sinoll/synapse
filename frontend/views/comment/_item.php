<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\PostComment $node */
/** @var string|null $replyToName 楼中楼里被回复者的昵称，顶级评论传 null */

use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$author = $node->comment?->user;
$authorName = $author !== null ? ($author->nickname ?: $author->username) : '已注销用户';
$initial = mb_strtoupper(mb_substr($authorName, 0, 1));
$createdAt = (int) $node->created_at;
$canManage = $node->canManage($identity);
?>
<div class="comment-item<?= $node->isRoot() ? ' comment-item-root' : ' comment-item-reply' ?>"
     data-comment-node="<?= (int) $node->id ?>">
    <?php if ($author !== null && $author->avatar): ?>
        <img class="moment-avatar comment-avatar" src="<?= Html::encode($author->avatar) ?>" alt="<?= Html::encode($authorName) ?>">
    <?php else: ?>
        <span class="moment-avatar moment-avatar-fallback comment-avatar" aria-hidden="true"><?= Html::encode($initial) ?></span>
    <?php endif; ?>

    <div class="comment-item-main">
        <div class="comment-item-head">
            <span class="comment-author"><?= Html::encode($authorName) ?></span>
            <time class="comment-time" datetime="<?= Html::encode(date(DATE_ATOM, $createdAt)) ?>"><?= Yii::$app->formatter->asRelativeTime($createdAt) ?></time>
        </div>

        <?php if ($replyToName !== null): ?>
            <p class="comment-reply-to">回复 <span><?= Html::encode($replyToName) ?></span></p>
        <?php endif; ?>

        <?php if ($node->isVisible()): ?>
            <div class="comment-text"><?= Html::encode((string) $node->comment->content) ?></div>
        <?php else: ?>
            <div class="comment-text comment-text-hidden">该评论已被隐藏。</div>
        <?php endif; ?>

        <div class="comment-item-actions">
            <?php if ($identity !== null): ?>
                <button type="button" class="comment-action" data-comment-action="reply"
                        data-node="<?= (int) $node->id ?>" data-name="<?= Html::encode($authorName) ?>">回复</button>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <?= Html::beginForm(['/comment/delete'], 'post', ['class' => 'comment-delete-form', 'onsubmit' => 'return confirm("确定删除这条评论？删除后无法恢复。")']) ?>
                <?= Html::hiddenInput('id', (int) $node->id) ?>
                <?= Html::submitButton('删除', ['class' => 'comment-action comment-action-danger']) ?>
                <?= Html::endForm() ?>
            <?php endif; ?>
        </div>

        <div class="comment-reply-slot" aria-live="polite"></div>
    </div>
</div>
