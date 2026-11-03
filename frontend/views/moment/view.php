<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var common\models\Moment $moment */
/** @var array<int, array{post: common\models\Post, reason: string}> $related */
/** @var common\models\Post|null $prev */
/** @var common\models\Post|null $next */
/** @var array{total: int, byType: array<string, int>} $authorCounts */

use common\models\Tag;
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
    <div class="col-12 col-lg-10 col-xl-11 col-xxl-10">
        <article class="content-view content-view-moment py-3 py-md-4">
            <div class="content-detail-layout">
                <div class="content-detail-main">
                    <?= $this->render('@frontend/views/site/_view_header', ['post' => $post, 'displayTitle' => $displayTitle]) ?>

                    <div class="border rounded-4 p-3 p-md-4 card">
                        <div class="rich-content text-break">
                            <?= Tag::linkifyHashtags(HtmlPurifier::process((string) $moment->content, ['HTML.Allowed' => 'p,br,div,strong,b,em,i,u,s,ul,ol,li,h2,h3,blockquote,a[href|title],span,img[src|alt|title]'])) ?>
                        </div>
                    </div>

                    <?= $this->render('@frontend/views/site/_prev_next', ['prev' => $prev, 'next' => $next]) ?>
                    <?= $this->render('@frontend/views/site/_channel_bar', ['post' => $post]) ?>
                </div>

                <aside class="content-detail-side">
                    <?= $this->render('@frontend/views/site/_author_card', ['post' => $post, 'authorCounts' => $authorCounts]) ?>
                    <?= $this->render('@frontend/views/site/_related_list', ['items' => $related, 'heading' => '相关动态']) ?>
                </aside>
            </div>

            <?= $this->render('@frontend/views/comment/_thread', ['post' => $post]) ?>
        </article>
    </div>
</div>
