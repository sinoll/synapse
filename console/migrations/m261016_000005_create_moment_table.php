<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the moment detail store. */
class m261016_000005_create_moment_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%moment}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'title' => $this->string(200)->null()->comment('标题'),
            'content' => $this->text()->notNull(),
            'thumb' => $this->string(1024)->null()->comment('缩略图存放路径'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('uq_moment_post', '{{%moment}}', 'post_id', true);
        $this->addForeignKey('fk_moment_post', '{{%moment}}', 'post_id', '{{%post}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%moment}}');
    }
}
