<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Cate;
use common\models\User;
use common\models\SiteSetting;
use frontend\models\CreateUserForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
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
        if (!in_array($section, ['site-name', 'users', 'categories', 'node'], true)) {
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
        $categories = $section === 'categories' ? $this->categoryList() : [];
        return $this->render('index', compact('users', 'categories', 'section', 'siteName', 'nodeEnabled'));
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

    public function actionCategoryCreate(): string|Response
    {
        $model = new Cate();
        if ($model->load(Yii::$app->request->post())) {
            $this->prepareCategorySlug($model);
            if ($model->save()) {
                Yii::$app->session->setFlash('success', '分类已创建。');
                return $this->redirect(['index', 'section' => 'categories']);
            }
        }

        return $this->render('category', ['model' => $model, 'isNew' => true]);
    }

    public function actionCategoryUpdate(int $id): string|Response
    {
        $model = $this->findCategory($id);
        if ($model->load(Yii::$app->request->post())) {
            $this->prepareCategorySlug($model);
            if ($model->save()) {
                Yii::$app->session->setFlash('success', '分类已更新。');
                return $this->redirect(['index', 'section' => 'categories']);
            }
        }

        return $this->render('category', ['model' => $model, 'isNew' => false]);
    }

    public function actionCategoryDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new MethodNotAllowedHttpException('请通过分类列表的删除按钮提交请求。');
        }

        $model = $this->findCategory($id);
        if ($model->getPosts()->count() > 0) {
            Yii::$app->session->setFlash('error', '该分类下仍有内容，请先调整相关内容再删除。');
        } elseif ($model->delete() !== false) {
            Yii::$app->session->setFlash('success', '分类已删除。');
        } else {
            Yii::$app->session->setFlash('error', '分类删除失败，请重试。');
        }

        return $this->redirect(['index', 'section' => 'categories']);
    }

    private function findCategory(int $id): Cate
    {
        $model = Cate::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('找不到该分类。');
        }

        return $model;
    }

    private function prepareCategorySlug(Cate $model): void
    {
        $slug = trim((string) $model->slug);
        $model->slug = $slug !== '' ? $slug : Cate::slugForName((string) $model->name);
    }

    /** @return Cate[] */
    private function categoryList(): array
    {
        return Cate::find()
            ->alias('c')
            ->select(['c.*', 'COUNT([[pc]].[[post_id]]) AS postCount'])
            ->leftJoin(['pc' => '{{%post_cate}}'], 'pc.cate_id = c.id')
            ->groupBy('c.id')
            ->orderBy(['c.name' => SORT_ASC])
            ->all();
    }
}
