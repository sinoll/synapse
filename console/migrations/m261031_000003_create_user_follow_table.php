<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the user-to-user follow relation. */
class m261031_000003_create_user_follow_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%user_follow}}', [
            'follower_id' => $this->integer()->notNull(),
            'following_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], $options);

        // One follow per direction; the composite key serves "who I follow" lookups.
        $this->addPrimaryKey('pk_user_follow', '{{%user_follow}}', ['follower_id', 'following_id']);
        $this->createIndex('idx_user_follow_following_created', '{{%user_follow}}', ['following_id', 'created_at']);
        $this->addCheck('chk_user_follow_not_self', '{{%user_follow}}', 'follower_id <> following_id');

        // MariaDB rejects ON UPDATE CASCADE on columns covered by a CHECK constraint.
        // User ids are immutable, so only the delete action needs cascading.
        $this->addForeignKey('fk_user_follow_follower', '{{%user_follow}}', 'follower_id', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_user_follow_following', '{{%user_follow}}', 'following_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%user_follow}}');
    }
}
