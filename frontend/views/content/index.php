<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $definitions */
/** @var string|null $selectedType */

use common\models\Post;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;

$this->title = '内容管理';
$typeLabels = [];
$typeControllers = [];
foreach ($definitions as $key => $definition) {
    $typeLabels[$definition['postType']] = $definition['label'];
    $typeControllers[$definition['postType']] = $key;
}
?>
<div class="content-index py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="mb-1"><?= Html::encode($this->title) ?></h1>
            <p class="text-body-secondary mb-0">统一管理你发布的博客、动态、图片、视频和文件内容。</p>
        </div>
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">发布内容</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($definitions as $key => $definition): ?>
                    <li><?= Html::a('发布' . $definition['label'], ['/' . $key . '/create'], ['class' => 'dropdown-item']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="btn-group flex-wrap mb-4" role="group" aria-label="内容类型筛选">
        <?= Html::a('全部', ['/content/index'], ['class' => 'btn btn-outline-secondary' . ($selectedType === null ? ' active' : '')]) ?>
        <?php foreach ($definitions as $key => $definition): ?>
            <?= Html::a($definition['label'], ['/content/index', 'type' => $key], ['class' => 'btn btn-outline-secondary' . ($selectedType === $key ? ' active' : '')]) ?>
        <?php endforeach; ?>
    </div>

    <?php if ($dataProvider->getCount() === 0): ?>
        <div class="alert alert-light border">目前没有可管理的内容。</div>
    <?php else: ?>
        <div class="table-responsive content-management-table">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th scope="col">类型</th>
                    <th scope="col">标题</th>
                    <th scope="col">作者</th>
                    <th scope="col">可见范围</th>
                    <th scope="col">发布时间</th>
                    <th scope="col" class="text-end">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dataProvider->getModels() as $post): ?>
                    <?php
                    $controller = $typeControllers[$post->type] ?? null;
                    $isOwner = !Yii::$app->user->isGuest && (int) $post->user_id === (int) Yii::$app->user->id;
                    $visibilityLabels = [
                        Post::VISIBILITY_PRIVATE => '仅自己',
                        Post::VISIBILITY_REGISTERED_USERS => '登录用户',
                        Post::VISIBILITY_PUBLIC => '公开',
                        Post::VISIBILITY_SELECTED_USERS => '指定用户',
                    ];
                    ?>
                    <tr>
                        <td><span class="badge text-bg-light"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span></td>
                        <td class="content-management-title">
                            <?= Html::a(Html::encode($post->title), ['/content/view', 'id' => $post->id], ['class' => 'fw-semibold text-decoration-none']) ?>
                            <?php if ($post->status === Post::STATUS_DRAFT): ?><span class="badge text-bg-secondary ms-1">草稿</span><?php endif; ?>
                        </td>
                        <td><?= Html::encode($post->user?->nickname ?: $post->user?->username ?: '用户') ?></td>
                        <td><?= Html::encode($visibilityLabels[$post->visibility] ?? '未知') ?></td>
                        <td class="text-body-secondary"><?= Yii::$app->formatter->asDatetime($post->created_at) ?></td>
                        <td class="text-end text-nowrap">
                            <?= Html::a('查看', ['/content/view', 'id' => $post->id], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                            <?php if ($isOwner && $controller !== null): ?>
                                <?= Html::a('编辑', ['/' . $controller . '/update', 'id' => $post->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                <?= Html::a('删除', ['/' . $controller . '/delete', 'id' => $post->id], [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'data' => ['method' => 'post', 'confirm' => '确定删除这条内容吗？'],
                                ]) ?>
                            <?php endif; ?>
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
</div>
