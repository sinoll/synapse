<?php

namespace app\restful\controllers;

use yii\web\Controller;
use yii\web\Response;

/**
 * Moment (动态) CRUD 控制器
 */
class MomentController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'like' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * 获取所有动态列表
     * GET /api/restful/moment
     */
    public function actionIndex()
    {
        return json(['data' => \app\restful\models\Moment::findAll()]);
    }

    /**
     * 获取特定用户的动态
     * GET /api/restful/moment/user/{userId}
     */
    public function actionUser($userId)
    {
        return json(['data' => \app\restful\models\Moment::findAll((int)$userId)]);
    }

    /**
     * 获取单个动态
     * GET /api/restful/moment/{id}
     */
    public function actionView($id)
    {
        $moment = \app\restful\models\Moment::findOne($id);
        
        return json([
            'code' => 200,
            'data' => $moment,
        ]);
    }

    /**
     * 创建动态
     * POST /api/restful/moment
     */
    public function actionCreate()
    {
        $moment = \app\restful\models\Moment::create(Yii::$app->request->post());
        
        return json($moment);
    }

    /**
     * 更新动态
     * PUT /api/restful/moment/{id}
     */
    public function actionUpdate($id)
    {
        $moment = \app\restful\models\Moment::update($id, Yii::$app->request->post());
        
        return json($moment);
    }

    /**
     * 点赞动态
     * POST /api/restful/moment/{id}/like
     */
    public function actionLike($id)
    {
        $result = \app\restful\models\Moment::like($id, isset($_SERVER['HTTP_AUTHORIZATION']) ? 1 : 0);
        
        return json($result);
    }

    /**
     * 删除动态
     * DELETE /api/restful/moment/{id}
     */
    public function actionDelete($id)
    {
        $result = \app\restful\models\Moment::delete($id);
        
        return json($result);
    }
}
