<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the file-level share recipients. */
class m261016_000009_create_shared_file_recipient_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        // Recipients are only consulted when the parent post visibility is selected-users.
        $this->createTable('{{%shared_file_recipient}}', [
            'shared_file_id' => $this->bigInteger()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY(shared_file_id, user_id)',
        ], $options);
        $this->createIndex('idx_shared_file_recipient_user', '{{%shared_file_recipient}}', 'user_id');
        $this->addForeignKey('fk_shared_file_recipient_file', '{{%shared_file_recipient}}', 'shared_file_id', '{{%shared_file}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_recipient_user', '{{%shared_file_recipient}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%shared_file_recipient}}');
    }
}
