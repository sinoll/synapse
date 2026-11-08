<?php

declare(strict_types=1);

namespace frontend\widgets;

use common\models\Post;
use frontend\assets\ShareAsset;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * 分享链接按钮：把内容详情页的绝对 URL 复制到剪贴板。
 *
 * 与点赞/收藏不同，分享不需要登录，游客也能复制传播。
 */
class ShareButton extends Widget
{
    public ?Post $post = null;
    /** 直接指定要分享的链接（须为绝对 URL）；设置后忽略 post。 */
    public string $url = '';
    /** 卡片等窄位置只放图标，详情页带「分享」文字。 */
    public bool $iconOnly = false;
    /** 追加到按钮上的布局类（如 ms-auto），便于在各卡片里对齐。 */
    public string $buttonClass = '';

    public function init(): void
    {
        parent::init();

        if ($this->post === null && $this->url === '') {
            throw new InvalidConfigException('ShareButton 需要设置 post 或 url。');
        }

        ShareAsset::register($this->view);
    }

    public function run(): string
    {
        $classes = ['share-button'];
        if ($this->iconOnly) {
            $classes[] = 'share-button--icon';
        }
        if ($this->buttonClass !== '') {
            $classes[] = $this->buttonClass;
        }

        $body = Html::tag('i', '', ['class' => 'bi bi-share', 'aria-hidden' => 'true']);
        if (!$this->iconOnly) {
            $body .= Html::tag('span', '分享', ['data-share-label' => '']);
        }

        return Html::tag('button', $body, [
            'type' => 'button',
            'class' => implode(' ', $classes),
            'title' => '复制分享链接',
            'aria-label' => '复制分享链接',
            'data-share-url' => $this->shareUrl(),
        ]);
    }

    private function shareUrl(): string
    {
        if ($this->url !== '') {
            return $this->url;
        }

        $route = Post::TYPE_DETAIL_ROUTES[$this->post->type] ?? null;
        if ($route === null) {
            return Url::current([], true);
        }

        return Url::to([$route, 'id' => (int) $this->post->id], true);
    }
}