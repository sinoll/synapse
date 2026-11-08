<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var common\models\BlogPost $blog */
/** @var array<int, array{post: common\models\Post, reason: string}> $related */
/** @var common\models\Post|null $prev */
/** @var common\models\Post|null $next */
/** @var array{total: int, byType: array<string, int>} $authorCounts */

use common\components\Mention;
use common\components\RichText;
use common\models\BlogPost;
use yii\helpers\Html;

$this->title = $post->title;

$this->registerCss(<<<'CSS'
.content-view-blog .rich-content { line-height: 1.85; }
CSS);

// 锚点在净化之后注入，因此 HTML.Allowed 不必放开 h2[id]。
[$contentHtml, $headings] = BlogPost::withHeadingAnchors(RichText::purify((string) $blog->content));
$contentHtml = Mention::linkify($contentHtml);
?>
<div class="row justify-content-center">
    <div class="col-12">
        <article class="content-view content-view-blog py-3 py-md-4">
            <div class="content-detail-layout">
                <div class="content-detail-main">
                    <?= $this->render('@frontend/views/site/_view_header', ['post' => $post, 'displayTitle' => $post->title, 'authorCounts' => $authorCounts]) ?>

                    <?php if (trim((string) $blog->summary) !== ''): ?>
                        <p class="content-view-summary"><?= Html::encode($blog->summary) ?></p>
                    <?php endif; ?>

                    <div class="rich-content text-break border-top pt-4 mt-4">
                        <?= $contentHtml ?>
                    </div>

                    <?= $this->render('@frontend/views/site/_prev_next', ['prev' => $prev, 'next' => $next]) ?>
                    <?= $this->render('@frontend/views/site/_channel_bar', ['post' => $post]) ?>
                </div>

                <aside class="content-detail-side">
                    <?= $this->render('@frontend/views/site/_toc', ['headings' => $headings]) ?>
                    <?= $this->render('@frontend/views/site/_related_list', ['items' => $related, 'heading' => '相关文章']) ?>
                </aside>
            </div>

            <?= $this->render('@frontend/views/comment/_thread', ['post' => $post]) ?>
        </article>
    </div>
</div>
