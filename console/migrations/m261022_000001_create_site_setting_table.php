<?php

declare(strict_types=1);

use yii\db\Migration;

class m261022_000001_create_site_setting_table extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%site_setting}}', [
            'key' => $this->string(64)->notNull(),
            'value' => $this->string(500)->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'PRIMARY KEY([[key]])',
        ], $options);
        $this->insert('{{%site_setting}}', [
            'key' => 'site_name',
            'value' => '拾光',
            'updated_at' => time(),
        ]);
        $this->insert('{{%site_setting}}', [
            'key' => 'node_enabled',
            'value' => '0',
            'updated_at' => time(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%site_setting}}');
    }
}
