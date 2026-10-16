<?php

namespace common\models\base;

use Yii;

/**
 * This is the model class for table "{{%images}}".
 *
 * @property int $id
 * @property int $post_id
 * @property string $title
 * @property string $file_path
 * @property string|null $alt_text
 * @property int $sort_order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Post $post
 */
class Image extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%images}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['alt_text'], 'default', 'value' => null],
            [['sort_order'], 'default', 'value' => 0],
            [['post_id', 'title', 'file_path', 'created_at', 'updated_at'], 'required'],
            [['post_id', 'sort_order', 'created_at', 'updated_at'], 'integer'],
            [['title'], 'string', 'max' => 200],
            [['file_path'], 'string', 'max' => 1024],
            [['alt_text'], 'string', 'max' => 255],
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
            'file_path' => 'File Path',
            'alt_text' => 'Alt Text',
            'sort_order' => 'Sort Order',
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
