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

    /** 内容类型的中文名称。 */
    public const TYPE_LABELS = [
        self::TYPE_BLOG => '博客',
        self::TYPE_IMAGES => '图片',
        self::TYPE_MOMENT => '动态',
        self::TYPE_SHORT_VIDEO => '短视频',
    ];

    /** 内容类型对应的控制器（用于 /{controller}/create|update|delete 路由）。 */
    public const TYPE_CONTROLLERS = [
        self::TYPE_BLOG => 'blog',
        self::TYPE_IMAGES => 'images',
        self::TYPE_MOMENT => 'moment',
        self::TYPE_SHORT_VIDEO => 'video',
    ];

    /** 内容类型对应的详情页路由。 */
    public const TYPE_DETAIL_ROUTES = [
        self::TYPE_BLOG => '/blog/view',
        self::TYPE_IMAGES => '/images/view',
        self::TYPE_MOMENT => '/moment/view',
        self::TYPE_SHORT_VIDEO => '/video/view',
    ];

    public const VISIBILITY_PRIVATE = 0;
    public const VISIBILITY_REGISTERED_USERS = 1;
    public const VISIBILITY_PUBLIC = 2;
    public const STATUS_DRAFT = 0;
    public const STATUS_PUBLISHED = 1;

    public static function tableName(): string { return '{{%post}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function init(): void
    {
        parent::init();
        $this->visibility = self::VISIBILITY_PUBLIC;
        $this->status = self::STATUS_PUBLISHED;
    }

    public function rules(): array
    {
        return [
            [['user_id', 'type'], 'required'],
            [['title'], 'required', 'when' => static fn (self $model): bool => $model->type !== self::TYPE_MOMENT],
            [['user_id', 'status', 'visibility'], 'integer'],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            [['visibility'], 'in', 'range' => [
                self::VISIBILITY_PRIVATE,
                self::VISIBILITY_REGISTERED_USERS,
                self::VISIBILITY_PUBLIC,
            ]],
            [['type'], 'string', 'max' => 32],
            [['title'], 'string', 'max' => 200],
        ];
    }

    public function canHaveTags(): bool
    {
        return in_array($this->type, [
            self::TYPE_BLOG,
            self::TYPE_IMAGES,
            self::TYPE_MOMENT,
            self::TYPE_SHORT_VIDEO,
        ], true);
    }

    /**
     * 发布/筛选下拉使用的类型定义，键为控制器名。
     * @return array<string, array{label: string, postType: string}>
     */
    public static function typeDefinitions(): array
    {
        $definitions = [];
        foreach (self::TYPE_LABELS as $type => $label) {
            $definitions[self::TYPE_CONTROLLERS[$type]] = ['label' => $label, 'postType' => $type];
        }

        return $definitions;
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
    public function getTags(): ActiveQuery { return $this->hasMany(Tag::class, ['id' => 'tag_id'])->viaTable('{{%post_tag}}', ['post_id' => 'id']); }
    public function getCategories(): ActiveQuery { return $this->hasMany(Cate::class, ['id' => 'cate_id'])->viaTable('{{%post_cate}}', ['post_id' => 'id']); }
    public function getBlog(): ActiveQuery { return $this->hasOne(BlogPost::class, ['post_id' => 'id']); }
    public function getImages(): ActiveQuery { return $this->hasMany(Image::class, ['post_id' => 'id'])->orderBy(['sort_order' => SORT_ASC]); }
    public function getMoment(): ActiveQuery { return $this->hasOne(Moment::class, ['post_id' => 'id']); }
    public function getShortVideo(): ActiveQuery { return $this->hasOne(ShortVideo::class, ['post_id' => 'id']); }
}
