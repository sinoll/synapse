<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates favorites (收藏) for any content type through the shared post table.
 *
 * 博客、动态、图集、短视频都是 {{%post}} 的行，靠 post.type 区分，所以这里只关联 post_id，
 * 不需要 target_type/target_id；收藏一条图集等于收藏整个图集（那个 post），不是其中单张照片。
 */
class m261102_000001_create_post_favorite_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%post_favorite}}', [
            'user_id' => $this->integer()->notNull()->comment('收藏者，对应 user.id'),
            'post_id' => $this->bigInteger()->notNull()->comment('被收藏的内容，对应 post.id'),
            'created_at' => $this->integer()->notNull()->comment('收藏时间，我的收藏列表排序依据'),
        ], $options);

        // 一人对一条内容只收藏一次。主键以 user_id 前导：InnoDB 主键即聚簇索引，
        // 而收藏的主要访问方向是「我的收藏」（用户优先），与 post_like 的 post 前导相反。
        $this->addPrimaryKey('pk_post_favorite', '{{%post_favorite}}', ['user_id', 'post_id']);
        // 反查某条内容的收藏数，以及列表页批量判断「当前用户是否已收藏」。
        $this->createIndex('idx_post_favorite_post_user', '{{%post_favorite}}', ['post_id', 'user_id']);
        // 我的收藏按时间倒序分页，避免 filesort。
        $this->createIndex('idx_post_favorite_user_created', '{{%post_favorite}}', ['user_id', 'created_at']);

        $this->addForeignKey('fk_post_favorite_post', '{{%post_favorite}}', 'post_id', '{{%post}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_post_favorite_user', '{{%post_favorite}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%post_favorite}}');
    }
}
