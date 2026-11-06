<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Cate;
use common\models\User;
use common\models\SiteSetting;
use frontend\models\CreateUserForm;
use frontend\models\InviteForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

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
        if (!in_array($section, ['site-name', 'users', 'categories', 'node', 'signup', 'invite'], true)) {
            $section = 'users';
        }

        $siteName = SiteSetting::read('site_name', Yii::$app->name);
        $nodeEnabled = SiteSetting::read('node_enabled', '0') === '1';
        $signupEnabled = SiteSetting::signupEnabled();
        $inviteModel = new InviteForm();
        if (Yii::$app->request->isPost) {
            if ($section === 'invite') {
                if ($inviteModel->load(Yii::$app->request->post()) && $inviteModel->send(Yii::$app->mailSender)) {
                    Yii::$app->session->setFlash('success', '邀请邮件已发往 ' . $inviteModel->email . '，对方打开链接即可注册。');

                    return $this->redirect(['index', 'section' => $section]);
                }
                if (!$inviteModel->hasErrors()) {
                    Yii::$app->session->setFlash('error', '邀请邮件发送失败，请检查发信配置；真实原因记在日志的 mail 分类里。');
                }
            } elseif ($section === 'site-name') {
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
            } elseif ($section === 'signup') {
                $signupEnabled = Yii::$app->request->post('enabled') === '1';
                if (SiteSetting::write('signup_enabled', $signupEnabled ? '1' : '0')) {
                    Yii::$app->session->setFlash('success', $signupEnabled ? '自助注册已开启。' : '自助注册已关闭。');
                    return $this->redirect(['index', 'section' => $section]);
                }
                Yii::$app->session->setFlash('error', '注册设置保存失败，请重试。');
            }
        }

        $users = $section === 'users' ? User::find()->orderBy(['id' => SORT_ASC])->all() : [];
        $categories = $section === 'categories' ? $this->categoryList() : [];
        return $this->render('index', [
            'users' => $users,
            'categories' => $categories,
            'section' => $section,
            'siteName' => $siteName,
            'nodeEnabled' => $nodeEnabled,
            'signupEnabled' => $signupEnabled,
            'inviteModel' => $inviteModel,
        ]);
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
            $storedThumb = $this->applyCategoryThumbUpload($model);
            if (!$model->hasErrors()) {
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', '分类已创建。');
                    return $this->redirect(['index', 'section' => 'categories']);
                }
                $this->deleteCategoryThumbFile($storedThumb);
                $model->thumb = '';
            }
        }

        return $this->render('category', ['model' => $model, 'isNew' => true]);
    }

    public function actionCategoryUpdate(int $id): string|Response
    {
        $model = $this->findCategory($id);
        $previousThumb = trim((string) $model->thumb);
        if ($model->load(Yii::$app->request->post())) {
            $this->prepareCategorySlug($model);
            $storedThumb = $this->applyCategoryThumbUpload($model);
            if (!$model->hasErrors()) {
                if ($model->save()) {
                    if ($storedThumb !== null) {
                        $this->deleteCategoryThumbFile($previousThumb);
                    }
                    Yii::$app->session->setFlash('success', '分类已更新。');
                    return $this->redirect(['index', 'section' => 'categories']);
                }
                $this->deleteCategoryThumbFile($storedThumb);
                $model->thumb = $previousThumb;
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
            $this->deleteCategoryThumbFile((string) $model->thumb);
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

    /**
     * Move an uploaded category thumbnail into the thumb directory.
     *
     * @return string|null the stored web path, or null when no file was submitted or storing failed
     */
    private function applyCategoryThumbUpload(Cate $model): ?string
    {
        $file = UploadedFile::getInstance($model, 'thumb');
        if ($file === null) {
            return null;
        }
        if ($file->error !== UPLOAD_ERR_OK
            || !in_array(strtolower((string) $file->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            || $file->size > 5 * 1024 * 1024
            || @getimagesize($file->tempName) === false) {
            $model->addError('thumb', '请选择有效的缩略图图片（JPG、PNG、GIF、WebP），且不超过 5 MB。');
            return null;
        }

        $directory = Yii::getAlias('@frontend/web/uploads/thumb/cate');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $model->addError('thumb', '缩略图目录创建失败，请检查 uploads/thumb/cate 的写入权限。');
            return null;
        }
        $name = Yii::$app->security->generateRandomString(32) . '.' . strtolower((string) $file->extension);
        if (!$file->saveAs($directory . DIRECTORY_SEPARATOR . $name)) {
            $model->addError('thumb', '缩略图保存失败，请重新选择文件。');
            return null;
        }

        $model->thumb = '/uploads/thumb/cate/' . $name;

        return $model->thumb;
    }

    /** Only unlinks files stored by this form; legacy hand-written thumb paths are left alone. */
    private function deleteCategoryThumbFile(?string $path): void
    {
        if ($path === null || $path === ''
            || !preg_match('#^/uploads/thumb/cate/[A-Za-z0-9_-]+\.(?:jpe?g|png|gif|webp)$#i', $path)) {
            return;
        }

        $file = Yii::getAlias('@frontend/web') . $path;
        if (is_file($file)) {
            @unlink($file);
        }
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
