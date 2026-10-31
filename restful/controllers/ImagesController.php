<?php

namespace app\restful\controllers;

use yii\web\Controller;
use yii\web\Response;

/**
 * Images (图片) CRUD 控制器
 */
class ImagesController extends Controller
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
     * 获取所有图片列表
     * GET /api/restful/images
     */
    public function actionIndex()
    {
        return json(['data' => \app\restful\models\Images::findAll()]);
    }

    /**
     * 获取特定用户的图片
     * GET /api/restful/images/user/{userId}
     */
    public function actionUser($userId)
    {
        return json(['data' => \app\restful\models\Images::findAll((int)$userId)]);
    }

    /**
     * 获取单个图片
     * GET /api/restful/images/{id}
     */
    public function actionView($id)
    {
        $image = \app\restful\models\Images::findOne($id);
        
        return json([
            'code' => 200,
            'data' => $image,
        ]);
    }

    /**
     * 上传图片
     * POST /api/restful/images
     */
    public function actionCreate()
    {
        $image = \app\restful\models\Images::create(Yii::$app->request->post());
        
        return json($image);
    }

    /**
     * 更新图片
     * PUT /api/restful/images/{id}
     */
    public function actionUpdate($id)
    {
        $image = \app\restful\models\Images::update($id, Yii::$app->request->post());
        
        return json($image);
    }

    /**
     * 点赞图片
     * POST /api/restful/images/{id}/like
     */
    public function actionLike($id)
    {
        $result = \app\restful\models\Images::like($id, isset($_SERVER['HTTP_AUTHORIZATION']) ? 1 : 0);
        
        return json($result);
    }

    /**
     * 获取图片预览
     * GET /api/restful/images/{id}/preview
     */
    public function actionPreview($id)
    {
        $result = \app\restful\models\Images::preview($id);
        
        return json($result);
    }

    /**
     * 删除图片
     * DELETE /api/restful/images/{id}
     */
    public function actionDelete($id)
    {
        $result = \app\restful\models\Images::delete($id);
        
        return json($result);
    }
}
