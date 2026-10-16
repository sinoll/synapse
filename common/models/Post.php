<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/** Common content entry shared by all content types. */
class Post extends \common\models\base\Post
{
    public const TYPE_BLOG = 'blog';
    public const TYPE_IMAGES = 'images';
    public const TYPE_MOMENT = 'moment';
    public const TYPE_SHORT_VIDEO = 'short_video';
    public const TYPE_SHARED_FILE = 'shared_file';

    public const VISIBILITY_PRIVATE = 0;
    public const VISIBILITY_REGISTERED_USERS = 1;
    public const VISIBILITY_PUBLIC = 2;
    public const VISIBILITY_SELECTED_USERS = 3;
    public const STATUS_DRAFT = 0;
    public const STATUS_PUBLISHED = 1;

    public static function tableName(): string { return '{{%post}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function init(): void
    {
        parent::init();
        $this->visibility = self::VISIBILITY_PRIVATE;
        $this->status = self::STATUS_PUBLISHED;
    }

    public function rules(): array
    {
        return [
            [['user_id', 'type', 'title'], 'required'],
            [['user_id', 'status', 'visibility'], 'integer'],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            [['visibility'], 'in', 'range' => [
                self::VISIBILITY_PRIVATE,
                self::VISIBILITY_REGISTERED_USERS,
                self::VISIBILITY_PUBLIC,
                self::VISIBILITY_SELECTED_USERS,
            ]],
            [['type'], 'string', 'max' => 32],
            [['title'], 'string', 'max' => 200],
            [['visibility'], 'validateVisibilityForType'],
        ];
    }

    public function validateVisibilityForType(string $attribute): void
    {
        if ($this->visibility === self::VISIBILITY_SELECTED_USERS && $this->type !== self::TYPE_SHARED_FILE) {
            $this->addError($attribute, '指定用户可见目前仅适用于文件分享。');
        }
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
    public function getBlog(): ActiveQuery { return $this->hasOne(BlogPost::class, ['post_id' => 'id']); }
    public function getImages(): ActiveQuery { return $this->hasMany(Image::class, ['post_id' => 'id'])->orderBy(['sort_order' => SORT_ASC]); }
    public function getMoment(): ActiveQuery { return $this->hasOne(Moment::class, ['post_id' => 'id']); }
    public function getShortVideo(): ActiveQuery { return $this->hasOne(ShortVideo::class, ['post_id' => 'id']); }
    public function getSharedFile(): ActiveQuery { return $this->hasOne(SharedFile::class, ['post_id' => 'id']); }
}
