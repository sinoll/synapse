<?php

namespace common\models\base;

use common\models\User;
use Yii;

/**
 * This is the model class for table "{{%post}}".
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property int $status
 * @property int $visibility
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Blog $blog
 * @property Image[] $images
 * @property Moment $moment
 * @property SharedFile $sharedFile
 * @property ShortVideo $shortVideo
 * @property User $user
 */
class Post extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%post}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status'], 'default', 'value' => 1],
            [['visibility'], 'default', 'value' => 0],
            [['user_id', 'type', 'title', 'created_at', 'updated_at'], 'required'],
            [['user_id', 'status', 'visibility', 'created_at', 'updated_at'], 'integer'],
            [['type'], 'string', 'max' => 32],
            [['title'], 'string', 'max' => 200],
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
            'type' => 'Type',
            'title' => 'Title',
            'status' => 'Status',
            'visibility' => 'Visibility',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[Blog]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBlog()
    {
        return $this->hasOne(Blog::class, ['post_id' => 'id']);
    }

    /**
     * Gets query for [[Images]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImages()
    {
        return $this->hasMany(Image::class, ['post_id' => 'id']);
    }

    /**
     * Gets query for [[Moment]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMoment()
    {
        return $this->hasOne(Moment::class, ['post_id' => 'id']);
    }

    /**
     * Gets query for [[SharedFile]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSharedFile()
    {
        return $this->hasOne(SharedFile::class, ['post_id' => 'id']);
    }

    /**
     * Gets query for [[ShortVideo]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShortVideo()
    {
        return $this->hasOne(ShortVideo::class, ['post_id' => 'id']);
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
