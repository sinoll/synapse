<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/** A standalone image in a user's image collection; unrelated to blog posts. */
class Image extends \common\models\base\Image
{
    public static function tableName(): string { return '{{%images}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function init(): void
    {
        parent::init();
        $this->sort_order = 0;
    }

    public function rules(): array
    {
        return [
            [['post_id', 'title', 'file_path'], 'required'],
            [['post_id', 'sort_order'], 'integer'],
            [['title'], 'string', 'max' => 200],
            [['file_path'], 'string', 'max' => 1024],
            [['alt_text'], 'string', 'max' => 255],
        ];
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
}
