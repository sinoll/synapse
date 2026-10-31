<?php

namespace common\models\base;

use Yii;

/**
 * This is the model class for table "{{%blog}}".
 *
 * @property int $id
 * @property int $post_id
 * @property string $title
 * @property string|null $summary
 * @property string $content
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Post $post
 */
class Blog extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%blog}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['summary'], 'default', 'value' => null],
            [['post_id', 'title', 'content', 'created_at', 'updated_at'], 'required'],
            [['post_id', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['summary'], 'string', 'max' => 500],
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
            'summary' => 'Summary',
            'content' => 'Content',
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
