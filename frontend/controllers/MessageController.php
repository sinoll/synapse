<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\User;
use common\models\UserMessage;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class MessageController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['@']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['send' => ['GET', 'POST']],
            ],
        ];
    }

    public function actionView(int $id): string
    {
        $userId = (int) Yii::$app->user->id;
        $message = UserMessage::find()
            ->where(['id' => $id])
            ->andWhere(['or', ['sender_id' => $userId], ['recipient_id' => $userId]])
            ->with(['sender', 'recipient'])
            ->one();
        if ($message === null) {
            throw new NotFoundHttpException('找不到这条消息。');
        }

        if ((int) $message->recipient_id === $userId && $message->read_at === null) {
            UserMessage::updateAll(
                ['read_at' => time()],
                ['id' => $message->id, 'recipient_id' => $userId, 'read_at' => null]
            );
            $message->refresh();
        }

        return $this->render('view', ['message' => $message]);
    }

    public function actionSend(?int $to = null): string|Response
    {
        $model = new UserMessage();
        if ($to !== null) {
            $recipient = User::findOne(['id' => $to, 'status' => User::STATUS_ACTIVE]);
            if ($recipient === null) {
                throw new NotFoundHttpException('找不到该用户。');
            }
            $model->recipientUsername = (string) $recipient->username;
        }

        if ($model->load(Yii::$app->request->post())) {
            $username = trim($model->recipientUsername);
            $recipient = User::findByUsername($username);
            if ($recipient === null) {
                $model->addError('recipientUsername', '请输入有效的收件人用户名。');
            } elseif ((int) $recipient->id === (int) Yii::$app->user->id) {
                $model->addError('recipientUsername', '不能给自己发送私信。');
            } else {
                $model->sender_id = (int) Yii::$app->user->id;
                $model->recipient_id = (int) $recipient->id;
                $model->type = UserMessage::TYPE_PRIVATE;
                if ($model->validate() && $model->save(false)) {
                    Yii::$app->session->setFlash('success', '私信已发送。');

                    return $this->redirect(['/users/message']);
                }
            }
        }

        return $this->render('send', ['model' => $model]);
    }
}
