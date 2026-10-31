<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the folder-level share recipients. */
class m261016_000010_create_shared_file_folder_recipient_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%shared_file_folder_recipient}}', [
            'folder_id' => $this->bigInteger()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY(folder_id, user_id)',
        ], $options);
        $this->createIndex('idx_shared_file_folder_recipient_user', '{{%shared_file_folder_recipient}}', 'user_id');
        $this->addForeignKey('fk_shared_file_folder_recipient_folder', '{{%shared_file_folder_recipient}}', 'folder_id', '{{%shared_file_folder}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_folder_recipient_user', '{{%shared_file_folder_recipient}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%shared_file_folder_recipient}}');
    }
}
