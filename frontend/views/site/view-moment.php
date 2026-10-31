<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var common\models\Moment $moment */

use yii\helpers\HtmlPurifier;

$displayTitle = $post->title ?: '动态';
$this->title = $displayTitle;

$this->registerCss(<<<'CSS'
.content-view-moment .rich-content { font-size: 1.05rem; line-height: 1.9; }
.content-view-moment .rich-content img { max-width: 100%; height: auto; border-radius: .75rem; }
.content-view-moment .rich-content > :first-child { margin-top: 0; }
.content-view-moment .rich-content > :last-child { margin-bottom: 0; }
CSS);
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 col-xl-7">
        <article class="content-view content-view-moment py-3 py-md-4">
            <?= $this->render('@frontend/views/site/_view_header', ['post' => $post, 'displayTitle' => $displayTitle]) ?>

            <div class="border rounded-4 p-3 p-md-4 bg-body-tertiary">
                <div class="rich-content text-break">
                    <?= HtmlPurifier::process((string) $moment->content, ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title]']) ?>
                </div>
            </div>
        </article>
    </div>
</div>
