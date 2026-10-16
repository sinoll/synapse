<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

class ShortVideo extends \common\models\base\ShortVideo
{
    public static function tableName(): string { return '{{%short_video}}'; }
    public function behaviors(): array { return [TimestampBehavior::class]; }
    public function rules(): array { return [[['post_id', 'title', 'video_path'], 'required'], [['post_id', 'duration_seconds'], 'integer'], [['description'], 'string'], [['title'], 'string', 'max' => 200], [['video_path', 'cover_path'], 'string', 'max' => 500]]; }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
}
