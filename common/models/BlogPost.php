<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\helpers\HtmlPurifier;

class BlogPost extends \common\models\base\Blog
{
    public static function tableName(): string { return '{{%blog}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function rules(): array
    {
        return [
            [['post_id', 'title', 'content'], 'required'],
            [['post_id'], 'integer'],
            [['content', 'summary'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['summary'], 'string', 'max' => 500],
        ];
    }

    public function beforeValidate(): bool
    {
        if (is_string($this->content)) {
            $this->content = HtmlPurifier::process($this->content, [
                'HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span',
            ]);
        }

        return parent::beforeValidate();
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
}
