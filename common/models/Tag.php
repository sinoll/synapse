<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/** Reusable normalized content tag. */
class Tag extends \yii\db\ActiveRecord
{
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
