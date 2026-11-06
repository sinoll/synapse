<?php

declare(strict_types=1);

namespace restful\controllers;

use restful\components\ApiResponder;
use restful\models\ApiResource;
use Yii;

/**
 * 四类内容共用的一套 REST 动作；子类只报告自己对应哪个资源。
 *
 * 路由里的动词已经限死了（DELETE /blog/1 只可能命中 actionDelete），
 * 可见性与归属判断都在 ApiResource 里，控制器只负责把 HTTP 翻成方法调用。
 */
abstract class ContentController extends ApiController
{
    /** @return class-string<ApiResource> */
    abstract protected function resource(): string;

    /** 列表与详情允许访客读取，具体能看到几条由可见性范围决定。 */
    protected function publicActions(): array
    {
        return ['index', 'view'];
    }

    public function actionIndex(): array
    {
        $resource = $this->resource();

        return ApiResponder::ok($resource::page(Yii::$app->request->getQueryParams(), $this->viewerId()));
    }

    /**
     * 详情不累加 views：前台的「同一浏览器每天一次」靠响应里的去重 Cookie 实现，
     * API 客户端不存 Cookie，接上去只会把访问量刷成无意义的数字。
     */
    public function actionView(int $id): array
    {
        $resource = $this->resource();
        $post = $resource::find($id, $this->viewerId());

        return ApiResponder::ok($resource::item($post, $this->viewerId(), true));
    }

    public function actionCreate(): array
    {
        $resource = $this->resource();

        return ApiResponder::ok($resource::create($this->actorId(), $this->payload()), '内容已创建。');
    }

    /** PUT 与 PATCH 都走这里：未提交的字段保持原值，语义是部分更新。 */
    public function actionUpdate(int $id): array
    {
        $resource = $this->resource();

        return ApiResponder::ok($resource::update($id, $this->actorId(), $this->payload()), '内容已更新。');
    }

    public function actionDelete(int $id): array
    {
        $resource = $this->resource();

        return ApiResponder::ok($resource::remove($id, $this->actorId()), '内容已删除。');
    }

    /** 点赞是切换：已赞则取消，返回切换后的状态与计数。 */
    public function actionLike(int $id): array
    {
        $resource = $this->resource();

        return ApiResponder::ok($resource::like($id, $this->actorId()));
    }
}
