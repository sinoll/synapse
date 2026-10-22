<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\User;
use common\models\SiteSetting;
use frontend\models\CreateUserForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class ManageController extends Controller
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
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->isAdministrator()) {
            throw new ForbiddenHttpException('只有默认管理员可以管理用户。');
        }
        if ($identity->must_change_password) {
            Yii::$app->response->redirect(['/users/change-password']);
            return false;
        }
        /***
        if (Yii::$app->user->identity->phone_verified_at === null) {
            Yii::$app->response->redirect(['/users/verify-phone']);
            return false;
        }
        ***/
        return true;
    }

    public function actionIndex(string $section = 'users'): string|Response
    {
        if (!in_array($section, ['site-name', 'users', 'node'], true)) {
            $section = 'users';
        }

        $siteName = SiteSetting::read('site_name', Yii::$app->name);
        $nodeEnabled = SiteSetting::read('node_enabled', '0') === '1';
        if (Yii::$app->request->isPost) {
            if ($section === 'site-name') {
                $submittedName = trim((string) Yii::$app->request->post('siteName', ''));
                if ($submittedName === '' || mb_strlen($submittedName) > 100) {
                    Yii::$app->session->setFlash('error', '站点名称不能为空，且不能超过 100 个字符。');
                    $siteName = $submittedName;
                } elseif (SiteSetting::write('site_name', $submittedName)) {
                    Yii::$app->name = $submittedName;
                    Yii::$app->session->setFlash('success', '站点名称已保存。');
                    return $this->redirect(['index', 'section' => $section]);
                } else {
                    Yii::$app->session->setFlash('error', '站点名称保存失败，请重试。');
                    $siteName = $submittedName;
                }
            } elseif ($section === 'node') {
                $nodeEnabled = Yii::$app->request->post('enabled') === '1';
                if (SiteSetting::write('node_enabled', $nodeEnabled ? '1' : '0')) {
                    Yii::$app->session->setFlash('success', $nodeEnabled ? '节点已开启。' : '节点已关闭。');
                    return $this->redirect(['index', 'section' => $section]);
                }
                Yii::$app->session->setFlash('error', '节点设置保存失败，请重试。');
            }
        }

        $users = $section === 'users' ? User::find()->orderBy(['id' => SORT_ASC])->all() : [];
        return $this->render('index', compact('users', 'section', 'siteName', 'nodeEnabled'));
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
