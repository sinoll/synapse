<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the shared post table that all content types reference.
 *
 * like_count 是冗余计数：post_like 是 (post_id, user_id) 主键的关联表，列表页每行都要显示点赞数，
 * 逐条 COUNT 会变成 N+1，所以在 post 上放一份计数，由 PostLike::toggle() 在同一事务里维护。
 * views 同样是每条内容的属性而非博客专有，四种内容详情页共用同一套按天去重的计数。
 */
class m261016_000002_create_post_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        // Keep type as a string so new content types do not require a schema change.
        $this->createTable('{{%post}}', [
            'id' => $this->bigPrimaryKey(),
            'user_id' => $this->integer()->notNull(),
            'type' => $this->string(32)->notNull(),
            'title' => $this->string(200)->null(),
            'status' => $this->smallInteger()->notNull()->defaultValue(1)->comment('0草稿，1已发布'),
            // 0: owner only, 1: authenticated users, 2: public, 3: selected users (shared files only)
            'visibility' => $this->smallInteger()->notNull()->defaultValue(0)->comment('0仅作者可见，1登录用户可见，3公开'),
            'like_count' => $this->integer()->notNull()->defaultValue(0)->comment('点赞数，由 post_like 增删同步维护'),
            'views' => $this->integer()->notNull()->defaultValue(0)->comment('浏览数，同一浏览器对同一条内容按天去重累计'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_post_type_visibility_status_created', '{{%post}}', ['type', 'visibility', 'status', 'created_at']);
        $this->createIndex('idx_post_user_created', '{{%post}}', ['user_id', 'created_at']);
        $this->addForeignKey('fk_post_user', '{{%post}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%post}}');
    }
}
