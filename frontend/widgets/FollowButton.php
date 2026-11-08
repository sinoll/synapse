<?php

declare(strict_types=1);

namespace frontend\widgets;

use common\models\User;
use common\models\UserFollow;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * 作者关注按钮。
 *
 * 当前用户关注了谁整页只查一次（见 isFollowing()），列表里放多少个按钮都不会逐行查询；
 * 粉丝数仅在 showCount 时按作者去重懒查询。显示自己的头像/名字时不渲染。
 */
class FollowButton extends Widget
{
    public ?User $user = null;
    /** 紧凑位置（卡片、评论行）只放图标，详情页与主页带「关注」文字。 */
    public bool $iconOnly = false;
    /** 按钮尾部带粉丝数（作者卡片、主页用）。 */
    public bool $showCount = false;
    /** 追加到按钮上的布局类（如 ms-auto），便于在各卡片里对齐。 */
    public string $buttonClass = '';

    /** @var array<int,bool>|null 当前用户已关注的 user_id 集合，请求内共用。 */
    private static ?array $followingIds = null;
    /** @var array<int,int> 作者粉丝数，按 user_id 去重缓存。 */
    private static array $followerCounts = [];

    public function init(): void
    {
        parent::init();

        if ($this->user === null) {
            throw new InvalidConfigException('FollowButton 需要设置 user。');
        }

    }

    public function run(): string
    {
        $userId = (int) $this->user->id;
        if ($userId <= 0) {
            return '';
        }

        // 本人的作者信息处不出现「关注自己」。
        if (!Yii::$app->user->isGuest && (int) Yii::$app->user->id === $userId) {
            return '';
        }

        if (Yii::$app->user->isGuest) {
            // 游客不带 data-follow-* 属性，让链接正常跳登录，而不是被脚本拦成一次注定 401 的请求。
            return Html::a($this->body(false), ['/users/login'], [
                'class' => $this->cssClass(false),
                'title' => '登录后可关注',
                'aria-label' => '登录后可关注',
            ]);
        }

        $followed = $this->isFollowing($userId);

        return Html::tag('button', $this->body($followed), [
            'type' => 'button',
            'class' => $this->cssClass($followed),
            'title' => $followed ? '取消关注' : '关注',
            'aria-pressed' => $followed ? 'true' : 'false',
            'data-follow-user' => $userId,
            'data-follow-url' => Url::to(['/follow/toggle']),
            'data-login-url' => Url::to(['/users/login']),
            'data-label-on' => '已关注',
            'data-label-off' => '关注',
        ]);
    }

    private function cssClass(bool $followed): string
    {
        $classes = ['follow-button'];
        if ($followed) {
            $classes[] = 'is-followed';
        }
        if ($this->iconOnly) {
            $classes[] = 'follow-button--icon';
        }
        if ($this->buttonClass !== '') {
            $classes[] = $this->buttonClass;
        }

        return implode(' ', $classes);
    }

    private function body(bool $followed): string
    {
        $icon = Html::tag('i', '', [
            'class' => 'bi ' . ($followed ? 'bi-check2' : 'bi-person-plus'),
            'aria-hidden' => 'true',
        ]);

        $parts = [$icon];
        if (!$this->iconOnly) {
            $parts[] = Html::tag('span', Html::encode($followed ? '已关注' : '关注'), [
                'data-follow-label' => 'follow',
            ]);
        }
        if ($this->showCount) {
            $parts[] = Html::tag('span', Html::encode($this->countLabel()), [
                'class' => 'follow-button-count',
                'data-follow-count' => '',
                'data-follow-count-for' => (string) (int) $this->user->id,
                'data-follow-full' => (string) $this->followerCount(),
            ]);
        }

        return implode('', $parts);
    }

    private function followerCount(): int
    {
        $id = (int) $this->user->id;

        return self::$followerCounts[$id] ??= (int) UserFollow::find()->where(['following_id' => $id])->count();
    }

    private function countLabel(): int|string
    {
        $count = $this->followerCount();

        return $count >= 10000 ? round($count / 10000, 1) . '万' : $count;
    }

    /**
     * 一次查询取回当前用户的全部关注 user_id，避免在列表里逐条判断。
     */
    private function isFollowing(int $userId): bool
    {
        if (self::$followingIds === null) {
            $ids = UserFollow::find()
                ->select('following_id')
                ->where(['follower_id' => (int) Yii::$app->user->id])
                ->column();

            self::$followingIds = array_fill_keys(array_map('intval', $ids), true);
        }

        return self::$followingIds[$userId] ?? false;
    }
}
