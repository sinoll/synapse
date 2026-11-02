<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use common\models\PostFavorite;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/** 收藏：对任意 post 内容切换收藏，列表页在「我的收藏」/users/favorite。 */
class FavoriteController extends Controller
{
    use ContentVisibilityTrait;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['toggle' => ['POST']],
            ],
        ];
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
