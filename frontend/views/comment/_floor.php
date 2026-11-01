<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\PostComment $root */

use common\models\PostComment;
use yii\helpers\Html;

// floorReplies 已随楼层一起预加载，折叠展示直接在内存里切片，翻页才走 /comment/replies。
$allReplies = $root->floorReplies;
$inlineReplies = array_slice($allReplies, 0, PostComment::INLINE_REPLIES);
$hasMoreReplies = count($allReplies) > PostComment::INLINE_REPLIES;
?>
<div class="comment-floor" data-comment-floor="<?= (int) $root->id ?>">
    <?= $this->render('@frontend/views/comment/_item', ['node' => $root, 'replyToName' => null]) ?>

    <div class="comment-floor-replies" data-floor-replies>
        <?= $this->render('@frontend/views/comment/_replies', ['replies' => $inlineReplies]) ?>
    </div>

    <?php if ($hasMoreReplies): ?>
        <?= Html::button(
            '查看全部 ' . count($allReplies) . ' 条回复',
            [
                'type' => 'button',
                'class' => 'comment-more',
                'data' => ['comment-action' => 'more-replies', 'node' => (int) $root->id, 'next-page' => 2],
            ],
        ) ?>
    <?php endif; ?>
</div>
