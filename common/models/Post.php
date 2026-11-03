<?php

declare(strict_types=1);

namespace common\models;

use Yii;
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
        self::TYPE_MOMENT => '动态',
        self::TYPE_IMAGES => '图集',        
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

    /** @var int|null 列表查询附加的收藏时间，不是 post 表字段（见 FavoriteController）。 */
    public $favoriteAt;
    /** @var int|null 列表查询附加的点赞时间，不是 post 表字段（见 LikeController）。 */
    public $likedAt;

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

    /**
     * 按访客身份限定可见范围，口径与 frontend\controllers\ContentVisibilityTrait::canViewContent() 一致。
     * 各控制器里散落的同名私有过滤逻辑以此为准。
     */
    public static function applyVisibilityScope(ActiveQuery $query, ?int $userId, string $alias = 'p'): void
    {
        if ($userId === null) {
            $query->andWhere([$alias . '.visibility' => self::VISIBILITY_PUBLIC]);

            return;
        }

        $query->andWhere([
            'or',
            [$alias . '.visibility' => self::VISIBILITY_PUBLIC],
            [$alias . '.visibility' => self::VISIBILITY_REGISTERED_USERS],
            ['and', [$alias . '.visibility' => self::VISIBILITY_PRIVATE], [$alias . '.user_id' => $userId]],
        ]);
    }

    /**
     * 详情页相关推荐：同类型共享标签 → 同类型同分类 → 同类型最新 → 跨类型共享标签，逐级兜底。
     * 图集、动态存量很少，只按同类型取会推不出东西，所以最后一级允许跨类型。
     *
     * @return array<int, array{post: Post, reason: string}>
     */
    public static function relatedFor(Post $post, ?int $userId, int $limit = 4): array
    {
        $limit = max(1, $limit);
        $hasTags = $post->tags !== [];
        $collected = [];

        if ($hasTags) {
            $collected = self::collectRelated($collected, $post, self::tagCandidateIds((int) $post->id), $post->type, $userId, $limit, 'tag');
        }
        if ($post->categories !== [] && count($collected) < $limit) {
            $collected = self::collectRelated($collected, $post, self::cateCandidateIds((int) $post->id), $post->type, $userId, $limit, 'cate');
        }
        if (count($collected) < $limit) {
            $collected = self::collectRecent($collected, $post, $userId, $limit);
        }
        if ($hasTags && count($collected) < $limit) {
            $collected = self::collectRelated($collected, $post, self::tagCandidateIds((int) $post->id), null, $userId, $limit, 'cross');
        }

        return array_slice($collected, 0, $limit, true);
    }

    /**
     * 同类型内的相邻内容，排序与频道列表一致（新→旧）。
     *
     * @return array{0: Post|null, 1: Post|null} [较新一篇, 较旧一篇]
     */
    public static function neighborsFor(Post $post, ?int $userId): array
    {
        return [
            self::neighborQuery($post, $userId, true)->one(),
            self::neighborQuery($post, $userId, false)->one(),
        ];
    }

    /** 详情卡片需要的关联数据 + 可见性范围。 */
    private static function cardQuery(?int $userId, ?string $type): ActiveQuery
    {
        $query = self::find()
            ->alias('p')
            ->with(['user', 'blog', 'moment', 'images', 'shortVideo', 'tags'])
            ->where(['p.status' => self::STATUS_PUBLISHED]);

        if ($type !== null) {
            $query->andWhere(['p.type' => $type]);
        }
        self::applyVisibilityScope($query, $userId);

        return $query;
    }

    private static function neighborQuery(Post $post, ?int $userId, bool $newer): ActiveQuery
    {
        $createdAt = (int) $post->created_at;
        $id = (int) $post->id;

        return self::cardQuery($userId, $post->type)
            ->andWhere(['not', ['p.id' => $id]])
            ->andWhere($newer
                ? ['or', ['>', 'p.created_at', $createdAt], ['and', ['p.created_at' => $createdAt], ['>', 'p.id', $id]]]
                : ['or', ['<', 'p.created_at', $createdAt], ['and', ['p.created_at' => $createdAt], ['<', 'p.id', $id]]])
            ->orderBy($newer
                ? ['p.created_at' => SORT_ASC, 'p.id' => SORT_ASC]
                : ['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC])
            ->limit(1);
    }

    /**
     * @param array<int, array{post: Post, reason: string}> $collected
     * @param int[] $candidateIds 已按相关度倒序
     * @return array<int, array{post: Post, reason: string}>
     */
    private static function collectRelated(array $collected, Post $post, array $candidateIds, ?string $type, ?int $userId, int $limit, string $kind): array
    {
        $candidateIds = array_values(array_diff($candidateIds, array_keys($collected), [(int) $post->id]));
        if (count($collected) >= $limit || $candidateIds === []) {
            return $collected;
        }

        $rows = self::cardQuery($userId, $type)->andWhere(['p.id' => $candidateIds])->all();
        $rank = array_flip($candidateIds);
        usort($rows, static fn (Post $a, Post $b): int => $rank[(int) $a->id] <=> $rank[(int) $b->id]);

        foreach ($rows as $row) {
            if (count($collected) >= $limit) {
                break;
            }
            $collected[(int) $row->id] = ['post' => $row, 'reason' => self::relatedReason($post, $row, $kind)];
        }

        return $collected;
    }

    /** @return array<int, array{post: Post, reason: string}> */
    private static function collectRecent(array $collected, Post $post, ?int $userId, int $limit): array
    {
        $remaining = $limit - count($collected);
        if ($remaining <= 0) {
            return $collected;
        }

        $rows = self::cardQuery($userId, $post->type)
            ->andWhere(['not', ['p.id' => array_merge([(int) $post->id], array_keys($collected))]])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC])
            ->limit($remaining)
            ->all();

        foreach ($rows as $row) {
            $collected[(int) $row->id] = ['post' => $row, 'reason' => '同类型最新'];
        }

        return $collected;
    }

    /** 与指定内容共享标签的候选 id，按共享数量倒序。 @return int[] */
    private static function tagCandidateIds(int $postId): array
    {
        $rows = Yii::$app->db->createCommand(
            'SELECT [[b]].[[post_id]] AS id FROM {{%post_tag}} [[a]]'
            . ' INNER JOIN {{%post_tag}} [[b]] ON [[a]].[[tag_id]] = [[b]].[[tag_id]]'
            . ' WHERE [[a]].[[post_id]] = :postId AND [[b]].[[post_id]] <> :postId'
            . ' GROUP BY [[b]].[[post_id]] ORDER BY COUNT(DISTINCT [[a]].[[tag_id]]) DESC, [[b]].[[post_id]] DESC LIMIT 200',
            [':postId' => $postId]
        )->queryAll();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** 与指定内容同分类的候选 id。 @return int[] */
    private static function cateCandidateIds(int $postId): array
    {
        $rows = Yii::$app->db->createCommand(
            'SELECT [[b]].[[post_id]] AS id FROM {{%post_cate}} [[a]]'
            . ' INNER JOIN {{%post_cate}} [[b]] ON [[a]].[[cate_id]] = [[b]].[[cate_id]]'
            . ' WHERE [[a]].[[post_id]] = :postId AND [[b]].[[post_id]] <> :postId'
            . ' GROUP BY [[b]].[[post_id]] ORDER BY COUNT(DISTINCT [[a]].[[cate_id]]) DESC, [[b]].[[post_id]] DESC LIMIT 200',
            [':postId' => $postId]
        )->queryAll();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private static function relatedReason(Post $current, Post $candidate, string $kind): string
    {
        if ($kind === 'cate') {
            $cateName = (string) ($current->categories[0]->name ?? '');

            return $cateName !== '' ? '同分类 ' . $cateName : '同分类';
        }

        $currentTagIds = [];
        foreach ($current->tags as $tag) {
            $currentTagIds[(int) $tag->id] = true;
        }
        $shared = [];
        foreach ($candidate->tags as $tag) {
            if (isset($currentTagIds[(int) $tag->id])) {
                $shared[] = '#' . $tag->name;
            }
        }
        $label = '共享 ' . implode(' ', array_slice($shared, 0, 2)) . (count($shared) > 2 ? ' 等' : '');

        return $kind === 'cross' ? '跨类型 · ' . $label : $label;
    }

    /**
     * 详情页侧栏与卡片用的封面路径，缺图时回退默认封面。
     * 博客的 thumb 入库时不带前导斜杠，这里统一补齐。
     */
    public function coverUrl(): string
    {
        $firstImage = $this->images[0] ?? null;
        $path = match ($this->type) {
            self::TYPE_BLOG => (string) ($this->blog?->thumb ?? ''),
            self::TYPE_MOMENT => (string) ($this->moment?->thumb ?? ''),
            self::TYPE_IMAGES => (string) ($firstImage?->getThumbnailPath() ?? ''),
            self::TYPE_SHORT_VIDEO => (string) ($this->shortVideo?->cover_path ?? ''),
            default => '',
        };

        return $path === '' ? '/images/default-cover.png' : '/' . ltrim($path, '/');
    }

    /**
     * 作者在可见范围内的发布量，供作者卡与作者主页使用。
     *
     * @return array{total: int, byType: array<string, int>}
     */
    public static function authorCounts(int $authorId, ?int $userId): array
    {
        $query = self::find()
            ->alias('p')
            ->select(['p.type', 'cnt' => 'COUNT(*)'])
            ->where(['p.user_id' => $authorId, 'p.status' => self::STATUS_PUBLISHED])
            ->groupBy('p.type');
        self::applyVisibilityScope($query, $userId);

        $byType = [];
        $total = 0;
        foreach ($query->asArray()->all() as $row) {
            $byType[(string) $row['type']] = (int) $row['cnt'];
            $total += (int) $row['cnt'];
        }

        return ['total' => $total, 'byType' => $byType];
    }

    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // 评论正文要靠挂载记录回收，而数据库级联删除不触发模型事件，所以删除内容前先走模型摘除评论。
        PostComment::detachFromPost((int) $this->id);

        return true;
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
    public function getTags(): ActiveQuery { return $this->hasMany(Tag::class, ['id' => 'tag_id'])->viaTable('{{%post_tag}}', ['post_id' => 'id']); }
    public function getCategories(): ActiveQuery { return $this->hasMany(Cate::class, ['id' => 'cate_id'])->viaTable('{{%post_cate}}', ['post_id' => 'id']); }
    public function getBlog(): ActiveQuery { return $this->hasOne(BlogPost::class, ['post_id' => 'id']); }
    public function getImages(): ActiveQuery { return $this->hasMany(Image::class, ['post_id' => 'id'])->orderBy(['sort_order' => SORT_ASC]); }
    public function getMoment(): ActiveQuery { return $this->hasOne(Moment::class, ['post_id' => 'id']); }
    public function getShortVideo(): ActiveQuery { return $this->hasOne(ShortVideo::class, ['post_id' => 'id']); }
    public function getComments(): ActiveQuery { return $this->hasMany(PostComment::class, ['post_id' => 'id'])->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC]); }
    public function getCommentRoots(): ActiveQuery { return $this->hasMany(PostComment::class, ['post_id' => 'id'])->onCondition(['depth' => 0])->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC]); }
    public function getCommentContents(): ActiveQuery { return $this->hasMany(Comment::class, ['id' => 'comment_id'])->viaTable('{{%post_comment}}', ['post_id' => 'id']); }
    public function getLikes(): ActiveQuery { return $this->hasMany(PostLike::class, ['post_id' => 'id']); }
    public function getLikedUsers(): ActiveQuery { return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%post_like}}', ['post_id' => 'id']); }
    public function getFavorites(): ActiveQuery { return $this->hasMany(PostFavorite::class, ['post_id' => 'id']); }
    public function getFavoritedUsers(): ActiveQuery { return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%post_favorite}}', ['post_id' => 'id']); }

    /** 关注该内容作者的用户。 */
    public function getAuthorFollowers(): ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'follower_id'])
            ->viaTable('{{%user_follow}}', ['following_id' => 'user_id']);
    }

    /** 该内容作者关注的其他用户。 */
    public function getAuthorFollowing(): ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'following_id'])
            ->viaTable('{{%user_follow}}', ['follower_id' => 'user_id']);
    }
}
