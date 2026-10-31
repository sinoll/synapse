<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** A user's like on any post-backed content type. */
class PostLike extends ActiveRecord
{
    public static function tableName(): string { return '{{%post_like}}'; }

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
            [['post_id', 'user_id'], 'required'],
            [['post_id', 'user_id'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'post_id' => '内容',
            'user_id' => '用户',
            'created_at' => '点赞时间',
        ];
    }

    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
}
