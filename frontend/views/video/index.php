<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post[] $horizontalVideos */
/** @var common\models\Post[] $verticalVideos */
/** @var common\models\Tag[] $videoTags */

use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$profileName = $identity !== null ? ($identity->nickname ?: $identity->username) : '';
$profileInitial = $profileName !== '' ? mb_strtoupper(mb_substr($profileName, 0, 1)) : '拾';

$this->title = '短视频';
$this->params['breadcrumbs'] = ['短视频'];
?>
<div class="row g-3 g-xl-4">
    <aside class="col-12 col-lg-3 d-none d-lg-block">
        <?php if ($identity === null): ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="card-body text-center py-4">
                    <span class="moment-avatar moment-avatar-fallback moment-avatar-lg mx-auto mb-3" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                    <h2 class="h5 mb-1">欢迎来到<?= Html::encode(Yii::$app->name) ?></h2>
                    <p class="text-body-secondary small mb-3">登录后即可上传视频，与大家分享你的片段。</p>
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
                        <?= Html::a('<i class="bi bi-camera-video me-1" aria-hidden="true"></i>上传视频', ['/video/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
                        <?= Html::a('内容管理', ['/content/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                <?= Html::a('<i class="bi bi-camera-reels me-2" aria-hidden="true"></i>短视频', ['/video/index'], ['class' => 'list-group-item list-group-item-action active', 'encode' => false, 'aria-current' => 'page']) ?>
                <?php if ($identity !== null): ?>
                    <?= Html::a('<i class="bi bi-collection me-2" aria-hidden="true"></i>我的内容', ['/content/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($videoTags !== []): ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="card-header moment-side-heading">热门标签</div>
                <div class="list-group list-group-flush moment-nav">
                    <?php foreach ($videoTags as $tag): ?>
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
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">短视频</h1>
                <p class="text-body-secondary small mb-0">横屏故事与竖屏片段，按视频方向分栏浏览。</p>
            </div>
            <?php if ($identity !== null): ?>
                <?= Html::a('<i class="bi bi-plus-lg me-1" aria-hidden="true"></i>上传视频', ['/video/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>

        <section class="video-shelf-section mb-4" aria-labelledby="video-horizontal-heading">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 id="video-horizontal-heading" class="video-shelf-title h5 mb-0">
                    <span class="video-shelf-icon" aria-hidden="true"><i class="bi bi-camera-reels"></i></span>
                    横屏视频
                    <span class="video-shelf-count"><?= count($horizontalVideos) ?></span>
                </h2>
                <?php if ($horizontalVideos !== []): ?>
                    <span class="video-shelf-note d-none d-sm-inline">16:9 · 左右滑动查看更多</span>
                <?php endif; ?>
            </div>
            <?php if ($horizontalVideos === []): ?>
                <div class="video-shelf-empty">
                    <i class="bi bi-camera-reels d-block mb-2" aria-hidden="true"></i>
                    <p class="mb-0">还没有横屏视频，<?= $identity !== null ? Html::a('上传第一个', ['/video/create']) : Html::a('登录后上传', ['/users/login']) ?>。</p>
                </div>
            <?php else: ?>
                <div class="video-shelf">
                    <?php foreach ($horizontalVideos as $post): ?>
                        <?= $this->render('_card', ['post' => $post, 'vertical' => false]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="video-shelf-section" aria-labelledby="video-vertical-heading">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 id="video-vertical-heading" class="video-shelf-title h5 mb-0">
                    <span class="video-shelf-icon" aria-hidden="true"><i class="bi bi-phone"></i></span>
                    竖屏短视频
                    <span class="video-shelf-count"><?= count($verticalVideos) ?></span>
                </h2>
                <?php if ($verticalVideos !== []): ?>
                    <span class="video-shelf-note d-none d-sm-inline">9:16 · 左右滑动查看更多</span>
                <?php endif; ?>
            </div>
            <?php if ($verticalVideos === []): ?>
                <div class="video-shelf-empty">
                    <i class="bi bi-phone d-block mb-2" aria-hidden="true"></i>
                    <p class="mb-0">还没有竖屏视频，<?= $identity !== null ? Html::a('上传第一个', ['/video/create']) : Html::a('登录后上传', ['/users/login']) ?>。</p>
                </div>
            <?php else: ?>
                <div class="video-shelf video-shelf--vertical">
                    <?php foreach ($verticalVideos as $post): ?>
                        <?= $this->render('_card', ['post' => $post, 'vertical' => true]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
