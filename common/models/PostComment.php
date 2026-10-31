<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** A comment or reply on any post-backed content type. */
class PostComment extends ActiveRecord
{
    public const STATUS_HIDDEN = 0;
    public const STATUS_VISIBLE = 1;

    public static function tableName(): string { return '{{%post_comment}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function init(): void
    {
        parent::init();
        $this->status = self::STATUS_VISIBLE;
    }

    public function rules(): array
    {
        return [
            [['post_id', 'user_id', 'content'], 'required'],
            [['post_id', 'user_id', 'parent_id', 'status'], 'integer'],
            [['content'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_HIDDEN, self::STATUS_VISIBLE]],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => self::class, 'targetAttribute' => ['parent_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'post_id' => '内容',
            'user_id' => '用户',
            'parent_id' => '回复的评论',
            'content' => '评论内容',
            'status' => '状态',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }

    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
    public function getParent(): ActiveQuery { return $this->hasOne(self::class, ['id' => 'parent_id']); }
    public function getReplies(): ActiveQuery { return $this->hasMany(self::class, ['parent_id' => 'id'])->orderBy(['created_at' => SORT_ASC]); }
}
