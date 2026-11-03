<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\LoginForm;
use common\models\ChangePasswordForm;
use common\models\VerifyPhoneForm;
use frontend\models\ProfileForm;
use frontend\components\ListPagination;
use common\models\Post;
use common\models\User;
use Yii;
use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * 用户中心 controller
 */
class UsersController extends Controller
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
                'only' => ['index', 'logout', 'favorite', 'like'],
                'rules' => [
                    [
                        'actions' => ['index', 'logout', 'favorite', 'like'],
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
    public function actionIndex(?string $type = null): string
    {
        $definitions = $this->definitions();
        if ($type !== null && !isset($definitions[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo'])
            ->where(['p.user_id' => (int) Yii::$app->user->id])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);
        if ($type !== null) {
            $query->andWhere(['p.type' => $definitions[$type]['postType']]);
        }

        $dataProvider = ListPagination::provider($query, ListPagination::SIZE_TABLE);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'definitions' => $definitions,
            'selectedType' => $type,
        ]);        
    }

    /**
     * 作者主页：展示该作者在当前身份下可见的内容，可按类型筛选。
     *
     * @return string
     */
    public function actionProfile(int $id, ?string $type = null): string
    {
        $author = User::find()->where(['id' => $id, 'status' => User::STATUS_ACTIVE])->one();
        if ($author === null) {
            throw new NotFoundHttpException('找不到该用户。');
        }
        if ($type !== null && !isset(Post::TYPE_LABELS[$type])) {
            throw new BadRequestHttpException('未知的内容类型。');
        }

        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'moment', 'images', 'shortVideo'])
            ->where(['p.user_id' => (int) $author->id, 'p.status' => Post::STATUS_PUBLISHED])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);
        if ($type !== null) {
            $query->andWhere(['p.type' => $type]);
        }
        Post::applyVisibilityScope($query, $userId);

        return $this->render('profile', [
            'author' => $author,
            'dataProvider' => ListPagination::provider($query, ListPagination::SIZE_GRID),
            'counts' => Post::authorCounts((int) $author->id, $userId),
            'activeType' => $type,
        ]);
    }

    /**
     * 我的收藏：按收藏时间倒序展示，可按内容类型筛选。
     *
     * @return string
     */
    public function actionFavorite(?string $type = null): string
    {
        $definitions = $this->definitions();
        if ($type !== null && !isset($definitions[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        $dataProvider = ListPagination::provider(
            $this->interactionQuery('{{%post_favorite}}', 'pf', 'favoriteAt', $type),
            ListPagination::SIZE_GRID,
        );

        return $this->render('favorite', [
            'dataProvider' => $dataProvider,
            'definitions' => $definitions,
            'selectedType' => $type,
        ]);
    }

    /**
     * 我的点赞：按点赞时间倒序展示，可按内容类型筛选。
     *
     * @return string
     */
    public function actionLike(?string $type = null): string
    {
        $definitions = $this->definitions();
        if ($type !== null && !isset($definitions[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        $dataProvider = ListPagination::provider(
            $this->interactionQuery('{{%post_like}}', 'pl', 'likedAt', $type),
            ListPagination::SIZE_GRID,
        );

        return $this->render('like', [
            'dataProvider' => $dataProvider,
            'definitions' => $definitions,
            'selectedType' => $type,
        ]);
    }

    /**
     * 收藏/点赞列表的共用查询：关联表决定别名与时间列，其余筛选条件一致。
     * 收藏或点赞后作者收紧了内容可见范围，列表要跟着消失，判定和 /favorite/toggle 用的是同一套 SQL 条件。
     */
    private function interactionQuery(string $table, string $alias, string $timeKey, ?string $type): ActiveQuery
    {
        $userId = (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->innerJoin([$alias => $table], $alias . '.post_id = p.id')
            // select() 必须显式带上 p.*：addSelect() 在 select 仍为空时会把它当成整个选择列表。
            ->select(['p.*', $timeKey => $alias . '.created_at'])
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo'])
            ->where([$alias . '.user_id' => $userId])
            ->andWhere(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere([
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                ['and', ['p.visibility' => Post::VISIBILITY_PRIVATE], ['p.user_id' => $userId]],
            ])
            ->orderBy([$alias . '.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);
        if ($type !== null) {
            $query->andWhere(['p.type' => $this->definitions()[$type]['postType']]);
        }

        return $query;
    }

    private function definitions(): array
    {
        return Post::typeDefinitions();
    }

    private function findPost(int $id): Post
    {
        $post = Post::findOne($id);
        if ($post === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $post;
    }

    private function contentModel(Post $post): ?ActiveRecord
    {
        return match ($post->type) {
            Post::TYPE_BLOG => $post->blog,
            Post::TYPE_IMAGES => $post->images[0] ?? null,
            Post::TYPE_MOMENT => $post->moment,
            Post::TYPE_SHORT_VIDEO => $post->shortVideo,
            default => null,
        };
    }

    private function canView(Post $post): bool
    {
        if (!Yii::$app->user->isGuest && (int) $post->user_id === (int) Yii::$app->user->id) {
            return true;
        }
        if ($post->status !== Post::STATUS_PUBLISHED) {
            return false;
        }

        return match ($post->visibility) {
            Post::VISIBILITY_PUBLIC => true,
            Post::VISIBILITY_REGISTERED_USERS => !Yii::$app->user->isGuest,
            default => false,
        };
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


    /***
     * 
     * 两个刚注册  需要修改密码 和 电话号码 实名认证
     * 
    ***/

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

    public function actionSettings(): string|Response
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['login']);
        }

        $profile = new ProfileForm(['nickname' => Yii::$app->user->identity->nickname ?? '']);
        if ($profile->load(Yii::$app->request->post()) && $profile->save()) {
            Yii::$app->session->setFlash('success', '个人资料已更新。');
            return $this->refresh();
        }

        return $this->render('settings', compact('profile'));
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
