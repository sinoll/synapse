<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination $pagination */
/** @var common\models\Cate|null $activeCate */
/** @var common\models\Cate[] $cateTabs */
/** @var common\models\Tag[] $momentTags */

use frontend\widgets\ContentPager;
use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$profileName = $identity !== null ? ($identity->nickname ?: $identity->username) : '';
$profileInitial = $profileName !== '' ? mb_strtoupper(mb_substr($profileName, 0, 1)) : '拾';

$this->title = $activeCate !== null ? '动态 · ' . $activeCate->name : '动态';
?>
<div class="row g-3 g-xl-4">
    <aside class="col-12 col-lg-3 d-none d-lg-block">
        <?php if ($identity === null): ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="card-body text-center py-4">
                    <span class="moment-avatar moment-avatar-fallback moment-avatar-lg mx-auto mb-3" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                    <h2 class="h5 mb-1">欢迎来到<?= Html::encode(Yii::$app->name) ?></h2>
                    <p class="text-body-secondary small mb-3">登录后即可发布动态，与大家分享此时此刻。</p>
                    <?= Html::a('立即登录', ['/users/login'], ['class' => 'btn btn-primary w-100']) ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="moment-profile-cover" aria-hidden="true"></div>
                <div class="card-body text-center moment-profile-body pb-3">
                    <?php if ($identity->avatar): ?>
                        <img class="moment-avatar moment-avatar-lg" src="<?= Html::encode($identity->avatar) ?>" alt="<?= Html::encode($profileName) ?>">
                    <?php else: ?>
                        <span class="moment-avatar moment-avatar-fallback moment-avatar-lg" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                    <?php endif; ?>
                    <h2 class="h5 mt-2 mb-0"><?= Html::encode($profileName) ?></h2>
                    <p class="text-body-secondary small mb-2">@<?= Html::encode($identity->username) ?></p>
                    <?php if ($identity->bio): ?>
                        <p class="text-body-secondary small mb-2"><?= Html::encode($identity->bio) ?></p>
                    <?php endif; ?>
                    <div class="d-grid gap-2 mt-3">
                        <?= Html::a('<i class="bi bi-pencil-square me-1" aria-hidden="true"></i>写动态', ['/moment/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
                        <?= Html::a('内容管理', ['/users/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card moment-side-card border-0 shadow-sm mb-3">
            <div class="card-header moment-side-heading">浏览</div>
            <div class="list-group list-group-flush moment-nav">
                <?= Html::a('<i class="bi bi-house-door me-2" aria-hidden="true"></i>首页', ['/site/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?= Html::a('<i class="bi bi-journal-text me-2" aria-hidden="true"></i>Blog', ['/blog/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?= Html::a('<i class="bi bi-chat-square-text me-2" aria-hidden="true"></i>动态', ['/moment/index'], ['class' => 'list-group-item list-group-item-action active', 'encode' => false, 'aria-current' => 'page']) ?>
                <?= Html::a('<i class="bi bi-images me-2" aria-hidden="true"></i>图集', ['/images/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?= Html::a('<i class="bi bi-camera-reels me-2" aria-hidden="true"></i>短视频', ['/video/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?php if ($identity !== null): ?>
                    <?= Html::a('<i class="bi bi-collection me-2" aria-hidden="true"></i>我的内容', ['/users/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($momentTags !== []): ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="list-group list-group-flush moment-nav">
                    <?php foreach ($momentTags as $tag): ?>
                        <?= Html::a(
                            '<i class="bi bi-hash me-1" aria-hidden="true"></i><span class="text-truncate">' . Html::encode($tag->name) . '</span><span class="moment-nav-count">' . (int) $tag->postCount . '</span>',
                            ['/site/tag', 'slug' => $tag->slug],
                            ['class' => 'list-group-item list-group-item-action', 'encode' => false],
                        ) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <div class="col-12 col-lg-9">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h1 class="h3 mb-1">动态</h1>
                <p class="text-body-secondary small mb-0">记录此刻，分享生活的碎片。</p>
            </div>
        </div>

        <nav class="moment-tabs mb-3" aria-label="动态分类">
            <?= Html::a('全部', ['/moment/index'], ['class' => 'moment-tab' . ($activeCate === null ? ' active' : '')]) ?>
            <?php foreach ($cateTabs as $tab): ?>
                <?= Html::a(
                    Html::encode($tab->name) . '<span class="moment-tab-count">' . (int) $tab->postCount . '</span>',
                    ['/moment/index', 'cate' => $tab->slug],
                    [
                        'class' => 'moment-tab' . ($activeCate !== null && (int) $activeCate->id === (int) $tab->id ? ' active' : ''),
                        'encode' => false,
                    ],
                ) ?>
            <?php endforeach; ?>
        </nav>

        <div class="card moment-composer border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if ($identity !== null): ?>
                        <?php if ($identity->avatar): ?>
                            <img class="moment-avatar" src="<?= Html::encode($identity->avatar) ?>" alt="<?= Html::encode($profileName) ?>">
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
                        <span class="ms-auto text-body-secondary small d-none d-md-inline"><i class="bi bi-hash" aria-hidden="true"></i> 正文中输入 #话题# 自动生成标签</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <?= $this->render('_items', ['posts' => $posts]) ?>
        </div>

        <?php if ($posts === []): ?>
            <div class="card moment-side-card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="moment-empty-icon mb-3" aria-hidden="true"><i class="bi bi-chat-square-text"></i></div>
                    <h2 class="h5 mb-2">这里还很安静</h2>
                    <p class="text-body-secondary mb-3">
                        <?= $activeCate !== null ? '该分类下还没有动态，换个分类看看。' : '还没有人发布动态，来记录第一条吧。' ?>
                    </p>
                    <?php if ($identity !== null): ?>
                        <?= Html::a('发布第一条动态', ['/moment/create'], ['class' => 'btn btn-primary btn-sm']) ?>
                    <?php else: ?>
                        <?= Html::a('登录后发布', ['/users/login'], ['class' => 'btn btn-primary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?= ContentPager::widget([
            'pagination' => $pagination,
            'route' => ['/moment/index'],
            'params' => ['cate' => $activeCate?->slug],
        ]) ?>
    </div>
</div>

<?php
$this->registerJs(<<<JS
(function () {
    const initMomentTexts = function (root) {
        root.querySelectorAll('[data-moment-text]').forEach(function (element) {
            if (element.dataset.ready === '1') return;
            element.dataset.ready = '1';
            element.classList.add('is-clamped');
            if (element.scrollHeight <= element.clientHeight + 2) {
                element.classList.remove('is-clamped');
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-link btn-sm p-0 moment-expand text-decoration-none';
            button.textContent = '展开全文';
            element.insertAdjacentElement('afterend', button);
            button.addEventListener('click', function () {
                const clamped = element.classList.toggle('is-clamped');
                button.textContent = clamped ? '展开全文' : '收起';
            });
        });
    };

    initMomentTexts(document);

    const copyText = async function (text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    };

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-moment-copy]');
        if (!button) return;
        const label = button.querySelector('span');
        const original = label ? label.textContent : '';
        try {
            await copyText(button.dataset.momentCopy);
            if (label) label.textContent = '已复制';
        } catch (error) {
            if (label) label.textContent = '复制失败';
        }
        window.setTimeout(function () {
            if (label) label.textContent = original;
        }, 1600);
    });
})();
JS);
?>
