<?php

declare(strict_types=1);

namespace common\models;

use common\components\RichText;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

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
            $this->content = RichText::purify($this->content);
        }

        return parent::beforeValidate();
    }

    /**
     * 为正文中的 h2/h3 注入锚点，并返回可用于渲染目录的条目。
     * 必须在 HTMLPurifier 之后调用：锚点由本站生成，因此无需放开白名单里的 h2[id]。
     *
     * @return array{0: string, 1: array<int, array{level: int, text: string, anchor: string}>}
     */
    public static function withHeadingAnchors(string $html): array
    {
        $index = 0;
        $headings = [];
        $annotated = preg_replace_callback(
            '/<h([23])\b([^>]*)>(.*?)<\/h\1>/su',
            static function (array $matches) use (&$index, &$headings): string {
                $text = trim(strip_tags($matches[3]));
                if ($text === '') {
                    return $matches[0];
                }

                $level = (int) $matches[1];
                $anchor = 'sec-' . (++$index);
                $headings[] = ['level' => $level, 'text' => $text, 'anchor' => $anchor];

                return '<h' . $level . ' id="' . $anchor . '"' . $matches[2] . '>' . $matches[3] . '</h' . $level . '>';
            },
            $html
        );

        return [$annotated ?? $html, $headings];
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
}
