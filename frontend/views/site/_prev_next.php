<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\Post|null $prev 同类型内较新的一篇 */
/** @var common\models\Post|null $next 同类型内较旧的一篇 */

use common\models\Post;
use yii\helpers\Html;
use yii\helpers\Url;

$typeLabels = Post::TYPE_LABELS;
$detailRoutes = Post::TYPE_DETAIL_ROUTES;

$renderCard = static function (?Post $neighbor, string $label, string $align) use ($typeLabels, $detailRoutes): string {
    if ($neighbor === null) {
        return '<div class="content-detail-neighbor is-empty" aria-hidden="true"></div>';
    }

    $title = Html::encode($neighbor->title ?: '动态');
    $meta = Html::encode(($typeLabels[$neighbor->type] ?? $neighbor->type) . ' · ' . date('Y-m-d', (int) $neighbor->created_at));
    $href = Html::encode(Url::to([$detailRoutes[$neighbor->type] ?? '/site/view', 'id' => (int) $neighbor->id]));
    $arrow = $align === 'prev' ? '← ' : '';
    $suffix = $align === 'next' ? ' →' : '';

    return <<<HTML
        <a class="content-detail-neighbor is-{$align}" href="{$href}">
            <span class="content-detail-neighbor-label">{$arrow}{$label}{$suffix}</span>
            <span class="content-detail-neighbor-title">{$title}</span>
            <span class="content-detail-neighbor-meta">{$meta}</span>
        </a>
    HTML;
};

if ($prev !== null || $next !== null):
?>
    <nav class="content-detail-prevnext" aria-label="相邻内容">
        <?= $renderCard($prev, '上一篇', 'prev') ?>
        <?= $renderCard($next, '下一篇', 'next') ?>
    </nav>
<?php endif; ?>
