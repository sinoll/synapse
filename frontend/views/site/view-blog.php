<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var common\models\BlogPost $blog */

use yii\helpers\Html;
use yii\helpers\HtmlPurifier;

$this->title = $post->title;

$this->registerCss(<<<'CSS'
.content-view-blog .rich-content { line-height: 1.85; }
CSS);
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-8">
        <article class="content-view content-view-blog py-3 py-md-4">
            <?= $this->render('@frontend/views/site/_view_header', ['post' => $post, 'displayTitle' => $post->title]) ?>

            <?php if (trim((string) $blog->summary) !== ''): ?>
                <p class="content-view-summary"><?= Html::encode($blog->summary) ?></p>
            <?php endif; ?>

            <div class="rich-content text-break border-top pt-4 mt-4">
                <?= HtmlPurifier::process((string) $blog->content, ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title],pre,code']) ?>
            </div>
        </article>
    </div>
</div>
