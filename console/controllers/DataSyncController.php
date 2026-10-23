<?php

namespace console\controllers;

use Throwable;
use Yii;
use yii\console\Controller;

class DataSyncController extends Controller
{
    public function actionIndex()
    {
        $this->stdout("控制器工作正常\n"."从旧数据库恢复数据几个函数\n");
    }

    /*****
     * 同步blog表文章数据。
     * 命令：php yii data-sync/blog
     *****/
    public function actionBlog()
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

        $this->stdout("开始同步 blog表 数据...\n");

        $rows = $oldDb->createCommand(<<<'SQL'
            SELECT b.id, b.title, b.summary, b.content, b.views, b.created_at, b.updated_at, p.user_id, p.type, p.title AS post_title, p.status, p.visibility
            FROM blog AS b 
            LEFT JOIN post AS p ON p.id = b.post_id 
            SQL)->queryAll();

        if (empty($rows)) {
            $this->stdout("旧表中没有数据\n");
            return;
        }

        $transaction = $newDb->beginTransaction();

        try {
            foreach ($rows as $row) {
                //$createdAt = !empty($row['created']) ? (strtotime($row['created']) ?: time()) : time();
                //$updatedAt = !empty($row['updated']) ? (strtotime($row['updated']) ?: $createdAt) : $createdAt;

                // 新库生成 post.id，再用该 ID 建立 blog.post_id 关联。
                $newDb->createCommand()->insert('{{%post}}', [
                    'user_id' => (int) $userId,
                    'type' => $row['type'],
                    'title' => $row['post_title'],
                    'status' => $row['status'],
                    'visibility' => $row['visibility'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ])->execute();

                $postId = $newDb->getLastInsertID();
                $newDb->createCommand()->insert('{{%blog}}', [
                    'post_id' => $postId,
                    'title' => $row['title'],
                    'summary' => $row['summary'],
                    'content' => $row['content'],
                    'views' => $row['views'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ])->execute();
            }

            $transaction->commit();
            $this->stdout("同步完成，共导入 " . count($rows) . " 条数据\n");
        } catch (Throwable $e) {
            $transaction->rollBack();
            $this->stderr("同步失败：" . $e->getMessage() . "\n");
        }
    }

    /*****
     * 同步 images表 数据。
     * 命令：php yii data-sync/images
     *****/
    public function actionImages()
    {
        $oldDb = Yii::$app->dbOld;
        $newDb = Yii::$app->db;

        // Assign imported posts to the default user in the new database.
        $userId = $newDb->createCommand(
            'SELECT id FROM {{%user}} WHERE username = :username',
            [':username' => 'default']
        )->queryScalar();

        if ($userId === false) {
            $this->stderr("New database does not contain the default user. Create it before syncing images.\n");
            return;
        }

        $this->stdout("Starting image post sync...\n");

        // Load image posts with their metadata from the old database.
        $posts = $oldDb->createCommand(<<<'SQL'
            SELECT
                p.id AS old_post_id,
                p.title AS post_title,
                p.status,
                p.visibility,
                p.created_at AS post_created_at,
                p.updated_at AS post_updated_at
            FROM post AS p
            WHERE p.type = :type
            SQL, [':type' => 'images'])->queryAll();

        if (empty($posts)) {
            $this->stdout("No image posts found in the old database.\n");
            return;
        }

        $oldPostIds = array_column($posts, 'old_post_id');
        $imageRows = (new \yii\db\Query())
            ->select(['post_id', 'title', 'file_path', 'alt_text', 'sort_order', 'created_at', 'updated_at'])
            ->from('images')
            ->where(['post_id' => $oldPostIds])
            ->orderBy(['post_id' => SORT_ASC, 'sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all($oldDb);

        $imagesByPostId = [];
        foreach ($imageRows as $imageRow) {
            $imagesByPostId[$imageRow['post_id']][] = $imageRow;
        }

        $transaction = $newDb->beginTransaction();
        $importedImageCount = 0;

        try {
            foreach ($posts as $postRow) {
                $newDb->createCommand()->insert('{{%post}}', [
                    'user_id' => (int) $userId,
                    'type' => 'images',
                    'title' => $postRow['post_title'],
                    'status' => $postRow['status'],
                    'visibility' => $postRow['visibility'],
                    'created_at' => $postRow['post_created_at'],
                    'updated_at' => $postRow['post_updated_at'],
                ])->execute();

                $newPostId = $newDb->getLastInsertID();
                $postImages = $imagesByPostId[$postRow['old_post_id']] ?? [];

                if (!empty($postImages)) {
                    $batchRows = [];
                    foreach ($postImages as $imageRow) {
                        $batchRows[] = [
                            $newPostId,
                            $imageRow['title'],
                            $imageRow['file_path'],
                            $imageRow['alt_text'],
                            $imageRow['sort_order'],
                            $imageRow['created_at'],
                            $imageRow['updated_at'],
                        ];
                    }

                    $newDb->createCommand()->batchInsert(
                        '{{%images}}',
                        ['post_id', 'title', 'file_path', 'alt_text', 'sort_order', 'created_at', 'updated_at'],
                        $batchRows
                    )->execute();
                    $importedImageCount += count($batchRows);
                }
            }

            $transaction->commit();
            $this->stdout(sprintf(
                "Image sync completed: %d posts and %d images imported.\n",
                count($posts),
                $importedImageCount
            ));
        } catch (Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            $this->stderr("Image sync failed: " . $e->getMessage() . "\n");
        }
    }

    /*****
     * 同步 moment表 数据。
     * 命令：php yii data-sync/moment
     *****/
    public function actionMoment()
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

        $this->stdout("开始同步 Moment表 数据...\n");

        $rows = $oldDb->createCommand(<<<'SQL'
            SELECT m.id, m.content, m.image_paths, m.created_at, m.updated_at, p.type, p.title AS post_title, p.status, p.visibility
            FROM moment AS m 
            LEFT JOIN post AS p ON p.id = m.post_id 
            SQL)->queryAll();

        if (empty($rows)) {
            $this->stdout("旧表中没有数据\n");
            return;
        }

        $transaction = $newDb->beginTransaction();

        try {
            foreach ($rows as $row) {
                //$createdAt = !empty($row['created']) ? (strtotime($row['created']) ?: time()) : time();
                //$updatedAt = !empty($row['updated']) ? (strtotime($row['updated']) ?: $createdAt) : $createdAt;

                // 新库生成 post.id，再用该 ID 建立 blog.post_id 关联。
                $newDb->createCommand()->insert('{{%post}}', [
                    'user_id' => (int) $userId,
                    'type' => 'moment',
                    'title' => $row['post_title'],
                    'status' => $row['status'],
                    'visibility' => $row['visibility'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ])->execute();

                $postId = $newDb->getLastInsertID();
                $newDb->createCommand()->insert('{{%moment}}', [
                    'post_id' => $postId,                    
                    'content' => $row['content'],
                    'image_paths' => $row['image_paths'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
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
