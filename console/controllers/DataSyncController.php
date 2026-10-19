<?php

namespace console\controllers;

use Throwable;
use Yii;
use yii\console\Controller;

class DataSyncController extends Controller
{
    public function actionIndex()
    {
        $this->stdout("控制器工作正常\n");
    }

    /**
     * 同步旧数据库文章数据。
     * 命令：php yii data-sync/post
     */
    public function actionPost()
    {
        $oldDb = Yii::$app->dbOld;
        $newDb = Yii::$app->db;

        // 不迁移旧 uid，所有导入文章归属新库的 default 用户。
        $userId = $newDb->createCommand(
            'SELECT id FROM {{%user}} WHERE username = :username',
            [':username' => 'default']
        )->queryScalar();

        if ($userId === false) {
            $this->stderr("新数据库中找不到 default 用户，请先创建该用户。\n");
            return;
        }

        $this->stdout("开始同步数据...\n");

        $rows = $oldDb->createCommand(<<<'SQL'
            SELECT title, postdesc, body, viewCount, created, updated
            FROM blog
            SQL)->queryAll();

        if (empty($rows)) {
            $this->stdout("旧表中没有数据\n");
            return;
        }

        $transaction = $newDb->beginTransaction();

        try {
            foreach ($rows as $row) {
                $createdAt = !empty($row['created']) ? (strtotime($row['created']) ?: time()) : time();
                $updatedAt = !empty($row['updated']) ? (strtotime($row['updated']) ?: $createdAt) : $createdAt;

                // 新库生成 post.id，再用该 ID 建立 blog.post_id 关联。
                $newDb->createCommand()->insert('{{%post}}', [
                    'user_id' => (int) $userId,
                    'type' => 'blog',
                    'title' => $row['title'],
                    'status' => 1,
                    'visibility' => 2,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ])->execute();

                $postId = $newDb->getLastInsertID();
                $newDb->createCommand()->insert('{{%blog}}', [
                    'post_id' => $postId,
                    'title' => $row['title'],
                    'summary' => $row['postdesc'],
                    'content' => $row['body'],
                    'views' => $row['viewCount'],
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ])->execute();
            }

            $transaction->commit();
            $this->stdout("同步完成，共导入 " . count($rows) . " 条数据\n");
        } catch (Throwable $e) {
            $transaction->rollBack();
            $this->stderr("同步失败：" . $e->getMessage() . "\n");
        }
    }
}
