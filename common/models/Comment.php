<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * 评论内容本体：只描述「谁说了什么」，不关心它挂在哪条内容下。
 * 会话结构（所属内容、父子回复关系）在 {@see PostComment} 上。
 */
class Comment extends ActiveRecord
{
    public const STATUS_HIDDEN = 0;
    public const STATUS_VISIBLE = 1;

    public static function tableName(): string { return '{{%comment}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function init(): void
    {
        parent::init();
        $this->status = self::STATUS_VISIBLE;
    }

    public function rules(): array
    {
        return [
            [['user_id', 'content'], 'required'],
            [['user_id', 'status'], 'integer'],
            [['content'], 'string'],
            [['content'], 'trim'],
            [['status'], 'in', 'range' => [self::STATUS_HIDDEN, self::STATUS_VISIBLE]],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => '评论作者',
            'content' => '评论内容',
            'status' => '状态',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }

    /** 该正文在各条内容下的挂载记录。 */
    public function getPostComments(): ActiveQuery { return $this->hasMany(PostComment::class, ['comment_id' => 'id']); }

    /** 挂载了该正文的内容。 */
    public function getPosts(): ActiveQuery
    {
        return $this->hasMany(Post::class, ['id' => 'post_id'])->viaTable('{{%post_comment}}', ['comment_id' => 'id']);
    }

    public function isVisible(): bool { return (int) $this->status === self::STATUS_VISIBLE; }

    /**
     * 回收没有任何挂载记录引用的正文。
     * 数据库级联删除（内容 → 挂载记录）不会反向删除正文，也不会触发模型事件，
     * 所以正文的回收要么走 {@see PostComment::afterDelete()}，要么由这里兜底清理。
     */
    public static function purgeUnbound(): int
    {
        $boundIds = (new Query())->select('comment_id')->from(PostComment::tableName())->where(['not', ['comment_id' => null]]);

        return (int) static::deleteAll(['not', ['id' => $boundIds]]);
    }
}
