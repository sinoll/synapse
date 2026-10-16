<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\BlogPost;
use common\models\Image;
use common\models\Moment;
use common\models\Post;
use common\models\SharedFile;
use common\models\SharedFileRecipient;
use common\models\ShortVideo;
use common\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

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
                'only' => ['create', 'update', 'delete'],
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST']],
            ],
        ];
    }

    public function actionIndex(?string $type = null): string
    {
        $definitions = $this->definitions();
        if ($type !== null && !isset($definitions[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'sharedFile'])
            ->orderBy(['p.created_at' => SORT_DESC]);
        if ($type !== null) {
            $query->andWhere(['p.type' => $definitions[$type]['postType']]);
        }

        if ($userId === null) {
            $query->andWhere([
                'p.status' => Post::STATUS_PUBLISHED,
                'p.visibility' => Post::VISIBILITY_PUBLIC,
            ]);
        } else {
            $visibleToUser = [
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                [
                    'and',
                    ['p.visibility' => Post::VISIBILITY_SELECTED_USERS],
                    ['exists', (new Query())
                        ->select('1')
                        ->from(['sf' => '{{%shared_file}}'])
                        ->innerJoin(['recipient' => '{{%shared_file_recipient}}'], 'recipient.shared_file_id = sf.id')
                        ->where('sf.post_id = p.id')
                        ->andWhere(['recipient.user_id' => $userId])
                        ->andWhere(['sf.status' => SharedFile::STATUS_ACTIVE])],
                ],
            ];
            $query->andWhere([
                'or',
                ['p.user_id' => $userId],
                ['and', ['p.status' => Post::STATUS_PUBLISHED], $visibleToUser],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'definitions' => $definitions,
            'selectedType' => $type,
        ]);
    }

    public function actionView(int $id): string
    {
        $post = $this->findPost($id);
        if (!$this->canView($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $this->render('view', [
            'post' => $post,
            'contentModel' => $this->contentModel($post),
            'canEdit' => !Yii::$app->user->isGuest && (int) $post->user_id === (int) Yii::$app->user->id,
        ]);
    }

    public function actionCreate(string $type): string|Response
    {
        $definitions = $this->definitions();
        if (!isset($definitions[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        $definition = $definitions[$type];
        $model = new $definition['class']();
        $post = new Post();
        $recipientUsernames = '';

        if ($this->loadContentModel($model, $definition['fields']) && $this->loadVisibility($post)) {
            $post->user_id = (int) Yii::$app->user->id;
            $post->type = $definition['postType'];
            $post->status = Post::STATUS_PUBLISHED;
            $post->title = $this->contentTitle($model);
            $model->post_id = 0;
            $recipientUsernames = trim((string) Yii::$app->request->post('recipient_usernames', ''));
            $recipientIds = $this->recipientIds($post, $recipientUsernames);

            if ($recipientIds !== null && $post->validate() && $model->validate()) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if (!$post->save(false)) {
                        throw new \RuntimeException('无法保存内容记录。');
                    }
                    $model->post_id = $post->id;
                    if (!$model->save()) {
                        $transaction->rollBack();
                        return $this->render('create', [
                            'model' => $model,
                            'post' => $post,
                            'label' => $definition['label'],
                            'fields' => $definition['fields'],
                            'type' => $type,
                            'recipientUsernames' => $recipientUsernames,
                        ]);
                    }
                    $this->saveRecipients($post, $recipientIds);
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', '内容已发布。');

                    return $this->redirect(['view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    if ($transaction->isActive) {
                        $transaction->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
            'post' => $post,
            'label' => $definition['label'],
            'fields' => $definition['fields'],
            'type' => $type,
            'recipientUsernames' => $recipientUsernames,
        ]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $post = $this->findPost($id);
        $this->ensureOwner($post);

        $definition = $this->definitionForPost($post);
        $model = $this->contentModel($post);
        if ($model === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $recipientUsernames = implode(', ', array_map(
            static fn (User $user): string => $user->username,
            $post->sharedFile?->users ?? [],
        ));

        if ($this->loadContentModel($model, $definition['fields']) && $this->loadVisibility($post)) {
            $model->post_id = (int) $post->id;
            $post->type = $definition['postType'];
            $post->title = $this->contentTitle($model);
            $recipientUsernames = trim((string) Yii::$app->request->post('recipient_usernames', ''));
            $recipientIds = $this->recipientIds($post, $recipientUsernames);

            if ($recipientIds !== null && $post->validate() && $model->validate()) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if (!$post->save(false) || !$model->save()) {
                        $transaction->rollBack();
                    } else {
                        $this->saveRecipients($post, $recipientIds);
                        $transaction->commit();
                        Yii::$app->session->setFlash('success', '内容已更新。');

                        return $this->redirect(['view', 'id' => $post->id]);
                    }
                } catch (\Throwable $exception) {
                    if ($transaction->isActive) {
                        $transaction->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
            'post' => $post,
            'label' => $definition['label'],
            'fields' => $definition['fields'],
            'type' => $definition['key'],
            'recipientUsernames' => $recipientUsernames,
            'isUpdate' => true,
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $post = $this->findPost($id);
        $this->ensureOwner($post);
        if ($post->delete() === false) {
            throw new \RuntimeException('无法删除内容记录。');
        }
        Yii::$app->session->setFlash('success', '内容已删除。');

        return $this->redirect(['index']);
    }

    private function definitions(): array
    {
        return [
            'blog' => ['key' => 'blog', 'class' => BlogPost::class, 'label' => '博客', 'fields' => ['title', 'summary', 'content'], 'postType' => Post::TYPE_BLOG],
            'images' => ['key' => 'images', 'class' => Image::class, 'label' => '图片', 'fields' => ['title', 'file_path', 'alt_text'], 'postType' => Post::TYPE_IMAGES],
            'moment' => ['key' => 'moment', 'class' => Moment::class, 'label' => '动态', 'fields' => ['content', 'image_paths'], 'postType' => Post::TYPE_MOMENT],
            'video' => ['key' => 'video', 'class' => ShortVideo::class, 'label' => '短视频', 'fields' => ['title', 'description', 'video_path', 'cover_path', 'duration_seconds'], 'postType' => Post::TYPE_SHORT_VIDEO],
            'file' => ['key' => 'file', 'class' => SharedFile::class, 'label' => '文件分享', 'fields' => ['title', 'description', 'file_path', 'original_name', 'mime_type', 'size_bytes'], 'postType' => Post::TYPE_SHARED_FILE],
        ];
    }

    private function definitionForPost(Post $post): array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['postType'] === $post->type) {
                return $definition;
            }
        }

        throw new NotFoundHttpException('找不到该内容。');
    }

    private function findPost(int $id): Post
    {
        $post = Post::findOne($id);
        if ($post === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $post;
    }

    private function contentModel(Post $post): ?ActiveRecord
    {
        return match ($post->type) {
            Post::TYPE_BLOG => $post->blog,
            Post::TYPE_IMAGES => $post->images[0] ?? null,
            Post::TYPE_MOMENT => $post->moment,
            Post::TYPE_SHORT_VIDEO => $post->shortVideo,
            Post::TYPE_SHARED_FILE => $post->sharedFile,
            default => null,
        };
    }

    private function canView(Post $post): bool
    {
        if (!Yii::$app->user->isGuest && (int) $post->user_id === (int) Yii::$app->user->id) {
            return true;
        }
        if ($post->status !== Post::STATUS_PUBLISHED
            || ($post->type === Post::TYPE_SHARED_FILE && $post->sharedFile?->status !== SharedFile::STATUS_ACTIVE)) {
            return false;
        }

        return match ($post->visibility) {
            Post::VISIBILITY_PUBLIC => true,
            Post::VISIBILITY_REGISTERED_USERS => !Yii::$app->user->isGuest,
            Post::VISIBILITY_SELECTED_USERS => !Yii::$app->user->isGuest
                && $post->type === Post::TYPE_SHARED_FILE
                && SharedFileRecipient::find()->where([
                    'shared_file_id' => $post->sharedFile?->id,
                    'user_id' => (int) Yii::$app->user->id,
                ])->exists(),
            default => false,
        };
    }

    private function ensureOwner(Post $post): void
    {
        if (Yii::$app->user->isGuest || (int) $post->user_id !== (int) Yii::$app->user->id) {
            throw new ForbiddenHttpException('你无权修改此内容。');
        }
    }

    private function contentTitle(ActiveRecord $model): string
    {
        if ($model->hasAttribute('title') && trim((string) $model->getAttribute('title')) !== '') {
            return trim((string) $model->getAttribute('title'));
        }

        return mb_substr(trim((string) $model->getAttribute('content')), 0, 200);
    }

    private function loadContentModel(ActiveRecord $model, array $fields): bool
    {
        $attributes = Yii::$app->request->post($model->formName());
        if (!is_array($attributes)) {
            return false;
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $attributes)) {
                $model->setAttribute($field, $attributes[$field]);
            }
        }

        return $attributes !== [];
    }

    private function loadVisibility(Post $post): bool
    {
        $attributes = Yii::$app->request->post($post->formName());
        if (!is_array($attributes) || !array_key_exists('visibility', $attributes)) {
            return false;
        }

        $visibility = filter_var($attributes['visibility'], FILTER_VALIDATE_INT);
        $post->visibility = $visibility === false ? -1 : $visibility;

        return true;
    }

    private function recipientIds(Post $post, string $usernames): ?array
    {
        if ($post->visibility !== Post::VISIBILITY_SELECTED_USERS) {
            return [];
        }

        $names = array_values(array_unique(preg_split('/[\s,;]+/', trim($usernames), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        $users = $names === [] ? [] : User::find()
            ->select(['id', 'username'])
            ->where([
                'username' => $names,
                'status' => User::STATUS_ACTIVE,
            ])
            ->andWhere(['not', ['phone_verified_at' => null]])
            ->all();
        if ($names === [] || count($users) !== count($names)) {
            $post->addError('visibility', '请填写至少一个有效的已验证用户名称。');

            return null;
        }

        return array_map(static fn (User $user): int => (int) $user->id, $users);
    }

    private function saveRecipients(Post $post, array $userIds): void
    {
        if ($post->type !== Post::TYPE_SHARED_FILE || $post->sharedFile === null) {
            return;
        }

        SharedFileRecipient::deleteAll(['shared_file_id' => $post->sharedFile->id]);
        foreach ($userIds as $userId) {
            $recipient = new SharedFileRecipient([
                'shared_file_id' => $post->sharedFile->id,
                'user_id' => $userId,
                'created_at' => time(),
            ]);
            if (!$recipient->save()) {
                throw new \RuntimeException('无法保存文件授权用户。');
            }
        }
    }
}
