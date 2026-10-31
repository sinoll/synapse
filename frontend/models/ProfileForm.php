<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class ProfileForm extends Model
{
    public string $nickname = '';
    public ?UploadedFile $avatarFile = null;

    /** Keep the posted file input's empty scalar out of the typed upload property. */
    public function load($data, $formName = null): bool
    {
        $scope = $formName === null ? $this->formName() : $formName;
        if ($scope === '') {
            $attributes = $data;
        } else {
            $attributes = $data[$scope] ?? null;
        }
        if (!is_array($attributes)) {
            return false;
        }

        unset($attributes['avatarFile']);
        $this->avatarFile = UploadedFile::getInstance($this, 'avatarFile');
        return parent::load([$scope => $attributes], $scope);
    }

    public function rules(): array
    {
        return [
            ['nickname', 'trim'],
            ['nickname', 'required'],
            ['nickname', 'string', 'max' => 80],
            ['avatarFile', 'file', 'extensions' => ['png', 'jpg', 'jpeg', 'gif', 'webp'], 'mimeTypes' => ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], 'maxSize' => 5 * 1024 * 1024],
            ['avatarFile', 'validateImageContent'],
        ];
    }

    public function validateImageContent(string $attribute): void
    {
        if ($this->avatarFile !== null && @getimagesize($this->avatarFile->tempName) === false) {
            $this->addError($attribute, '请选择有效的图片文件。');
        }
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        $oldNickname = $user->nickname;
        $oldAvatar = $user->avatar;
        $directory = Yii::getAlias('@frontend/web/uploads/avatars');
        $user->nickname = $this->nickname;
        $attributes = ['nickname', 'updated_at'];
        if ($this->avatarFile !== null) {
            FileHelper::createDirectory($directory, 0755, true);
            $filename = Yii::$app->security->generateRandomString(32) . '.' . strtolower($this->avatarFile->extension);
            if (!$this->avatarFile->saveAs($directory . DIRECTORY_SEPARATOR . $filename)) {
                $this->addError('avatarFile', '头像保存失败，请重试。');
                return false;
            }
            $user->avatar = '/uploads/avatars/' . $filename;
            $attributes[] = 'avatar';
        }

        if (!$user->save(false, $attributes)) {
            $user->nickname = $oldNickname;
            $user->avatar = $oldAvatar;
            if ($this->avatarFile !== null && is_file($directory . DIRECTORY_SEPARATOR . $filename)) {
                @unlink($directory . DIRECTORY_SEPARATOR . $filename);
            }
            $this->addError('nickname', '个人资料保存失败，请重试。');
            return false;
        }

        if ($this->avatarFile !== null && $oldAvatar !== null
            && preg_match('#^/uploads/avatars/([A-Za-z0-9_-]+\.(?:png|jpe?g|gif|webp))$#i', $oldAvatar, $matches)) {
            $oldFile = $directory . DIRECTORY_SEPARATOR . $matches[1];
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        return true;
    }
}
