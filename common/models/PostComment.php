<?php

declare(strict_types=1);

namespace common\models;

use InvalidArgumentException;
use Throwable;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * 评论与内容的挂载关系，同时是该内容会话树里的一个节点。
 * 正文、作者、审核状态在 {@see Comment} 上；本表只描述「挂在哪条内容下、回复了谁、处在第几层」。
 */
class PostComment extends ActiveRecord
{
    /** 会话树允许的最大层级，0 为顶级评论。 */
    public const MAX_DEPTH = 3;

    /** 详情页一次取出的楼层（顶级评论）数。 */
    public const FLOORS_PAGE_SIZE = 20;

    /** 一个楼层默认展开的回复数，其余走 /comment/replies 翻页。 */
    public const INLINE_REPLIES = 3;

    public static function tableName(): string { return '{{%post_comment}}'; }

    public function behaviors(): array
    {
        return [[
            'class' => TimestampBehavior::class,
            'attributes' => [self::EVENT_BEFORE_INSERT => ['created_at']],
        ]];
    }

    public function rules(): array
    {
        return [
            [['post_id', 'comment_id'], 'required'],
            [['post_id', 'comment_id', 'parent_comment_id', 'root_comment_id'], 'integer'],
            [['depth'], 'integer', 'min' => 0, 'max' => self::MAX_DEPTH],
            [['post_id'], 'exist', 'skipOnError' => true, 'targetClass' => Post::class, 'targetAttribute' => ['post_id' => 'id']],
            [['comment_id'], 'exist', 'skipOnError' => true, 'targetClass' => Comment::class, 'targetAttribute' => ['comment_id' => 'id']],
            [['comment_id'], 'unique', 'targetAttribute' => ['post_id', 'comment_id'], 'message' => '这条内容下已经存在该评论。'],
            // 父子关系只在同一条内容的会话树内成立，数据库无法跨列校验，由模型保证。
            [['parent_comment_id', 'root_comment_id'], 'validateNodeInSamePost', 'skipOnEmpty' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'post_id' => '内容',
            'comment_id' => '评论内容',
            'parent_comment_id' => '回复的评论',
            'root_comment_id' => '所属顶级评论',
            'depth' => '层级',
            'created_at' => '评论时间',
        ];
    }

    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
    public function getComment(): ActiveQuery { return $this->hasOne(Comment::class, ['id' => 'comment_id']); }

    /** 评论作者——正文自带的属性，这里提供快捷访问。 */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id'])->viaTable('{{%comment}}', ['id' => 'comment_id']);
    }

