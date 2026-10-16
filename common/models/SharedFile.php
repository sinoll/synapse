<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

class SharedFile extends \common\models\base\SharedFile
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_TRASH = 9;
    public const SHARE_INHERIT = 0;
    public const SHARE_EXPLICIT = 1;
    public static function tableName(): string { return '{{%shared_file}}'; }
    public function behaviors(): array { return [TimestampBehavior::class]; }
    public function init(): void
    {
        parent::init();
        $this->status = self::STATUS_ACTIVE;
        $this->share_mode = self::SHARE_INHERIT;
        $this->size_bytes = 0;
    }
    public function rules(): array { return [[['post_id', 'title', 'file_path', 'original_name'], 'required'], [['post_id', 'folder_id', 'size_bytes', 'status', 'deleted_at', 'share_mode', 'share_starts_at', 'share_ends_at'], 'integer'], [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_TRASH]], [['share_mode'], 'in', 'range' => [self::SHARE_INHERIT, self::SHARE_EXPLICIT]], [['folder_id'], 'validateFolderOwner'], [['share_ends_at'], 'validateShareWindow'], [['description'], 'string'], [['title'], 'string', 'max' => 200], [['file_path'], 'string', 'max' => 500], [['original_name'], 'string', 'max' => 255], [['mime_type'], 'string', 'max' => 127]]; }
    public function validateFolderOwner(string $attribute): void
    {
        if ($this->folder_id === null) {
            return;
        }
        $folder = SharedFileFolder::findOne($this->folder_id);
        $post = Post::findOne($this->post_id);
        if ($folder === null || $post === null || (int) $folder->user_id !== (int) $post->user_id) {
            $this->addError($attribute, '文件夹必须属于文件所有者。');
        }
    }
    public function validateShareWindow(string $attribute): void
    {
        if ($this->share_starts_at !== null && $this->share_ends_at !== null && $this->share_ends_at < $this->share_starts_at) {
            $this->addError($attribute, '共享结束时间不能早于开始时间。');
        }
    }
    public function getUser(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id'])->via('post'); }
    public function getPost(): ActiveQuery { return $this->hasOne(Post::class, ['id' => 'post_id']); }
    public function getFolder(): ActiveQuery { return $this->hasOne(SharedFileFolder::class, ['id' => 'folder_id']); }
    public function getRecipients(): ActiveQuery { return $this->hasMany(SharedFileRecipient::class, ['shared_file_id' => 'id']); }
    public function getUsers(): ActiveQuery { return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%shared_file_recipient}}', ['shared_file_id' => 'id']); }
}
