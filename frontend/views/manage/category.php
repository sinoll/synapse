<?php

declare(strict_types=1);

use common\models\Cate;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var Cate $model */
/** @var bool $isNew */

$this->title = $isNew ? '添加分类' : '编辑分类';
$currentThumb = trim((string) $model->thumb);
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
            <div class="row">
                <div class="col-12 col-xl-10">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-3 p-md-4">
                            <?php $form = ActiveForm::begin(['id' => 'category-form', 'options' => ['enctype' => 'multipart/form-data']]); ?>
                            <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
                            <?= $form->field($model, 'slug', ['enableClientValidation' => false])
                                ->textInput(['maxlength' => true, 'placeholder' => '留空自动生成'])
                                ->hint('留空时根据名称自动生成；修改已有分类的标识会让旧链接失效。') ?>
                            <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>
                            <div class="mb-3">
                                <img id="cate-thumb-preview" src="<?= $currentThumb === '' ? '' : Html::encode(Url::to($currentThumb)) ?>"
                                     alt="分类缩略图预览" width="168" height="105"
                                     class="rounded border bg-body-tertiary object-fit-cover<?= $currentThumb === '' ? ' d-none' : '' ?>">
                                <p id="cate-thumb-caption" class="small text-body-secondary mt-2 mb-0"><?= $currentThumb === '' ? '尚未设置缩略图' : '当前缩略图' ?></p>
                            </div>
                            <?= $form->field($model, 'thumb')->fileInput(['accept' => 'image/png,image/jpeg,image/gif,image/webp'])
                                ->hint($isNew
                                    ? '可选，支持 JPG、PNG、GIF、WebP，最大 20 MB，保存至 uploads/thumb/cate/ 目录。'
                                    : '支持 JPG、PNG、GIF、WebP，最大 20 MB，保存至 uploads/thumb/cate/ 目录；留空则继续使用当前缩略图。') ?>
                            <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                                <?= Html::submitButton($isNew ? '创建分类' : '保存修改', ['class' => 'btn btn-primary px-4']) ?>
                                <?= Html::a('返回分类列表', ['index', 'section' => 'categories'], ['class' => 'btn btn-outline-secondary px-4']) ?>
                            </div>
                            <?php ActiveForm::end(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<?php
$this->registerJs(<<<'JS'
const cateThumbInput = document.getElementById('cate-thumb');
const cateThumbPreview = document.getElementById('cate-thumb-preview');
const cateThumbCaption = document.getElementById('cate-thumb-caption');
let cateThumbUrl = null;
cateThumbInput?.addEventListener('change', () => {
    if (cateThumbUrl) URL.revokeObjectURL(cateThumbUrl);
    const file = cateThumbInput.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;
    cateThumbUrl = URL.createObjectURL(file);
    cateThumbPreview.src = cateThumbUrl;
    cateThumbPreview.classList.remove('d-none');
    cateThumbCaption.textContent = '新缩略图预览';
});
JS
);
