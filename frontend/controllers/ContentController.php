<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\BlogPost;
use common\models\Image;
use common\models\Moment;
use common\models\Post;
use common\models\SharedFile;
use common\models\ShortVideo;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/** Entry points for the content types; upload processing is handled by dedicated services as they are added. */
class ContentController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::$app->user->identity;
        if ($user !== null && $user->must_change_password) {
            Yii::$app->response->redirect(['/site/change-password']);
            return false;
        }
        if ($user !== null && $user->phone_verified_at === null) {
            Yii::$app->response->redirect(['/site/verify-phone']);
            return false;
        }

        return true;
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['create'],
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
        ];
    }

    public function actionCreate(string $type): string|Response
    {
        $definitions = [
            'blog' => [BlogPost::class, '博客', ['title', 'summary', 'content'], Post::TYPE_BLOG],
            'images' => [Image::class, '图片集', ['title', 'file_path', 'alt_text'], Post::TYPE_IMAGES],
            'moment' => [Moment::class, '瞬间', ['content'], Post::TYPE_MOMENT],
            'video' => [ShortVideo::class, '短视频', ['title', 'description', 'video_path', 'cover_path'], Post::TYPE_SHORT_VIDEO],
            'file' => [SharedFile::class, '文件分享', ['title', 'description', 'file_path', 'original_name', 'mime_type', 'size_bytes'], Post::TYPE_SHARED_FILE],
        ];
        if (!isset($definitions[$type])) {
            throw new ForbiddenHttpException('未知的内容类型。');
        }

        [$class, $label, $fields, $postType] = $definitions[$type];
        $model = new $class();
        if ($model->load(Yii::$app->request->post())) {
            $post = new Post();
            $post->user_id = (int) Yii::$app->user->id;
            $post->type = $postType;
            $post->title = isset($model->title) && $model->title !== ''
                ? $model->title
                : mb_substr(trim((string) $model->content), 0, 200);
            $post->status = Post::STATUS_PUBLISHED;

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $postSaved = $post->save();
                if ($postSaved) {
                    $model->post_id = $post->id;
                }
                if ($postSaved && $model->save()) {
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', '内容已发布。');
                    return $this->redirect(['/site/index']);
                }
                $transaction->rollBack();
            } catch (\Throwable $exception) {
                $transaction->rollBack();
                throw $exception;
            }
        }

        return $this->render('create', compact('model', 'label', 'fields', 'type'));
    }
}
