<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\UserMessage $message */

use common\models\Post;
use common\models\UserMessage;
use yii\helpers\Html;

$this->title = '查看消息';
$isIncoming = (int) $message->recipient_id === (int) Yii::$app->user->id;
$otherUser = $isIncoming ? $message->sender : $message->recipient;
$name = $otherUser?->nickname ?: $otherUser?->username ?: '用户';
?>

<div class="py-3 py-md-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <div>
                    <h1 class="h4 mb-1"><?= Html::encode($message->type === UserMessage::TYPE_MENTION ? '提及提醒' : '私信') ?></h1>
                    <p class="text-body-secondary mb-0">
                        <?= $isIncoming ? '来自 ' : '发送给 ' ?><?= Html::encode($name) ?>
                        · <?= Html::encode(date('Y-m-d H:i', (int) $message->created_at)) ?>
                    </p>
                </div>
                <?= Html::a('返回消息列表', ['/users/message'], ['class' => 'btn btn-outline-secondary btn-sm align-self-start']) ?>
            </div>

            <div class="border-top pt-3 mb-4" style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= Html::encode((string) $message->content) ?></div>

            <?php if ($message->type === UserMessage::TYPE_MENTION && $message->source_type !== null && $message->source_id !== null): ?>
                <?php $detailRoute = Post::TYPE_DETAIL_ROUTES[$message->source_type] ?? null; ?>
                <?php if ($detailRoute !== null): ?>
                    <?= Html::a('查看提及内容', [$detailRoute, 'id' => (int) $message->source_id], ['class' => 'btn btn-primary']) ?>
                <?php endif; ?>
            <?php elseif ($isIncoming): ?>
                <?= Html::a('回复私信', ['/message/send', 'to' => (int) $message->sender_id], ['class' => 'btn btn-primary']) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
