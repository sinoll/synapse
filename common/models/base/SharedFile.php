<?php

namespace common\models\base;

use common\models\User;
use Yii;

/**
 * This is the model class for table "{{%shared_file}}".
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $folder_id
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property int $status
 * @property int|null $deleted_at
 * @property int $share_mode
 * @property int|null $share_starts_at
 * @property int|null $share_ends_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property SharedFileFolder $folder
 * @property Post $post
 * @property SharedFileRecipient[] $sharedFileRecipients
 * @property User[] $users
 */
class SharedFile extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%shared_file}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['folder_id', 'description', 'mime_type', 'deleted_at', 'share_starts_at', 'share_ends_at'], 'default', 'value' => null],
            [['share_mode'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => 1],
            [['post_id', 'title', 'file_path', 'original_name', 'created_at', 'updated_at'], 'required'],
            [['post_id', 'folder_id', 'size_bytes', 'status', 'deleted_at', 'share_mode', 'share_starts_at', 'share_ends_at', 'created_at', 'updated_at'], 'integer'],
            [['description'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['file_path'], 'string', 'max' => 500],
            [['original_name'], 'string', 'max' => 255],
            [['mime_type'], 'string', 'max' => 127],
            [['post_id'], 'unique'],
            [['folder_id'], 'exist', 'skipOnError' => true, 'targetClass' => SharedFileFolder::class, 'targetAttribute' => ['folder_id' => 'id']],
            [['post_id'], 'exist', 'skipOnError' => true, 'targetClass' => Post::class, 'targetAttribute' => ['post_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'post_id' => 'Post ID',
            'folder_id' => 'Folder ID',
            'title' => 'Title',
            'description' => 'Description',
            'file_path' => 'File Path',
            'original_name' => 'Original Name',
            'mime_type' => 'Mime Type',
            'size_bytes' => 'Size Bytes',
            'status' => 'Status',
            'deleted_at' => 'Deleted At',
            'share_mode' => 'Share Mode',
            'share_starts_at' => 'Share Starts At',
            'share_ends_at' => 'Share Ends At',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
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
     * Gets query for [[Post]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPost()
    {
        return $this->hasOne(Post::class, ['id' => 'post_id']);
    }

    /**
     * Gets query for [[SharedFileRecipients]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSharedFileRecipients()
    {
        return $this->hasMany(SharedFileRecipient::class, ['shared_file_id' => 'id']);
    }

    /**
     * Gets query for [[Users]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%shared_file_recipient}}', ['shared_file_id' => 'id']);
    }

}
