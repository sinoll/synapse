<?php

declare(strict_types=1);

/***
 *  注意： 保存到 项目根目录下
 * 
 * windows:
 *  	vendor\bin\psysh.bat psysh-bootstrap.php
 * Ubuntu :
 * 		vendor/bin/psysh  psysh-bootstrap.php
 * 
 * 进去后就能直接查:
 *		>>> Yii::$app->params
 *		>>> Yii::$app->db->dsn
 *		>>> Yii::$app->components
 * 
 * 结束：  exit
 * **/

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/common/config/bootstrap.php';
require __DIR__ . '/console/config/bootstrap.php';

$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/common/config/main.php',
    require __DIR__ . '/common/config/main-local.php',
    require __DIR__ . '/console/config/main.php',
    require __DIR__ . '/console/config/main-local.php'
);

$config['id'] = 'console';
$config['basePath'] = __DIR__ . '/console';

new yii\console\Application($config);