<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\IntegrityException;

/** A user's favorite (收藏) on any post-backed content type. */
class PostFavorite extends ActiveRecord
{
    public static function tableName(): string { return '{{%post_favorite}}'; }

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
            'created_at' => '收藏时间',
        ];
    }

    /**
     * 切换收藏状态，返回切换后的结果（true 为已收藏）。
     * 主键 (user_id, post_id) 保证同一用户对同一内容只有一行。
     */
    public static function toggle(int $userId, Post $post): bool
    {
        $favorite = self::findOne(['user_id' => $userId, 'post_id' => $post->id]);
        if ($favorite !== null) {
            $favorite->delete();

            return false;
        }

        try {
            (new self(['user_id' => $userId, 'post_id' => $post->id]))->save();
        } catch (IntegrityException) {
            // 连点或重复请求时另一个事务已插入同一行，结果同样是已收藏。
        }

        return true;
    }

    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
}
