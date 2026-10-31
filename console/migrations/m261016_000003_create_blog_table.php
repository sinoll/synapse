<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the blog detail store. */
class m261016_000003_create_blog_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%blog}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'title' => $this->string(200)->notNull(),
            'summary' => $this->string(500)->null()->comment('文章简介'),
            'thumb' => $this->string(1024)->null()->comment('文章缩略地址'),
            'content' => $this->text()->notNull()->comment('文章正文'),
            'views' => $this->integer()->defaultValue(0)->comment('文章浏览数'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('uq_blog_post', '{{%blog}}', 'post_id', true);
        $this->addForeignKey('fk_blog_post', '{{%blog}}', 'post_id', '{{%post}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%blog}}');
    }
}
