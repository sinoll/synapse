<?php

declare(strict_types=1);

use yii\db\Migration;

class m261025_000001_create_cate_tables extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        // 创建分类表
        $this->createTable('{{%cate}}', [
            'id' => $this->bigPrimaryKey(),
            'name' => $this->string(50)->notNull()->unique(),
            'slug' => $this->string(64)->notNull()->unique(),
            'description' => $this->text(),
            'thumb' => $this->string(255),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);

        // 创建文章-分类关联表
        $this->createTable('{{%post_cate}}', [
            'post_id' => $this->bigInteger()->notNull(),
            'cate_id' => $this->bigInteger()->notNull(),
            'PRIMARY KEY([[post_id]], [[cate_id]])',
        ], $options);

        // 添加外键约束
        $this->addForeignKey(
            'fk-post-cate-post-id',
            '{{%post_cate}}',
            'post_id',
            '{{%post}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-post-cate-cate-id',
            '{{%post_cate}}',
            'cate_id',
            '{{%cate}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // 添加索引
        $this->createIndex('idx-post-cate-post-id', '{{%post_cate}}', 'post_id');
        $this->createIndex('idx-post-cate-cate-id', '{{%post_cate}}', 'cate_id');
    }

    public function safeDown(): void
    {
        // 删除外键
        $this->dropForeignKey('fk-post-cate-post-id', '{{%post_cate}}');
        $this->dropForeignKey('fk-post-cate-cate-id', '{{%post_cate}}');

        // 删除表
        $this->dropTable('{{%post_cate}}');
        $this->dropTable('{{%cate}}');
    }
}
