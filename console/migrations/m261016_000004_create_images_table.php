<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the image gallery detail store. */
class m261016_000004_create_images_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%images}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'title' => $this->string(200)->notNull(),
            'file_path' => $this->string(1024)->notNull(),
            'alt_text' => $this->string(255)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_images_post_sort', '{{%images}}', ['post_id', 'sort_order']);
        $this->addForeignKey('fk_images_post', '{{%images}}', 'post_id', '{{%post}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%images}}');
    }
}
