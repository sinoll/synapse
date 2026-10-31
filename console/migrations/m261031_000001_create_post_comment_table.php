<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates threaded comments for any content type through the shared post table. */
class m261031_000001_create_post_comment_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%post_comment}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'user_id' => $this->integer()->notNull(),
            // Null for top-level comments, otherwise the id of the comment being replied to.
            'parent_id' => $this->bigInteger()->null(),
            'content' => $this->text()->notNull(),
            // 1: visible, 0: hidden by moderation (row is kept so replies stay intact).
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);

        $this->createIndex('idx_post_comment_post_status_created', '{{%post_comment}}', ['post_id', 'status', 'created_at']);
        $this->createIndex('idx_post_comment_user_created', '{{%post_comment}}', ['user_id', 'created_at']);
        $this->createIndex('idx_post_comment_parent', '{{%post_comment}}', ['parent_id']);

        $this->addForeignKey('fk_post_comment_post', '{{%post_comment}}', 'post_id', '{{%post}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_post_comment_user', '{{%post_comment}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_post_comment_parent', '{{%post_comment}}', 'parent_id', '{{%post_comment}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%post_comment}}');
    }
}
