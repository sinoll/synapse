<?php

namespace app\restful\models;

use Yii;
use yii\base\Model;

/**
 * 用户模型 - 用于登录和登出验证
 */
class User extends Model
{
    /** @var string 用户名 */
    public $username;
    
    /** @var string 密码 */
    public $password;
    
    /**
     * 登录验证
     * @return bool
     */
    public function login()
    {
        // TODO: 这里需要连接数据库查询用户
        // 暂时使用简单的硬编码验证，实际项目中应该从数据库获取
        $users = [
            'admin' => '123456',
            'user' => 'password',
        ];
        
        if (isset($users[$this->username])) {
            return $this->password === $users[$this->username];
        }
        
        return false;
    }
    
    /**
     * 登出（清除此处可以添加 Session 清除等逻辑）
     * @return bool
     */
    public function logout()
    {
        // TODO: 清除 session，销毁 token 等
        Yii::$app->session->close();
        
        return true;
    }
}
