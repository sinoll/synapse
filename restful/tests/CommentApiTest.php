<?php

declare(strict_types=1);

use common\models\Post;
use restful\controllers\CommentController;
use restful\exceptions\ValidationException;
use restful\services\ApiToken;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require_once __DIR__ . '/../../common/config/bootstrap.php';
require_once __DIR__ . '/../config/bootstrap.php';

final class CommentApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        new yii\web\Application([
            'id' => 'api-comment-test',
            'basePath' => dirname(__DIR__),
            'components' => [
                'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
                'user' => ['identityClass' => common\models\User::class, 'enableSession' => false],
                'request' => ['cookieValidationKey' => 'test'],
            ],
            'params' => ['api.pageSizeDefault' => 20, 'api.pageSizeMax' => 100, 'api.tokenExpire' => 3600],
        ]);
        foreach ([
            'CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT, nickname TEXT, avatar TEXT, status INTEGER, is_admin INTEGER DEFAULT 0)',
            'CREATE TABLE site_setting (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at INTEGER NOT NULL)',
            'CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, type TEXT, title TEXT, status INTEGER, visibility INTEGER, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE comment (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, content TEXT, status INTEGER, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE post_comment (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, comment_id INTEGER, parent_comment_id INTEGER, root_comment_id INTEGER, depth INTEGER, created_at INTEGER)',
        ] as $statement) {
            Yii::$app->db->createCommand($statement)->execute();
        }
        Yii::$app->db->createCommand()->batchInsert('user', ['id', 'username', 'nickname', 'avatar', 'status', 'is_admin'], [
            [1, 'alice', 'Alice', null, 10, 0],
            [2, 'bob', 'Bob', null, 10, 0],
        ])->execute();
        Yii::$app->db->createCommand()->insert('site_setting', ['key' => 'api_token_secret', 'value' => 'test-secret', 'updated_at' => time()])->execute();
        Yii::$app->db->createCommand()->batchInsert('post', ['id', 'user_id', 'type', 'title', 'status', 'visibility', 'created_at', 'updated_at'], [
            [1, 1, Post::TYPE_BLOG, 'Public', Post::STATUS_PUBLISHED, Post::VISIBILITY_PUBLIC, time(), time()],
            [2, 1, Post::TYPE_BLOG, 'Private', Post::STATUS_PUBLISHED, Post::VISIBILITY_PRIVATE, time(), time()],
        ])->execute();
    }

    protected function tearDown(): void
    {
        Yii::$app->errorHandler->unregister();
        Yii::$app = null;
        parent::tearDown();
    }

    private function authenticate(int $id): void
    {
        Yii::$app->user->setIdentity(null);
        Yii::$app->request->headers->set('Authorization', 'Bearer ' . ApiToken::issue($id));
    }

    private function controller(): CommentController
    {
        return new CommentController('comment', Yii::$app);
    }

    public function testCreatesAndListsCommentTree(): void
    {
        $this->authenticate(1);
        Yii::$app->request->setBodyParams(['post_id' => 1, 'content' => ' 根评论 ']);
        $root = $this->controller()->runAction('create')['data'];
        self::assertSame('根评论', $root['content']);
        self::assertSame(0, $root['depth']);
        self::assertNull($root['parent_id']);

        $this->authenticate(2);
        Yii::$app->request->setBodyParams(['post_id' => 1, 'parent_id' => $root['id'], 'content' => '回复']);
        $reply = $this->controller()->runAction('create')['data'];
        self::assertSame(1, $reply['depth']);
        self::assertSame('alice', $reply['reply_to']['username']);

        Yii::$app->request->setQueryParams(['post_id' => '1']);
        $page = $this->controller()->runAction('index')['data'];
        self::assertSame(1, $page['total']);
        self::assertSame(1, $page['items'][0]['reply_count']);
        self::assertSame($reply['id'], $page['items'][0]['replies'][0]['id']);

        Yii::$app->request->setQueryParams(['page' => '1']);
        $replyPage = $this->controller()->runAction('replies', ['id' => $root['id']])['data'];
        self::assertSame(1, $replyPage['total']);
        self::assertSame($reply['id'], $replyPage['items'][0]['id']);
    }

    public function testRequiresAuthenticationAndProtectsInvisiblePosts(): void
    {
        Yii::$app->request->headers->remove('Authorization');
        Yii::$app->request->setBodyParams(['post_id' => 1, 'content' => '游客评论']);
        try {
            $this->controller()->runAction('create');
            self::fail('Creating a comment must require authentication.');
        } catch (UnauthorizedHttpException) {
            self::assertTrue(true);
        }

        Yii::$app->request->setQueryParams(['post_id' => '2']);
        try {
            $this->controller()->runAction('index');
            self::fail('Private content must not be visible to guests.');
        } catch (NotFoundHttpException) {
            self::assertTrue(true);
        }
    }

    public function testRejectsCrossPostRepliesAndInvalidContent(): void
    {
        $this->authenticate(1);
        Yii::$app->request->setBodyParams(['post_id' => 1, 'content' => '评论']);
        $root = $this->controller()->runAction('create')['data'];

        Yii::$app->request->setBodyParams(['post_id' => 2, 'parent_id' => $root['id'], 'content' => '跨内容回复']);
        try {
            $this->controller()->runAction('create');
            self::fail('Replies to another post must be rejected.');
        } catch (NotFoundHttpException) {
            self::assertTrue(true);
        }

        Yii::$app->request->setBodyParams(['post_id' => 1, 'content' => str_repeat('长', 501)]);
        $this->expectException(ValidationException::class);
        $this->controller()->runAction('create');
    }

    public function testCommentRoutesAndDeletionPermissions(): void
    {
        $config = require __DIR__ . '/../config/main.php';
        $manager = new yii\web\UrlManager($config['components']['urlManager']);
        $request = Yii::$app->request;
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        try {
            foreach ([
                ['GET', 'comments', 'comment/index'],
                ['POST', 'comments', 'comment/create'],
                ['GET', 'comments/12/replies', 'comment/replies'],
                ['DELETE', 'comments/12', 'comment/delete'],
            ] as [$verb, $path, $route]) {
                $_SERVER['REQUEST_METHOD'] = $verb;
                $request->setPathInfo($path);
                self::assertSame($route, $manager->parseRequest($request)[0]);
            }
        } finally {
            if ($method === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $method;
            }
        }

        $this->authenticate(1);
        Yii::$app->request->setBodyParams(['post_id' => 1, 'content' => '作者评论']);
        $comment = $this->controller()->runAction('create')['data'];

        $this->authenticate(2);
        try {
            $this->controller()->runAction('delete', ['id' => $comment['id']]);
            self::fail('An unrelated user must not delete a comment.');
        } catch (ForbiddenHttpException) {
            self::assertTrue(true);
        }

        $this->authenticate(1);
        $deleted = $this->controller()->runAction('delete', ['id' => $comment['id']])['data'];
        self::assertSame(1, $deleted['deleted_count']);
    }
}
