<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array<int,int> $tagCounts */
/** @var common\models\Cate[] $blogCategories */

use yii\bootstrap5\LinkPager;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Blog';
$this->params['breadcrumbs'] = ['Blog'];

$posts = $dataProvider->getModels();
$webRoot = Yii::getAlias('@webroot');

$identity = Yii::$app->user->identity;
$profileName = $identity !== null ? ($identity->nickname ?: $identity->username) : '';
$profileInitial = $profileName !== '' ? mb_strtoupper(mb_substr($profileName, 0, 1)) : '拾';
?>
<div class="row g-3 g-xl-4">
    <aside class="col-12 col-lg-3 d-none d-lg-block">
        <?php if ($identity === null): ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="card-body text-center py-4">
                    <span class="moment-avatar moment-avatar-fallback moment-avatar-lg mx-auto mb-3" aria-hidden="true"><?= Html::encode($profileInitial) ?></span>
                    <h2 class="h5 mb-1">欢迎来到<?= Html::encode(Yii::$app->name) ?></h2>
                    <p class="text-body-secondary small mb-3">登录后即可发布内容，与大家分享你的见解。</p>
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
                        <?= Html::a('<i class="bi bi-pencil-square me-1" aria-hidden="true"></i>写博客', ['/blog/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
                        <?= Html::a('内容管理', ['/content/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card moment-side-card border-0 shadow-sm mb-3">
            <div class="card-header moment-side-heading">浏览</div>
            <div class="list-group list-group-flush moment-nav">
                <?= Html::a('<i class="bi bi-house-door me-2" aria-hidden="true"></i>首页', ['/site/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?= Html::a('<i class="bi bi-journal-text me-2" aria-hidden="true"></i>Blog', ['/blog/index'], ['class' => 'list-group-item list-group-item-action active', 'encode' => false, 'aria-current' => 'page']) ?>
                <?= Html::a('<i class="bi bi-chat-square-text me-2" aria-hidden="true"></i>动态', ['/moment/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?php if ($identity !== null): ?>
                    <?= Html::a('<i class="bi bi-collection me-2" aria-hidden="true"></i>我的内容', ['/content/index'], ['class' => 'list-group-item list-group-item-action', 'encode' => false]) ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($blogCategories !== []): ?>
            <div class="card moment-side-card border-0 shadow-sm mb-3">
                <div class="card-header moment-side-heading">分类</div>
                <div class="list-group list-group-flush moment-nav">
                    <?php foreach ($blogCategories as $category): ?>
                        <?= Html::a(
                            '<i class="bi bi-folder2 me-2" aria-hidden="true"></i><span class="text-truncate">' . Html::encode($category->name) . '</span><span class="moment-nav-count">' . (int) $category->postCount . '</span>',
                            ['/site/cate', 'slug' => $category->slug],
                            ['class' => 'list-group-item list-group-item-action', 'encode' => false],
                        ) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <div class="col-12 col-lg-9">
        <div class="blog-index py-1">
            <?php if ($posts === []): ?>
                <div class="alert alert-light border">暂时还没有博客文章。</div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php
                    $blog = $post->blog;
                    if ($blog === null) {
                        continue;
                    }

                    $detailUrl = ['/blog/view', 'id' => $post->id];
                    $author = $post->user?->nickname ?: $post->user?->username ?: '用户';
                    $cate = $post->categories[0] ?? null;

                    $thumb = trim((string) $blog->thumb);
                    $thumbPath = '/' . ltrim($thumb, '/');
                    $thumbUrl = $thumb !== '' && is_file($webRoot . $thumbPath) ? $thumbPath : '/images/default-cover.png';

                    $excerpt = trim((string) ($blog->summary ?: $blog->content));
                    $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags($excerpt)) ?? '');
                    if (mb_strlen($excerpt) > 200) {
                        $excerpt = mb_substr($excerpt, 0, 200) . '…';
                    }
                    ?>
                    <article class="row g-3 g-md-4 align-items-center border-bottom pb-4 mb-4">
                        <div class="col-12 col-md-4 text-center">
                            <a href="<?= Html::encode(Url::to($detailUrl)) ?>">
                                <img class="img-fluid rounded" src="<?= Html::encode($thumbUrl) ?>"
                                     alt="<?= Html::encode($post->title) ?>" loading="lazy">
                            </a>
                        </div>

                        <div class="col-12 col-md-8">
                            <h2 class="h4 fw-bold mb-3">
                                <?= Html::a(Html::encode($post->title), $detailUrl, ['class' => 'text-decoration-none']) ?>
                            </h2>

                            <p class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <span class="badge text-bg-dark"><?= Html::encode($author) ?></span>
                                <span class="badge text-bg-light">发布于</span>
                                <span class="badge text-bg-dark"><?= Html::encode(date('Y-m-d H:i:s', (int) $post->created_at)) ?></span>
                                <?php if ($cate !== null): ?>
                                    <?= Html::a(Html::encode($cate->name), ['/site/cate', 'slug' => $cate->slug], ['class' => 'btn btn-outline-success btn-sm ms-md-3']) ?>
                                <?php endif; ?>
                                <?php foreach ($post->tags as $tag): ?>
                                    <?= Html::a(
                                        Html::encode($tag->name) . '<span class="badge text-bg-light ms-1">' . ($tagCounts[(int) $tag->id] ?? 0) . '</span>',
                                        ['/site/tag', 'slug' => $tag->slug],
                                        ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false],
                                    ) ?>
                                <?php endforeach; ?>
                            </p>

                            <?php if ($excerpt !== ''): ?>
                                <p class="card-text text-body-secondary mb-3"><?= Html::encode($excerpt) ?></p>
                            <?php endif; ?>

                            <div class="d-flex justify-content-end">
                                <?= Html::a('阅读全文', $detailUrl, ['class' => 'btn btn-sm btn-outline-success']) ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

                <div class="d-flex justify-content-center mt-4">
                    <?= LinkPager::widget([
                        'pagination' => $dataProvider->pagination,
                        'options' => ['class' => 'pagination mb-0'],
                        'maxButtonCount' => 10,
                    ]) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
