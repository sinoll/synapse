<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/** A standalone image in a user's image collection; unrelated to blog posts. */
class Image extends \common\models\base\Image
{
    /** 发布表单的合并输入：第一行是标题，其余是图片说明；非数据表字段。 */
    public ?string $caption = null;

    public static function tableName(): string { return '{{%images}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function init(): void
    {
        parent::init();
        $this->sort_order = 0;
    }

    /** 把 caption 拆成标题与图片说明，#话题# 由标签逻辑单独处理。 */
    public function applyCaption(): void
    {
        $lines = preg_split('/\R/u', (string) $this->caption) ?: [];
        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), $lines),
            static fn (string $line): bool => $line !== '',
        ));

        $title = Tag::stripHashtags((string) array_shift($lines));
        $description = Tag::stripHashtags(implode(' ', $lines));
        $this->title = $title !== '' ? $title : ($description !== '' ? $description : '未命名图集');
        $this->alt_text = $description;
    }

    /** 编辑时用已保存的标题与说明还原成单个输入框的内容。 */
    public function buildCaption(): string
    {
        return trim(implode("\n", array_filter(
            [trim((string) $this->title), trim((string) $this->alt_text)],
            static fn (string $line): bool => $line !== '',
        )));
    }

    public function rules(): array
    {
        return [
            [['post_id', 'title', 'file_path'], 'required'],
            [['post_id', 'sort_order'], 'integer'],
            [['title'], 'string', 'max' => 200],
            [['file_path'], 'string', 'max' => 1024],
            [['alt_text'], 'string', 'max' => 255],
        ];
    }

    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }

    /** Gallery thumbnail when one was generated on upload, otherwise the original file. */
    public function getThumbnailPath(): string
    {
        return self::thumbnailForPath((string) $this->file_path) ?? (string) $this->file_path;
    }

    public static function thumbnailForPath(string $imagePath): ?string
    {
        if (!preg_match('#^/uploads/images/(?:\d{6}/)?([A-Za-z0-9_-]+)\.[A-Za-z0-9]+$#', $imagePath, $match)) {
            return null;
        }

        $thumbnail = '/uploads/thumb/images/' . $match[1] . '.jpg';

        return is_file(Yii::getAlias('@frontend/web') . $thumbnail) ? $thumbnail : null;
    }
}
