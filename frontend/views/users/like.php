<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $definitions */
/** @var string|null $selectedType */

use common\models\Post;
use frontend\widgets\LikeButton;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;

$this->title = '我的点赞';
$posts = $dataProvider->getModels();
$typeLabels = Post::TYPE_LABELS;
$detailRoutes = Post::TYPE_DETAIL_ROUTES;
?>

<div class="user-center like-index py-3 py-md-4">
    <div class="row g-4 align-items-start">
        <aside class="col-12 col-lg-3">
            <?= $this->render('_sidebar', ['active' => 'like']) ?>
        </aside>

        <section class="col-lg-9" aria-labelledby="like-index-title">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="mb-1" id="like-index-title"><?= Html::encode($this->title) ?></h3>
                    <p class="text-body-secondary mb-0">
                        共点赞 <?= (int) $dataProvider->getCount() ?> 条内容，按点赞时间倒序排列。
                    </p>
                </div>
                <?= Html::a('浏览内容', ['/site/index'], ['class' => 'btn btn-primary']) ?>
            </div>

            <div class="btn-group flex-wrap mb-4" role="group" aria-label="点赞类型筛选">
                <?= Html::a('全部', ['/users/like'], ['class' => 'btn btn-outline-secondary' . ($selectedType === null ? ' active' : '')]) ?>
                <?php foreach ($definitions as $key => $definition): ?>
                    <?= Html::a($definition['label'], ['/users/like', 'type' => $key], ['class' => 'btn btn-outline-secondary' . ($selectedType === $key ? ' active' : '')]) ?>
                <?php endforeach; ?>
            </div>

            <?php if ($posts === []): ?>
                <div class="alert alert-light border">还没有点赞过任何内容。</div>
            <?php else: ?>
                <div class="table-responsive content-management-table" data-like-list>
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">类型</th>
                            <th scope="col">标题</th>
                            <th scope="col">作者</th>
                            <th scope="col">点赞时间</th>
                            <th scope="col" class="text-end">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($posts as $post): ?>
                            <?php $detailUrl = [$detailRoutes[$post->type] ?? '/site/view', 'id' => $post->id]; ?>
                            <tr>
                                <td><span class="badge text-bg-light"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span></td>
                                <td class="content-management-title">
                                    <?= Html::a(
                                        Html::encode($post->title ?: '动态'),
                                        $detailUrl,
                                        ['class' => 'fw-semibold text-decoration-none'],
                                    ) ?>
                                </td>
                                <td><?= Html::encode($post->user?->nickname ?: $post->user?->username ?: '用户') ?></td>
                                <td class="text-body-secondary"><?= date('Y-m-d H:i', (int) $post->likedAt) ?></td>
                                <td class="text-end text-nowrap">
                                    <?= Html::a('查看', $detailUrl, ['class' => 'btn btn-sm btn-outline-secondary me-1']) ?>
                                    <?= LikeButton::widget(['post' => $post]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-light border like-list-empty d-none">这一页的点赞都取消了，去内容里挑几条赞一下吧。</div>
                <div class="mt-4">
                    <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
