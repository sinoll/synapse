<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use common\models\PostFavorite;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/** 收藏：对任意 post 内容切换收藏并输出「我的收藏」列表。 */
class FavoriteController extends Controller
{
    use ContentVisibilityTrait;

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index'],
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['toggle' => ['POST']],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // 只拦页面动作，toggle 是 AJAX，被重定向成登录页 HTML 会让按钮静默失败。
        if ($action->id === 'index') {
            $user = Yii::$app->user->identity;
            if ($user !== null && $user->must_change_password) {
                Yii::$app->response->redirect(['/users/change-password']);
                return false;
            }
        }

        return true;
    }

    /** 我的收藏：按收藏时间倒序展示，可按内容类型筛选。 */
    public function actionIndex(?string $type = null): string
    {
        $definitions = Post::typeDefinitions();
        if ($type !== null && !isset($definitions[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        $userId = (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->innerJoin(['pf' => '{{%post_favorite}}'], 'pf.post_id = p.id')
            // select() 必须显式带上 p.*：addSelect() 在 select 仍为空时会把它当成整个选择列表。
            ->select(['p.*', 'favoriteAt' => 'pf.created_at'])
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo'])
            ->where(['pf.user_id' => $userId])
            ->andWhere(['p.status' => Post::STATUS_PUBLISHED])
            // 收藏后作者把内容收紧了可见范围，列表要跟着消失，和 toggle 用的是同一套判定。
            ->andWhere([
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                ['and', ['p.visibility' => Post::VISIBILITY_PRIVATE], ['p.user_id' => $userId]],
            ])
            ->orderBy(['pf.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);
        if ($type !== null) {
            $query->andWhere(['p.type' => $definitions[$type]['postType']]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 12],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'definitions' => $definitions,
            'selectedType' => $type,
        ]);
    }

    public function actionToggle(): Response
    {
        if (Yii::$app->user->isGuest) {
            return $this->asJson(['favorited' => false, 'loginUrl' => Url::to(['/users/login'])])
                ->setStatusCode(401);
        }

        $postId = (int) Yii::$app->request->post('post_id');
        $post = $postId > 0 ? Post::findOne($postId) : null;
        // 已经收藏过的私有内容被作者改为草稿后仍要在按钮上可见，所以这里用 canViewContent 兜住越权，
        // 不做额外放行分支。
        if ($post === null || !$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $this->asJson([
            'post_id' => (int) $post->id,
            'favorited' => PostFavorite::toggle((int) Yii::$app->user->id, $post),
        ]);
    }
}
