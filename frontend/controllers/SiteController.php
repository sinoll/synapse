<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\LoginForm;
use common\models\ChangePasswordForm;
use common\models\VerifyPhoneForm;
use Yii;
use yii\captcha\CaptchaAction;
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
        if ($user !== null && $user->must_change_password && !in_array($actionId, ['changepassword', 'logout'], true)) {
            $this->redirect(['change-password']);
            return false;
        }
        /***
        if ($user !== null && $user->phone_verified_at === null && !in_array($actionId, ['changepassword', 'verifyphone', 'logout'], true)) {
            $this->redirect(['verify-phone']);
            return false;
        }
        ***/
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
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
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
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
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
     * Logs in a user.
     *
     * @return string|Response
     */
    public function actionLogin(): string|Response
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
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
     * Logs out the current user.
     *
     * @return Response
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout(): string
    {
        return $this->render('about');
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
