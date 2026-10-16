<?php

namespace common\models\base;

use common\models\User;
use Yii;

/**
 * This is the model class for table "{{%shared_file_folder_recipient}}".
 *
 * @property int $folder_id
 * @property int $user_id
 * @property int $created_at
 *
 * @property SharedFileFolder $folder
 * @property User $user
 */
class SharedFileFolderRecipient extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%shared_file_folder_recipient}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['folder_id', 'user_id', 'created_at'], 'required'],
            [['folder_id', 'user_id', 'created_at'], 'integer'],
            [['folder_id', 'user_id'], 'unique', 'targetAttribute' => ['folder_id', 'user_id']],
            [['folder_id'], 'exist', 'skipOnError' => true, 'targetClass' => SharedFileFolder::class, 'targetAttribute' => ['folder_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'folder_id' => 'Folder ID',
            'user_id' => 'User ID',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Folder]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getFolder()
    {
        return $this->hasOne(SharedFileFolder::class, ['id' => 'folder_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

}
