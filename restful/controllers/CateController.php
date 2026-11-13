<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\Cate;
use restful\components\ApiResponder;
use yii\web\NotFoundHttpException;

/** 分类查询接口：/cate。分类的创建与维护仍由站点管理后台负责。 */
final class CateController extends ApiController
{
    protected function publicActions(): array
    {
        return ['index', 'view'];
    }

    public function actionIndex(): array
    {
        $categories = Cate::find()
            ->orderBy(['name' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return ApiResponder::ok([
            'items' => array_map(self::item(...), $categories),
            'total' => count($categories),
        ]);
    }

    public function actionView(int $id): array
    {
        $category = Cate::findOne($id);
        if ($category === null) {
            throw new NotFoundHttpException('分类不存在。');
        }

        return ApiResponder::ok(self::item($category));
    }

    private static function item(Cate $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'thumb' => ApiResponder::assetUrl($category->thumb),
            'thumb_url' => ApiResponder::assetUrl($category->thumb),
            'created_at' => ApiResponder::timestamp((int) $category->created_at),
            'updated_at' => ApiResponder::timestamp((int) $category->updated_at),
        ];
    }
}
