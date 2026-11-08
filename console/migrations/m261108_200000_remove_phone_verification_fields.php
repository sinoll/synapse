<?php

declare(strict_types=1);

use yii\db\Migration;

class m261108_200000_remove_phone_verification_fields extends Migration
{
    private const COLUMNS = [
        'phone_verified_at',
        'sms_code_hash',
        'sms_code_expires_at',
        'sms_code_sent_at',
        'sms_code_attempts',
    ];

    public function safeUp(): void
    {
        foreach (self::COLUMNS as $column) {
            $this->dropColumn('{{%user}}', $column);
        }
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%user}}', 'phone_verified_at', $this->integer()->null());
        $this->addColumn('{{%user}}', 'sms_code_hash', $this->string(255)->null());
        $this->addColumn('{{%user}}', 'sms_code_expires_at', $this->integer()->null());
        $this->addColumn('{{%user}}', 'sms_code_sent_at', $this->integer()->null());
        $this->addColumn('{{%user}}', 'sms_code_attempts', $this->smallInteger()->notNull()->defaultValue(0));
    }
}
