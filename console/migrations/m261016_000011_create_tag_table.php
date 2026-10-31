<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the tag dictionary. */
class m261016_000011_create_tag_table extends Migration
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
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%tag}}');
    }
}
