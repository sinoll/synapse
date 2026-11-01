<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\PostComment[] $replies */

foreach ($replies as $reply):
    // 楼层的二级以下回复才需要标出「回复 谁」；直接回复顶级评论的行不显示。
    $replyToName = null;
    if ($reply->depth > 1) {
        $parentAuthor = $reply->parent?->comment?->user;
        if ($parentAuthor !== null) {
            $replyToName = $parentAuthor->nickname ?: $parentAuthor->username;
        }
    }

    echo $this->render('@frontend/views/comment/_item', ['node' => $reply, 'replyToName' => $replyToName]);
endforeach;
