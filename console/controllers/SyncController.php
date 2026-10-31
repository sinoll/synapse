<?php

namespace console\controllers;

use common\models\Post;
use Throwable;
use Yii;
use yii\console\Controller;

class SyncController extends Controller
{
    public function actionIndex()
    {
        $this->stdout("控制器工作正常\n"."从旧数据库恢复数据几个函数\n");
    }

    /*****
     * 同步 旧blog表 文章数据。
     * 命令：php yii sync/blog
     *****/
    public function actionBlog()
    {
        $oldDb = Yii::$app->dbBlog;
        $newDb = Yii::$app->db;

        $this->stdout("开始同步 blog表 数据...\n");

        $rows = $oldDb->createCommand("
            SELECT id, uid, cateid, title, tags, status, postdesc, thumb, body,
                   viewCount, commentCount, created, updated, isRecommend
            FROM blog
        ")->queryAll();

        if (empty($rows)) {
            $this->stdout("旧表中没有数据\n");
            return;
        }

        $transaction = $newDb->beginTransaction();
        $imported = 0;
        $skipped = 0;

        $oldUids = array_unique(array_column($rows, 'uid'));
        $uidMap = [];
        foreach ($oldUids as $uid) {
            $uidMap[$uid] = $newDb->createCommand(
                "SELECT id FROM {{%user}} WHERE id = :id",
                [':id' => $uid]
            )->queryOne() ? $uid : 1;
        }

        try {
            foreach ($rows as $row) {
                $createdAt = !empty($row['created']) ? (strtotime($row['created']) ?: time()) : time();
                $updatedAt = !empty($row['updated']) ? (strtotime($row['updated']) ?: $createdAt) : $createdAt;

                $newUid = $uidMap[$row['uid']];

                $exists = $newDb->createCommand(
                    "SELECT id FROM {{%post}} WHERE type = :type AND user_id = :uid AND title = :title",
                    [':type' => Post::TYPE_BLOG, ':uid' => $newUid, ':title' => $row['title']]
                )->queryOne();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $newDb->createCommand()->insert('{{%post}}', [
                    'user_id' => $newUid,
                    'type' => Post::TYPE_BLOG,
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
                    'thumb' => $row['thumb'],
                    'content' => $row['body'],
                    'views' => $row['viewCount'],
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ])->execute();

                $imported++;
            }

            $transaction->commit();
            $this->stdout("同步完成，导入 {$imported} 条，跳过 {$skipped} 条（已存在）\n");
        } catch (Throwable $e) {
            $transaction->rollBack();
            $this->stderr("同步失败：" . $e->getMessage() . "\n");
        }
    }

    /*****
     * 同步 旧数据 blogtag 表并与文章关联。
     * 旧库 blog.tags 字段存储逗号分隔的 blogtag.id。
     * 命令：php yii sync/tag
     *****/
    public function actionTag()
    {
        $oldDb = Yii::$app->dbBlog;
        $newDb = Yii::$app->db;

        $this->stdout("开始同步 tag 数据...\n");

        $oldTags = $oldDb->createCommand("SELECT id, name, alias FROM blogtag")->queryAll();
        $oldBlogs = $oldDb->createCommand("SELECT id, uid, title, tags FROM blog")->queryAll();

        if (empty($oldTags)) {
            $this->stdout("旧表中没有标签数据\n");
            return;
        }

        $transaction = $newDb->beginTransaction();

        try {
            $oldTagIdToNewTagId = [];
            $usedSlugs = [];
            foreach ($oldTags as $tag) {
                $existing = $newDb->createCommand(
                    "SELECT id FROM {{%tag}} WHERE name = :name",
                    [':name' => $tag['name']]
                )->queryOne();

                if ($existing) {
                    $oldTagIdToNewTagId[$tag['id']] = $existing['id'];
                    continue;
                }

                $slug = $tag['alias'] ?: ('tag-' . substr(md5($tag['name']), 0, 12));
                $baseSlug = $slug;
                $suffix = 1;
                while (isset($usedSlugs[$slug])) {
                    $slug = $baseSlug . '-' . $suffix++;
                }

                $slugExists = $newDb->createCommand(
                    "SELECT id FROM {{%tag}} WHERE slug = :slug",
                    [':slug' => $slug]
                )->queryOne();
                while ($slugExists) {
                    $slug = $baseSlug . '-' . $suffix++;
                    $slugExists = $newDb->createCommand(
                        "SELECT id FROM {{%tag}} WHERE slug = :slug",
                        [':slug' => $slug]
                    )->queryOne();
                }

                $now = time();
                $newDb->createCommand()->insert('{{%tag}}', [
                    'name' => $tag['name'],
                    'slug' => $slug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
                $usedSlugs[$slug] = true;
                $oldTagIdToNewTagId[$tag['id']] = $newDb->getLastInsertID();
            }

            $oldUids = array_unique(array_column($oldBlogs, 'uid'));
            $uidMap = [];
            foreach ($oldUids as $uid) {
                $uidMap[$uid] = $newDb->createCommand(
                    "SELECT id FROM {{%user}} WHERE id = :id",
                    [':id' => $uid]
                )->queryOne() ? $uid : 1;
            }

            $oldBlogIdToNewPostId = [];
            foreach ($oldBlogs as $blog) {
                $newUid = $uidMap[$blog['uid']];
                $newPost = $newDb->createCommand(
                    "SELECT id FROM {{%post}} WHERE type = :type AND user_id = :uid AND title = :title",
                    [':type' => Post::TYPE_BLOG, ':uid' => $newUid, ':title' => $blog['title']]
                )->queryOne();
                if ($newPost) {
                    $oldBlogIdToNewPostId[$blog['id']] = $newPost['id'];
                }
            }

            $imported = 0;
            $skipped = 0;
            foreach ($oldBlogs as $blog) {
                if (!isset($oldBlogIdToNewPostId[$blog['id']])) {
                    continue;
                }
                $newPostId = $oldBlogIdToNewPostId[$blog['id']];
                $tagIds = array_filter(array_map('intval', explode(',', (string) $blog['tags'])));

                foreach ($tagIds as $oldTagId) {
                    if (!isset($oldTagIdToNewTagId[$oldTagId])) {
                        $skipped++;
                        continue;
                    }
                    $newTagId = $oldTagIdToNewTagId[$oldTagId];

                    $exists = $newDb->createCommand(
                        "SELECT 1 FROM {{%post_tag}} WHERE post_id = :pid AND tag_id = :tid",
                        [':pid' => $newPostId, ':tid' => $newTagId]
                    )->queryOne();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $newDb->createCommand()->insert('{{%post_tag}}', [
                        'post_id' => $newPostId,
                        'tag_id' => $newTagId,
                    ])->execute();
                    $imported++;
                }
            }

            $transaction->commit();
            $this->stdout("同步完成，标签映射 " . count($oldTagIdToNewTagId) . " 个，关联导入 {$imported} 条，跳过 {$skipped} 条\n");
        } catch (Throwable $e) {
            $transaction->rollBack();
            $this->stderr("同步失败：" . $e->getMessage() . "\n");
        }
    }

    /*****
     * 同步 旧数据 blogcate 表并与文章关联。
     * 旧库 blog.cateid 字段存储 blogcate.id。
     * 命令：php yii sync/cate
     *****/
    public function actionCate()
    {
        $oldDb = Yii::$app->dbBlog;
        $newDb = Yii::$app->db;

        $this->stdout("开始同步 cate 数据...\n");

        $oldCates = $oldDb->createCommand("SELECT id, name, alias, thumb, catedesc FROM blogcate")->queryAll();
        $oldBlogs = $oldDb->createCommand("SELECT id, uid, title, cateid FROM blog")->queryAll();

        if (empty($oldCates)) {
            $this->stdout("旧表中没有分类数据\n");
            return;
        }

        $transaction = $newDb->beginTransaction();

        try {
            $oldCateIdToNewCateId = [];
            $usedSlugs = [];
            foreach ($oldCates as $cate) {
                $existing = $newDb->createCommand(
                    "SELECT id FROM {{%cate}} WHERE name = :name",
                    [':name' => $cate['name']]
                )->queryOne();

                if ($existing) {
                    $oldCateIdToNewCateId[$cate['id']] = $existing['id'];
                    continue;
                }

                $slug = $cate['alias'] ?: ('cate-' . substr(md5($cate['name']), 0, 12));
                $baseSlug = $slug;
                $suffix = 1;
                while (isset($usedSlugs[$slug])) {
                    $slug = $baseSlug . '-' . $suffix++;
                }

                $slugExists = $newDb->createCommand(
                    "SELECT id FROM {{%cate}} WHERE slug = :slug",
                    [':slug' => $slug]
                )->queryOne();
                while ($slugExists) {
                    $slug = $baseSlug . '-' . $suffix++;
                    $slugExists = $newDb->createCommand(
                        "SELECT id FROM {{%cate}} WHERE slug = :slug",
                        [':slug' => $slug]
                    )->queryOne();
                }

                $now = time();
                $newDb->createCommand()->insert('{{%cate}}', [
                    'name' => $cate['name'],
                    'slug' => $slug,
                    'description' => $cate['catedesc'],
                    'thumb' => $cate['thumb'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
                $usedSlugs[$slug] = true;
                $oldCateIdToNewCateId[$cate['id']] = $newDb->getLastInsertID();
            }

            $oldUids = array_unique(array_column($oldBlogs, 'uid'));
            $uidMap = [];
            foreach ($oldUids as $uid) {
                $uidMap[$uid] = $newDb->createCommand(
                    "SELECT id FROM {{%user}} WHERE id = :id",
                    [':id' => $uid]
                )->queryOne() ? $uid : 1;
            }

            $oldBlogIdToNewPostId = [];
            foreach ($oldBlogs as $blog) {
                $newUid = $uidMap[$blog['uid']];
                $newPost = $newDb->createCommand(
                    "SELECT id FROM {{%post}} WHERE type = :type AND user_id = :uid AND title = :title",
                    [':type' => Post::TYPE_BLOG, ':uid' => $newUid, ':title' => $blog['title']]
                )->queryOne();
                if ($newPost) {
                    $oldBlogIdToNewPostId[$blog['id']] = $newPost['id'];
                }
            }

            $imported = 0;
            $skipped = 0;
            foreach ($oldBlogs as $blog) {
                if (!isset($oldBlogIdToNewPostId[$blog['id']])) {
                    continue;
                }
                if (empty($blog['cateid'])) {
                    continue;
                }
                $newPostId = $oldBlogIdToNewPostId[$blog['id']];
                $oldCateId = (int) $blog['cateid'];

                if (!isset($oldCateIdToNewCateId[$oldCateId])) {
                    $skipped++;
                    continue;
                }
                $newCateId = $oldCateIdToNewCateId[$oldCateId];

                $exists = $newDb->createCommand(
                    "SELECT 1 FROM {{%post_cate}} WHERE post_id = :pid AND cate_id = :cid",
                    [':pid' => $newPostId, ':cid' => $newCateId]
                )->queryOne();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $newDb->createCommand()->insert('{{%post_cate}}', [
                    'post_id' => $newPostId,
                    'cate_id' => $newCateId,
                ])->execute();
                $imported++;
            }

            $transaction->commit();
            $this->stdout("同步完成，分类映射 " . count($oldCateIdToNewCateId) . " 个，关联导入 {$imported} 条，跳过 {$skipped} 条\n");
        } catch (Throwable $e) {
            $transaction->rollBack();
            $this->stderr("同步失败：" . $e->getMessage() . "\n");
        }
    }

}