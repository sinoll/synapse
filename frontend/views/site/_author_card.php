<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post $post */
/** @var array{total: int, byType: array<string, int>} $authorCounts */

use frontend\widgets\FollowButton;
use yii\helpers\Html;
use yii\helpers\Url;

$author = $post->user;
if ($author !== null):
    $displayName = trim((string) ($author->nickname ?: $author->username)) ?: '用户';
    $avatar = trim((string) $author->avatar);
    $bio = trim((string) $author->bio);
    $profileUrl = Url::to(['/users/profile', 'id' => (int) $author->id]);
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $joinedAt = $author->created_at > 0 ? date('Y-n', (int) $author->created_at) : null;
?>
    <section class="content-detail-author" aria-label="作者">
        <div class="content-detail-author-row">
        <a class="content-detail-author-head" href="<?= Html::encode($profileUrl) ?>">
            <?php if ($avatar !== ''): ?>
                <img class="content-detail-avatar" src="<?= Html::encode($avatar) ?>" alt="" width="44" height="44" loading="lazy">
            <?php else: ?>
                <span class="content-detail-avatar content-detail-avatar-initial" aria-hidden="true"><?= Html::encode($initial) ?></span>
            <?php endif; ?>
            <span class="content-detail-author-name">
                <span class="content-detail-author-title"><?= Html::encode($displayName) ?></span>
                <span class="content-detail-author-sub">
                    <?= $authorCounts['total'] ?> 篇内容<?php if ($joinedAt !== null): ?> · <?= Html::encode($joinedAt) ?> 加入<?php endif; ?>
                </span>
            </span>
        </a>
        <?= FollowButton::widget(['user' => $author, 'showCount' => true]) ?>
        </div>
        <?php if ($bio !== ''): ?>
            <p class="content-detail-author-bio"><?= Html::encode($bio) ?></p>
        <?php endif; ?>
        <div class="mt-2">
            <?= Html::a('查看主页', ['/users/profile', 'id' => (int) $author->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
        </div>
    </section>
<?php endif; ?>
