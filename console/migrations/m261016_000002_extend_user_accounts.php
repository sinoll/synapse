<?php

declare(strict_types=1);

use yii\db\Migration;

/** Adds managed-account, first-login, SMS verification, and profile fields. */
class m261016_000002_extend_user_accounts extends Migration
{
    public function safeUp(): void
    {
        $table = '{{%user}}';
        $schema = $this->db->getTableSchema($table, true);

        $columns = [
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
        ];

        foreach ($columns as $name => $definition) {
            if (!isset($schema->columns[$name])) {
                $this->addColumn($table, $name, $definition);
            }
        }
        if (!isset($schema->columns['phone'])) {
            $this->createIndex('uq_user_phone', $table, 'phone', true);
        }

        if (!$this->db->createCommand("SELECT 1 FROM {{%user}} WHERE username = :username", [':username' => 'default'])->queryScalar()) {
            $now = time();
            $this->insert($table, [
                'username' => 'default',
                'auth_key' => Yii::$app->security->generateRandomString(32),
                'password_hash' => Yii::$app->security->generatePasswordHash('default'),
                'password_reset_token' => null,
                'email' => 'default@localhost.invalid',
                'status' => 10,
                'created_at' => $now,
                'updated_at' => $now,
                'nickname' => '默认管理员',
                'must_change_password' => 1,
                'is_admin' => 1,
            ]);
        } else {
            $this->update($table, ['is_admin' => 1, 'must_change_password' => 1], ['username' => 'default']);
        }
    }

    public function safeDown(): void
    {
        $table = '{{%user}}';
        $this->dropIndex('uq_user_phone', $table);
        foreach ([
            'is_admin',
            'must_change_password',
            'sms_code_expires_at',
            'sms_code_sent_at',
            'sms_code_attempts',
            'sms_code_hash',
            'phone_verified_at',
            'phone',
            'bio',
            'avatar',
            'nickname',
        ] as $name) {
            $schema = $this->db->getTableSchema($table, true);
            if (isset($schema->columns[$name])) {
                $this->dropColumn($table, $name);
            }
        }
    }
}
