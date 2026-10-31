<?php

namespace common\models\base;

use Yii;

/**
 * This is the model class for table "{{%moment}}".
 *
 * @property int $id
 * @property int $post_id
 * @property string|null $title 标题
 * @property string $content
 * @property string|null $thumb 缩略图存放路径
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
            [['title', 'thumb'], 'default', 'value' => null],
            [['post_id', 'content', 'created_at', 'updated_at'], 'required'],
            [['post_id', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['thumb'], 'string', 'max' => 1024],
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
            'content' => 'Content',
            'thumb' => 'Thumb',
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
