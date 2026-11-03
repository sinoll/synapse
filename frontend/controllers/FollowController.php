<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\User;
use common\models\UserFollow;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/** 用户关注：切换对某作者的关注状态，粉丝数随返回值同步。 */
class FollowController extends Controller
{
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
            return $this->asJson(['followed' => false, 'loginUrl' => Url::to(['/users/login'])])
                ->setStatusCode(401);
        }

        $userId = (int) Yii::$app->request->post('user_id');
        $target = $userId > 0 ? User::findOne($userId) : null;
        if ($target === null) {
            throw new NotFoundHttpException('找不到该用户。');
        }

        $followerId = (int) Yii::$app->user->id;
        if ($followerId === (int) $target->id) {
            return $this->asJson(['followed' => false, 'error' => 'self'])->setStatusCode(400);
        }

        $result = UserFollow::toggle($followerId, $target);

        return $this->asJson([
            'user_id' => (int) $target->id,
            'followed' => $result['followed'],
            'count' => $result['count'],
        ]);
    }
}
