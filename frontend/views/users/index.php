<?php

declare(strict_types=1);

use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;
use common\models\User;

$this->title = '用户管理';
?>
<div class="users-index py-3 py-md-4">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-3 mb-4">
        <div>
            <p class="small text-body-secondary text-uppercase mb-1">系统管理</p>
            <h1 class="h2 mb-1"><?= Html::encode($this->title) ?></h1>
            <p class="text-body-secondary mb-0">查看账户信息与首次登录状态。</p>
        </div>
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
</div>
