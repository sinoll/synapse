<?php

declare(strict_types=1);

/** @var common\models\Post[] $posts */

use common\models\Post;
use frontend\widgets\FavoriteButton;
use frontend\widgets\LikeButton;
use yii\helpers\Html;
use yii\helpers\Url;

$typeLabels = Post::TYPE_LABELS;
$detailRoutes = Post::TYPE_DETAIL_ROUTES;

foreach ($posts as $post):
    $summary = '';
    if ($post->type === Post::TYPE_BLOG && $post->blog !== null) {
        $summary = (string) ($post->blog->summary ?: $post->blog->content);
    } elseif ($post->type === Post::TYPE_MOMENT && $post->moment !== null) {
        $summary = (string) $post->moment->content;
    } elseif ($post->type === Post::TYPE_SHORT_VIDEO && $post->shortVideo !== null) {
        $summary = (string) $post->shortVideo->description;
    }
    $summary = trim(preg_replace('/\s+/u', ' ', strip_tags($summary)) ?? '');
    if (mb_strlen($summary) > 120) {
        $summary = mb_substr($summary, 0, 120) . '…';
    }
    $cover = $post->coverUrl();
    ?>
    <div class="col cover-list-cell">
        <?= LikeButton::widget([
            'post' => $post,
            'iconOnly' => true,
            'overlay' => true,
        ]) ?>
        <?= FavoriteButton::widget([
            'postId' => (int) $post->id,
            'iconOnly' => true,
            'overlay' => true,
        ]) ?>
        <a class="cover-list-item" href="<?= Html::encode(Url::to([$detailRoutes[$post->type] ?? '/site/view', 'id' => $post->id])) ?>">
            <img class="cover-list-cover" src="<?= Html::encode($cover) ?>" alt="<?= Html::encode($post->title) ?>" loading="lazy" width="460" height="260">
            <div class="cover-list-body">
                <div class="cover-list-title"><?= Html::encode($post->title ?: '动态') ?></div>
                <?php if ($summary !== ''): ?><p class="cover-list-summary"><?= Html::encode($summary) ?></p><?php endif; ?>
                <div class="cover-list-meta">
                    <span class="home-feed-type"><?= Html::encode($typeLabels[$post->type] ?? $post->type) ?></span>
                    <span><?= Html::encode($post->user?->nickname ?: $post->user?->username ?: '用户') ?></span>
                    <time datetime="<?= Html::encode(date(DATE_ATOM, (int) $post->created_at)) ?>"><?= Yii::$app->formatter->asRelativeTime($post->created_at) ?></time>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
