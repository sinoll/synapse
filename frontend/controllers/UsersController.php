<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\LoginForm;
use common\models\ChangePasswordForm;
use common\models\VerifyPhoneForm;
use frontend\models\ProfileForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResendVerificationEmailForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\VerifyEmailForm;
use frontend\components\ListPagination;
use common\models\Post;
use common\models\User;
use common\models\UserFollow;
use Yii;
use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\base\InvalidArgumentException;
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
    /** 注册与发信类接口按来源 IP 的固定窗口配额，配合表单里的单邮箱冷却一起用。 */
    private const RATE_WINDOW = 3600;
    private const SIGNUP_PER_IP = 10;
    private const MAIL_PER_IP = 20;
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::$app->user->identity;
        $actionId = strtolower(str_replace('-', '', $action->id));
        // 找回密码/注册是自助入口，不能被“请先修改密码”的门禁截断，否则锁死。
        $publicAuthActions = ['signup', 'requestpasswordreset', 'resetpassword', 'verifyemail', 'resendverificationemail'];
        if ($user !== null && $user->must_change_password && !in_array($actionId, array_merge(['changepassword', 'logout'], $publicAuthActions), true)) {
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
                'only' => ['index', 'logout', 'favorite', 'like', 'followers', 'following'],
                'rules' => [
                    [
                        'actions' => ['index', 'logout', 'favorite', 'like', 'followers', 'following'],
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
            'followerCount' => (int) UserFollow::find()->where(['following_id' => (int) $author->id])->count(),
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
     * 我的粉丝：按关注时间倒序展示关注了我的人。
     *
     * @return string
     */
    public function actionFollowers(): string
    {
        $userId = (int) Yii::$app->user->id;
        $query = $this->relationUserQuery('following_id', 'follower_id', $userId);

        return $this->render('followers', [
            'dataProvider' => ListPagination::provider($query, ListPagination::SIZE_TABLE),
        ]);
    }

    /**
     * 我的关注：按关注时间倒序展示我关注的人。
     *
     * @return string
     */
    public function actionFollowing(): string
    {
        $userId = (int) Yii::$app->user->id;
        $query = $this->relationUserQuery('follower_id', 'following_id', $userId);

        return $this->render('following', [
            'dataProvider' => ListPagination::provider($query, ListPagination::SIZE_TABLE),
        ]);
    }

    /**
     * 粉丝/关注列表共用查询：$ownColumn 是 user_follow 上代表「我」的列，$otherColumn 代表对方。
     * 只列活跃用户，时间落在虚拟属性 followedAt 上（同 Post::likedAt 的做法）。
     */
    private function relationUserQuery(string $ownColumn, string $otherColumn, int $userId): ActiveQuery
    {
        return User::find()
            ->alias('u')
            ->innerJoin(['uf' => '{{%user_follow}}'], 'uf.' . $otherColumn . ' = u.id')
            // select() 必须显式带上 u.*：addSelect() 在 select 仍为空时会把它当成整个选择列表。
            ->select(['u.*', 'followedAt' => 'uf.created_at'])
            ->where(['uf.' . $ownColumn => $userId])
            ->andWhere(['u.status' => User::STATUS_ACTIVE])
            ->orderBy(['uf.created_at' => SORT_DESC, 'u.id' => SORT_DESC]);
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

    /**
     * 自助注册：注册即激活，邮箱只作为找回密码与账号通知的凭证。
     *
     * @return string|Response
     */
    public function actionSignup(): string|Response
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            // 配额只统计真正的提交：注册页本身允许反复打开，否则用户试错几次就被锁在门外。
            if ($this->tooManyRequests('signup', self::SIGNUP_PER_IP)) {
                Yii::warning('注册提交超过来源 IP 配额', 'auth');
                Yii::$app->session->setFlash('error', '注册过于频繁，请稍后再试。');

                return $this->redirect(['login']);
            }

            $user = $model->signup();
            if ($user !== null) {
                Yii::$app->user->login($user);
                // 确认信发不出去也不回滚注册：登录页有重发入口。
                $model->sendVerificationEmail(Yii::$app->mailSender);
                Yii::$app->session->setFlash('success', '注册完成，确认邮件已发送到 ' . $user->email . '。');

                return $this->redirect(['index']);
            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    /**
     * 忘记密码：受理重置申请。
     *
     * 无论邮箱是否注册过都返回同一句提示，避免被用来枚举账号；投递失败只记日志。
     *
     * @return string|Response
     */
    public function actionRequestPasswordReset(): string|Response
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['change-password']);
        }

        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($this->tooManyRequests('password-reset', self::MAIL_PER_IP)) {
                Yii::warning('找回密码请求超过来源 IP 配额', 'auth');
                Yii::$app->session->setFlash('error', '操作过于频繁，请稍后再试。');

                return $this->refresh();
            }
            if ($model->validate()) {
                if (!$model->sendEmail(Yii::$app->mailSender)) {
                    Yii::error('找回密码邮件投递失败', 'mail');
                }
                Yii::$app->session->setFlash('success', '如果该邮箱注册过账户，重置链接已发送，请在 1 小时内查收邮件。');

                return $this->refresh();
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * 用邮件链接里的一次性令牌设置新密码。
     *
     * @param string $token 明文令牌（secret.过期时间）
     * @return string|Response
     */
    public function actionResetPassword(string $token = ''): string|Response
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidArgumentException $exception) {
            Yii::$app->session->setFlash('error', '重置链接无效或已过期，请重新申请。');

            return $this->redirect(['request-password-reset']);
        }

        if ($model->load(Yii::$app->request->post()) && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', '密码已重置，请用新密码登录。');

            return $this->redirect(['login']);
        }

        return $this->render('resetPassword', [
            'model' => $model,
            'token' => $token,
        ]);
    }

    /**
     * 确认邮箱归属：消费邮件链接里的一次性令牌。
     *
     * @param string $token 明文令牌
     * @return Response
     */
    public function actionVerifyEmail(string $token = ''): Response
    {
        try {
            $model = new VerifyEmailForm($token);
            $user = $model->verifyEmail();
        } catch (InvalidArgumentException) {
            $user = null;
        }

        Yii::$app->session->setFlash(
            $user === null ? 'error' : 'success',
            $user === null
                ? '确认链接无效或已过期，请重新发送确认邮件。'
                : '邮箱已确认。',
        );

        return $this->redirect(Yii::$app->user->isGuest ? ['login'] : ['index']);
    }

    /**
     * 重新发送邮箱确认信。
     *
     * @return string|Response
     */
    public function actionResendVerificationEmail(): string|Response
    {
        $model = new ResendVerificationEmailForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($this->tooManyRequests('email-verify', self::MAIL_PER_IP)) {
                Yii::warning('重发确认信超过来源 IP 配额', 'auth');
                Yii::$app->session->setFlash('error', '操作过于频繁，请稍后再试。');

                return $this->refresh();
            }
            if ($model->validate()) {
                if (!$model->sendEmail(Yii::$app->mailSender)) {
                    Yii::error('邮箱确认信投递失败', 'mail');
                }
                Yii::$app->session->setFlash('success', '如果该邮箱还需要确认，新的确认邮件已发送。');

                return $this->refresh();
            }
        }

        return $this->render('resendVerificationEmail', [
            'model' => $model,
        ]);
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

    /**
     * 固定窗口内按来源 IP 计数。缓存里没有原子自增，超额一次多算一次可接受。
     */
    private function tooManyRequests(string $scope, int $limit): bool
    {
        $bucket = intdiv(time(), self::RATE_WINDOW);
        $key = 'auth-rate/' . $scope . '/' . $bucket . '/'
            . hash('sha256', (string) Yii::$app->request->userIP);
        $count = (int) Yii::$app->cache->get($key);
        Yii::$app->cache->set($key, $count + 1, self::RATE_WINDOW);

        return $count >= $limit;
    }

}
