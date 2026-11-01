<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the comment schema for every post-backed content type.
 *
 * 数据拆成两层：
 *   {{%comment}}       评论内容本体——谁在什么时候说了什么，以及正文自身的审核状态与生命周期。
 *   {{%post_comment}}  评论与内容的挂载关系——挂在哪条内容下、回复了谁、处在第几层。
 *
 * 回复关系放在关系表而不是正文表上：「回复了哪条评论」是某条内容会话树里的位置属性，
 * 不是评论文本自身的属性。同一条正文因此可以被挂载到多处（转发、跨内容复用）而不复制文本，
 * 按内容隐藏、置顶等会话级状态将来也可以只加在 {{%post_comment}} 上，不影响正文行。
 */
class m261031_000001_create_post_comment_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%comment}}', [
            'id' => $this->bigPrimaryKey(),
            'user_id' => $this->integer()->notNull()->comment('评论作者'),
            'content' => $this->text()->notNull()->comment('评论正文'),
            // 正文级别的审核状态：0 隐藏时该正文在所有内容里同时不可见，行保留以免会话树断裂。
            'status' => $this->smallInteger()->notNull()->defaultValue(1)->comment('正文审核状态：1 可见，0 隐藏'),
            'created_at' => $this->integer()->notNull()->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->comment('修改时间'),
        ], $options);

        $this->createIndex('idx_comment_user_created', '{{%comment}}', ['user_id', 'created_at']);
        $this->addForeignKey('fk_comment_user', '{{%comment}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        $this->createTable('{{%post_comment}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull()->comment('被评论的内容'),
            'comment_id' => $this->bigInteger()->notNull()->comment('评论内容本体'),
            // 顶级评论为 null，否则指向被回复的那条挂载记录（同一条内容下的会话树）。
            'parent_comment_id' => $this->bigInteger()->null()->comment('被回复的挂载记录，顶级评论为空'),
            // 指向所属顶级评论，冗余一层用于楼中楼分页，避免递归查询整棵子树。
            'root_comment_id' => $this->bigInteger()->null()->comment('所属顶级评论的挂载记录，顶级评论为空'),
            'depth' => $this->smallInteger()->notNull()->defaultValue(0)->comment('回复层级：0 顶级评论，大于 0 为楼中楼'),
            'created_at' => $this->integer()->notNull()->comment('在该内容下发表的时间，楼层排序与分页依据'),
        ], $options);

        // 同一条正文在同一条内容下只能挂载一次；复合索引同时服务于按内容扫描会话。
        $this->createIndex('uq_post_comment_post_comment', '{{%post_comment}}', ['post_id', 'comment_id'], true);
        // 取某一楼的回复，以及取某条内容下的顶级评论（parent_comment_id IS NULL 走同一索引）。
        $this->createIndex('idx_post_comment_post_parent_created', '{{%post_comment}}', ['post_id', 'parent_comment_id', 'created_at']);
        // 按楼取全部楼中楼。
        $this->createIndex('idx_post_comment_post_root_created', '{{%post_comment}}', ['post_id', 'root_comment_id', 'created_at']);
        $this->createIndex('idx_post_comment_comment', '{{%post_comment}}', ['comment_id']);
        $this->createIndex('idx_post_comment_parent', '{{%post_comment}}', ['parent_comment_id']);

        // 自我回复（parent_comment_id = id）无法写成 CHECK：MariaDB 不允许在 CHECK 里引用 AUTO_INCREMENT 列，
        // 且插入时本行的 id 尚未产生，该情形只在模型层显式改指针时才可能出现，由 PostComment::bindTo() 拦截。
        $this->addForeignKey('fk_post_comment_post', '{{%post_comment}}', 'post_id', '{{%post}}', 'id', 'CASCADE');
        // 级联方向是「删正文 → 解除它的所有挂载」，回复子树再由 parent_comment_id 的级联带走。
        // 反向的正文回收数据库做不到（删挂载记录不会删父表，级联也不触发模型），由
        // PostComment::afterDelete() 与 Comment::purgeUnbound() 负责，别让正文变成孤儿行。
        $this->addForeignKey('fk_post_comment_comment', '{{%post_comment}}', 'comment_id', '{{%comment}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_post_comment_parent', '{{%post_comment}}', 'parent_comment_id', '{{%post_comment}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        // {{%post_comment}} 引用 {{%comment}}，必须先删关系表再删正文表。
        $this->dropTable('{{%post_comment}}');
        $this->dropTable('{{%comment}}');
    }
}
