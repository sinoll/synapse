<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;

class SharedFileRecipient extends \common\models\base\SharedFileRecipient
{
    public static function tableName(): string { return '{{%shared_file_recipient}}'; }

    public static function primaryKey(): array { return ['shared_file_id', 'user_id']; }

    public function rules(): array
    {
        return [
            [['shared_file_id', 'user_id', 'created_at'], 'required'],
            [['shared_file_id', 'user_id', 'created_at'], 'integer'],
        ];
    }

    public function getSharedFile(): ActiveQuery { return $this->hasOne(SharedFile::class, ['id' => 'shared_file_id']); }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
}
