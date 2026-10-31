<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates likes for any content type through the shared post table. */
class m261031_000002_create_post_like_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%post_like}}', [
            'post_id' => $this->bigInteger()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], $options);

        // One like per user per post; the composite key also serves counts by post.
        $this->addPrimaryKey('pk_post_like', '{{%post_like}}', ['post_id', 'user_id']);
        $this->createIndex('idx_post_like_user_created', '{{%post_like}}', ['user_id', 'created_at']);

        $this->addForeignKey('fk_post_like_post', '{{%post_like}}', 'post_id', '{{%post}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_post_like_user', '{{%post_like}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%post_like}}');
    }
}
