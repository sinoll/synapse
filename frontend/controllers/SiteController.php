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

    /**
     * 标签页：带 slug 看单个标签的内容，不带 slug 就是标签云总览。
     * 页脚的「标签云」入口靠这个空 slug 形态，省掉一个只有云没有内容的独立页。
     */
    public function actionTag(?string $slug = null): string
    {
        $tags = Tag::find()
            ->alias('t')
            ->select(['t.*', 'COUNT([[pt]].[[post_id]]) AS postCount'])
            ->leftJoin(['pt' => '{{%post_tag}}'], 'pt.tag_id = t.id')
            ->groupBy('t.id')
            ->orderBy(['t.name' => SORT_ASC])
            ->all();

        if ($slug === null || $slug === '') {
            return $this->render('tag', [
                'tag' => null,
                'tags' => $tags,
                'posts' => [],
                'pagination' => null,
            ]);
        }

        $tag = Tag::find()->where(['slug' => $slug])->one();
        if ($tag === null) {
            throw new NotFoundHttpException('找不到该标签。');
        }

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

    /**
     * 分类页：带 slug 看单个分类的内容，不带 slug 就是分类总览。
     */
    public function actionCate(?string $slug = null): string
    {
        $cates = Cate::find()
            ->alias('c')
            ->select(['c.*', 'COUNT([[pc]].[[post_id]]) AS postCount'])
            ->leftJoin(['pc' => '{{%post_cate}}'], 'pc.cate_id = c.id')
            ->groupBy('c.id')
            ->orderBy(['c.name' => SORT_ASC])
            ->all();

        if ($slug === null || $slug === '') {
            return $this->render('cate', [
                'cate' => null,
                'cates' => $cates,
                'posts' => [],
                'pagination' => null,
            ]);
        }

        $cate = Cate::find()->where(['slug' => $slug])->one();
        if ($cate === null) {
            throw new NotFoundHttpException('找不到该分类。');
        }

        $provider = ListPagination::provider($this->cateQuery((int) $cate->id), ListPagination::SIZE_GRID);

        return $this->render('cate', [
            'cate' => $cate,
            'cates' => $cates,
            'posts' => $provider->getModels(),
            'pagination' => $provider->getPagination(),
        ]);
    }

    /**
     * 时间归档：一屏一年的时间轴，month=YYYY-MM 只把视线锚到某个月份，不改变取数范围。
     * year 与 month 都不给时，落在最近有内容的年份。
     */
    public function actionArchive(?string $month = null, ?string $year = null): string
    {
        $months = $this->archiveMonths();

        if ($month !== null && $month !== '') {
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
                throw new BadRequestHttpException('归档月份的格式应为 YYYY-MM。');
            }
            $targetYear = (int) substr($month, 0, 4);
        } elseif ($year !== null && $year !== '') {
            if (preg_match('/^\d{4}$/', $year) !== 1) {
                throw new BadRequestHttpException('归档年份的格式应为 YYYY。');
            }
            $month = null;
            $targetYear = (int) $year;
        } else {
            $month = null;
            // 分组键是 'YYYY-MM' 且按倒序返回，第一个键的年份就是最近有内容的年份。
            $targetYear = $months === [] ? (int) date('Y') : (int) substr((string) array_key_first($months), 0, 4);
        }

        $years = [];
        foreach ($months as $ym => $count) {
            $key = substr((string) $ym, 0, 4);
            $years[$key] = ($years[$key] ?? 0) + $count;
        }

        return $this->render('archive', [
            'year' => $targetYear,
            'month' => $month,
            'years' => $years,
            'items' => $this->archiveYearItems($targetYear),
        ]);
    }

    /** @return array<string,int> 形如 ['2026-03' => 5]，按月份倒序，只算当前访客可见的已发布内容。 */
    private function archiveMonths(): array
    {
        $query = Post::find()
            ->alias('p')
            ->select(['ym' => "FROM_UNIXTIME([[p]].[[created_at]], '%Y-%m')", 'cnt' => 'COUNT([[p]].[[id]])'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->groupBy(['ym'])
            ->orderBy(['ym' => SORT_DESC]);
        Post::applyVisibilityScope($query, $this->currentUserId());

        $months = [];
        foreach ($query->asArray()->all() as $row) {
            $months[(string) $row['ym']] = (int) $row['cnt'];
        }

        return $months;
    }

    /**
     * 时间轴条目：整年可见内容的极简字段。
     * 一行只渲染类型 / 标题 / 时刻，所以不关联加载子表和标签，一年几百条也不至于拖垮查询。
     *
     * @return array<int, array<string, mixed>>
     */
    private function archiveYearItems(int $year): array
    {
        $query = Post::find()
            ->alias('p')
            ->select(['p.id', 'p.type', 'p.title', 'p.created_at'])
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere([
                'between',
                'p.created_at',
                strtotime($year . '-01-01 00:00:00'),
                strtotime($year . '-12-31 23:59:59'),
            ])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        Post::applyVisibilityScope($query, $this->currentUserId());

        return $query->asArray()->all();
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
