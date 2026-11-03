<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array<int, array{level: int, text: string, anchor: string}> $headings */

use yii\helpers\Html;
?>
<?php if (count($headings) >= 2): ?>
    <nav class="content-detail-toc" aria-label="本文目录">
        <h2 class="content-detail-heading">本文目录</h2>
        <ol>
            <?php foreach ($headings as $heading): ?>
                <li class="<?= $heading['level'] >= 3 ? 'is-sub' : '' ?>">
                    <?= Html::a($heading['text'], '#' . $heading['anchor'], ['class' => 'content-detail-toc-link']) ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
<?php endif; ?>
