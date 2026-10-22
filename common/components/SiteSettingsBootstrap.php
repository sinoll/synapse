<?php

declare(strict_types=1);

namespace common\components;

use Yii;
use yii\base\BootstrapInterface;
use yii\db\Exception as DbException;

class SiteSettingsBootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        try {
            $name = $app->db->createCommand(
                'SELECT [[value]] FROM {{%site_setting}} WHERE [[key]] = :key',
                [':key' => 'site_name'],
            )->queryScalar();
            if (is_string($name) && trim($name) !== '') {
                $app->name = $name;
            }
        } catch (DbException $exception) {
            Yii::warning('Unable to load the configured site name: ' . $exception->getMessage(), __METHOD__);
        }
    }
}
