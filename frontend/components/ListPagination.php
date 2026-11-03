<?php

declare(strict_types=1);

namespace frontend\components;

use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * 内容列表的统一分页口径。
 *
 * 站点里所有「可被搜索引擎抓取」的列表页都从这里出分页，避免每处各写一套
 * offset/limit 或各挑一个页宽。
 */
final class ListPagination
{
    /** 封面网格：图集、分类、标签、作者主页、视频网格。 */
    public const SIZE_GRID = 12;

    /** 单列信息流：首页、动态、博客列表、搜索结果。 */
    public const SIZE_FEED = 10;

    /** 自管理表格：内容管理、收藏、点赞。 */
    public const SIZE_TABLE = 20;

    public static function provider(ActiveQuery $query, int $pageSize): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $pageSize,
                // 与 pageSize 保持一致，否则 LinkPager 会往每个链接上挂 ?per-page=。
                'defaultPageSize' => $pageSize,
                // 锁死页宽：?per-page=500 这类参数不得放大单次查询。
                'pageSizeLimit' => [$pageSize, $pageSize],
                // 第一页不带 ?page=，入口链接与规范地址一致。
                'forcePageParam' => false,
            ],
            'sort' => false,
        ]);
    }

    /** 当前页码，从 1 开始，与 URL 里的 ?page= 对齐。 */
    public static function currentPage(ActiveDataProvider $provider): int
    {
        return $provider->getPagination()->getPage() + 1;
    }

    public static function pageCount(ActiveDataProvider $provider): int
    {
        return max(1, $provider->getPagination()->getPageCount());
    }

    /** 是否还有下一页。 */
    public static function hasMore(ActiveDataProvider $provider): bool
    {
        return self::currentPage($provider) < self::pageCount($provider);
    }
}
