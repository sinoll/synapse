<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the shared post table that all content types reference. */
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
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            // 0: owner only, 1: authenticated users, 2: public, 3: selected users (shared files only)
            'visibility' => $this->smallInteger()->notNull()->defaultValue(0),
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
