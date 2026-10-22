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
        return [TimestampBehavior::class];
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
}
