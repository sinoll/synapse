<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $sender_id
 * @property int $recipient_id
 * @property string $type
 * @property string $content
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $read_at
 * @property int $created_at
 * @property int $updated_at
 * @property User|null $sender
 * @property User|null $recipient
 */
class UserMessage extends \yii\db\ActiveRecord
{
    public const TYPE_PRIVATE = 'private';
    public const TYPE_MENTION = 'mention';

    public string $recipientUsername = '';

    public static function tableName(): string
    {
        return '{{%user_message}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['sender_id', 'recipient_id', 'type', 'content'], 'required'],
            [['sender_id', 'recipient_id', 'source_id', 'read_at', 'created_at', 'updated_at'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_PRIVATE, self::TYPE_MENTION]],
            [['source_type'], 'in', 'range' => [Post::TYPE_IMAGES, Post::TYPE_MOMENT], 'skipOnEmpty' => true],
            [['content'], 'string', 'max' => 5000],
            [['recipientUsername'], 'string', 'max' => 80],
        ];
    }

    public function getSender(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'sender_id']);
    }

    public function getRecipient(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'recipient_id']);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) self::find()
            ->where(['recipient_id' => $userId, 'read_at' => null])
            ->count();
    }
}
