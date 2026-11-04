<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Persistent site-wide settings.
 *
 * @property string $key
 * @property string $value
 * @property int $updated_at
 */
final class SiteSetting extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%site_setting}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                // 表里只有 updated_at，用默认的 created_at+updated_at 会让新 key 的首次写入
                // 在 beforeInsert 阶段抛 UnknownPropertyException，只有已存在的行才碰巧能存下来。
                'attributes' => [
                    self::EVENT_BEFORE_INSERT => ['updated_at'],
                    self::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['key', 'value'], 'required'],
            [['key'], 'string', 'max' => 64],
            [['value'], 'string', 'max' => 500],
        ];
    }

    public static function read(string $key, string $default = ''): string
    {
        $setting = self::findOne(['key' => $key]);
        return $setting === null ? $default : (string) $setting->value;
    }

    public static function write(string $key, string $value): bool
    {
        $setting = self::findOne(['key' => $key]) ?? new self(['key' => $key]);
        $setting->value = $value;
        return $setting->save();
    }

    /**
     * 自助注册开关。缺行时按开启处理：新环境还没插这行数据也不该把注册入口整个藏掉。
     */
    public static function signupEnabled(): bool
    {
        return self::read('signup_enabled', '1') === '1';
    }
}
