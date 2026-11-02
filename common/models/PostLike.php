<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\IntegrityException;

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

    /**
     * 切换点赞，并在同一事务里同步 post.like_count。
     *
     * @return array{liked: bool, count: int} 切换后的状态与最新点赞数
     */
    public static function toggle(int $userId, Post $post): array
    {
        $transaction = self::getDb()->beginTransaction();
        try {
            $like = self::findOne(['user_id' => $userId, 'post_id' => $post->id]);
            if ($like === null) {
                (new self(['user_id' => $userId, 'post_id' => $post->id]))->save();
                $post->updateCounters(['like_count' => 1]);
                $liked = true;
            } else {
                $like->delete();
                $post->updateCounters(['like_count' => -1]);
                $liked = false;
            }
            $transaction->commit();
        } catch (IntegrityException) {
            // 连点并发：另一个请求已插入同一行，计数交给它维护，这里只回读状态。
            $transaction->rollBack();
            $post->refresh();
            $liked = self::find()->where(['user_id' => $userId, 'post_id' => $post->id])->exists();
        }

        return ['liked' => $liked, 'count' => max(0, (int) $post->like_count)];
    }
}
