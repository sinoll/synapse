<?php

declare(strict_types=1);

namespace frontend\widgets;

use common\models\Post;
use common\models\PostLike;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * 内容点赞按钮。
 *
 * 数字读 post.like_count 冗余列（随内容行一起取回，不额外查询），
 * 当前用户是否点过赞整页只查一次（见 isLiked()），所以列表里放多少个按钮都不会产生逐行查询。
 */
class LikeButton extends Widget
{
    public ?Post $post = null;
    /** 卡片等窄位置只放图标与数字，详情页带「点赞」文字。 */
    public bool $iconOnly = false;
    /** 叠在封面或图片之上时使用反色底，保证在任意背景上都可读。 */
    public bool $overlay = false;
    /** 点赞数为 0 时是否仍占位显示，关掉可以让卡片更干净。 */
    public bool $showZero = true;
    /** 追加到按钮上的布局类（如 ms-auto），便于在各卡片里对齐。 */
    public string $buttonClass = '';

    /** @var array<int,bool>|null 当前用户已点赞的 post_id 集合，请求内共用。 */
    private static ?array $likedIds = null;

    public function init(): void
    {
        parent::init();

        if ($this->post === null) {
            throw new InvalidConfigException('LikeButton 需要设置 post。');
        }

    }

    public function run(): string
    {
        if (Yii::$app->user->isGuest) {
            // 游客不带 data-like-* 属性，让链接正常跳登录，而不是被脚本拦成一次注定 401 的请求。
            return Html::a($this->body(false), ['/users/login'], [
                'class' => $this->cssClass(false),
                'title' => '登录后可点赞',
                'aria-label' => '登录后可点赞',
            ]);
        }

        $liked = $this->isLiked();

        return Html::tag('button', $this->body($liked), [
            'type' => 'button',
            'class' => $this->cssClass($liked),
            'title' => $liked ? '取消点赞' : '点赞',
            'aria-pressed' => $liked ? 'true' : 'false',
            'data-like-post' => (int) $this->post->id,
            'data-like-url' => Url::to(['/like/toggle']),
            'data-login-url' => Url::to(['/users/login']),
            'data-label-on' => '已赞',
            'data-label-off' => '点赞',
        ]);
    }

    private function cssClass(bool $liked): string
    {
        $classes = ['like-button'];
        // 服务端就把已点赞态写上，避免首屏只有实心红心没有高亮，点一次才跳色。
        if ($liked) {
            $classes[] = 'is-liked';
        }
        if ($this->iconOnly) {
            $classes[] = 'like-button--icon';
        }
        if ($this->overlay) {
            $classes[] = 'like-button--overlay';
        }
        if ($this->buttonClass !== '') {
            $classes[] = $this->buttonClass;
        }

        return implode(' ', $classes);
    }

    private function body(bool $liked): string
    {
        $icon = Html::tag('i', '', [
            'class' => 'bi ' . ($liked ? 'bi-heart-fill' : 'bi-heart'),
            'aria-hidden' => 'true',
        ]);

        $parts = [$icon];
        if (!$this->iconOnly) {
            // 文字同时承载「点赞 / 已赞」状态，切换时由 JS 改写；aria 靠外层 title 与 aria-pressed。
            $parts[] = Html::tag('span', Html::encode($liked ? '已赞' : '点赞'), [
                'data-like-label' => 'like',
            ]);
        }
        $parts[] = $this->countSpan();

        return implode('', $parts);
    }

    private function countSpan(): string
    {
        return Html::tag('span', Html::encode($this->countLabel()), [
            'class' => 'like-button-count',
            'data-like-count' => '',
            'data-like-full' => (string) $this->likeCount(),
        ]);
    }

    /** @return array<string,string> 供 JS 在切换后回填数字与隐藏态。 */
    private function likeCount(): int
    {
        return max(0, (int) $this->post->like_count);
    }

    private function countLabel(): int|string
    {
        $count = $this->likeCount();
        if ($count === 0) {
            return $this->showZero ? '0' : '';
        }

        return $count >= 10000 ? round($count / 10000, 1) : $count;
    }

    /**
     * 一次查询取回当前用户的全部点赞 post_id，避免在列表里逐条判断。
     */
    private function isLiked(): bool
    {
        if (self::$likedIds === null) {
            $ids = PostLike::find()
                ->select('post_id')
                ->where(['user_id' => (int) Yii::$app->user->id])
                ->column();

            self::$likedIds = array_fill_keys(array_map('intval', $ids), true);
        }

        return self::$likedIds[(int) $this->post->id] ?? false;
    }
}
