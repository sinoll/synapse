<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\IntegrityException;

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

    /** 关注/取关一次切换，返回最新状态与被关注者的粉丝数。 */
    public static function toggle(int $followerId, User $following): array
    {
        $transaction = self::getDb()->beginTransaction();
        try {
            $row = self::findOne(['follower_id' => $followerId, 'following_id' => (int) $following->id]);
            if ($row === null) {
                (new self(['follower_id' => $followerId, 'following_id' => (int) $following->id]))->save();
                $followed = true;
            } else {
                $row->delete();
                $followed = false;
            }
            $transaction->commit();
        } catch (IntegrityException) {
            // 连点并发：另一个请求已插入同一行（主键冲突），回读真实状态即可。
            $transaction->rollBack();
            $followed = self::find()->where(['follower_id' => $followerId, 'following_id' => (int) $following->id])->exists();
        }

        $count = (int) self::find()->where(['following_id' => (int) $following->id])->count();

        return ['followed' => $followed, 'count' => max(0, $count)];
    }
}
