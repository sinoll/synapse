<?php

declare(strict_types=1);

use yii\db\Migration;

/** Allows dynamic posts to store no title; other types remain model-validated. */
class m261021_000001_allow_null_moment_titles extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn('{{%post}}', 'title', $this->string(200)->null());
    }

    public function safeDown(): void
    {
        // Existing moment rows must satisfy the old schema before NOT NULL returns.
        $this->update('{{%post}}', ['title' => '动态'], ['type' => 'moment', 'title' => null]);
        $this->alterColumn('{{%post}}', 'title', $this->string(200)->notNull());
    }
}
