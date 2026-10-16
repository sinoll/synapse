<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

class Moment extends \common\models\base\Moment
{
    public static function tableName(): string { return '{{%moment}}'; }
    public function behaviors(): array { return [TimestampBehavior::class]; }
    public function rules(): array { return [[['post_id', 'content'], 'required'], [['post_id'], 'integer'], [['content'], 'string', 'max' => 5000], [['image_paths'], 'safe']]; }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
}
