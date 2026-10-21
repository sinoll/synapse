<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\helpers\HtmlPurifier;

class Moment extends \common\models\base\Moment
{
    public static function tableName(): string { return '{{%moment}}'; }
    public function behaviors(): array { return [TimestampBehavior::class]; }
    public function rules(): array
    {
        return [
            [['post_id'], 'required'],
            [['content'], 'required', 'isEmpty' => static fn ($value): bool => trim(strip_tags((string) $value)) === '' && !preg_match('/<img\b/i', (string) $value)],
            [['post_id'], 'integer'],
            [['content'], 'string'],
            [['content'], 'validateContentLength'],
            [['image_paths'], 'safe'],
        ];
    }
    public function beforeValidate(): bool
    {
        if (is_string($this->content)) {
            $this->content = HtmlPurifier::process($this->content, [
                'HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title]',
            ]);
        }

        return parent::beforeValidate();
    }
    public function validateContentLength(string $attribute): void
    {
        if (mb_strlen(trim(strip_tags((string) $this->$attribute))) > 5000) {
            $this->addError($attribute, '动态内容不能超过 5000 个字符。');
        }
    }
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), ['content' => '动态内容']);
    }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
}
