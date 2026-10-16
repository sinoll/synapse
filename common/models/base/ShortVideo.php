<?php

namespace common\models\base;

use Yii;

/**
 * This is the model class for table "{{%short_video}}".
 *
 * @property int $id
 * @property int $post_id
 * @property string $title
 * @property string|null $description
 * @property string $video_path
 * @property string|null $cover_path
 * @property int|null $duration_seconds
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Post $post
 */
class ShortVideo extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%short_video}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description', 'cover_path', 'duration_seconds'], 'default', 'value' => null],
            [['post_id', 'title', 'video_path', 'created_at', 'updated_at'], 'required'],
            [['post_id', 'duration_seconds', 'created_at', 'updated_at'], 'integer'],
            [['description'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['video_path', 'cover_path'], 'string', 'max' => 500],
            [['post_id'], 'unique'],
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
            'title' => 'Title',
            'description' => 'Description',
            'video_path' => 'Video Path',
            'cover_path' => 'Cover Path',
            'duration_seconds' => 'Duration Seconds',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
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

}
