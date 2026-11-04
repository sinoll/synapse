<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\ChangePasswordForm;
use common\models\Cate;
use common\models\Post;
use common\models\Tag;
use common\models\VerifyPhoneForm;
use frontend\components\ListPagination;
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
    /**
     * 首页信息流：唯一保留无限滚动的列表。
     * ?page=N 仍会整页渲染，爬虫与无 JS 访客拿到的就是第 N 页。
     * ?type=blog|moment|images|video 按内容类型筛选。
     *
     * @return string
     */
    public function actionIndex(?string $type = null): string
    {
        $definitions = Post::typeDefinitions();
        $provider = ListPagination::provider($this->publicQuery($this->resolveType($definitions, $type)), ListPagination::SIZE_FEED);

        return $this->render('index', [
            'posts' => $provider->getModels(),
            'pagination' => $provider->getPagination(),
            'definitions' => $definitions,
            'activeType' => $type,
            'typeCounts' => $this->typeCounts(),
        ]);
    }

    /** Return the next page of public content for the home feed. */
    public function actionFeed(int $page = 1, ?string $type = null): Response
    {
        $pageSize = ListPagination::SIZE_FEED;
        $query = $this->publicQuery($this->resolveType(Post::typeDefinitions(), $type));
        $total = (int) (clone $query)->count();
        $page = max(1, $page);
        $posts = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->asJson([
            'html' => $this->renderPartial('_feed_items', ['posts' => $posts]),
            'hasMore' => $page * $pageSize < $total,
        ]);
    }

    /** 首页信息流查询：已发布且当前访客可见，可按内容类型筛选，按时间倒序。 */
    private function publicQuery(?string $postType = null): ActiveQuery
    {
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        if ($postType !== null) {
            $query->andWhere(['p.type' => $postType]);
        }

        Post::applyVisibilityScope($query, $this->currentUserId());

        return $query;
    }

    /** 侧栏筛选项用的类型参数是控制器名，这里换成 post.type 并挡掉非法值。 */
    private function resolveType(array $definitions, ?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }
        if (!isset($definitions[$type])) {
            throw new BadRequestHttpException('未知的内容类型。');
        }

        return $definitions[$type]['postType'];
    }

    /** @return array<string,int> 各内容类型的可见条目数，键为 post.type。 */
    private function typeCounts(): array
    {
        $query = Post::find()
            ->alias('p')
            ->select(['type' => 'p.type', 'cnt' => 'COUNT([[p]].[[id]])'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->groupBy(['p.type']);
        Post::applyVisibilityScope($query, $this->currentUserId());

        $counts = [];
        // 统计列不是表字段，AR 水合时会丢掉，这里直接取数组行。
        foreach ($query->asArray()->all() as $row) {
            $counts[(string) $row['type']] = (int) $row['cnt'];
        }

        return $counts;
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

        $provider = ListPagination::provider($this->tagQuery((int) $tag->id), ListPagination::SIZE_GRID);

        return $this->render('tag', [
            'tag' => $tag,
            'tags' => $tags,
            'posts' => $provider->getModels(),
            'pagination' => $provider->getPagination(),
        ]);
    }

    /** 指定标签下的内容查询：已发布且当前访客可见，按时间倒序。 */
    private function tagQuery(int $tagId): ActiveQuery
    {
        $query = Post::find()
            ->alias('p')
            ->innerJoin(['pt' => '{{%post_tag}}'], 'pt.post_id = p.id')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere(['pt.tag_id' => $tagId])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        Post::applyVisibilityScope($query, $this->currentUserId());

        return $query;
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

        $provider = ListPagination::provider($this->cateQuery((int) $cate->id), ListPagination::SIZE_GRID);

        return $this->render('cate', [
            'cate' => $cate,
            'cates' => $cates,
            'posts' => $provider->getModels(),
            'pagination' => $provider->getPagination(),
        ]);
    }

    /** 指定分类下的内容查询：已发布且当前访客可见，按时间倒序。 */
    private function cateQuery(int $cateId): ActiveQuery
    {
        $query = Post::find()
            ->alias('p')
            ->innerJoin(['pc' => '{{%post_cate}}'], 'pc.post_id = p.id')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere(['pc.cate_id' => $cateId])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        Post::applyVisibilityScope($query, $this->currentUserId());

        return $query;
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
