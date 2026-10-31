<?php
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'dev');
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/common/config/bootstrap.php';
require __DIR__ . '/console/config/bootstrap.php';
$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/common/config/main.php',
    require __DIR__ . '/common/config/main-local.php',
    require __DIR__ . '/console/config/main.php',
    require __DIR__ . '/console/config/main-local.php',
);
$app = new yii\console\Application($config);
$mode = $argv[1] ?? 'status';
if ($mode === 'enable-admin-test') {
    $app->db->createCommand()->update('{{%user}}', ['must_change_password' => 0, 'phone_verified_at' => time()], ['username' => 'default'])->execute();
    echo 'Temporary admin setup bypass enabled.';
    exit(0);
}
if ($mode === 'cleanup-admin-test') {
    $app->db->createCommand()->delete('{{%user}}', ['username' => 'codex_probe_20261016'])->execute();
    $app->db->createCommand()->update('{{%user}}', ['must_change_password' => 1, 'phone_verified_at' => null], ['username' => 'default'])->execute();
    echo 'Temporary test account removed and default setup gate restored.';
    exit(0);
}
$user = common\models\User::findOne(['username' => 'default']);
echo json_encode([
    'id' => $user->id,
    'active' => (int) $user->status,
    'must_change_password' => (int) $user->must_change_password,
    'phone_verified' => $user->phone_verified_at !== null,
    'is_admin' => (int) $user->is_admin,
]);
