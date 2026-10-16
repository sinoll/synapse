<?php

declare(strict_types=1);

use yii\bootstrap5\Html;
use yii\grid\GridView;
use common\models\User;

$this->title = '用户管理';
?>
<div class="users-index py-4">
    <h1><?= Html::encode($this->title) ?></h1>
    <p><?= Html::a('添加用户', ['create'], ['class' => 'btn btn-primary']) ?></p>
    <?= GridView::widget([
        'dataProvider' => new yii\data\ArrayDataProvider(['allModels' => $users, 'pagination' => ['pageSize' => 30]]),
        'columns' => [
            'id', 'username', 'nickname', 'email', 'phone',
            ['attribute' => 'is_admin', 'value' => static fn (User $user): string => $user->is_admin ? '管理员' : '普通用户'],
            ['attribute' => 'must_change_password', 'value' => static fn (User $user): string => $user->must_change_password ? '待修改' : '已完成'],
        ],
    ]) ?>
</div>
