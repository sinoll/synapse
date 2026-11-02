<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use common\models\PostLike;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/** 点赞：切换点赞并同步计数，列表页在「我的点赞」/users/like。 */
class LikeController extends Controller
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
            return $this->asJson(['liked' => false, 'loginUrl' => Url::to(['/users/login'])])
                ->setStatusCode(401);
        }

        $postId = (int) Yii::$app->request->post('post_id');
        $post = $postId > 0 ? Post::findOne($postId) : null;
        if ($post === null || !$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        $result = PostLike::toggle((int) Yii::$app->user->id, $post);

        return $this->asJson([
            'post_id' => (int) $post->id,
            'liked' => $result['liked'],
            'count' => $result['count'],
        ]);
    }
}
