<?php

declare(strict_types=1);

use yii\db\Migration;

class m130524_201442_init extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string()->notNull()->unique(),
            'auth_key' => $this->string(32)->notNull(),
            'password_hash' => $this->string()->notNull(),
            'password_reset_token' => $this->string()->unique(),
            'verification_token' => $this->string()->defaultValue(null),
            'email' => $this->string()->notNull()->unique(),

            'nickname' => $this->string(80)->null(),
            'avatar' => $this->string(500)->null(),
            'bio' => $this->string(500)->null(),
            'phone' => $this->string(32)->null(),
            'phone_verified_at' => $this->integer()->null(),
            'sms_code_hash' => $this->string(255)->null(),
            'sms_code_expires_at' => $this->integer()->null(),
            'sms_code_sent_at' => $this->integer()->null(),
            'sms_code_attempts' => $this->smallInteger()->notNull()->defaultValue(0),
            'must_change_password' => $this->boolean()->notNull()->defaultValue(1),
            'is_admin' => $this->boolean()->notNull()->defaultValue(0),

            'status' => $this->smallInteger()->notNull()->defaultValue(10),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('uq_user_phone', '{{%user}}', 'phone', true);

        $now = time();
        $this->insert('{{%user}}', [
            'username' => 'default',
            'auth_key' => Yii::$app->security->generateRandomString(32),
            'password_hash' => Yii::$app->security->generatePasswordHash('default'),
            'password_reset_token' => null,
            'verification_token' => null,
            'email' => 'default@localhost.invalid',
            'status' => 10,
            'created_at' => $now,
            'updated_at' => $now,
            'nickname' => '默认管理员',
            'must_change_password' => 1,
            'is_admin' => 1,
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%user}}');
    }
}
