<?php

declare(strict_types=1);

namespace backend\controllers;

use common\models\LoginForm;
use common\models\ChangePasswordForm;
use common\models\VerifyPhoneForm;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

/**
 * Site controller
 */
class SiteController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::$app->user->identity;
        $actionId = strtolower(str_replace('-', '', $action->id));
        if ($user !== null && !$user->isAdministrator() && !in_array($actionId, ['login', 'error', 'logout'], true)) {
            $this->redirect(['/site/index']);
            return false;
        }
        if ($user !== null && $user->must_change_password && !in_array($actionId, ['changepassword', 'logout'], true)) {
            $this->redirect(['change-password']);
            return false;
        }
        if ($user !== null && $user->phone_verified_at === null && !in_array($actionId, ['changepassword', 'verifyphone', 'logout'], true)) {
            $this->redirect(['verify-phone']);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['login', 'error'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['logout', 'index', 'change-password', 'verify-phone'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return string|Response
     */
    public function actionLogin(): string|Response
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'blank';

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            if (!Yii::$app->user->identity->isAdministrator()) {
                Yii::$app->user->logout();
                Yii::$app->session->setFlash('error', '请使用前台账户入口登录。');
                return $this->redirect(['/site/login']);
            }
            if (Yii::$app->user->identity->must_change_password) {
                return $this->redirect(['change-password']);
            }
            return $this->goBack();
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionChangePassword(): string|Response
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['login']);
        }

        $model = new ChangePasswordForm();
        if ($model->load(Yii::$app->request->post()) && $model->changePassword()) {
            Yii::$app->session->setFlash('success', '密码已更新。');
            return $this->goHome();
        }

        return $this->render('changePassword', compact('model'));
    }

    public function actionVerifyPhone(): string|Response
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['login']);
        }
        if (Yii::$app->user->identity->must_change_password) {
            return $this->redirect(['change-password']);
        }

        $model = new VerifyPhoneForm(['phone' => Yii::$app->user->identity->phone ?? '']);
        $post = Yii::$app->request->post();
        if ($model->load($post)) {
            if (isset($post['sendCode']) && $model->sendCode(Yii::$app->smsSender)) {
                Yii::$app->session->setFlash('success', '验证码已发送。');
            } elseif (isset($post['verifyCode']) && $model->verify()) {
                Yii::$app->session->setFlash('success', '手机号验证完成。');
                return $this->goHome();
            }
        }

        return $this->render('verifyPhone', compact('model'));
    }
}
