<?php

namespace common\models\base;

use Yii;

/**
 * This is the model class for table "{{%moment}}".
 *
 * @property int $id
 * @property int $post_id
 * @property string $content
 * @property string|null $image_paths
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Post $post
 */
class Moment extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%moment}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['image_paths'], 'default', 'value' => null],
            [['post_id', 'content', 'created_at', 'updated_at'], 'required'],
            [['post_id', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['image_paths'], 'safe'],
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
            'content' => 'Content',
            'image_paths' => 'Image Paths',
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
