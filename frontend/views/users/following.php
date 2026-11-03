<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use frontend\widgets\FollowButton;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;

$this->title = '我的关注';
$users = $dataProvider->getModels();
?>

<div class="user-center following-index py-3 py-md-4">
    <div class="row g-4 align-items-start">
        <aside class="col-12 col-lg-3">
            <?= $this->render('_sidebar', ['active' => 'following']) ?>
        </aside>

        <section class="col-lg-9" aria-labelledby="following-index-title">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="mb-1" id="following-index-title"><?= Html::encode($this->title) ?></h3>
                    <p class="text-body-secondary mb-0">
                        共关注 <?= (int) $dataProvider->getCount() ?> 位作者，按关注时间倒序排列。
                    </p>
                </div>
                <?= Html::a('我的粉丝', ['/users/followers'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>

            <?php if ($users === []): ?>
                <div class="alert alert-light border">你还没有关注任何人，去内容页点「关注」支持喜欢的作者吧。</div>
            <?php else: ?>
                <div class="table-responsive content-management-table">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">作者</th>
                            <th scope="col">简介</th>
                            <th scope="col">关注时间</th>
                            <th scope="col" class="text-end">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $name = trim((string) ($user->nickname ?: $user->username)) ?: '用户';
                            $bio = trim((string) $user->bio);
                            $profileUrl = '/u/' . (int) $user->id;
                            ?>
                            <tr>
                                <td>
                                    <span class="d-flex align-items-center gap-2">
                                        <?php if ($user->avatar): ?>
                                            <img class="moment-avatar" src="<?= Html::encode($user->avatar) ?>" alt="<?= Html::encode($name) ?>" loading="lazy">
                                        <?php else: ?>
                                            <span class="moment-avatar moment-avatar-fallback" aria-hidden="true"><?= Html::encode(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                                        <?php endif; ?>
                                        <?= Html::a(Html::encode($name), $profileUrl, ['class' => 'fw-semibold text-decoration-none']) ?>
                                    </span>
                                </td>
                                <td class="text-body-secondary"><?= Html::encode($bio !== '' ? $bio : '这位作者还没有写简介。') ?></td>
                                <td class="text-body-secondary"><?= date('Y-m-d H:i', (int) $user->followedAt) ?></td>
                                <td class="text-end text-nowrap">
                                    <?= Html::a('查看主页', $profileUrl, ['class' => 'btn btn-sm btn-outline-secondary me-1']) ?>
                                    <?= FollowButton::widget(['user' => $user]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
