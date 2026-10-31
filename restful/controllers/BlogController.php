<?php

namespace app\restful\controllers;

use yii\web\Controller;
use yii\web\Response;

/**
 * Blog 文章 CRUD 控制器
 */
class BlogController extends Controller
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
                ],
            ],
        ];
    }

    /**
     * 获取所有文章列表
     * GET /api/restful/blog
     */
    public function actionIndex()
    {
        return json(['data' => \app\restful\models\Blog::findAll()]);
    }

    /**
     * 获取单篇文章
     * GET /api/restful/blog/{id}
     */
    public function actionView($id)
    {
        $article = \app\restful\models\Blog::findOne($id);
        
        return json([
            'code' => 200,
            'data' => $article,
        ]);
    }

    /**
     * 创建文章
     * POST /api/restful/blog
     */
    public function actionCreate()
    {
        $post = \app\restful\models\Blog::create(Yii::$app->request->post());
        
        return json($post);
    }

    /**
     * 更新文章
     * PUT /api/restful/blog/{id}
     */
    public function actionUpdate($id)
    {
        $post = \app\restful\models\Blog::update($id, Yii::$app->request->post());
        
        return json($post);
    }

    /**
     * 删除文章
     * DELETE /api/restful/blog/{id}
     */
    public function actionDelete($id)
    {
        $result = \app\restful\models\Blog::delete($id);
        
        return json($result);
    }
}
