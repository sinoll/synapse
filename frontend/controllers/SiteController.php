<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\ChangePasswordForm;
use common\models\Cate;
use common\models\Post;
use common\models\Tag;
use common\models\VerifyPhoneForm;
use Yii;
use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * 公开 可访问控制器  不需要权限检查和限制
 */
class SiteController extends Controller
{
    use ContentVisibilityTrait;

    public function actions(): array
    {
        return [
            'error' => ['class' => ErrorAction::class],
        ];
    }

    /**
     * 按发布时间先后顺序，显示post表的内容.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        [$posts, $hasMore] = $this->publicFeed(1);

        return $this->render('index', [
            'posts' => $posts,
            'hasMore' => $hasMore,
            'definitions' => Post::typeDefinitions(),
        ]);
    }

    /** Return the next page of public content for the home feed. */
    public function actionFeed(int $page = 1): Response
    {
        $page = max(1, $page);
        [$posts, $hasMore] = $this->publicFeed($page);

        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->asJson([
            'html' => $this->renderPartial('_feed_items', ['posts' => $posts]),
            'hasMore' => $hasMore,
        ]);
    }

    /** Display all content associated with a specific tag. */
    public function actionTag(string $slug): string
    {
        $tag = Tag::find()->where(['slug' => $slug])->one();
        if ($tag === null) {
            throw new NotFoundHttpException('找不到该标签。');
        }

        $tags = Tag::find()
            ->alias('t')
            ->select(['t.*', 'COUNT([[pt]].[[post_id]]) AS postCount'])
            ->leftJoin(['pt' => '{{%post_tag}}'], 'pt.tag_id = t.id')
            ->groupBy('t.id')
            ->orderBy(['t.name' => SORT_ASC])
            ->all();

        [$posts, $hasMore] = $this->tagFeed($tag->id, 1);

        return $this->render('tag', [
            'tag' => $tag,
            'tags' => $tags,
            'posts' => $posts,
            'hasMore' => $hasMore,
        ]);
    }

    /** Return the next page of content for a specific tag. */
    public function actionTagFeed(string $slug, int $page = 1): Response
    {
        $tag = Tag::find()->where(['slug' => $slug])->one();
        if ($tag === null) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $this->asJson(['html' => '', 'hasMore' => false]);
        }

        $page = max(1, $page);
        [$posts, $hasMore] = $this->tagFeed($tag->id, $page);

        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->asJson([
            'html' => $this->renderPartial('_cover_items', ['posts' => $posts]),
            'hasMore' => $hasMore,
        ]);
    }

    /** @return array{0: Post[], 1: bool} */
    private function tagFeed(int $tagId, int $page): array
    {
        $pageSize = 10;
        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->innerJoin(['pt' => '{{%post_tag}}'], 'pt.post_id = p.id')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere(['pt.tag_id' => $tagId]);

        if ($userId === null) {
            $query->andWhere(['p.visibility' => Post::VISIBILITY_PUBLIC]);
        } else {
            $visibleToUser = [
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                [
                    'and',
                    ['p.visibility' => Post::VISIBILITY_PRIVATE],
                    ['p.user_id' => $userId],
                ],
            ];
            $query->andWhere($visibleToUser);
        }

        $query->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        $total = (clone $query)->count();
        $posts = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

        return [$posts, $page * $pageSize < $total];
    }

    /** Display all content associated with a specific category. */
    public function actionCate(string $slug): string
    {
        $cate = Cate::find()->where(['slug' => $slug])->one();
        if ($cate === null) {
            throw new NotFoundHttpException('找不到该分类。');
        }

        $cates = Cate::find()
            ->alias('c')
            ->select(['c.*', 'COUNT([[pc]].[[post_id]]) AS postCount'])
            ->leftJoin(['pc' => '{{%post_cate}}'], 'pc.cate_id = c.id')
            ->groupBy('c.id')
            ->orderBy(['c.name' => SORT_ASC])
            ->all();

        [$posts, $hasMore] = $this->cateFeed($cate->id, 1);

        return $this->render('cate', [
            'cate' => $cate,
            'cates' => $cates,
            'posts' => $posts,
            'hasMore' => $hasMore,
        ]);
    }

    /** Return the next page of content for a specific category. */
    public function actionCateFeed(string $slug, int $page = 1): Response
    {
        $cate = Cate::find()->where(['slug' => $slug])->one();
        if ($cate === null) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $this->asJson(['html' => '', 'hasMore' => false]);
        }

        $page = max(1, $page);
        [$posts, $hasMore] = $this->cateFeed($cate->id, $page);

        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->asJson([
            'html' => $this->renderPartial('_cover_items', ['posts' => $posts]),
            'hasMore' => $hasMore,
        ]);
    }

    /** @return array{0: Post[], 1: bool} */
    private function cateFeed(int $cateId, int $page): array
    {
        $pageSize = 10;
        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->innerJoin(['pc' => '{{%post_cate}}'], 'pc.post_id = p.id')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere(['pc.cate_id' => $cateId]);

        if ($userId === null) {
            $query->andWhere(['p.visibility' => Post::VISIBILITY_PUBLIC]);
        } else {
            $visibleToUser = [
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                [
                    'and',
                    ['p.visibility' => Post::VISIBILITY_PRIVATE],
                    ['p.user_id' => $userId],
                ],
            ];
            $query->andWhere($visibleToUser);
        }

        $query->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        $total = (clone $query)->count();
        $posts = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

        return [$posts, $page * $pageSize < $total];
    }

    /** @return array{0: Post[], 1: bool} */
    private function publicFeed(int $page): array
    {
        $pageSize = 10;
        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->where(['p.status' => Post::STATUS_PUBLISHED]);

        if ($userId === null) {
            $query->andWhere(['p.visibility' => Post::VISIBILITY_PUBLIC]);
        } else {
            $visibleToUser = [
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                [
                    'and',
                    ['p.visibility' => Post::VISIBILITY_PRIVATE],
                    ['p.user_id' => $userId],
                ],
            ];
            $query->andWhere($visibleToUser);
        }

        $query->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        $total = (clone $query)->count();
        $posts = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

        return [$posts, $page * $pageSize < $total];
    }


    public function actionView(int $id): Response
    {
        $post = $this->findContentPost($id);
        if (!$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        $route = Post::TYPE_DETAIL_ROUTES[$post->type] ?? null;
        if ($route === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $this->redirect([$route, 'id' => $post->id]);
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





    

}
