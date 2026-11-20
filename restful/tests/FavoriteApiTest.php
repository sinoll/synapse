<?php

declare(strict_types=1);

use common\models\Post;
use common\models\PostFavorite;
use PHPUnit\Framework\TestCase;
use restful\controllers\FavoriteController;
use restful\models\Blog;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require_once __DIR__ . '/../../common/config/bootstrap.php';
require_once __DIR__ . '/../config/bootstrap.php';

final class FavoriteApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        new yii\web\Application([
            'id' => 'api-favorite-test',
            'basePath' => dirname(__DIR__),
            'components' => [
                'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
                'user' => ['identityClass' => common\models\User::class, 'enableSession' => false],
                'request' => ['cookieValidationKey' => 'test'],
            ],
            'params' => [
                'api.pageSizeDefault' => 20,
                'api.pageSizeMax' => 100,
                'thumbnail.width' => 460,
                'thumbnail.height' => 260,
            ],
        ]);
        foreach ([
            'CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT, nickname TEXT, avatar TEXT, status INTEGER, password_hash TEXT, auth_key TEXT)',
            'CREATE TABLE post (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, status INTEGER, visibility INTEGER, like_count INTEGER, views INTEGER, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE blog (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, summary TEXT, content TEXT, thumb TEXT)',
            'CREATE TABLE moment (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, content TEXT, thumb TEXT)',
            'CREATE TABLE short_video (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, description TEXT, video_path TEXT, cover_path TEXT, duration_seconds INTEGER, video_type INTEGER)',
            'CREATE TABLE images (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, alt_text TEXT, file_path TEXT, sort_order INTEGER, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE tag (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)',
            'CREATE TABLE post_tag (post_id INTEGER, tag_id INTEGER)',
            'CREATE TABLE cate (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)',
            'CREATE TABLE post_cate (post_id INTEGER, cate_id INTEGER)',
            'CREATE TABLE post_like (post_id INTEGER, user_id INTEGER, created_at INTEGER)',
            'CREATE TABLE post_favorite (post_id INTEGER, user_id INTEGER, created_at INTEGER, PRIMARY KEY (user_id, post_id))',
            'CREATE TABLE post_comment (id INTEGER PRIMARY KEY, post_id INTEGER)',
        ] as $sql) {
            Yii::$app->db->createCommand($sql)->execute();
        }
        $db = Yii::$app->db;
        $db->createCommand()->insert('user', ['id' => 1, 'username' => 'alice', 'nickname' => 'Alice', 'status' => 10])->execute();
        $db->createCommand()->batchInsert('post', ['id', 'user_id', 'type', 'title', 'status', 'visibility', 'like_count', 'views', 'created_at', 'updated_at'], [
            [1, 1, 'blog', 'Public blog', 1, 2, 0, 0, 100, 100],
            [2, 1, 'blog', 'Private blog', 1, 0, 0, 0, 200, 200],
            [3, 1, 'blog', 'Other private blog', 1, 0, 0, 0, 300, 300],
        ])->execute();
        $db->createCommand()->batchInsert('blog', ['id', 'post_id', 'title', 'summary', 'content', 'thumb'], [
            [1, 1, 'Public blog', 'summary', 'content', null],
            [2, 2, 'Private blog', 'summary', 'content', null],
            [3, 3, 'Other private blog', 'summary', 'content', null],
        ])->execute();
        $db->createCommand()->update('post', ['user_id' => 2], ['id' => 3])->execute();
        Yii::$app->user->setIdentity(common\models\User::findOne(1));
    }

    protected function tearDown(): void
    {
        Yii::$app->errorHandler->unregister();
        Yii::$app = null;
        parent::tearDown();
    }

    public function testFavoriteToggleAndList(): void
    {
        $added = Blog::favorite(1, 1);
        self::assertSame(['post_id' => 1, 'favorited' => true], $added);
        self::assertTrue(Blog::item(Post::findOne(1), 1)['favorited']);

        Yii::$app->request->setQueryParams(['page' => '1', 'per_page' => '10']);
        $result = (new FavoriteController('favorite', Yii::$app))->actionIndex()['data'];
        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['items'][0]['id']);
        self::assertTrue($result['items'][0]['favorited']);
        self::assertNotNull($result['items'][0]['favorited_at']);

        self::assertSame(['post_id' => 1, 'favorited' => false], Blog::favorite(1, 1));
        self::assertSame(0, (int) PostFavorite::find()->count());
    }

    public function testPrivateFavoriteVisibilityAndTypeFilter(): void
    {
        Blog::favorite(2, 1);
        $this->expectException(yii\web\NotFoundHttpException::class);
        Blog::favorite(3, 1);
    }

    public function testRemoveIsIdempotent(): void
    {
        Blog::favorite(1, 1);
        $controller = new FavoriteController('favorite', Yii::$app);
        self::assertFalse($controller->actionRemove(1)['data']['favorited']);
        self::assertFalse($controller->actionRemove(1)['data']['favorited']);
        self::assertSame(0, (int) PostFavorite::find()->count());
    }
}
