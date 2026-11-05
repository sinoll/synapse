<?php

declare(strict_types=1);

namespace frontend\widgets;

use common\models\Post;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;

/**
 * 浏览数：眼睛图标加数字，读 post.views 冗余列，所以列表里放多少个都不会产生额外查询。
 *
 * 计数由 Post::countDailyView() 在各详情页渲染前写入，这里只负责展示。
 */
class ViewCount extends Widget
{
    public ?Post $post = null;

    public function init(): void
    {
        parent::init();

        if ($this->post === null) {
            throw new InvalidConfigException('ViewCount 需要设置 post。');
        }
    }

    public function run(): string
    {
        $count = max(0, (int) $this->post->views);

        return Html::tag('span',
            Html::tag('i', '', ['class' => 'bi bi-eye', 'aria-hidden' => 'true'])
            . Html::tag('span', Html::encode($this->label($count)), ['class' => 'view-count-number'])
            . Html::tag('span', '次浏览', ['class' => 'visually-hidden']),
            [
                'class' => 'view-count',
                'title' => $count . ' 次浏览',
            ],
        );
    }

    private function label(int $count): string
    {
        return $count >= 10000 ? round($count / 10000, 1) . '万' : (string) $count;
    }
}
