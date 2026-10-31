<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

class ShortVideo extends \common\models\base\ShortVideo
{
    public const ORIENTATION_HORIZONTAL = 1;
    public const ORIENTATION_VERTICAL = 2;

    public static function tableName(): string { return '{{%short_video}}'; }

    /** @return array<int, string> 视频方向选项，定义于 params['video.type']。 */
    public static function videoTypeOptions(): array
    {
        return Yii::$app->params['video.type'] ?? [];
    }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function rules(): array
    {
        return [
            [['post_id', 'title', 'video_path'], 'required'],
            [['post_id', 'duration_seconds', 'video_type'], 'integer'],
            [['video_type'], 'default', 'value' => array_key_first(self::videoTypeOptions())],
            [['video_type'], 'in', 'range' => array_keys(self::videoTypeOptions())],
            [['description'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['video_path', 'cover_path'], 'string', 'max' => 500],
        ];
    }

    public function getVideoTypeLabel(): ?string
    {
        return self::videoTypeOptions()[(int) $this->video_type] ?? null;
    }

    public function isVertical(): bool
    {
        return (int) $this->video_type === self::ORIENTATION_VERTICAL;
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
}
