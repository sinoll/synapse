<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the post-tag pivot table. */
class m261016_000012_create_post_tag_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%post_tag}}', [
            'post_id' => $this->bigInteger()->notNull(),
            'tag_id' => $this->bigInteger()->notNull(),
        ], $options);
        $this->addPrimaryKey('pk_post_tag', '{{%post_tag}}', ['post_id', 'tag_id']);
        $this->createIndex('idx_post_tag_tag_post', '{{%post_tag}}', ['tag_id', 'post_id']);
        $this->addForeignKey('fk_post_tag_post', '{{%post_tag}}', 'post_id', '{{%post}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_post_tag_tag', '{{%post_tag}}', 'tag_id', '{{%tag}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%post_tag}}');
    }
}
