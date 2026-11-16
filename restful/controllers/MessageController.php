<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\User;
use common\models\UserMessage;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use RuntimeException;
use Yii;
use yii\web\NotFoundHttpException;

final class MessageController extends ApiController
{
    public function actionIndex(): array
    {
        $userId = $this->actorId();
        $queryParams = Yii::$app->request->getQueryParams();
        $page = $this->positiveInteger($queryParams, 'page', 1);
        $perPage = $this->positiveInteger($queryParams, 'per_page', (int) Yii::$app->params['api.pageSizeDefault']);
        $max = (int) Yii::$app->params['api.pageSizeMax'];
        if ($perPage > $max) {
            throw new ValidationException(['per_page' => ["per_page 只能在 1 到 $max 之间。"]]);
        }

        $query = UserMessage::find()
            ->where(['or', ['sender_id' => $userId], ['recipient_id' => $userId]]);
        $total = (int) (clone $query)->count();
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $messages = $page > $totalPages ? [] : $query->with(['sender', 'recipient'])
                ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)->all();

        return ApiResponder::ok([
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'items' => array_map(static fn (UserMessage $message): array => self::item($message), $messages),
        ]);
    }

    public function actionCreate(): array
    {
        $userId = $this->actorId();
        $payload = $this->payload();
        $username = $payload['recipient_username'] ?? null;
        $content = $payload['content'] ?? null;
        $errors = [];
        if (!is_string($username) || trim($username) === '' || mb_strlen(trim($username)) > 80) {
            $errors['recipient_username'] = ['请填写不超过 80 个字符的收件人用户名。'];
        }
        if (!is_string($content) || trim($content) === '' || mb_strlen($content) > 5000) {
            $errors['content'] = ['请填写不超过 5000 个字符的消息内容。'];
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $recipient = User::findByUsername(trim($username));
        if ($recipient === null) {
            throw new ValidationException(['recipient_username' => ['请输入有效的收件人用户名。']]);
        }
        if ((int) $recipient->id === $userId) {
            throw new ValidationException(['recipient_username' => ['不能给自己发送私信。']]);
        }

        $message = new UserMessage();
        $message->sender_id = $userId;
        $message->recipient_id = (int) $recipient->id;
        $message->type = UserMessage::TYPE_PRIVATE;
        $message->content = $content;
        if (!$message->validate()) {
            throw new ValidationException($message->getErrors());
        }
        if (!$message->save(false)) {
            throw new RuntimeException('私信保存失败。');
        }

        return ApiResponder::ok(self::item($message), '私信已发送。');
    }

    public function actionView(int $id): array
    {
        $userId = $this->actorId();
        $message = $this->findMessage($id, $userId);
        if ((int) $message->recipient_id === $userId) {
            $this->markRead($message, $userId);
        }

        return ApiResponder::ok(self::item($message));
    }

    public function actionRead(int $id): array
    {
        $userId = $this->actorId();
        $message = UserMessage::find()->where(['id' => $id, 'recipient_id' => $userId])
            ->with(['sender', 'recipient'])->one();
        if ($message === null) {
            throw new NotFoundHttpException('找不到这条消息。');
        }
        $this->markRead($message, $userId);

        return ApiResponder::ok(self::item($message));
    }

    public function actionUnreadCount(): array
    {
        return ApiResponder::ok(['unread_count' => UserMessage::unreadCount($this->actorId())]);
    }

    private function findMessage(int $id, int $userId): UserMessage
    {
        $message = UserMessage::find()->where(['id' => $id])
            ->andWhere(['or', ['sender_id' => $userId], ['recipient_id' => $userId]])
            ->with(['sender', 'recipient'])->one();
        if ($message === null) {
            throw new NotFoundHttpException('找不到这条消息。');
        }

        return $message;
    }

    private function markRead(UserMessage $message, int $userId): void
    {
        if ($message->read_at === null) {
            UserMessage::updateAll(['read_at' => time()], [
                'id' => $message->id, 'recipient_id' => $userId, 'read_at' => null,
            ]);
            if (!$message->refresh()) {
                throw new NotFoundHttpException('找不到这条消息。');
            }
        }
    }

    private static function item(UserMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'type' => $message->type,
            'content' => $message->content,
            'sender' => UsersController::summary($message->sender),
            'recipient' => UsersController::summary($message->recipient),
            'source_type' => $message->source_type,
            'source_id' => $message->source_id === null ? null : (int) $message->source_id,
            'read_at' => $message->read_at === null ? null : ApiResponder::timestamp((int) $message->read_at),
            'created_at' => ApiResponder::timestamp((int) $message->created_at),
        ];
    }

    private function positiveInteger(array $query, string $key, int $default): int
    {
        if (!array_key_exists($key, $query)) {
            return $default;
        }
        $value = filter_var($query[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new ValidationException([$key => [$key . ' 必须是正整数。']]);
        }

        return $value;
    }
}
