<?php

declare(strict_types=1);

namespace frontend\components;

use common\models\Post;
use common\models\User;
use common\models\UserMessage;

final class MentionNotifier
{
    public static function notify(string $content, Post $post): void
    {
        if ((int) $post->visibility === Post::VISIBILITY_PRIVATE) {
            return;
        }

        $senderId = (int) $post->user_id;
        $sourceType = (string) $post->type;
        $sourceId = (int) $post->id;
        $mentionedUserIds = array_diff(Mention::mentionedUserIds($content), [$senderId]);
        $sender = User::findOne($senderId);
        $senderName = trim((string) ($sender?->nickname ?: $sender?->username)) ?: '有人';
        $kind = $sourceType === Post::TYPE_IMAGES ? '图集中' : '动态中';
        $title = trim((string) $post->title);
        $messageContent = $senderName . '在' . $kind . '提到了你' . ($title !== '' ? '：「' . $title . '」' : '。');

        foreach ($mentionedUserIds as $recipientId) {
            $alreadyNotified = UserMessage::find()
                ->where([
                    'type' => UserMessage::TYPE_MENTION,
                    'recipient_id' => $recipientId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ])
                ->exists();
            if ($alreadyNotified) {
                continue;
            }

            $message = new UserMessage();
            $message->sender_id = $senderId;
            $message->recipient_id = $recipientId;
            $message->type = UserMessage::TYPE_MENTION;
            $message->content = $messageContent;
            $message->source_type = $sourceType;
            $message->source_id = $sourceId;
            if (!$message->save()) {
                throw new \RuntimeException('提及消息保存失败：' . json_encode($message->getErrors(), JSON_UNESCAPED_UNICODE));
            }
        }
    }
}
