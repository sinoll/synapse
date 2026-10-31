<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the shared file detail store. */
class m261016_000008_create_shared_file_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%shared_file}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'folder_id' => $this->bigInteger()->null(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text()->null(),
            'file_path' => $this->string(500)->notNull(),
            'original_name' => $this->string(255)->notNull(),
            'mime_type' => $this->string(127)->null(),
            'size_bytes' => $this->bigInteger()->notNull()->defaultValue(0),
            // 1: active, 9: trash. Visibility remains centralized on post.visibility.
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            'deleted_at' => $this->integer()->null(),
            // 0: inherit the folder policy, 1: use post.visibility and file recipients
            'share_mode' => $this->smallInteger()->notNull()->defaultValue(0),
            'share_starts_at' => $this->integer()->null(),
            'share_ends_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_shared_file_status_created', '{{%shared_file}}', ['status', 'created_at']);
        $this->createIndex('idx_shared_file_status_deleted', '{{%shared_file}}', ['status', 'deleted_at']);
        $this->createIndex('idx_shared_file_folder_status', '{{%shared_file}}', ['folder_id', 'status']);
        $this->createIndex('idx_shared_file_share_mode_status', '{{%shared_file}}', ['share_mode', 'status']);
        $this->createIndex('uq_shared_file_post', '{{%shared_file}}', 'post_id', true);
        $this->addForeignKey('fk_shared_file_post', '{{%shared_file}}', 'post_id', '{{%post}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_folder', '{{%shared_file}}', 'folder_id', '{{%shared_file_folder}}', 'id', 'SET NULL');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%shared_file}}');
    }
}
