<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */

use common\models\Post;
use yii\helpers\Html;

$typeLabels = Post::TYPE_LABELS;
$controller = Post::TYPE_CONTROLLERS[$post->type] ?? null;
$primaryCate = $post->categories[0] ?? null;
$author = $post->user;
?>
<?php if ($controller !== null): ?>
    <div class="content-detail-channel">
        <?= Html::a(
            '返回' . ($typeLabels[$post->type] ?? $post->type) . '频道',
            ['/' . $controller . '/index'],
            ['class' => 'content-detail-channel-link']
        ) ?>
        <?php if ($primaryCate !== null): ?>
            <span class="content-detail-channel-sep" aria-hidden="true">·</span>
            <?= Html::a(
                '分类「' . $primaryCate->name . '」',
                ['/site/cate', 'slug' => $primaryCate->slug],
                ['class' => 'content-detail-channel-link']
            ) ?>
        <?php endif; ?>
        <?php if ($author !== null): ?>
            <span class="content-detail-channel-sep" aria-hidden="true">·</span>
            <?= Html::a(
                ($author->nickname ?: $author->username) . ' 的全部内容',
                ['/users/profile', 'id' => (int) $author->id],
                ['class' => 'content-detail-channel-link']
            ) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
