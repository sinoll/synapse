<?php

namespace app\restful\models;

use Yii;
use yii\base\Model;
use yii\web\BadRequestHttpException;

/**
 * 数据库连接配置
 */
class DatabaseConfig extends Model
{
    /** @var string DSN */
    public $dsn = 'mysql:host=localhost;dbname=synapse;charset=utf8mb4';
    
    /** @var string 用户名 */
    public $username = 'root';
    
    /** @var string 密码 */
    public $password = '';
    
    /**
     * 获取数据库连接
     * @return \PDO
     */
    public static function getConnection()
    {
        return Yii::createInstance([
            'class' => 'yii\db\Connection',
            'dsn' => self::$dsn,
            'username' => self::$username,
            'password' => self::$password,
            'charset' => 'utf8mb4',
        ]);
    }
}
