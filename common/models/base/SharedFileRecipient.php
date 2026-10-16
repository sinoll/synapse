<?php

namespace common\models\base;

use common\models\User;
use Yii;

/**
 * This is the model class for table "{{%shared_file_recipient}}".
 *
 * @property int $shared_file_id
 * @property int $user_id
 * @property int $created_at
 *
 * @property SharedFile $sharedFile
 * @property User $user
 */
class SharedFileRecipient extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%shared_file_recipient}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shared_file_id', 'user_id', 'created_at'], 'required'],
            [['shared_file_id', 'user_id', 'created_at'], 'integer'],
            [['shared_file_id', 'user_id'], 'unique', 'targetAttribute' => ['shared_file_id', 'user_id']],
            [['shared_file_id'], 'exist', 'skipOnError' => true, 'targetClass' => SharedFile::class, 'targetAttribute' => ['shared_file_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'shared_file_id' => 'Shared File ID',
            'user_id' => 'User ID',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[SharedFile]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSharedFile()
    {
        return $this->hasOne(SharedFile::class, ['id' => 'shared_file_id']);
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
