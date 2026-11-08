<?php

declare(strict_types=1);

namespace frontend\widgets;

use common\models\PostFavorite;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * 内容收藏按钮。
 *
 * 游客渲染成登录入口，登录用户渲染成可切换的开关；状态由 /favorite/toggle 就地更新，不整页刷新。
 * 整页只查一次当前用户的收藏集合（见 isFavorited()），所以列表里放多少个按钮都不会产生逐行查询。
 */
class FavoriteButton extends Widget
{
    public int $postId = 0;
    /** 卡片等窄位置只放图标，详情页带「收藏」文字。 */
    public bool $iconOnly = false;
    /** 叠在封面或图片之上时使用反色底，保证在任意背景上都可读。 */
    public bool $overlay = false;
    /** 追加到按钮上的布局类（如 ms-auto），便于在各卡片里对齐。 */
    public string $buttonClass = '';

    /** @var array<int,bool>|null 当前用户已收藏的 post_id 集合，请求内共用。 */
    private static ?array $favoritedIds = null;

    public function init(): void
    {
        parent::init();

        if ($this->postId <= 0) {
            throw new InvalidConfigException('FavoriteButton 需要设置 postId。');
        }

    }

    public function run(): string
    {
        if (Yii::$app->user->isGuest) {
            return Html::a(
                $this->body(false),
                ['/users/login'],
                [
                    'class' => $this->cssClass(),
                    'title' => '登录后可收藏',
                    'aria-label' => '登录后可收藏',
                ],
            );
        }

        $favorited = $this->isFavorited();

        return Html::tag('button', $this->body($favorited), [
            'type' => 'button',
            'class' => $this->cssClass($favorited),
            'data-favorite-post' => $this->postId,
            'data-favorite-url' => Url::to(['/favorite/toggle']),
            'data-login-url' => Url::to(['/users/login']),
            'data-label-on' => '已收藏',
            'data-label-off' => '收藏',
            'aria-pressed' => $favorited ? 'true' : 'false',
            'title' => $favorited ? '取消收藏' : '收藏',
        ]);
    }

    private function cssClass(bool $favorited = false): string
    {
        $classes = ['favorite-button'];
        // 服务端就把已收藏态写上，否则首屏只有实心星没有高亮，点一次才跳成金色。
        if ($favorited) {
            $classes[] = 'is-favorited';
        }
        if ($this->iconOnly) {
            $classes[] = 'favorite-button--icon';
        }
        if ($this->overlay) {
            $classes[] = 'favorite-button--overlay';
        }
        if ($this->buttonClass !== '') {
            $classes[] = $this->buttonClass;
        }

        return implode(' ', $classes);
    }

    private function body(bool $favorited): string
    {
        $icon = Html::tag('i', '', [
            'class' => 'bi ' . ($favorited ? 'bi-star-fill' : 'bi-star'),
            'aria-hidden' => 'true',
        ]);
        if ($this->iconOnly) {
            return $icon;
        }

        // 文字同时承载「收藏 / 已收藏」状态，切换时由 JS 改写；aria 靠外层 title 与 aria-pressed。
        return $icon . Html::tag('span', Html::encode($favorited ? '已收藏' : '收藏'), [
            'data-favorite-label' => 'favorite',
        ]);
    }

    /**
     * 一次查询取回当前用户的全部收藏 post_id，避免在列表里逐条判断。
     * 收藏量极大时可以改成按本页 post id 批量过滤，代价是每个控制器都要传 id 集合。
     */
    private function isFavorited(): bool
    {
        if (self::$favoritedIds === null) {
            $ids = PostFavorite::find()
                ->select('post_id')
                ->where(['user_id' => (int) Yii::$app->user->id])
                ->column();

            self::$favoritedIds = array_fill_keys(array_map('intval', $ids), true);
        }

        return self::$favoritedIds[$this->postId] ?? false;
    }
}
