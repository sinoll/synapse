<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var int $year 当前轴所在的年份 */
/** @var string|null $month 形如 2026-03，只负责把时间轴锚到某个月 */
/** @var array<string,int> $years 年份 => 该年可见篇数，倒序 */
/** @var array<int, array<string, mixed>> $items 该年可见内容，按时间倒序 */

use common\models\Post;
use yii\helpers\Html;
use yii\web\View;

$typeLabels = Post::TYPE_LABELS;
$typeIcons = [
    Post::TYPE_BLOG => 'bi-journal-text',
    Post::TYPE_MOMENT => 'bi-chat-square-text',
    Post::TYPE_IMAGES => 'bi-images',
    Post::TYPE_SHORT_VIDEO => 'bi-camera-reels',
];
$weekdayLabels = ['日', '一', '二', '三', '四', '五', '六'];

// 条目本身已按时间倒序，分组只需保持插入顺序，于是月与日都是自然倒序。
$byMonth = [];
foreach ($items as $item) {
    $timestamp = (int) $item['created_at'];
    $byMonth[date('Y-m', $timestamp)][date('Y-m-d', $timestamp)][] = $item;
}

$monthCounts = [];
foreach ($byMonth as $ym => $days) {
    $monthCounts[$ym] = array_sum(array_map('count', $days));
}
$maxMonthCount = $monthCounts === [] ? 0 : max($monthCounts);
$totalAllYears = array_sum($years);
$hasLocated = $month !== null && isset($byMonth[$month]);

$this->title = $year . ' 年 · 时间归档';
$this->params['meta_description'] = '拾光 ' . $year . ' 年时间轴归档：按月份与日期顺序回看这一年发布的全部内容。';
?>

<div class="archive-page py-3 py-md-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><?= Html::a('首页', ['/site/index']) ?></li>
            <li class="breadcrumb-item"><?= Html::a('时间归档', ['/site/archive']) ?></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $year ?> 年</li>
        </ol>
    </nav>

    <header class="archive-head">
        <div class="archive-head-text">
            <h1 class="browse-page-title mb-1"><?= $year ?> 年</h1>
            <p class="text-body-secondary mb-0">
                <?php if ($monthCounts !== []): ?>
                    这一年写下 <strong><?= array_sum($monthCounts) ?></strong> 篇，分布在 <strong><?= count($monthCounts) ?></strong> 个月。
                <?php else: ?>
                    这一年没有对你可见的内容。
                <?php endif; ?>
            </p>
        </div>
        <?php if ($years !== []): ?>
            <nav class="archive-years" aria-label="按年份浏览">
                <?php foreach ($years as $key => $count): ?>
                    <?= Html::a(
                        '<span class="archive-year-num">' . Html::encode($key) . '</span><span class="archive-year-count">' . $count . '</span>',
                        ['/site/archive', 'year' => $key],
                        [
                            'class' => 'archive-year' . ((int) $key === $year ? ' active' : ''),
                            'encode' => false,
                            'aria-current' => (int) $key === $year ? 'page' : null,
                        ],
                    ) ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </header>

    <?php if ($monthCounts === []): ?>
        <div class="alert alert-light border">换一个年份看看，上面是全部有内容的年份。</div>
    <?php else: ?>
        <?php if ($month !== null && !$hasLocated): ?>
            <?php /* 深链指向空月份时不装作没事：说清楚这一年这个月没有可见内容，再交回整轴。 */ ?>
            <div class="alert alert-light border">
                <?= (int) substr($month, 5, 2) ?> 月没有对你可见的内容，下面是 <?= $year ?> 年有记录的月份。
            </div>
        <?php endif; ?>
        <ol class="archive-timeline">
            <?php foreach ($byMonth as $ym => $days): ?>
                <?php $count = $monthCounts[$ym]; ?>
                <li class="archive-month<?= $hasLocated && $ym === $month ? ' is-located' : '' ?>" id="m-<?= Html::encode($ym) ?>">
                    <div class="archive-month-head">
                        <h2 class="archive-month-title"><?= (int) substr($ym, 5, 2) ?> 月</h2>
                        <span class="archive-month-count"><?= $count ?> 篇</span>
                        <span class="archive-month-density" style="--w:<?= $maxMonthCount > 0 ? round($count / $maxMonthCount, 3) : 1 ?>"
                              aria-hidden="true"></span>
                    </div>

                    <ol class="archive-days">
                        <?php foreach ($days as $day => $dayItems): ?>
                            <li class="archive-day">
                                <div class="archive-day-mark">
                                    <span class="archive-day-num"><?= (int) substr($day, 8, 2) ?></span>
                                    <span class="archive-day-week">周<?= Html::encode($weekdayLabels[(int) date('w', strtotime((string) $day))]) ?></span>
                                </div>
                                <ul class="archive-entries">
                                    <?php foreach ($dayItems as $item): ?>
                                        <?php
                                        $type = (string) $item['type'];
                                        $route = Post::TYPE_DETAIL_ROUTES[$type] ?? null;
                                        $title = trim((string) $item['title']) !== ''
                                            ? (string) $item['title']
                                            : ($typeLabels[$type] ?? '内容');
                                        $label = $typeLabels[$type] ?? $type;
                                        ?>
                                        <li class="archive-entry<?= $route === null ? ' is-dead' : '' ?>">
                                            <span class="archive-entry-type is-<?= Html::encode($type) ?>">
                                                <?php if (isset($typeIcons[$type])): ?>
                                                    <i class="bi <?= $typeIcons[$type] ?>" aria-hidden="true"></i>
                                                <?php endif; ?>
                                                <span class="visually-hidden"><?= Html::encode($label) ?></span>
                                            </span>
                                            <?php if ($route === null): ?>
                                                <span class="archive-entry-title"><?= Html::encode($title) ?></span>
                                            <?php else: ?>
                                                <?= Html::a($title, [$route, 'id' => (int) $item['id']], ['class' => 'archive-entry-title']) ?>
                                            <?php endif; ?>
                                            <time class="archive-entry-time" datetime="<?= Html::encode(date('Y-m-d\TH:i', (int) $item['created_at'])) ?>">
                                                <?= date('H:i', (int) $item['created_at']) ?>
                                            </time>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <p class="archive-foot text-body-secondary">
        全站共 <?= $totalAllYears ?> 篇可见内容，跨 <?= count($years) ?> 个年份<?= $hasLocated ? '，已定位到 ' . (int) substr($month, 5, 2) . ' 月' : '' ?>。
    </p>
</div>

<?php
if ($hasLocated):
    // ?month= 只做定位：同年整轴已渲染，这里把视线送到那个月，月份头的 scroll-margin 负责躲开固定导航。
    $this->registerJs(
        'document.getElementById("m-' . $month . '")?.scrollIntoView({block: "start"});',
        View::POS_END,
    );
endif;
?>
