<?php

namespace app\restful\controllers;

use yii\web\Controller;
use yii\web\Response;

/**
 * ShortVideo (短视频) CRUD 控制器
 */
class ShortVideoController extends Controller
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
     * 获取所有短视频列表
     * GET /api/restful/short_video
     */
    public function actionIndex()
    {
        return json(['data' => \app\restful\models\ShortVideo::findAll()]);
    }

    /**
     * 获取特定用户的视频
     * GET /api/restful/short_video/user/{userId}
     */
    public function actionUser($userId)
    {
        return json(['data' => \app\restful\models\ShortVideo::findAll((int)$userId)]);
    }

    /**
     * 获取单个视频
     * GET /api/restful/short_video/{id}
     */
    public function actionView($id)
    {
        $video = \app\restful\models\ShortVideo::findOne($id);
        
        return json([
            'code' => 200,
            'data' => $video,
        ]);
    }

    /**
     * 创建视频
     * POST /api/restful/short_video
     */
    public function actionCreate()
    {
        $video = \app\restful\models\ShortVideo::create(Yii::$app->request->post());
        
        return json($video);
    }

    /**
     * 更新视频
     * PUT /api/restful/short_video/{id}
     */
    public function actionUpdate($id)
    {
        $video = \app\restful\models\ShortVideo::update($id, Yii::$app->request->post());
        
        return json($video);
    }

    /**
     * 点赞视频
     * POST /api/restful/short_video/{id}/like
     */
    public function actionLike($id)
    {
        $result = \app\restful\models\ShortVideo::like($id, isset($_SERVER['HTTP_AUTHORIZATION']) ? 1 : 0);
        
        return json($result);
    }

    /**
     * 删除视频
     * DELETE /api/restful/short_video/{id}
     */
    public function actionDelete($id)
    {
        $result = \app\restful\models\ShortVideo::delete($id);
        
        return json($result);
    }
}
