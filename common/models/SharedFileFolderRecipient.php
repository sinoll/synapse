<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;

class SharedFileFolderRecipient extends \common\models\base\SharedFileFolderRecipient
{
    public static function tableName(): string { return '{{%shared_file_folder_recipient}}'; }

    public static function primaryKey(): array { return ['folder_id', 'user_id']; }

    public function rules(): array
    {
        return [
            [['folder_id', 'user_id', 'created_at'], 'required'],
            [['folder_id', 'user_id', 'created_at'], 'integer'],
        ];
    }

    public function getFolder(): ActiveQuery { return $this->hasOne(SharedFileFolder::class, ['id' => 'folder_id']); }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
}
