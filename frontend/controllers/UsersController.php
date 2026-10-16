<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\User;
use frontend\models\CreateUserForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class UsersController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!Yii::$app->user->identity->isAdministrator()) {
            throw new ForbiddenHttpException('只有默认管理员可以管理用户。');
        }
        if (Yii::$app->user->identity->must_change_password) {
            Yii::$app->response->redirect(['/site/change-password']);
            return false;
        }
        if (Yii::$app->user->identity->phone_verified_at === null) {
            Yii::$app->response->redirect(['/site/verify-phone']);
            return false;
        }

        return true;
    }

    public function actionIndex(): string
    {
        $users = User::find()->orderBy(['id' => SORT_ASC])->all();
        return $this->render('index', compact('users'));
    }

    public function actionCreate(): string|Response
    {
        $model = new CreateUserForm();
        if ($model->load(Yii::$app->request->post()) && $model->create()) {
            Yii::$app->session->setFlash('success', '用户已创建，首次登录需要修改密码并完成手机号验证。');
            return $this->redirect(['index']);
        }

        return $this->render('create', compact('model'));
    }
}
