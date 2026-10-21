<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates reusable tags and many-to-many links to supported post types. */
class m261021_000002_create_tag_tables extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%tag}}', [
            'id' => $this->bigPrimaryKey(),
            'name' => $this->string(50)->notNull(),
            'slug' => $this->string(64)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('uq_tag_name', '{{%tag}}', 'name', true);
        $this->createIndex('uq_tag_slug', '{{%tag}}', 'slug', true);

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
        $this->dropForeignKey('fk_post_tag_tag', '{{%post_tag}}');
        $this->dropForeignKey('fk_post_tag_post', '{{%post_tag}}');
        $this->dropTable('{{%post_tag}}');
        $this->dropTable('{{%tag}}');
    }
}
