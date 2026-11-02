<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;
use yii\web\Controller;

/** 全站关键词搜索：覆盖各内容类型的标题、正文、摘要、图集说明、标签、分类和作者。 */
class SearchController extends Controller
{
    private const MAX_KEYWORD_LENGTH = 60;

    public function actionIndex(string $q = '', ?string $type = null): string
    {
        $definitions = Post::typeDefinitions();
        if ($type !== null && $type !== '' && !isset($definitions[$type])) {
            throw new BadRequestHttpException('未知的内容类型。');
        }
        $selectedType = $type !== null && $type !== '' ? $type : null;
        $keyword = mb_substr(trim($q), 0, self::MAX_KEYWORD_LENGTH);

        $dataProvider = null;
        if ($keyword !== '') {
            $dataProvider = new ActiveDataProvider([
                'query' => $this->searchQuery($keyword, $definitions[$selectedType]['postType'] ?? null),
                'pagination' => ['pageSize' => 10],
                'sort' => false,
            ]);
        }

        return $this->render('index', [
            'keyword' => $keyword,
            'selectedType' => $selectedType,
            'definitions' => $definitions,
            'dataProvider' => $dataProvider,
        ]);
    }

    private function searchQuery(string $keyword, ?string $postType): ActiveQuery
    {
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->leftJoin(['b' => '{{%blog}}'], 'b.post_id = p.id')
            ->leftJoin(['m' => '{{%moment}}'], 'm.post_id = p.id')
            ->leftJoin(['v' => '{{%short_video}}'], 'v.post_id = p.id')
            ->leftJoin(['i' => '{{%images}}'], 'i.post_id = p.id')
            ->leftJoin(['pt' => '{{%post_tag}}'], 'pt.post_id = p.id')
            ->leftJoin(['t' => '{{%tag}}'], 't.id = pt.tag_id')
            ->leftJoin(['pc' => '{{%post_cate}}'], 'pc.post_id = p.id')
            ->leftJoin(['c' => '{{%cate}}'], 'c.id = pc.cate_id')
            ->leftJoin(['u' => '{{%user}}'], 'u.id = p.user_id')
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere([
                'or',
                ['like', 'p.title', $keyword],
                ['like', 'b.title', $keyword],
                ['like', 'b.summary', $keyword],
                ['like', 'b.content', $keyword],
                ['like', 'm.title', $keyword],
                ['like', 'm.content', $keyword],
                ['like', 'v.title', $keyword],
                ['like', 'v.description', $keyword],
                ['like', 'i.title', $keyword],
                ['like', 'i.alt_text', $keyword],
                ['like', 't.name', $keyword],
                ['like', 'c.name', $keyword],
                ['like', 'u.nickname', $keyword],
                ['like', 'u.username', $keyword],
            ])
            // 一个图集多条图片、一条内容多个标签都会放大行数，交给 DISTINCT 按 post 行去重。
            ->distinct()
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        if ($postType !== null) {
            $query->andWhere(['p.type' => $postType]);
        }

        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        if ($userId === null) {
            $query->andWhere(['p.visibility' => Post::VISIBILITY_PUBLIC]);
        } else {
            $query->andWhere([
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                [
                    'and',
                    ['p.visibility' => Post::VISIBILITY_PRIVATE],
                    ['p.user_id' => $userId],
                ],
            ]);
        }

        return $query;
    }
}
