<?php

declare(strict_types=1);

use common\models\Post;
use common\services\PostSearch;
use PHPUnit\Framework\TestCase;
use restful\controllers\SearchController;
use restful\exceptions\ValidationException;
use restful\models\Blog;
use restful\services\ApiToken;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require_once __DIR__ . '/../../common/config/bootstrap.php';
require_once __DIR__ . '/../config/bootstrap.php';

final class SearchApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        new yii\web\Application([
            'id' => 'api-search-test',
            'basePath' => dirname(__DIR__),
            'components' => [
                'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
                'user' => ['identityClass' => common\models\User::class, 'enableSession' => false],
                'request' => ['cookieValidationKey' => 'test'],
            ],
            'params' => [
                'api.pageSizeDefault' => 20, 'api.pageSizeMax' => 100, 'api.tokenExpire' => 3600,
                'shortVideo.videoTypeOptions' => [1 => 'Horizontal', 2 => 'Vertical'],
            ],
        ]);
        foreach ([
            'CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT, nickname TEXT, avatar TEXT, status INTEGER)',
            'CREATE TABLE site_setting (key TEXT PRIMARY KEY, value TEXT, updated_at INTEGER)',
            'CREATE TABLE post (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, status INTEGER, visibility INTEGER, like_count INTEGER, views INTEGER, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE blog (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, summary TEXT, content TEXT, thumb TEXT)',
            'CREATE TABLE moment (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, content TEXT, thumb TEXT)',
            'CREATE TABLE short_video (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, description TEXT, video_path TEXT, cover_path TEXT, duration_seconds INTEGER, video_type INTEGER)',
            'CREATE TABLE images (id INTEGER PRIMARY KEY, post_id INTEGER, title TEXT, alt_text TEXT, file_path TEXT, sort_order INTEGER, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE tag (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)',
            'CREATE TABLE post_tag (post_id INTEGER, tag_id INTEGER)',
            'CREATE TABLE cate (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)',
            'CREATE TABLE post_cate (post_id INTEGER, cate_id INTEGER)',
            'CREATE TABLE post_like (post_id INTEGER, user_id INTEGER)',
            'CREATE TABLE post_comment (id INTEGER PRIMARY KEY, post_id INTEGER, comment_id INTEGER, parent_comment_id INTEGER, root_comment_id INTEGER, depth INTEGER, created_at INTEGER)',
        ] as $sql) {
            Yii::$app->db->createCommand($sql)->execute();
        }
        $db = Yii::$app->db;
        $db->createCommand()->batchInsert('user', ['id', 'username', 'nickname', 'avatar', 'status'], [
            [1, 'alice', 'Alice', null, 10], [2, 'bob', 'Bob', null, 10],
        ])->execute();
        $db->createCommand()->insert('site_setting', ['key' => 'api_token_secret', 'value' => 'test-secret', 'updated_at' => 1])->execute();
        $db->createCommand()->batchInsert('post', ['id', 'user_id', 'type', 'title', 'status', 'visibility', 'like_count', 'views', 'created_at', 'updated_at'], [
            [1, 1, 'blog', 'Blog', 1, 2, 0, 0, 1, 1],
            [2, 1, 'moment', 'Moment', 1, 2, 0, 0, 2, 2],
            [3, 1, 'images', 'Gallery', 1, 2, 0, 0, 3, 3],
            [4, 1, 'short_video', 'Video', 1, 2, 0, 0, 4, 4],
            [5, 1, 'moment', 'needle registered', 1, 1, 0, 0, 5, 5],
            [6, 1, 'moment', 'needle private', 1, 0, 0, 0, 6, 6],
            [7, 2, 'moment', 'needle other private', 1, 0, 0, 0, 7, 7],
            [8, 1, 'moment', 'needle draft', 0, 2, 0, 0, 8, 8],
        ])->execute();
        $db->createCommand()->insert('blog', ['id' => 1, 'post_id' => 1, 'title' => 'Blog', 'summary' => 'summary-key', 'content' => 'body-key', 'thumb' => null])->execute();
        $db->createCommand()->batchInsert('moment', ['id', 'post_id', 'title', 'content', 'thumb'], [
            [1, 2, 'Moment', 'moment-key', null], [2, 5, 'registered', 'needle', null],
            [3, 6, 'private', 'needle', null], [4, 7, 'private', 'needle', null], [5, 8, 'draft', 'needle', null],
        ])->execute();
        $db->createCommand()->insert('short_video', ['id' => 1, 'post_id' => 4, 'title' => 'Video', 'description' => 'video-key', 'video_path' => '/uploads/video/x.mp4', 'cover_path' => null, 'duration_seconds' => 5, 'video_type' => 1])->execute();
        $db->createCommand()->batchInsert('images', ['id', 'post_id', 'title', 'alt_text', 'file_path', 'sort_order', 'created_at', 'updated_at'], [
            [1, 3, 'gallery-key', 'alt-key', '/uploads/images/a.jpg', 0, 1, 1],
            [2, 3, 'gallery-key', 'alt-key', '/uploads/images/b.jpg', 1, 1, 1],
        ])->execute();
        $db->createCommand()->batchInsert('tag', ['id', 'name', 'slug'], [[1, 'tag-key', 'a'], [2, 'tag-key-two', 'b']])->execute();
        $db->createCommand()->batchInsert('post_tag', ['post_id', 'tag_id'], [[3, 1], [3, 2]])->execute();
        $db->createCommand()->insert('cate', ['id' => 1, 'name' => 'category-key', 'slug' => 'category'])->execute();
        $db->createCommand()->insert('post_cate', ['post_id' => 1, 'cate_id' => 1])->execute();
        $db->createCommand()->insert('post_like', ['post_id' => 2, 'user_id' => 1])->execute();
        $db->createCommand()->batchInsert('post_comment', ['id', 'post_id', 'comment_id', 'parent_comment_id', 'root_comment_id', 'depth', 'created_at'], [
            [1, 2, 1, null, null, 0, 1],
            [2, 2, 2, 1, 1, 1, 2],
        ])->execute();
    }

    protected function tearDown(): void
    {
        Yii::$app->errorHandler->unregister();
        Yii::$app = null;
        parent::tearDown();
    }

    private function search(array $params): array
    {
        Yii::$app->request->setQueryParams($params);

        return (new SearchController('search', Yii::$app))->runAction('index')['data'];
    }

    private function login(int $id): void
    {
        Yii::$app->user->setIdentity(null);
        Yii::$app->request->headers->set('Authorization', 'Bearer ' . ApiToken::issue($id));
    }

    public function testMatchingFieldsAndTypes(): void
    {
        foreach ([
            'summary-key' => 1, 'body-key' => 1, 'category-key' => 1,
            'moment-key' => 2, 'gallery-key' => 3, 'alt-key' => 3, 'tag-key' => 3, 'video-key' => 4,
            'Blog' => 1,
        ] as $keyword => $id) {
            $result = $this->search(['q' => $keyword]);
            self::assertSame(1, $result['total'], $keyword);
            self::assertSame($id, $result['items'][0]['id']);
        }
        self::assertSame(4, $this->search(['q' => 'alice'])['total']);
        self::assertSame(4, $this->search(['q' => 'Alice'])['total']);
        self::assertSame(4, $this->search(['q' => 'video-key', 'type' => 'video'])['items'][0]['id']);
        self::assertSame('short_video', $this->search(['q' => 'video-key', 'type' => 'short_video'])['type']);
        self::assertSame(0, $this->search(['q' => 'video-key', 'type' => 'moment'])['total']);
        self::assertSame(0, $this->search(['q' => ''])['total']);
        self::assertSame(0, $this->search(['q' => '%'])['total']);
        self::assertSame(0, $this->search(['q' => '_'])['total']);
        self::assertSame(str_repeat('x', 60), $this->search(['q' => str_repeat('x', 61)])['q']);
    }

    public function testVisibilityPaginationAndLiked(): void
    {
        self::assertSame(0, $this->search(['q' => 'needle'])['total']);
        $this->login(1);
        $result = $this->search(['q' => 'needle', 'per_page' => 1]);
        self::assertSame(2, $result['total']);
        self::assertSame(2, $result['total_pages']);
        self::assertSame(6, $result['items'][0]['id']);
        self::assertSame(5, $this->search(['q' => 'needle', 'per_page' => 1, 'page' => 2])['items'][0]['id']);
        self::assertSame([], $this->search(['q' => 'needle', 'page' => (string) PHP_INT_MAX])['items']);
        self::assertTrue($this->search(['q' => 'moment-key'])['items'][0]['liked']);

        $this->login(2);
        $result = $this->search(['q' => 'needle']);
        self::assertSame([7, 5], array_column($result['items'], 'id'));
        self::assertTrue($result['authenticated']);
    }

    public function testContentResponsesIncludeCommentCount(): void
    {
        self::assertSame(0, $this->search(['q' => 'body-key'])['items'][0]['comment_count']);
        self::assertSame(2, $this->search(['q' => 'moment-key'])['items'][0]['comment_count']);

        $page = Blog::page([], null);
        self::assertSame(0, $page['items'][0]['comment_count']);
        self::assertSame(0, Blog::item(Post::findOne(1), null, true)['comment_count']);
    }

    public function testSharedQueryDeduplicatesAndWebsitePaginationMatches(): void
    {
        $query = PostSearch::query('tag-key', null, null);
        $provider = frontend\components\ListPagination::provider($query, 1);
        self::assertSame(1, $provider->getTotalCount());
        self::assertCount(1, $provider->getModels());
        self::assertSame(3, (int) $provider->getModels()[0]->id);
        self::assertSame(1, $this->search(['q' => 'tag-key', 'per_page' => 1])['total']);
    }

    public function testInvalidParametersAreRejected(): void
    {
        foreach ([['q' => []], ['type' => []], ['type' => 'unknown'], ['page' => 0], ['page' => 'abc'], ['per_page' => 101]] as $params) {
            try {
                $this->search($params);
                self::fail('Invalid parameters must fail.');
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testStrictRoute(): void
    {
        $config = require __DIR__ . '/../config/main.php';
        $manager = new yii\web\UrlManager($config['components']['urlManager']);
        $request = Yii::$app->request;
        $request->setPathInfo('search');
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            self::assertSame('search/index', $manager->parseRequest($request)[0]);
            $_SERVER['REQUEST_METHOD'] = 'POST';
            self::assertFalse($manager->parseRequest($request));
        } finally {
            if ($method === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $method;
            }
        }
    }
}