    public function getParent(): ActiveQuery { return $this->hasOne(self::class, ['id' => 'parent_comment_id']); }
    public function getRoot(): ActiveQuery { return $this->hasOne(self::class, ['id' => 'root_comment_id']); }
    public function getReplies(): ActiveQuery { return $this->hasMany(self::class, ['parent_comment_id' => 'id'])->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC]); }

    /** 同一楼层（顶级评论）下的全部回复。 */
    public function getFloorReplies(): ActiveQuery { return $this->hasMany(self::class, ['root_comment_id' => 'id'])->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC]); }

    public function isRoot(): bool { return (int) $this->depth === 0; }

    public function isVisible(): bool { return $this->comment !== null && $this->comment->isVisible(); }

    /** 评论作者、被评论内容的所有者、站点管理员可以删除该节点及其回复。 */
    public function canManage(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = (int) $user->id;

        return $userId === (int) $this->comment?->user_id
            || $userId === (int) $this->post?->user_id
            || (bool) (int) $user->is_admin;
    }

    /**
     * 按会话树位置绑定到被回复的节点，写入 parent/root/depth；顶级评论传 null。
     * 必须在被回复节点已入库后调用，否则没有可用于指向的 id。
     */
    public function bindTo(?self $parent): void
    {
        if ($parent === null) {
            $this->parent_comment_id = null;
            $this->root_comment_id = null;
            $this->depth = 0;

            return;
        }

        if (!$parent->isSavedNode()) {
            throw new InvalidArgumentException('被回复的评论尚未保存，无法建立回复关系。');
        }

        if ($parent === $this || (int) $parent->id === (int) $this->id) {
            throw new InvalidArgumentException('评论不能回复自己。');
        }

        if ((int) $parent->post_id !== (int) $this->post_id) {
            throw new InvalidArgumentException('不能回复其它内容下的评论。');
        }

        $this->parent_comment_id = $parent->id;
        $this->root_comment_id = $parent->isRoot() ? $parent->id : $parent->root_comment_id;
        $this->depth = (int) $parent->depth + 1;
    }

    /** 写入正文与挂载记录，返回会话节点；两步在同一个事务里完成。 */
    public static function publish(Post $post, int $userId, string $content, ?self $parent = null): self
    {
        $node = new self(['post_id' => $post->id]);
        $node->bindTo($parent);
        $comment = new Comment(['user_id' => $userId, 'content' => $content]);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$comment->save()) {
                throw new InvalidArgumentException('评论内容保存失败：' . implode('；', $comment->getFirstErrors()));
            }

            $node->comment_id = $comment->id;
            if (!$node->save()) {
                throw new InvalidArgumentException('评论关系保存失败：' . implode('；', $node->getFirstErrors()));
            }

            $transaction->commit();
        } catch (Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }

        return $node;
    }

    /**
     * 某条内容下的楼层分页，正文与作者一并预加载，返回 [节点数组, 是否还有下一页]。
     * @return array{0: self[], 1: bool}
     */
    public static function floorPage(int $postId, int $page = 1): array
    {
        $roots = static::find()
            ->where(['post_id' => $postId, 'depth' => 0])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->offset((max(1, $page) - 1) * self::FLOORS_PAGE_SIZE)
            ->limit(self::FLOORS_PAGE_SIZE + 1)
            ->with(['comment.user', 'floorReplies.comment.user', 'floorReplies.parent.comment.user'])
            ->all();

        return [array_slice($roots, 0, self::FLOORS_PAGE_SIZE), count($roots) > self::FLOORS_PAGE_SIZE];
    }

    /**
     * 该楼层（顶级评论）下回复的分页，按时间正序，返回 [回复节点数组, 是否还有下一页]。
     * 折叠展示与 /comment/replies 翻页共用同一个页宽，页码连续不跳号。
     * @return array{0: self[], 1: bool}
     */
    public function replyPage(int $page = 1): array
    {
        $rows = static::find()
            ->where(['root_comment_id' => $this->id])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->offset((max(1, $page) - 1) * self::INLINE_REPLIES)
            ->limit(self::INLINE_REPLIES + 1)
            ->with(['comment.user', 'parent.comment.user'])
            ->all();

        return [array_slice($rows, 0, self::INLINE_REPLIES), count($rows) > self::INLINE_REPLIES];
    }

    /**
     * 删除该节点及其整棵回复子树，逐行走模型以便回收正文。
     * 只删本节点会让数据库级联悄悄带走子树，子节点的正文就成了无人引用的孤儿行。
     */
    public function purge(): int
    {
        if (!$this->isSavedNode()) {
            return 0;
        }

        $descendants = static::find()
            ->where(['post_id' => $this->post_id])
            ->andWhere(['or', ['parent_comment_id' => $this->id], ['root_comment_id' => $this->id]])
            ->orderBy(['depth' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        $deleted = 0;
        foreach ($descendants as $descendant) {
            if ($descendant->delete() !== false) {
                $deleted++;
            }
        }

        if ($this->delete() !== false) {
            $deleted++;
        }

        return $deleted;
    }

    /**
     * 摘除某条内容下的全部评论，深层节点先删，保证每条评论的正文都能被回收。
     * 由 {@see Post::beforeDelete()} 在内容删除时调用。
     */
    public static function detachFromPost(int $postId): int
    {
        $deleted = 0;
        foreach (static::find()->where(['post_id' => $postId])->orderBy(['depth' => SORT_DESC, 'id' => SORT_DESC])->all() as $node) {
            if ($node->delete() !== false) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function afterDelete(): void
    {
        parent::afterDelete();

        // 这是正文的最后一条挂载记录时一并删除正文；仍被其它内容挂着的正文保留。
        $commentId = (int) $this->comment_id;
        if ($commentId > 0 && !static::find()->where(['comment_id' => $commentId])->exists()) {
            Comment::deleteAll(['id' => $commentId]);
        }
    }

    private function isSavedNode(): bool { return !$this->isNewRecord && $this->id !== null; }

    /** 父子指针必须属于同一条内容的会话树。 */
    public function validateNodeInSamePost(string $attribute): void
    {
        $nodeId = $this->{$attribute};
        if ($nodeId === null || $nodeId === '') {
            return;
        }

        $node = self::find()->select('post_id')->where(['id' => $nodeId])->one();
        if ($node === null) {
            $this->addError($attribute, '被回复的评论不存在。');

            return;
        }

        if ((int) $node->post_id !== (int) $this->post_id) {
            $this->addError($attribute, '不能回复其它内容下的评论。');
        }
    }
}
