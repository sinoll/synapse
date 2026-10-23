<?php

declare(strict_types=1);

use yii\db\Migration;

/** Creates the shared post table and five type-specific content stores. */
class m261016_000001_create_share_content_tables extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        // Keep type as a string so new content types do not require a schema change.
        $this->createTable('{{%post}}', [
            'id' => $this->bigPrimaryKey(),
            'user_id' => $this->integer()->notNull(),
            'type' => $this->string(32)->notNull(),
            'title' => $this->string(200)->null(),
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            // 0: owner only, 1: authenticated users, 2: public, 3: selected users (shared files only)
            'visibility' => $this->smallInteger()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_post_type_visibility_status_created', '{{%post}}', ['type', 'visibility', 'status', 'created_at']);
        $this->createIndex('idx_post_user_created', '{{%post}}', ['user_id', 'created_at']);
        $this->addForeignKey('fk_post_user', '{{%post}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        $this->createTable('{{%blog}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'title' => $this->string(200)->notNull(),
            'summary' => $this->string(500)->null(),
            'content' => $this->text()->notNull(),
            'views'   => $this->integer()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('uq_blog_post', '{{%blog}}', 'post_id', true);
        $this->addForeignKey('fk_blog_post', '{{%blog}}', 'post_id', '{{%post}}', 'id', 'CASCADE');

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

        $this->createTable('{{%moment}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'content' => $this->text()->notNull(),
            'image_paths' => $this->json()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('uq_moment_post', '{{%moment}}', 'post_id', true);
        $this->addForeignKey('fk_moment_post', '{{%moment}}', 'post_id', '{{%post}}', 'id', 'CASCADE');

        $this->createTable('{{%short_video}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text()->null(),
            'video_path' => $this->string(500)->notNull(),
            'cover_path' => $this->string(500)->null(),
            'duration_seconds' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('uq_short_video_post', '{{%short_video}}', 'post_id', true);
        $this->addForeignKey('fk_short_video_post', '{{%short_video}}', 'post_id', '{{%post}}', 'id', 'CASCADE');

        // A user's drive folder. share_mode=0 inherits from the parent; the root resolves to private.
        $this->createTable('{{%shared_file_folder}}', [
            'id' => $this->bigPrimaryKey(),
            'user_id' => $this->integer()->notNull(),
            'parent_id' => $this->bigInteger()->null(),
            'name' => $this->string(200)->notNull(),
            // 0: inherit, 1: private, 2: selected users, 3: registered users, 4: public
            'share_mode' => $this->smallInteger()->notNull()->defaultValue(0),
            // 1: active, 9: trash
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            'deleted_at' => $this->integer()->null(),
            'share_starts_at' => $this->integer()->null(),
            'share_ends_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_shared_file_folder_owner_parent', '{{%shared_file_folder}}', ['user_id', 'parent_id']);
        $this->createIndex('idx_shared_file_folder_status_deleted', '{{%shared_file_folder}}', ['status', 'deleted_at']);
        $this->createIndex('idx_shared_file_folder_share_status', '{{%shared_file_folder}}', ['share_mode', 'status']);
        $this->addForeignKey('fk_shared_file_folder_user', '{{%shared_file_folder}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_folder_parent', '{{%shared_file_folder}}', 'parent_id', '{{%shared_file_folder}}', 'id', 'RESTRICT');

        $this->createTable('{{%shared_file}}', [
            'id' => $this->bigPrimaryKey(),
            'post_id' => $this->bigInteger()->notNull(),
            'folder_id' => $this->bigInteger()->null(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text()->null(),
            'file_path' => $this->string(500)->notNull(),
            'original_name' => $this->string(255)->notNull(),
            'mime_type' => $this->string(127)->null(),
            'size_bytes' => $this->bigInteger()->notNull()->defaultValue(0),
            // 1: active, 9: trash. Visibility remains centralized on post.visibility.
            'status' => $this->smallInteger()->notNull()->defaultValue(1),
            'deleted_at' => $this->integer()->null(),
            // 0: inherit the folder policy, 1: use post.visibility and file recipients
            'share_mode' => $this->smallInteger()->notNull()->defaultValue(0),
            'share_starts_at' => $this->integer()->null(),
            'share_ends_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_shared_file_status_created', '{{%shared_file}}', ['status', 'created_at']);
        $this->createIndex('idx_shared_file_status_deleted', '{{%shared_file}}', ['status', 'deleted_at']);
        $this->createIndex('idx_shared_file_folder_status', '{{%shared_file}}', ['folder_id', 'status']);
        $this->createIndex('idx_shared_file_share_mode_status', '{{%shared_file}}', ['share_mode', 'status']);
        $this->createIndex('uq_shared_file_post', '{{%shared_file}}', 'post_id', true);
        $this->addForeignKey('fk_shared_file_post', '{{%shared_file}}', 'post_id', '{{%post}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_folder', '{{%shared_file}}', 'folder_id', '{{%shared_file_folder}}', 'id', 'SET NULL');

        // Recipients are only consulted when the parent post visibility is selected-users.
        $this->createTable('{{%shared_file_recipient}}', [
            'shared_file_id' => $this->bigInteger()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY(shared_file_id, user_id)',
        ], $options);
        $this->createIndex('idx_shared_file_recipient_user', '{{%shared_file_recipient}}', 'user_id');
        $this->addForeignKey('fk_shared_file_recipient_file', '{{%shared_file_recipient}}', 'shared_file_id', '{{%shared_file}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_recipient_user', '{{%shared_file_recipient}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        $this->createTable('{{%shared_file_folder_recipient}}', [
            'folder_id' => $this->bigInteger()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY(folder_id, user_id)',
        ], $options);
        $this->createIndex('idx_shared_file_folder_recipient_user', '{{%shared_file_folder_recipient}}', 'user_id');
        $this->addForeignKey('fk_shared_file_folder_recipient_folder', '{{%shared_file_folder_recipient}}', 'folder_id', '{{%shared_file_folder}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_shared_file_folder_recipient_user', '{{%shared_file_folder_recipient}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

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
        $this->dropTable('{{%shared_file_recipient}}');
        $this->dropTable('{{%shared_file_folder_recipient}}');
        $this->dropTable('{{%shared_file}}');
        $this->dropTable('{{%shared_file_folder}}');
        $this->dropTable('{{%short_video}}');
        $this->dropTable('{{%moment}}');
        $this->dropTable('{{%images}}');
        $this->dropTable('{{%blog}}');
        $this->dropTable('{{%post}}');
    }
}
