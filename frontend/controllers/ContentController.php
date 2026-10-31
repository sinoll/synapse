<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class ContentController extends Controller
{
    public function getViewPath(): string
    {
        return Yii::getAlias('@frontend/views/content');
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::$app->user->identity;
        if ($user !== null && $user->must_change_password) {
            Yii::$app->response->redirect(['/users/change-password']);
            return false;
        }
        /***
        if ($user !== null && $user->phone_verified_at === null) {
            Yii::$app->response->redirect(['/users/verify-phone']);
            return false;
        }
        ***/
        return true;
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index', 'view'],
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
        ];
    }

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

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'definitions' => $definitions,
            'selectedType' => $type,
        ]);
    }

    public function actionView(int $id): string
    {
        $post = $this->findPost($id);
        if (!$this->canView($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $this->render('view', [
            'post' => $post,
            'contentModel' => $this->contentModel($post),
            'canEdit' => !Yii::$app->user->isGuest && (int) $post->user_id === (int) Yii::$app->user->id,
        ]);
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
}
