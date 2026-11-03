<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Html;

$styles = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning'];
$notices = [];
foreach ($styles as $flashKey => $alertStyle) {
    $message = Yii::$app->session->getFlash($flashKey);
    if (is_string($message) && $message !== '') {
        $notices[] = [$alertStyle, $message];
    }
}
if ($notices === []) {
    return;
}
?>
<?php foreach ($notices as [$alertStyle, $message]): ?>
    <div class="alert alert-<?= $alertStyle ?> py-2 small" role="<?= $alertStyle === 'danger' ? 'alert' : 'status' ?>">
        <?= Html::encode($message) ?>
    </div>
<?php endforeach; ?>
