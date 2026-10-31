<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post[] $posts */
/** @var bool $hasMore */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = '拾光';
?>

<div class="home-feed py-3 py-md-4">
    <div class="home-feed-header mb-4">
        <div>
            <p class="home-feed-eyebrow mb-2">欢迎回来，<?= Yii::$app->user->identity->nickname ?? '访客' ?> ！</p>           
        </div>
        <?php if (!Yii::$app->user->isGuest): ?>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">发布内容</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($definitions as $key => $definition): ?>
                        <li><?= Html::a('发布' . $definition['label'], ['/' . $key . '/create'], ['class' => 'dropdown-item']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div id="home-feed-list" class="home-feed-list" data-next-page="2" data-has-more="<?= $hasMore ? '1' : '0' ?>">
        <?= $this->render('_feed_items', ['posts' => $posts]) ?>
    </div>

    <div id="home-feed-status" class="home-feed-status text-center py-4" aria-live="polite">
        <?php if ($hasMore): ?>
            <span class="home-feed-loading d-none"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>正在加载更多内容…</span>
            <span class="home-feed-hint">继续下滑加载更多</span>
        <?php elseif ($posts !== []): ?>
            <span class="text-body-secondary">已经到底了</span>
        <?php else: ?>
            <span class="text-body-secondary">暂时还没有公开内容</span>
        <?php endif; ?>
    </div>
    <div id="home-feed-sentinel" aria-hidden="true"></div>
</div>

<?php
$feedUrl = Url::to(['/site/feed']);
$this->registerJs(<<<JS
(function () {
    const list = document.getElementById('home-feed-list');
    const status = document.getElementById('home-feed-status');
    const sentinel = document.getElementById('home-feed-sentinel');
    if (!list || !status || !sentinel) return;

    const loading = status.querySelector('.home-feed-loading');
    const hint = status.querySelector('.home-feed-hint');
    let loadingPage = false;

    const loadNextPage = async function () {
        if (loadingPage || list.dataset.hasMore !== '1') return;
        loadingPage = true;
        if (loading) loading.classList.remove('d-none');
        if (hint) hint.classList.add('d-none');

        try {
            const page = list.dataset.nextPage;
            const response = await fetch('{$feedUrl}?page=' + encodeURIComponent(page), {
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
