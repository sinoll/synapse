<?php

declare(strict_types=1);

namespace frontend\widgets;

use yii\base\Widget;
use yii\bootstrap5\LinkPager;
use yii\data\Pagination;
use yii\helpers\Url;

/**
 * 列表分页器：输出真实 <a href="?page=N"> 页码，并为当前列表页登记
 * canonical / prev / next，保证分页内容可被搜索引擎抓取。
 *
 * 无限滚动的页面把 $showPager 设为 false，只登记链接关系。
 */
final class ContentPager extends Widget
{
    public Pagination $pagination;

    /** @var array 当前列表路由，例如 ['images/index']。 */
    public array $route = [];

    /** @var array<string, scalar|null> 翻页时需要保留的筛选参数。 */
    public array $params = [];

    public bool $showPager = true;

    public function run(): string
    {
        $pageCount = max(1, $this->pagination->getPageCount());
        $current = $this->pagination->getPage() + 1;

        // 第 2 页起在标题上标页码，避免搜索引擎把同一列表判成重复内容。
        if ($current > 1 && $this->view->title !== null && $this->view->title !== '') {
            $this->view->title .= ' · 第 ' . $current . ' 页';
        }

        $this->view->registerLinkTag(['rel' => 'canonical', 'href' => $this->pageUrl($current)]);
        if ($pageCount > 1) {
            if ($current > 1) {
                $this->view->registerLinkTag(['rel' => 'prev', 'href' => $this->pageUrl($current - 1)]);
            }
            if ($current < $pageCount) {
                $this->view->registerLinkTag(['rel' => 'next', 'href' => $this->pageUrl($current + 1)]);
            }
        }

        if (!$this->showPager || $pageCount <= 1) {
            return '';
        }

        return LinkPager::widget([
            'pagination' => $this->pagination,
            'options' => ['class' => ['content-pager', 'mt-4'], 'aria' => ['label' => '分页导航']],
            // listOptions 会整体覆盖默认值，pagination 类必须一起带上。
            'listOptions' => ['class' => ['pagination', 'justify-content-center']],
        ]);
    }

    /** @param array<int|string, string> $params */
    private function pageUrl(int $page): string
    {
        $params = $this->params;
        unset($params['page']);
        if ($page > 1) {
            $params['page'] = $page;
        }

        return Url::to(array_merge($this->route, $params), true);
    }
}
