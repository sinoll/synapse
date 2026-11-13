<?php

declare(strict_types=1);

use yii\db\Migration;

class m261113_000001_create_user_message_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%user_message}}', [
            'id' => $this->bigPrimaryKey(),
            'sender_id' => $this->integer()->notNull(),
            'recipient_id' => $this->integer()->notNull(),
            'type' => $this->string(20)->notNull(),
            'content' => $this->text()->notNull(),
            'source_type' => $this->string(32)->null(),
            'source_id' => $this->bigInteger()->null(),
            'read_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);

        $this->createIndex(
            'uq_user_message_mention_source',
            '{{%user_message}}',
            ['type', 'recipient_id', 'source_type', 'source_id'],
            true
        );
        $this->createIndex('idx_user_message_recipient_read_created', '{{%user_message}}', ['recipient_id', 'read_at', 'created_at']);
        $this->createIndex('idx_user_message_sender_created', '{{%user_message}}', ['sender_id', 'created_at']);
        $this->addForeignKey('fk_user_message_sender', '{{%user_message}}', 'sender_id', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_user_message_recipient', '{{%user_message}}', 'recipient_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%user_message}}');
    }
}
