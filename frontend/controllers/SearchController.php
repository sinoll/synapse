<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use common\services\PostSearch;
use frontend\components\ListPagination;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\Controller;

/** 全站关键词搜索：覆盖各内容类型的标题、正文、摘要、图集说明、标签、分类和作者。 */
class SearchController extends Controller
{
    public function actionIndex(string $q = '', ?string $type = null): string
    {
        $definitions = Post::typeDefinitions();
        if ($type !== null && $type !== '' && !isset($definitions[$type])) {
            throw new BadRequestHttpException('未知的内容类型。');
        }
        $selectedType = $type !== null && $type !== '' ? $type : null;
        $keyword = PostSearch::keyword($q);

        $dataProvider = null;
        if ($keyword !== '') {
            $dataProvider = ListPagination::provider(
                PostSearch::query(
                    $keyword,
                    $definitions[$selectedType]['postType'] ?? null,
                    Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id
                ),
                ListPagination::SIZE_FEED,
            );
        }

        return $this->render('index', [
            'keyword' => $keyword,
            'selectedType' => $selectedType,
            'definitions' => $definitions,
            'dataProvider' => $dataProvider,
        ]);
    }
}
