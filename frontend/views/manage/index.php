<?php

declare(strict_types=1);

use common\models\User;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;

/** @var User[] $users */
/** @var string $section */
/** @var string $siteName */
/** @var bool $nodeEnabled */

$titles = [
    'site-name' => '站点名称',
    'users' => '用户管理',
    'node' => '开启节点',
];
$this->title = $titles[$section] ?? '系统管理';
?>
<div class="manage-layout py-3 py-md-4">
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3">
            <?= $this->render('_sidebar', ['activeSection' => $section]) ?>
        </aside>
        <section class="col-lg-9" aria-labelledby="manage-title">
            <div class="mb-4">
                <p class="small text-body-secondary text-uppercase mb-1">系统管理</p>
                <h1 id="manage-title" class="h2 mb-1"><?= Html::encode($this->title) ?></h1>
                <p class="text-body-secondary mb-0">管理站点设置、用户账号和节点状态。</p>
            </div>

            <?php if ($section === 'site-name'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3 p-md-4">
                        <h2 class="h5 mb-2">站点名称</h2>
                        <p class="text-body-secondary">此名称会显示在页面导航栏和浏览器标题中。</p>
                        <?php $form = ActiveForm::begin(['id' => 'site-name-form']); ?>
                        <label class="form-label" for="site-name-input">名称</label>
                        <?= Html::textInput('siteName', $siteName, [
                            'id' => 'site-name-input',
                            'class' => 'form-control',
                            'maxlength' => 100,
                            'required' => true,
                        ]) ?>
                        <div class="form-text">最多 100 个字符。</div>
                        <div class="d-flex justify-content-end mt-4">
                            <?= Html::submitButton('保存名称', ['class' => 'btn btn-primary px-4']) ?>
                        </div>
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            <?php elseif ($section === 'node'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3 p-md-4">
                        <h2 class="h5 mb-2">节点状态</h2>
                        <p class="text-body-secondary">控制本站的节点开关状态。节点发现与内容同步流程尚未接入。</p>
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="badge <?= $nodeEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $nodeEnabled ? '已开启' : '未开启' ?>
                            </span>
                        </div>
                        <?php $form = ActiveForm::begin(['id' => 'node-setting-form']); ?>
                        <?= Html::hiddenInput('enabled', $nodeEnabled ? '0' : '1') ?>
                        <?= Html::submitButton($nodeEnabled ? '关闭节点' : '开启节点', [
                            'class' => $nodeEnabled ? 'btn btn-outline-secondary' : 'btn btn-primary',
                        ]) ?>
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-end mb-3">
                    <?= Html::a('添加用户', ['create'], ['class' => 'btn btn-primary']) ?>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <?= GridView::widget([
                            'dataProvider' => new yii\data\ArrayDataProvider(['allModels' => $users, 'pagination' => ['pageSize' => 30]]),
                            'layout' => "{summary}\n<div class=\"table-responsive\">{items}</div>\n{pager}",
                            'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
                            'summaryOptions' => ['class' => 'small text-body-secondary px-3 pt-3'],
                            'pager' => ['class' => LinkPager::class, 'options' => ['class' => 'pagination justify-content-end px-3 py-3 mb-0']],
                            'columns' => [
                                'id', 'username', 'nickname', 'email', 'phone',
                                ['attribute' => 'is_admin', 'label' => '角色', 'value' => static fn (User $user): string => $user->is_admin ? '管理员' : '普通用户'],
                                ['attribute' => 'must_change_password', 'label' => '密码状态', 'value' => static fn (User $user): string => $user->must_change_password ? '待修改' : '已完成'],
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
