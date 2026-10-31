<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the short video detail store. */
class m261016_000006_create_short_video_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%short_video}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text()->null(),
            'video_path' => $this->string(500)->notNull(),
            'cover_path' => $this->string(500)->null(),
            'duration_seconds' => $this->integer()->null(),
            // 1: Horizontal, 2: Vertical（对应 params['video.type']）
            'video_type' => $this->smallInteger()->notNull()->defaultValue(1),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('uq_short_video_post', '{{%short_video}}', 'post_id', true);
        $this->addForeignKey('fk_short_video_post', '{{%short_video}}', 'post_id', '{{%post}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%short_video}}');
    }
}
