<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination $pagination */
/** @var array $definitions */
/** @var array $typeCounts */
/** @var string|null $activeType */

use frontend\widgets\ContentPager;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = '拾光';
$currentPage = $pagination->getPage() + 1;
$pageCount = max(1, $pagination->getPageCount());
$hasMore = $currentPage < $pageCount;

$displayName = '拾光';
$profileInitial = '拾';
$identity = null;
if (!Yii::$app->user->isGuest) {
    $identity = Yii::$app->user->identity;
    $displayName = $identity->nickname ?? $identity->username;
    $profileInitial = mb_substr($displayName, 0, 1, 'UTF-8');
}
?>

<div class="home-layout py-3 py-md-4">
    <div class="home-feed">
        <div class="home-feed-list card-body p-3">
            <div class="d-flex align-items-center gap-3">
                <?php if ($identity !== null): ?>
                    <?php if ($identity->avatar): ?>
                        <img class="moment-avatar" src="<?= Html::encode($identity->avatar) ?>" alt="<?= Html::encode($displayName) ?>">
                    <?php else: ?>
                        <span class="moment-avatar moment-avatar-fallback" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                    <?php endif; ?>
                    <?= Html::a('分享此刻的想法…', ['/moment/create'], ['class' => 'moment-composer-input flex-grow-1']) ?>
                    <?= Html::a('<i class="bi bi-pencil-square" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">写动态</span>', ['/moment/create'], ['class' => 'btn btn-primary btn-sm flex-shrink-0', 'encode' => false]) ?>
                <?php else: ?>
                    <span class="moment-avatar moment-avatar-fallback" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                    <?= Html::a('登录后即可发布动态', ['/users/login'], ['class' => 'moment-composer-input flex-grow-1']) ?>
                    <?= Html::a('登录', ['/users/login'], ['class' => 'btn btn-primary btn-sm flex-shrink-0']) ?>
                <?php endif; ?>
            </div>
            <?php if ($identity !== null): ?>
                <div class="moment-composer-actions d-flex flex-wrap align-items-center gap-3 mt-3 ps-sm-5">
                    <?= Html::a('<i class="bi bi-image me-1" aria-hidden="true"></i>图片', ['/images/create'], ['encode' => false]) ?>
                    <?= Html::a('<i class="bi bi-camera-video me-1" aria-hidden="true"></i>视频', ['/video/create'], ['encode' => false]) ?>
                    <?= Html::a('<i class="bi bi-journal-text me-1" aria-hidden="true"></i>博客', ['/blog/create'], ['encode' => false]) ?>
                    <span class="ms-auto text-body-secondary small d-none d-md-inline">正文中输入 #话题# 自动生成标签</span>
                </div>
            <?php endif; ?>
        </div>
        <div id="home-feed-list" class="home-feed-list" data-next-page="<?= $currentPage + 1 ?>" data-has-more="<?= $hasMore ? '1' : '0' ?>">
            <?= $this->render('_feed_items', ['posts' => $posts]) ?>
        </div>

        <div id="home-feed-status" class="home-feed-status text-center py-4" aria-live="polite">
            <?php if ($hasMore): ?>
                <span class="home-feed-loading d-none"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>正在加载更多内容…</span>
                <span class="home-feed-hint">继续下滑加载更多</span>
                <?php /* 无限滚动的可抓取回退：JS 接管后隐藏，爬虫与无 JS 访客沿这条链接翻页。 */ ?>
                <?= Html::a(
                    '查看更早的内容',
                    ['/', 'page' => $currentPage + 1, 'type' => $activeType],
                    ['class' => 'btn btn-outline-secondary btn-sm home-feed-more'],
                ) ?>
            <?php elseif ($posts !== []): ?>
                <span class="text-body-secondary">已经到底了</span>
            <?php else: ?>
                <span class="text-body-secondary">暂时还没有公开内容</span>
            <?php endif; ?>
        </div>
        <div id="home-feed-sentinel" aria-hidden="true"></div>
    </div>

    <aside class="home-rail">
        <?= $this->render('_rail', [
            'definitions' => $definitions,
            'typeCounts' => $typeCounts,
            'activeType' => $activeType,
        ]) ?>
    </aside>
</div>

<?= ContentPager::widget(['pagination' => $pagination, 'route' => ['/'], 'params' => ['type' => $activeType], 'showPager' => false]) ?>

<?php
$feedUrl = Url::to(['/site/feed', 'type' => $activeType]);
// 带上 type 之后 feedUrl 可能已经有查询串，翻页参数只能用对应的分隔符接上去。
$feedSeparator = str_contains($feedUrl, '?') ? '&' : '?';
$this->registerJs(<<<JS
(function () {
    const list = document.getElementById('home-feed-list');
    const status = document.getElementById('home-feed-status');
    const sentinel = document.getElementById('home-feed-sentinel');
    if (!list || !status || !sentinel) return;

    const loading = status.querySelector('.home-feed-loading');
    const hint = status.querySelector('.home-feed-hint');
    const more = status.querySelector('.home-feed-more');
    let loadingPage = false;

    // JS 可用时由滚动加载接管，隐藏给爬虫和无 JS 访客的翻页链接。
    if (more) more.classList.add('d-none');

    const loadNextPage = async function () {
        if (loadingPage || list.dataset.hasMore !== '1') return;
        loadingPage = true;
        if (loading) loading.classList.remove('d-none');
        if (hint) hint.classList.add('d-none');

        try {
            const page = list.dataset.nextPage;
            const response = await fetch('{$feedUrl}{$feedSeparator}page=' + encodeURIComponent(page), {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            });
            if (!response.ok) throw new Error('feed request failed');
            const result = await response.json();
            list.insertAdjacentHTML('beforeend', result.html || '');
            list.dataset.nextPage = String(Number(page) + 1);
            list.dataset.hasMore = result.hasMore ? '1' : '0';
            if (!result.hasMore) status.innerHTML = '<span class="text-body-secondary">已经到底了</span>';
        } catch (error) {
            if (hint) {
                hint.textContent = '加载失败，点击重试';
                hint.classList.remove('d-none');
                hint.classList.add('home-feed-retry');
            }
        } finally {
            loadingPage = false;
            if (loading) loading.classList.add('d-none');
        }
    };

    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) loadNextPage();
        }, {rootMargin: '500px 0px'}).observe(sentinel);
    } else {
        window.addEventListener('scroll', function () {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) loadNextPage();
        }, {passive: true});
    }
})();
JS);
?>
