<?php

declare(strict_types=1);

use common\models\Cate;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var Cate $model */
/** @var bool $isNew */

$this->title = $isNew ? '添加分类' : '编辑分类';
?>
<div class="manage-layout py-3 py-md-4">
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3">
            <?= $this->render('_sidebar', ['activeSection' => 'categories']) ?>
        </aside>
        <section class="col-lg-9">
            <div class="mb-4">
                <p class="small text-body-secondary text-uppercase mb-1">系统管理 / 分类管理</p>
                <h1 class="h2 mb-2"><?= Html::encode($this->title) ?></h1>
                <p class="text-body-secondary mb-0">分类用于内容归属，标识会作为分类页地址的一部分。</p>
            </div>
            <div class="card border-0 shadow-sm" style="max-width: 760px">
                <div class="card-body p-3 p-md-4">
                    <?php $form = ActiveForm::begin(['id' => 'category-form']); ?>
                    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
                    <?= $form->field($model, 'slug', ['enableClientValidation' => false])
                        ->textInput(['maxlength' => true, 'placeholder' => '留空自动生成'])
                        ->hint('留空时根据名称自动生成；修改已有分类的标识会让旧链接失效。') ?>
                    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>
                    <?= $form->field($model, 'thumb')->textInput(['maxlength' => true])
                        ->hint('可选，分类缩略图地址，例如 /images/cate/example.jpg') ?>
                    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                        <?= Html::submitButton($isNew ? '创建分类' : '保存修改', ['class' => 'btn btn-primary px-4']) ?>
                        <?= Html::a('返回分类列表', ['index', 'section' => 'categories'], ['class' => 'btn btn-outline-secondary px-4']) ?>
                    </div>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </section>
    </div>
</div>
