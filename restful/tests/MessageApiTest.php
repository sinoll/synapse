<?php

declare(strict_types=1);

use common\models\Post;
use common\models\UserMessage;
use PHPUnit\Framework\TestCase;
use restful\controllers\MessageController;
use restful\controllers\UsersController;
use restful\exceptions\ValidationException;
use restful\models\Images;
use restful\models\Moment;
use restful\services\ApiToken;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require_once __DIR__ . '/../../common/config/bootstrap.php';
require_once __DIR__ . '/../config/bootstrap.php';

final class MessageApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        new yii\web\Application([
            'id' => 'api-test',
            'basePath' => dirname(__DIR__),
            'components' => [
                'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:'],
                'user' => ['identityClass' => common\models\User::class, 'enableSession' => false],
                'request' => ['cookieValidationKey' => 'test'],
            ],
            'params' => ['api.pageSizeDefault' => 20, 'api.pageSizeMax' => 100, 'api.tokenExpire' => 3600],
        ]);
        $sql = [
            'CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT, nickname TEXT, avatar TEXT, status INTEGER)',
            'CREATE TABLE site_setting (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at INTEGER NOT NULL)',
            'CREATE TABLE user_message (id INTEGER PRIMARY KEY AUTOINCREMENT, sender_id INTEGER NOT NULL, recipient_id INTEGER NOT NULL, type TEXT NOT NULL, content TEXT NOT NULL, source_type TEXT, source_id INTEGER, read_at INTEGER, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, UNIQUE(type, recipient_id, source_type, source_id))',
            'CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, type TEXT, title TEXT, status INTEGER, visibility INTEGER, like_count INTEGER DEFAULT 0, views INTEGER DEFAULT 0, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE moment (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, title TEXT, content TEXT, thumb TEXT, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE images (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, title TEXT, file_path TEXT, alt_text TEXT, sort_order INTEGER, created_at INTEGER, updated_at INTEGER)',
            'CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT)',
            'CREATE TABLE post_tag (post_id INTEGER, tag_id INTEGER)',
            'CREATE TABLE post_like (post_id INTEGER, user_id INTEGER)',
            'CREATE TABLE post_favorite (post_id INTEGER, user_id INTEGER, created_at INTEGER, PRIMARY KEY (user_id, post_id))',
            'CREATE TABLE post_comment (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, comment_id INTEGER, parent_comment_id INTEGER, root_comment_id INTEGER, depth INTEGER, created_at INTEGER)',
        ];
        foreach ($sql as $statement) {
            Yii::$app->db->createCommand($statement)->execute();
        }
        Yii::$app->db->createCommand()->batchInsert('user', ['id', 'username', 'nickname', 'avatar', 'status'], [
            [1, 'alice', 'Alice', null, 10],
            [2, 'bob', 'Bobby', '/uploads/avatar.jpg', 10],
            [3, 'carol', 'Carol', null, 10],
            [4, 'inactive', 'Inactive', null, 9],
        ])->execute();
        Yii::$app->db->createCommand()->insert('site_setting', ['key' => 'api_token_secret', 'value' => 'test-secret', 'updated_at' => time()])->execute();
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

    private function messages(): MessageController
    {
        return new MessageController('message', Yii::$app);
    }

    public function testAuthenticationAndSuggestions(): void
    {
        $controller = new UsersController('users', Yii::$app);
        try {
            $controller->runAction('suggest');
            self::fail('Authentication must be required.');
        } catch (UnauthorizedHttpException) {
            self::assertTrue(true);
        }

        $this->authenticate(1);
        Yii::$app->request->setQueryParams(['q' => 'Bob']);
        $users = $controller->runAction('suggest')['data']['users'];
        self::assertCount(1, $users);
        self::assertSame(2, $users[0]['id']);
        self::assertArrayNotHasKey('email', $users[0]);
        Yii::$app->request->setQueryParams(['q' => ['invalid']]);
        $this->expectException(ValidationException::class);
        $controller->runAction('suggest');
    }

    public function testRoutesAreStrictAndMethodSpecific(): void
    {
        $config = require __DIR__ . '/../config/main.php';
        $manager = new yii\web\UrlManager($config['components']['urlManager']);
        $request = Yii::$app->request;
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        try {
            foreach ([
                ['GET', 'users/suggest', 'users/suggest'],
                ['GET', 'messages', 'message/index'],
                ['POST', 'messages', 'message/create'],
                ['GET', 'messages/unread-count', 'message/unread-count'],
                ['GET', 'messages/1', 'message/view'],
                ['PATCH', 'messages/1/read', 'message/read'],
            ] as [$verb, $path, $route]) {
                $_SERVER['REQUEST_METHOD'] = $verb;
                $request->setPathInfo($path);
                self::assertSame($route, $manager->parseRequest($request)[0]);
            }
            $_SERVER['REQUEST_METHOD'] = 'DELETE';
            $request->setPathInfo('messages/1');
            self::assertFalse($manager->parseRequest($request));
        } finally {
            if ($method === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $method;
            }
        }
    }

    public function testMessagesAndReadAccess(): void
    {
        $this->authenticate(1);
        Yii::$app->request->setBodyParams(['recipient_username' => 'bob', 'content' => '你好']);
        $sent = $this->messages()->runAction('create')['data'];
        self::assertSame('private', $sent['type']);
        self::assertNull($sent['read_at']);
        $id = $sent['id'];
        self::assertSame(1, $this->messages()->runAction('index')['data']['total']);
        self::assertNull($this->messages()->runAction('view', ['id' => $id])['data']['read_at']);
        Yii::$app->request->setQueryParams(['page' => (string) PHP_INT_MAX]);
        self::assertSame([], $this->messages()->runAction('index')['data']['items']);
        Yii::$app->request->setQueryParams([]);

        $this->authenticate(3);
        self::assertSame(0, $this->messages()->runAction('index')['data']['total']);
        try {
            $this->messages()->runAction('view', ['id' => $id]);
            self::fail('Unrelated user must not view a message.');
        } catch (NotFoundHttpException) {
            self::assertNull(UserMessage::findOne($id)->read_at);
        }

        $this->authenticate(1);
        try {
            $this->messages()->runAction('read', ['id' => $id]);
            self::fail('Sender must not mark a message read.');
        } catch (NotFoundHttpException) {
            self::assertNull(UserMessage::findOne($id)->read_at);
        }

        $this->authenticate(2);
        self::assertSame(1, $this->messages()->runAction('unread-count')['data']['unread_count']);
        $read = $this->messages()->runAction('view', ['id' => $id])['data'];
        self::assertNotNull($read['read_at']);
        self::assertSame($read['read_at'], $this->messages()->runAction('read', ['id' => $id])['data']['read_at']);
        self::assertSame(0, $this->messages()->runAction('unread-count')['data']['unread_count']);
    }

    public function testInvalidMessagesAndPagination(): void
    {
        $this->authenticate(1);
        foreach ([
            ['recipient_username' => 'alice', 'content' => 'hello'],
            ['recipient_username' => 'inactive', 'content' => 'hello'],
            ['recipient_username' => 'bob', 'content' => '  '],
            ['recipient_username' => 'bob', 'content' => str_repeat('a', 5001)],
            ['recipient_username' => ['bob'], 'content' => 'hello'],
        ] as $payload) {
            Yii::$app->request->setBodyParams($payload);
            try {
                $this->messages()->runAction('create');
                self::fail('Invalid message must fail.');
            } catch (ValidationException) {
                self::assertSame(0, (int) UserMessage::find()->count());
            }
        }
        Yii::$app->request->setQueryParams(['per_page' => 101]);
        $this->expectException(ValidationException::class);
        $this->messages()->runAction('index');
    }

    public function testMomentMentionsOnlyAfterPublicationAndOnlyOnce(): void
    {
        $post = Moment::create(1, ['content' => '<p>你好 @bob @alice @inactive</p>', 'status' => Post::STATUS_DRAFT]);
        self::assertSame(0, (int) UserMessage::find()->count());
        Moment::update($post['id'], 1, ['status' => Post::STATUS_PUBLISHED]);
        self::assertSame(1, UserMessage::unreadCount(2));
        Moment::update($post['id'], 1, ['content' => '再次 @bob']);
        self::assertSame(1, UserMessage::unreadCount(2));
        $message = UserMessage::find()->one();
        self::assertSame('moment', $message->source_type);
        self::assertSame($post['id'], (int) $message->source_id);

        Moment::create(1, ['content' => '@carol', 'visibility' => Post::VISIBILITY_PRIVATE]);
        self::assertSame(0, UserMessage::unreadCount(3));
    }

    public function testImageMentionsUseStoredCaption(): void
    {
        $post = Images::create(1, [
            'caption' => "相册\n你好 @bob",
            'file_paths' => ['/images/default-cover.png'],
        ]);
        self::assertSame(1, UserMessage::unreadCount(2));
        Images::update($post['id'], 1, ['caption' => "相册\n你好 @carol"]);
        self::assertSame(1, UserMessage::unreadCount(3));
        self::assertSame(2, (int) UserMessage::find()->count());
    }

    public function testAuthenticatedContentEndpointsGenerateMentions(): void
    {
        $this->authenticate(1);
        Yii::$app->request->setBodyParams(['content' => '联系 @bob']);
        $controller = new restful\controllers\MomentController('moment', Yii::$app);
        $post = $controller->runAction('create')['data'];
        self::assertSame(1, UserMessage::unreadCount(2));

        Yii::$app->request->setBodyParams(['content' => '再找 @carol']);
        $controller->runAction('update', ['id' => $post['id']]);
        self::assertSame(1, UserMessage::unreadCount(3));

        $this->authenticate(2);
        $messages = $this->messages()->runAction('index')['data']['items'];
        self::assertSame('mention', $messages[0]['type']);
        self::assertSame($post['id'], $messages[0]['source_id']);
    }

    public function testNotificationFailureRollsBackPost(): void
    {
        Yii::$app->db->open();
        Yii::$app->db->pdo->exec(
            "CREATE TRIGGER reject_message BEFORE INSERT ON user_message BEGIN SELECT RAISE(ABORT, 'notification failed'); END"
        );
        try {
            Moment::create(1, ['content' => '你好 @bob']);
            self::fail('Notification failure must fail the write.');
        } catch (yii\db\Exception) {
            self::assertSame(0, (int) Post::find()->count());
            self::assertSame(0, (int) common\models\Moment::find()->count());
        }
    }
}
