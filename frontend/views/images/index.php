<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post[] $posts */
/** @var yii\data\Pagination $pagination */
/** @var int $total */
/** @var common\models\Tag|null $activeTag */
/** @var common\models\Tag[] $galleryTags */

use frontend\widgets\ContentPager;
use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$profileName = $identity !== null ? ($identity->nickname ?: $identity->username) : '';
$profileInitial = $profileName !== '' ? mb_strtoupper(mb_substr($profileName, 0, 1)) : '拾';

$this->title = $activeTag !== null ? '图集 · ' . $activeTag->name : '图集';
?>
<div class="row g-3 g-xl-4">
    <aside class="col-12 col-lg-3 d-none d-lg-block">
        <?php if ($identity === null): ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="card-body text-center py-4">
                    <span class="moment-avatar moment-avatar-fallback moment-avatar-lg mx-auto mb-3" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                    <h2 class="h5 mb-1">欢迎来到<?= Html::encode(Yii::$app->name) ?></h2>
                    <p class="text-body-secondary small mb-3">登录后即可上传图集，与大家分享你的镜头瞬间。</p>
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
                    <div class="d-grid gap-2 mt-3">
                        <?= Html::a('<i class="bi bi-images me-1" aria-hidden="true"></i>上传图片', ['/images/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
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
                <?= Html::a('<i class="bi bi-chat-square-text me-2" aria-hidden="true"></i>动态', ['/moment/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?= Html::a('<i class="bi bi-images me-2" aria-hidden="true"></i>图集', ['/images/index'], ['class' => 'list-group-item list-group-item-action active', 'encode' => false, 'aria-current' => 'page']) ?>
                <?= Html::a('<i class="bi bi-camera-reels me-2" aria-hidden="true"></i>短视频', ['/video/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?php if ($identity !== null): ?>
                    <?= Html::a('<i class="bi bi-collection me-2" aria-hidden="true"></i>我的内容', ['/users/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?php endif; ?>
            </div>
        </div>
        
    </aside>

    <div class="col-12 col-lg-9">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h1 class="h3 mb-1">图集</h1>
                <p class="text-body-secondary small mb-0">
                    <?php if ($activeTag !== null): ?>
                        标签 #<?= Html::encode($activeTag->name) ?> · 共 <?= (int) $total ?> 个图集
                    <?php else: ?>
                        用镜头收集生活，把每个瞬间装订成册 · 共 <?= (int) $total ?> 个图集
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($galleryTags !== []): ?>
            <nav class="moment-tabs mb-3" aria-label="图集标签">
                <?= Html::a('全部', ['/images/index'], ['class' => 'moment-tab' . ($activeTag === null ? ' active' : '')]) ?>
                <?php foreach ($galleryTags as $tab): ?>
                    <?= Html::a(
                        '<i class="bi bi-hash me-1" aria-hidden="true"></i>' . Html::encode($tab->name) . '<span class="moment-tab-count">' . (int) $tab->postCount . '</span>',
                        ['/images/index', 'tag' => $tab->slug],
                        [
                            'class' => 'moment-tab' . ($activeTag !== null && (int) $activeTag->id === (int) $tab->id ? ' active' : ''),
                            'encode' => false,
                        ],
                    ) ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="card moment-composer border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if ($identity !== null): ?>
                        <?php if ($identity->avatar): ?>
                            <img class="moment-avatar" src="<?= Html::encode($identity->avatar) ?>" alt="<?= Html::encode($profileName) ?>">
                        <?php else: ?>
                            <span class="moment-avatar moment-avatar-fallback" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                        <?php endif; ?>
                        <?= Html::a('分享你的图集…', ['/images/create'], ['class' => 'moment-composer-input flex-grow-1']) ?>
                        <?= Html::a('<i class="bi bi-image" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">上传图片</span>', ['/images/create'], ['class' => 'btn btn-primary btn-sm flex-shrink-0', 'encode' => false]) ?>
                    <?php else: ?>
                        <span class="moment-avatar moment-avatar-fallback" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                        <?= Html::a('登录后即可上传图集', ['/users/login'], ['class' => 'moment-composer-input flex-grow-1']) ?>
                        <?= Html::a('登录', ['/users/login'], ['class' => 'btn btn-primary btn-sm flex-shrink-0']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row row-cols-2 row-cols-sm-3 g-3">
            <?= $this->render('_items', ['posts' => $posts]) ?>
        </div>

        <?php if ($posts === []): ?>
            <div class="card moment-side-card border-0 shadow-sm mt-3">
                <div class="card-body text-center py-5">
                    <div class="moment-empty-icon mb-3" aria-hidden="true"><i class="bi bi-images"></i></div>
                    <h2 class="h5 mb-2"><?= $activeTag !== null ? '该标签下还没有图集' : '还没有人发布图集' ?></h2>
                    <p class="text-body-secondary mb-3">
                        <?= $activeTag !== null ? '换个标签看看，或用这个标签发布第一组图片。' : '用镜头记录的第一组图片，等你来上传。' ?>
                    </p>
                    <?php if ($identity !== null): ?>
                        <?= Html::a('上传图片', ['/images/create'], ['class' => 'btn btn-primary btn-sm']) ?>
                    <?php else: ?>
                        <?= Html::a('登录后上传', ['/users/login'], ['class' => 'btn btn-primary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?= ContentPager::widget([
            'pagination' => $pagination,
            'route' => ['/images/index'],
            'params' => ['tag' => $activeTag?->slug],
        ]) ?>
    </div>
</div>
