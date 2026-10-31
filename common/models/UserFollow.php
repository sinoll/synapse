<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** A directed follow relation between two users. */
class UserFollow extends ActiveRecord
{
    public static function tableName(): string { return '{{%user_follow}}'; }

    public function behaviors(): array
    {
        return [[
            'class' => TimestampBehavior::class,
            'attributes' => [self::EVENT_BEFORE_INSERT => ['created_at']],
        ]];
    }

    public function rules(): array
    {
        return [
            [['follower_id', 'following_id'], 'required'],
            [['follower_id', 'following_id'], 'integer'],
            [['follower_id'], 'compare', 'compareAttribute' => 'following_id', 'operator' => '!=', 'message' => '不能关注自己。'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'follower_id' => '关注者',
            'following_id' => '被关注者',
            'created_at' => '关注时间',
        ];
    }

    public function getFollower(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'follower_id']); }
    public function getFollowing(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'following_id']); }
}
