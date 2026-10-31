<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\helpers\Html;

/** Reusable normalized content tag. */
class Tag extends \yii\db\ActiveRecord
{
    /** @var int|null populated by feed queries that count associated posts */
    public $postCount;

    public static function tableName(): string
    {
        return '{{%tag}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name', 'slug'], 'required'],
            [['name'], 'trim'],
            [['name'], 'string', 'max' => 50],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'match', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            [['name'], 'unique'],
            [['slug'], 'unique'],
        ];
    }

    public static function slugForName(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $ascii === false ? '' : $ascii) ?? '', '-'));
        $base = substr($base, 0, 45);

        return ($base !== '' ? $base : 'tag') . '-' . substr(hash('sha256', mb_strtolower($name)), 0, 12);
    }

    /**
     * 从 HTML 正文中提取微博式 #话题# 名称，按出现顺序去重。
     * @return string[]
     */
    public static function extractHashtags(string $html): array
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match_all('/#([^#\r\n<>]{1,50})#/u', $text, $matches)) {
            return [];
        }

        $names = [];
        $seen = [];
        foreach ($matches[1] as $rawName) {
            $name = self::normalizeHashtagName($rawName);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }

    /** 把正文中的 #话题# 文本转换为标签页链接，跳过 HTML 标签本身。 */
    public static function linkifyHashtags(string $html): string
    {
        $segments = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            return $html;
        }

        $insideAnchor = false;
        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }
            if (str_starts_with($segment, '<')) {
                if (preg_match('/^<a\b/i', $segment)) {
                    $insideAnchor = true;
                } elseif (preg_match('/^<\/a\s*>$/i', $segment)) {
                    $insideAnchor = false;
                }
                continue;
            }
            if ($insideAnchor) {
                continue;
            }
            $segments[$index] = (string) preg_replace_callback(
                '/#([^#\r\n<>]{1,50})#/u',
                static function (array $match): string {
                    $name = self::normalizeHashtagName($match[1]);
                    if ($name === '') {
                        return $match[0];
                    }

                    return Html::a('#' . Html::encode($name) . '#', ['/site/tag', 'slug' => self::slugForName($name)], ['class' => 'moment-hashtag']);
                },
                $segment
            );
        }

        return implode('', $segments);
    }

    private static function normalizeHashtagName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => '标签名称',
            'slug' => '标签标识',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }

    public function getPosts(): ActiveQuery
    {
        return $this->hasMany(Post::class, ['id' => 'post_id'])
            ->viaTable('{{%post_tag}}', ['tag_id' => 'id']);
    }
}
