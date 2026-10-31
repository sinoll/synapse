<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the shared-file folder tree. */
class m261016_000007_create_shared_file_folder_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        // A user's drive folder. share_mode=0 inherits from the parent; the root resolves to private.
        $this->createTable('{{%shared_file_folder}}', [
            'id' => $this->bigPrimaryKey(),
            'user_id' => $this->integer()->notNull(),
            'parent_id' => $this->bigInteger()->null(),
            'name' => $this->string(200)->notNull(),
            // 0: inherit, 1: private, 2: selected users, 3: registered users, 4: public
            'share_mode' => $this->smallInteger()->notNull()->defaultValue(0),
            // 1: active, 9: trash
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            'deleted_at' => $this->integer()->null(),
            'share_starts_at' => $this->integer()->null(),
            'share_ends_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_shared_file_folder_owner_parent', '{{%shared_file_folder}}', ['user_id', 'parent_id']);
        $this->createIndex('idx_shared_file_folder_status_deleted', '{{%shared_file_folder}}', ['status', 'deleted_at']);
        $this->createIndex('idx_shared_file_folder_share_status', '{{%shared_file_folder}}', ['share_mode', 'status']);
        $this->addForeignKey('fk_shared_file_folder_user', '{{%shared_file_folder}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_folder_parent', '{{%shared_file_folder}}', 'parent_id', '{{%shared_file_folder}}', 'id', 'RESTRICT');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%shared_file_folder}}');
    }
}
