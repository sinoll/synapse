<?php

declare(strict_types=1);

namespace restful\models;

use common\models\Cate;
use common\models\Post;
use common\models\PostLike;
use common\models\Tag;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use RuntimeException;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * 四类内容的共同骨架。
 *
 * 读：post 主表按类型 + 可见性筛选，子表补类型专属字段，输出真实列名。
 * 写：事务里 post → 子表 → 标签/分类，与前台四个控制器同一顺序；权限也沿用前台口径
 * （只有作者本人能改，管理员不豁免）。
 *
 * 前台把 syncPostTags 之类别实现复制了四份，这里只写一次。
 */
abstract class ApiResource
{
    /** 排序白名单：只放开 post 表上真实存在、且有索引可用的列。 */
    private const SORT_COLUMNS = ['created_at', 'updated_at', 'like_count', 'views'];

    private const VISIBILITY_VALUES = [
        Post::VISIBILITY_PRIVATE,
        Post::VISIBILITY_REGISTERED_USERS,
        Post::VISIBILITY_PUBLIC,
    ];

    /**
     * 本站 uploads 目录的规范路径形状：/uploads/{一段到三段目录}/{随机名}.{扩展名}。
     * 目录名和文件名都不含点号，因此 ../ 与绝对路径逃逸在这一步就被挡掉。
     */
    private const MEDIA_PATTERN = '#^/uploads/(?:[A-Za-z0-9_-]+/){1,3}[A-Za-z0-9_-]+\.[A-Za-z0-9]{1,11}$#';

    /** Post::coverUrl() 的兜底图；历史数据把它直接写进了 thumb，客户端原样回传时必须接受。 */
    private const DEFAULT_COVER = '/images/default-cover.png';

    /** 媒体列 => 所在表，用于「文件还被别的内容引用吗」的反查。 */
    private const MEDIA_COLUMNS = [
        ['{{%blog}}', 'thumb'],
        ['{{%moment}}', 'thumb'],
        ['{{%images}}', 'file_path'],
        ['{{%short_video}}', 'video_path'],
        ['{{%short_video}}', 'cover_path'],
    ];

    abstract public static function postType(): string;

    /** 中文类型名，用在错误文案里。 */
    abstract public static function label(): string;

    /** 列表和详情都允许的写字段；详情多出来的大字段由 typeFields($detail) 决定。 */
    abstract protected static function childFields(): array;

    /** Post 上指向子表的关系名；图集是「一个 post 多张图」，返回 null 由子类自行展开。 */
    abstract protected static function childRelation(): ?string;

    /** 类型专属响应字段。 */
    abstract protected static function typeFields(Post $post, bool $detail): array;

    /** 把 payload 里的类型字段写进子表；$isCreate=false 表示改已有记录。 */
    abstract protected static function persistChild(Post $post, array $payload, bool $isCreate): void;

    /** 创建时没给 title 的派生规则。 */
    abstract protected static function derivedTitle(array $payload): string;

    /** 哪些字段的 #话题# 会进标签；博客按前台口径不解析话题，返回空数组。 */
    abstract protected static function hashtagFields(): array;

    /** 该内容当前占用的媒体路径（库里原样，可能没有前导斜杠）。 */
    abstract protected static function currentMedia(Post $post): array;

    /**
     * 由某个媒体文件派生、但不在库里登记的附属文件（缩略图这一类）。
     * 只在源文件确认无人引用、即将删除时一并回收——反过来用来自删会误伤共用同一源文件的内容。
     *
     * @return string[]
     */
    protected static function derivedMedia(string $path): array
    {
        return [];
    }

    /** 只有博客挂分类。 */
    protected static function supportsCategory(): bool
    {
        return false;
    }

    /* ------------------------------------------------------------------ 读 ------------------------------------------------------------------ */

