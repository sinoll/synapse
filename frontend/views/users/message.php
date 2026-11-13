<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use common\models\UserMessage;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;

$this->title = '我的消息';
?>

<div class="user-center py-3 py-md-4">
    <div class="row g-4 align-items-start">
        <aside class="col-12 col-lg-3">
            <?= $this->render('_sidebar', ['active' => 'message']) ?>
        </aside>

        <section class="col-lg-9" aria-labelledby="user-messages-title">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="h3 mb-1" id="user-messages-title">我的消息</h1>
                    <p class="text-body-secondary mb-0">私信和提及你的内容提醒都会显示在这里。</p>
                </div>
                <?= Html::a('发送私信', ['/message/send'], ['class' => 'btn btn-primary']) ?>
            </div>

            <?php if ($dataProvider->getCount() === 0): ?>
                <div class="alert alert-light border">暂时没有消息。</div>
            <?php else: ?>
                <div class="list-group mb-4">
                    <?php foreach ($dataProvider->getModels() as $message): ?>
                        <?php
                        $isIncoming = (int) $message->recipient_id === (int) Yii::$app->user->id;
                        $otherUser = $isIncoming ? $message->sender : $message->recipient;
                        $name = $otherUser?->nickname ?: $otherUser?->username ?: '用户';
                        $isUnread = $isIncoming && $message->read_at === null;
                        ?>
                        <?= Html::a(
                            '<div class="d-flex justify-content-between gap-3">'
                            . '<strong>' . Html::encode($name) . ($isIncoming ? ' 发来' : ' · 已发送') . '</strong>'
                            . '<small class="text-body-secondary text-nowrap">' . Html::encode(date('Y-m-d H:i', (int) $message->created_at)) . '</small>'
                            . '</div><div class="d-flex justify-content-between align-items-center gap-3 mt-1">'
                            . '<span class="text-body-secondary">' . Html::encode(mb_strimwidth((string) $message->content, 0, 180, '…', 'UTF-8')) . '</span>'
                            . ($isUnread ? '<span class="badge text-bg-danger">未读</span>' : '')
                            . '</div>',
                            ['/message/view', 'id' => $message->id],
                            ['class' => 'list-group-item list-group-item-action', 'encode' => false]
                        ) ?>
                    <?php endforeach; ?>
                </div>
                <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
            <?php endif; ?>
        </section>
    </div>
</div>
