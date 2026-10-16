<?php

namespace common\models\base;

use common\models\User;
use Yii;

/**
 * This is the model class for table "{{%shared_file_folder}}".
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property int $share_mode
 * @property int $status
 * @property int|null $deleted_at
 * @property int|null $share_starts_at
 * @property int|null $share_ends_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property SharedFileFolder $parent
 * @property SharedFileFolderRecipient[] $sharedFileFolderRecipients
 * @property SharedFileFolder[] $sharedFileFolders
 * @property SharedFile[] $sharedFiles
 * @property User $user
 * @property User[] $users
 */
class SharedFileFolder extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%shared_file_folder}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['parent_id', 'deleted_at', 'share_starts_at', 'share_ends_at'], 'default', 'value' => null],
            [['share_mode'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => 1],
            [['user_id', 'name', 'created_at', 'updated_at'], 'required'],
            [['user_id', 'parent_id', 'share_mode', 'status', 'deleted_at', 'share_starts_at', 'share_ends_at', 'created_at', 'updated_at'], 'integer'],
            [['name'], 'string', 'max' => 200],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => SharedFileFolder::class, 'targetAttribute' => ['parent_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'parent_id' => 'Parent ID',
            'name' => 'Name',
            'share_mode' => 'Share Mode',
            'status' => 'Status',
            'deleted_at' => 'Deleted At',
            'share_starts_at' => 'Share Starts At',
            'share_ends_at' => 'Share Ends At',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Parent]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(SharedFileFolder::class, ['id' => 'parent_id']);
    }

    /**
     * Gets query for [[SharedFileFolderRecipients]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSharedFileFolderRecipients()
    {
        return $this->hasMany(SharedFileFolderRecipient::class, ['folder_id' => 'id']);
    }

    /**
     * Gets query for [[SharedFileFolders]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSharedFileFolders()
    {
        return $this->hasMany(SharedFileFolder::class, ['parent_id' => 'id']);
    }

    /**
     * Gets query for [[SharedFiles]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSharedFiles()
    {
        return $this->hasMany(SharedFile::class, ['folder_id' => 'id']);
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

    /**
     * Gets query for [[Users]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%shared_file_folder_recipient}}', ['folder_id' => 'id']);
    }

}