    /**
     * 分页列表。
     *
     * @param array $query 请求查询参数
     * @return array{items: array, page: int, per_page: int, total: int, total_pages: int, sort: string, order: string, authenticated: bool}
     */
    public static function page(array $query, int|null $viewerId): array
    {
        [$page, $perPage] = self::paging($query);
        [$sort, $order] = self::sorting($query);

        $criteria = Post::find()->alias('p')
            ->where(['p.type' => static::postType()]);
        if ($viewerId === null) {
            $criteria->andWhere(['p.status' => Post::STATUS_PUBLISHED]);
        } else {
            $criteria->andWhere([
                'or',
                ['p.status' => Post::STATUS_PUBLISHED],
                [
                    'and',
                    ['p.status' => Post::STATUS_DRAFT],
                    ['p.user_id' => $viewerId],
                ],
            ]);
        }
        Post::applyVisibilityScope($criteria, $viewerId);

        if (filter_var($query['user_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $criteria->andWhere(['p.user_id' => (int) $query['user_id']]);
        }
        if (is_string($query['q'] ?? null) && trim($query['q']) !== '') {
            $criteria->andWhere(['like', 'p.title', trim($query['q']), false]);
        }
        $tagSlug = self::tagFilter($query);
        if ($tagSlug !== null) {
            $criteria->innerJoin('{{%post_tag}}', 'pt.post_id = p.id')
                ->innerJoin('{{%tag}}', 'tag.id = pt.tag_id')
                ->andWhere(['tag.slug' => $tagSlug]);
        }

        $total = (int) (clone $criteria)->count();
        $relations = ['user', 'tags', static::childRelation() ?? 'images'];
        if (static::supportsCategory()) {
            $relations[] = 'categories';
        }
        $posts = (clone $criteria)
            ->with(array_values(array_unique($relations)))
            ->orderBy(["p.$sort" => $order, 'p.id' => $order])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();
        $liked = self::likedIds($posts, $viewerId);

        return [
            'authenticated' => $viewerId !== null,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            'sort' => $sort,
            'order' => $order === SORT_ASC ? 'asc' : 'desc',
            'items' => array_map(
                static fn (Post $post): array => self::item($post, $viewerId, false, in_array((int) $post->id, $liked, true)),
                $posts
            ),
        ];
    }

    /**
     * 取单条并做可见性判断。
     *
     * 无权查看也返回 404：私密内容不应该通过状态码差异暴露「它存在」。
     */
    public static function find(int $postId, int|null $viewerId): Post
    {
        $post = Post::findOne($postId);
        if ($post === null || $post->type !== static::postType() || !self::canView($post, $viewerId)) {
            throw new NotFoundHttpException(static::label() . '不存在，或你没有查看它的权限。');
        }

        return $post;
    }

    /** @return array 单条内容的对外结构 */
    public static function item(Post $post, int|null $viewerId, bool $detail = false, bool|null $liked = null): array
    {
        return [
            'id' => (int) $post->id,
            'type' => $post->type,
            'title' => $post->title,
            'status' => (int) $post->status,
            'visibility' => (int) $post->visibility,
            'like_count' => (int) $post->like_count,
            'views' => (int) $post->views,
            'liked' => $liked ?? self::isLiked($post, $viewerId),
            'author' => self::author($post),
            'tags' => array_map(static fn (Tag $tag): string => $tag->name, $post->tags),
            'cover_url' => ApiResponder::assetUrl($post->coverUrl()),
            'created_at' => ApiResponder::timestamp((int) $post->created_at),
            'updated_at' => ApiResponder::timestamp((int) $post->updated_at),
        ] + static::typeFields($post, $detail);
    }

    private static function author(Post $post): array|null
    {
        $user = $post->user;
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar_url' => ApiResponder::assetUrl($user->avatar),
        ];
    }

    private static function canView(Post $post, int|null $viewerId): bool
    {
        $isOwner = $viewerId !== null && (int) $post->user_id === $viewerId;
        // 草稿只有作者能看到；前台不存在草稿，所以这条只在 API 显式写了 status=0 时生效。
        if ((int) $post->status !== Post::STATUS_PUBLISHED) {
            return $isOwner;
        }

        return match ((int) $post->visibility) {
            Post::VISIBILITY_PUBLIC => true,
            Post::VISIBILITY_REGISTERED_USERS => $viewerId !== null,
            default => $isOwner,
        };
    }

    private static function isLiked(Post $post, int|null $viewerId): bool
    {
        if ($viewerId === null) {
            return false;
        }

        return PostLike::find()->where(['post_id' => $post->id, 'user_id' => $viewerId])->exists();
    }

    /** 整页的点赞状态一次查完，别让 item() 每行问一次库。 @return int[] */
    private static function likedIds(array $posts, int|null $viewerId): array
    {
        if ($viewerId === null || $posts === []) {
            return [];
        }

        return array_map(
            'intval',
            PostLike::find()->select('post_id')->where(['user_id' => $viewerId, 'post_id' => array_map(static fn (Post $p): int => (int) $p->id, $posts)])->column()
        );
    }

    /** @return array{0: int, 1: int} */
    private static function paging(array $query): array
    {
        $max = (int) Yii::$app->params['api.pageSizeMax'];
        $page = self::intOrNull($query['page'] ?? null) ?? 1;
        $perPage = self::intOrNull($query['per_page'] ?? null) ?? (int) Yii::$app->params['api.pageSizeDefault'];

        if ($page < 1) {
            throw new ValidationException(['page' => ['page 是从 1 开始的页码。']]);
        }
        if ($perPage < 1 || $perPage > $max) {
            throw new ValidationException(['per_page' => ["per_page 只能在 1 到 $max 之间。"]]);
        }

        return [$page, $perPage];
    }

    /** @return array{0: string, 1: int} */
    private static function sorting(array $query): array
    {
        $sort = (string) ($query['sort'] ?? 'created_at');
        if (!in_array($sort, self::SORT_COLUMNS, true)) {
            throw new ValidationException(['sort' => ['sort 只能是 ' . implode('、', self::SORT_COLUMNS) . '。']]);
        }
        $order = strtolower((string) ($query['order'] ?? 'desc'));
        if (!in_array($order, ['asc', 'desc'], true)) {
            throw new ValidationException(['order' => ['order 只能是 asc 或 desc。']]);
        }

        return [$sort, $order === 'asc' ? SORT_ASC : SORT_DESC];
    }

    private static function tagFilter(array $query): string|null
    {
        $tag = trim((string) ($query['tag'] ?? ''));
        if ($tag === '') {
            return null;
        }
        $normalized = trim($tag, "# \t\n\r\0\x0B");
        $existing = Tag::find()->select(['slug'])->where(['or', ['name' => $normalized], ['slug' => $normalized]])->scalar();

        return $existing === null ? Tag::slugForName($normalized) : (string) $existing;
    }

    /* ------------------------------------------------------------------ 写 ------------------------------------------------------------------ */

    /** @param array $payload 请求体 */
    public static function create(int $authorId, array $payload): array
    {
        $post = new Post();
        $post->user_id = $authorId;
        $post->type = static::postType();
        self::applyRootFields($post, $payload, true);

        self::transaction($post, $payload, true);
        $post->refresh();

        return self::item($post, $authorId, true);
    }

    /** 部分更新：只改请求里出现的字段，没出现的保持原值。 */
    public static function update(int $postId, int $actorId, array $payload): array
    {
        $post = self::find($postId, $actorId);
        self::assertOwner($post, $actorId);
        self::assertHasWritable($payload);

        self::applyRootFields($post, $payload, false);
        $replaced = static::currentMedia($post);
        self::transaction($post, $payload, false);
        $post->refresh();

        self::purgeUnreferenced($replaced);

        return self::item($post, $actorId, true);
    }

    public static function remove(int $postId, int $actorId): array
    {
        $post = self::find($postId, $actorId);
        self::assertOwner($post, $actorId);
        $media = static::currentMedia($post);
        $title = (string) $post->title;

        if (!$post->delete()) {
            throw new RuntimeException('内容删除失败，请稍后重试。');
        }

        // Post::beforeDelete 已把评论摘干净，子表靠 ON DELETE CASCADE 回收，这里只处理落到磁盘上的文件。
        self::purgeUnreferenced($media);

        return ['id' => $postId, 'title' => $title];
    }

    /** 点赞切换：口径与前台 LikeController 一致，计数由 PostLike::toggle 在同一事务里维护。 */
    public static function like(int $postId, int $userId): array
    {
        $post = self::find($postId, $userId);

        return PostLike::toggle($userId, $post) + ['post_id' => (int) $post->id];
    }

    /**
     * post → 子表 → 标签/分类，一个事务。
     *
     * post 用 save(false)：草稿状态下的 title 允许为空，而 Post::rules 无条件要求 title，
     * 前台也是这么绕过的——校验交给子表和这里的显式字段检查。
     */
    private static function transaction(Post $post, array $payload, bool $isCreate): void
    {
        Post::getDb()->transaction(static function () use ($post, $payload, $isCreate): void {
            $post->title = self::titleFor($post, $payload, $isCreate);
            if (!$post->save(false)) {
                throw new RuntimeException('内容保存失败。');
            }
            static::persistChild($post, $payload, $isCreate);
            self::syncTaxonomy($post, $payload, $isCreate);
        });
    }

    private static function applyRootFields(Post $post, array $payload, bool $isCreate): void
    {
        if ($isCreate || array_key_exists('status', $payload)) {
            $status = self::intOrNull($payload['status'] ?? null);
            if ($status === null) {
                if (!$isCreate) {
                    throw new ValidationException(['status' => ['status 只能是 0（草稿）或 1（已发布）。']]);
                }
            } elseif (!in_array($status, [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED], true)) {
                throw new ValidationException(['status' => ['status 只能是 0（草稿）或 1（已发布）。']]);
            } else {
                $post->status = $status;
            }
        }

        if ($isCreate || array_key_exists('visibility', $payload)) {
            $visibility = self::intOrNull($payload['visibility'] ?? null);
            if ($visibility === null) {
                if (!$isCreate) {
                    throw new ValidationException(['visibility' => ['visibility 只能是 0、1 或 2。']]);
                }
            } elseif (!in_array($visibility, self::VISIBILITY_VALUES, true)) {
                throw new ValidationException(['visibility' => [
                    'visibility 只能是 0（私密）、1（注册用户可见）或 2（公开）。',
                ]]);
            } else {
                $post->visibility = $visibility;
            }
        }
    }

    private static function titleFor(Post $post, array $payload, bool $isCreate): ?string
    {
        if (!array_key_exists('title', $payload)) {
            return $isCreate ? static::derivedTitle($payload) : $post->title;
        }

        $title = self::trimmed($payload['title'], 'title', 200);

        return $title === '' ? static::derivedTitle($payload) : $title;
    }

    /** 标签与分类：创建时总是同步，更新时只在相关字段出现时同步（部分更新不该重算标签）。 */
    private static function syncTaxonomy(Post $post, array $payload, bool $isCreate): void
    {
        $touchesTags = $isCreate
            || array_key_exists('tags', $payload)
            || array_intersect(array_keys($payload), static::hashtagFields()) !== [];
        if ($touchesTags) {
            self::syncTags($post, $payload);
        }
        if (static::supportsCategory() && ($isCreate || array_key_exists('cate_id', $payload))) {
            self::syncCategory($post, $payload);
        }
    }

    /** 显式 tags[] 加上正文里的 #话题#，去重后整组替换 post_tag。 */
    private static function syncTags(Post $post, array $payload): void
    {
        $names = self::submittedTagNames($payload['tags'] ?? []);
        foreach (static::hashtagFields() as $field) {
            if (!is_string($payload[$field] ?? null)) {
                continue;
            }
            $names = self::mergeTagNames($names, Tag::extractHashtags($payload[$field]));
        }

        Yii::$app->db->createCommand()->delete('{{%post_tag}}', ['post_id' => $post->id])->execute();
        foreach ($names as $name) {
            $tag = Tag::find()->where(['name' => $name])->one();
            if ($tag === null) {
                $tag = new Tag();
                $tag->name = $name;
                $tag->slug = Tag::slugForName($name);
                if (!$tag->save()) {
                    throw new ValidationException(['tags' => ['标签「' . $name . '」保存失败。']], '标签保存失败。');
                }
            }
            $post->link('tags', $tag);
        }
    }

    /** @return string[] */
    private static function submittedTagNames(mixed $submitted): array
    {
        if ($submitted === null || $submitted === '') {
            return [];
        }
        if (!is_array($submitted)) {
            throw new ValidationException(['tags' => ['tags 必须是字符串数组。']]);
        }

        $names = [];
        foreach ($submitted as $item) {
            if (!is_string($item)) {
                throw new ValidationException(['tags' => ['tags 必须是字符串数组。']]);
            }
            $names[] = trim((string) preg_replace('/\s+/u', ' ', trim($item)), "# \t\n\r\0\x0B");
        }
        $names = array_values(array_filter($names, static fn (string $name): bool => $name !== ''));

        return self::mergeTagNames([], $names);
    }

    /** 去重、限长、限数量——沿用前台 submittedTagNames 的口径（≤10 个，单个 ≤50 字）。 */
    private static function mergeTagNames(array $existing, array $incoming): array
    {
        $names = [];
        $seen = [];
        foreach (array_merge($existing, $incoming) as $name) {
            if (mb_strlen($name) > 50 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
                throw new ValidationException(['tags' => ["标签「" . mb_substr($name, 0, 20) . '」不合法：不能含控制字符，且不超过 50 个字符。']]);
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            if (count($seen) >= 10) {
                throw new ValidationException(['tags' => ['一条内容最多 10 个标签。']]);
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }

    /** 博客的分类是单值；没给 cate_id 时按 0 处理（不挂分类）。 */
    private static function syncCategory(Post $post, array $payload): void
    {
        $cateId = array_key_exists('cate_id', $payload)
            ? self::intOrNull($payload['cate_id'])
            : 0;
        if ($cateId === null || $cateId < 0) {
            throw new ValidationException(['cate_id' => ['cate_id 必须是 0 或已存在的正整数分类 ID。']]);
        }
        if ($cateId > 0 && !Cate::find()->where(['id' => $cateId])->exists()) {
            throw new ValidationException(['cate_id' => ['分类不存在。']]);
        }

        Yii::$app->db->createCommand()->delete('{{%post_cate}}', ['post_id' => $post->id])->execute();
        if ($cateId > 0) {
            Yii::$app->db->createCommand()->insert('{{%post_cate}}', ['post_id' => $post->id, 'cate_id' => $cateId])->execute();
        }
    }

    /** 权限口径照前台 ensureOwner()：只有作者本人能改，管理员也不豁免。 */
    private static function assertOwner(Post $post, int $actorId): void
    {
        if ((int) $post->user_id !== $actorId) {
            throw new ForbiddenHttpException('你无权修改此内容。');
        }
    }

    /**
     * 本类型的接口接受的写字段：post 根字段 + 子表字段（博客再多一个 cate_id）。
     * 错误文案和 GET / 的能力清单都用它，免得两处各列一遍再各错一遍。
     *
     * @return string[]
     */
    public static function writableFields(): array
    {
        $fields = array_merge(['title', 'status', 'visibility', 'tags'], static::childFields());
        if (static::supportsCategory()) {
            $fields[] = 'cate_id';
        }

        // 图集把子表的 title/alt_text 也当写字段用，和根字段重名，清单里只留一份。
        return array_values(array_unique($fields));
    }

    private static function assertHasWritable(array $payload): void
    {
        $accepted = self::writableFields();
        if (array_intersect(array_keys($payload), $accepted) !== []) {
            return;
        }

        $unknown = array_values(array_diff(array_keys($payload), $accepted));
        throw new ValidationException(
            ['payload' => ['没有可更新的字段，本接口接受：' . implode('、', $accepted) . '。']
                + ($unknown === [] ? [] : ['unknown' => ['未识别的字段：' . implode('、', $unknown) . '。']])],
            '请求里没有可更新的字段。'
        );
    }

    /* -------------------------------------------------------------- 子类共用工具 -------------------------------------------------------------- */

    /**
     * 子表落库；AR 校验失败转成 422 的字段错误。
     *
     * 长度、必填、取值范围这些规则都以子模型的 rules() 为准（含 RichText::purify 的正文白名单），
     * API 层不重复实现一遍，只补 rules() 管不到的入参类型与媒体路径。
     */
    final protected static function saveChildModel(ActiveRecord $model): void
    {
        if ($model->save()) {
            return;
        }
        $errors = $model->getFirstErrors();
        throw new ValidationException($errors === [] ? ['content' => ['内容保存失败，请检查提交的字段。']] : $errors);
    }

    /**
     * 单个媒体入参：校验形状与文件是否存在，空值返回 null。
     * 数组字段（如图集的 file_paths）逐个调用它，报错时共用同一个 $field 键。
     *
     * @return string|null 库里原样存放的根相对路径
     */
    protected static function mediaPath(mixed $value, string $field): ?string
    {
        $path = self::trimmed($value, $field, 1024);
        if ($path === '') {
            return null;
        }
        if ($path === self::DEFAULT_COVER) {
            return $path;
        }
        if (preg_match(self::MEDIA_PATTERN, $path) !== 1) {
            throw new ValidationException([$field => [
                $field . ' 必须是本站 /upload 返回的路径，形如 /uploads/images/202611/xxxx.jpg。',
            ]]);
        }
        if (!is_file(Yii::getAlias('@frontend/web') . $path)) {
            throw new ValidationException([$field => [$field . ' 指向的文件不存在，请先用 POST /upload 上传。']]);
        }

        return $path;
    }

    /** @return string|null 库里原样存放的根相对路径 */
    protected static function mediaArg(array $payload, string $field, bool $required = false): ?string
    {
        if (!array_key_exists($field, $payload)) {
            if ($required) {
                throw new ValidationException([$field => [$field . ' 不能为空。']]);
            }

            return null;
        }

        $path = self::mediaPath($payload[$field], $field);
        if ($path === null && $required) {
            throw new ValidationException([$field => [$field . ' 不能为空。']]);
        }

        return $path;
    }

    /**
     * 从正文里第一张本站插图推出缩略图路径，口径与前台 blogThumbnailPath() 一致。
     *
     * 只在「新建且没提交 thumb」时调用：部分更新不碰封面，否则 PATCH 一次正文
     * 就可能把客户端明确设过的封面清掉。
     */
    protected static function thumbnailFromContent(string $content): ?string
    {
        preg_match_all(
            '#/uploads/(?:blog|moment)/(?:\d{4}/(?:\d{2}/)?|\d{6}/)[A-Za-z0-9_-]+\.[A-Za-z0-9]+#',
            $content,
            $matches,
        );

        foreach (array_unique($matches[0] ?? []) as $imagePath) {
            $thumbnail = self::blogThumbnailForPath($imagePath);
            if ($thumbnail !== null) {
                return $thumbnail;
            }
        }

        return null;
    }

    /**
     * 博客/动态源图对应的缩略图路径，找不到返回 null；命名口径与前台 blogThumbnailPath() 相同。
     *
     * 两个用途：创建时按正文推封面，删除时把库里只登记了源图的那份缩略图一并回收。
     */
    protected static function blogThumbnailForPath(string $imagePath): ?string
    {
        if (preg_match('#^/uploads/(?:blog|moment)/(?:\d{4}/(?:\d{2}/)?|\d{6}/)([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', '/' . ltrim($imagePath, '/'), $match) !== 1) {
            return null;
        }
        $webRoot = Yii::getAlias('@frontend/web');
        $base = pathinfo($match[1], PATHINFO_FILENAME);

        // thumb/blog 平铺，源图却分过 YYYY/、YYYY/MM/ 两种目录，扩展名也未必归一成 jpg。
        foreach (['/uploads/thumb/blog/', '/uploads/thumb/moment/'] as $prefix) {
            foreach ([$match[1], $base . '.jpg'] as $candidate) {
                if (is_file($webRoot . '/' . $prefix . $candidate)) {
                    return $prefix . $candidate;
                }
            }
        }

        return null;
    }

    /** 字符串入参：接受字符串或数字，去首尾空白；$maxLength 为 null 表示不限长（正文类字段）。 */
    protected static function trimmed(mixed $value, string $field, int|null $maxLength = null): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new ValidationException([$field => [$field . ' 必须是字符串。']]);
        }
        $string = trim((string) $value);
        if ($maxLength !== null && mb_strlen($string) > $maxLength) {
            throw new ValidationException([$field => ["$field 不能超过 $maxLength 个字符。"]]);
        }

        return $string;
    }

    protected static function intArg(mixed $value, string $field, int|null $min = null, int|null $max = null): int
    {
        $int = self::intOrNull($value);
        if ($int === null || ($min !== null && $int < $min) || ($max !== null && $int > $max)) {
            $range = $min !== null && $max !== null ? "$min 到 $max" : ($min !== null ? "不小于 $min" : "不大于 $max");
            throw new ValidationException([$field => [$field . ' 必须是整数且 ' . $range . '。']]);
        }

        return $int;
    }

    /** 只接受真正的整数或纯数字字符串，避免 (int) 'abc' 静默变成 0。 */
    protected static function intOrNull(mixed $value): int|null
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    /** 富文本转标题：剥掉标签、合并空白、截断，空正文用兜底名。 */
    protected static function plainText(string $html, int $limit): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_substr($text, 0, $limit);
    }

    /** 提交后被替换掉的文件：只有库里已无人引用才删，同路径可能被别的内容复用。 */
    private static function purgeUnreferenced(array $paths): void
    {
        $webRoot = Yii::getAlias('@frontend/web');
        foreach (array_unique(array_filter($paths)) as $path) {
            $normalized = '/' . ltrim((string) $path, '/');
            // 只回收上传目录：默认封面这类随程序发布的静态图被内容引用着，删掉会波及全站。
            if (!str_starts_with($normalized, '/uploads/') || self::mediaInUse($path)) {
                continue;
            }
            foreach (array_merge([$path], static::derivedMedia((string) $path)) as $file) {
                $absolute = $webRoot . '/' . ltrim((string) $file, '/');
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
        }
    }

    private static function mediaInUse(string $path): bool
    {
        foreach (self::MEDIA_COLUMNS as [$table, $column]) {
            if ((new Query())->from($table)->where([$column => $path])->exists()) {
                return true;
            }
        }

        return false;
    }
}
