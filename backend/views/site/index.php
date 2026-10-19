<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = '管理控制台';
$identity = Yii::$app->user->identity;
$displayName = $identity?->nickname ?: $identity?->username ?: '管理员';
?>
<section class="site-index py-3 py-md-4">
    <div class="dashboard-banner text-white rounded-4 p-4 p-md-5 mb-4 shadow-sm">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <p class="small text-uppercase fw-semibold opacity-75 mb-2">管理控制台</p>
                <h1 class="display-6 fw-bold mb-2">欢迎回来，<?= Html::encode($displayName) ?></h1>
                <p class="mb-0 opacity-75">在这里查看账户状态并管理站点后台。</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge rounded-pill text-bg-light px-3 py-2">管理员账户</span>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <article class="card h-100 shadow-sm">
                <div class="card-body p-4">
                    <p class="small text-body-secondary text-uppercase mb-2">账户</p>
                    <h2 class="h5">个人资料</h2>
                    <p class="text-body-secondary mb-0">当前登录账户：<?= Html::encode($displayName) ?></p>
                </div>
            </article>
        </div>
        <div class="col-md-6">
            <article class="card h-100 shadow-sm">
                <div class="card-body p-4">
                    <p class="small text-body-secondary text-uppercase mb-2">安全</p>
                    <h2 class="h5">账户安全</h2>
                    <p class="text-body-secondary mb-0">请使用安全且独立的密码保护管理账户。</p>
                </div>
            </article>
        </div>
    </div>
</section>
