<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/** Reusable normalized content category. */
class Cate extends \yii\db\ActiveRecord
{
    /** @var int|null populated by feed queries that count associated posts */
    public $postCount;

    public static function tableName(): string
    {
        return '{{%cate}}';
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
            [['slug'], 'match', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'message' => '标识只能包含小写字母、数字和连字符（-），且不能以连字符开头或结尾。'],
            [['name'], 'unique'],
            [['slug'], 'unique'],
            [['description'], 'string'],
            [['thumb'], 'string', 'max' => 255],
        ];
    }

    public static function slugForName(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $ascii === false ? '' : $ascii) ?? '', '-'));
        $base = substr($base, 0, 45);

        return ($base !== '' ? $base : 'cate') . '-' . substr(hash('sha256', mb_strtolower($name)), 0, 12);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => '分类名称',
            'slug' => '分类标识',
            'description' => '分类描述',
            'thumb' => '分类缩略图',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }

    public function getPosts(): ActiveQuery
    {
        return $this->hasMany(Post::class, ['id' => 'post_id'])
            ->viaTable('{{%post_cate}}', ['cate_id' => 'id']);
    }
}
