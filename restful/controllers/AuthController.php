<?php

namespace app\restful\controllers;

use app\restful\models\User;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\web\UnauthorizedHttpException;

/**
 * 认证控制器 - 处理用户登录登出操作
 */
class AuthController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * 用户登录接口
     * POST /api/restful/auth/login
     * @return Response
     */
    public function actionLogin()
    {
        $model = new User();
        
        // 获取 POST 请求参数
        $data = Yii::$app->request->post();
        
        if (empty($data['username']) || empty($data['password'])) {
            return json(['code' => 401, 'message' => '用户名和密码不能为空']);
        }
        
        // 设置模型属性
        $model->username = $data['username'];
        $model->password = $data['password'];
        
        // 验证登录
        if ($model->login()) {
            // 生成 Token (实际项目中应使用 JWT)
            $token = 'auth_token_' . md5($data['username'] . time());
            
            return json([
                'code' => 200,
                'message' => '登录成功',
                'data' => [
                    'token' => $token,
                    'expires_in' => 7 * 24 * 3600, // 7 天过期
                ],
            ]);
        } else {
            return json([
                'code' => 401,
                'message' => '用户名或密码错误',
            ]);
        }
    }

    /**
     * 用户登出接口
     * POST /api/restful/auth/logout
     * @return Response
     */
    public function actionLogout()
    {
        // 验证是否已登录（检查 Session 或 Token）
        if (empty($_SESSION['username']) && empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return json([
                'code' => 401,
                'message' => '用户未登录，无法登出',
            ]);
        }

        // 执行登出操作
        if (Yii::$app->session->isActive) {
            Yii::$app->session->close();
        }
        
        // 清除 Token (如果有)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader)) {
            // TODO: 这里可以存储 Token 到数据库用于黑名单验证
        }

        return json([
            'code' => 200,
            'message' => '登出成功',
        ]);
    }
}
