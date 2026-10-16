<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

class SharedFileFolder extends \common\models\base\SharedFileFolder
{
    public const SHARE_INHERIT = 0;
    public const SHARE_PRIVATE = 1;
    public const SHARE_SELECTED_USERS = 2;
    public const SHARE_REGISTERED_USERS = 3;
    public const SHARE_PUBLIC = 4;

    public const STATUS_ACTIVE = 1;
    public const STATUS_TRASH = 9;

    public static function tableName(): string { return '{{%shared_file_folder}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function init(): void
    {
        parent::init();
        $this->share_mode = self::SHARE_INHERIT;
        $this->status = self::STATUS_ACTIVE;
    }

    public function rules(): array
    {
        return [
            [['user_id', 'name'], 'required'],
            [['user_id', 'parent_id', 'share_mode', 'status', 'deleted_at', 'share_starts_at', 'share_ends_at'], 'integer'],
            [['name'], 'string', 'max' => 200],
            [['name'], 'validateNameUniqueInParent'],
            [['parent_id'], 'validateParentFolder'],
            [['share_mode'], 'in', 'range' => [self::SHARE_INHERIT, self::SHARE_PRIVATE, self::SHARE_SELECTED_USERS, self::SHARE_REGISTERED_USERS, self::SHARE_PUBLIC]],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_TRASH]],
            [['share_ends_at'], 'validateShareWindow'],
        ];
    }

    public function validateShareWindow(string $attribute): void
    {
        if ($this->share_starts_at !== null && $this->share_ends_at !== null && $this->share_ends_at < $this->share_starts_at) {
            $this->addError($attribute, '共享结束时间不能早于开始时间。');
        }
    }

    public function validateParentFolder(string $attribute): void
    {
        if ($this->parent_id === null) {
            return;
        }

        $parent = self::findOne($this->parent_id);
        if ($parent === null || (int) $parent->user_id !== (int) $this->user_id) {
            $this->addError($attribute, '父文件夹必须存在并属于当前用户。');
            return;
        }

        $visited = [];
        for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->parent) {
            if (!$this->getIsNewRecord() && (int) $ancestor->id === (int) $this->id) {
                $this->addError($attribute, '文件夹不能移动到自身或其子孙目录中。');
                return;
            }
            if (isset($visited[$ancestor->id])) {
                $this->addError($attribute, '父文件夹关系存在循环。');
                return;
            }
            $visited[$ancestor->id] = true;
        }
    }

    public function validateNameUniqueInParent(string $attribute): void
    {
        $query = self::find()->where([
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
        ]);
        if (!$this->getIsNewRecord()) {
            $query->andWhere(['<>', 'id', $this->id]);
        }
        if ($query->exists()) {
            $this->addError($attribute, '同一目录下已存在同名文件夹。');
        }
    }

    public function getOwner(): ActiveQuery { return $this->hasOne(User::class, ['id' => 'user_id']); }
    public function getUser(): ActiveQuery { return $this->getOwner(); }
    public function getParent(): ActiveQuery { return $this->hasOne(self::class, ['id' => 'parent_id']); }
    public function getChildren(): ActiveQuery { return $this->hasMany(self::class, ['parent_id' => 'id']); }
    public function getFiles(): ActiveQuery { return $this->hasMany(SharedFile::class, ['folder_id' => 'id']); }
    public function getRecipients(): ActiveQuery { return $this->hasMany(SharedFileFolderRecipient::class, ['folder_id' => 'id']); }
    public function getUsers(): ActiveQuery { return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%shared_file_folder_recipient}}', ['folder_id' => 'id']); }
}
