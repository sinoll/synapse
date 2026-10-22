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
use common\models\Tag;
use common\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ContentController extends Controller
{
    public function getViewPath(): string
    {
        return Yii::getAlias('@frontend/views/content');
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::$app->user->identity;
        if ($user !== null && $user->must_change_password) {
            Yii::$app->response->redirect(['/users/change-password']);
            return false;
        }
        /***
        if ($user !== null && $user->phone_verified_at === null) {
            Yii::$app->response->redirect(['/users/verify-phone']);
            return false;
        }
        ***/
        return true;
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index', 'view', 'create', 'update', 'delete'],
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
        $query->andWhere([
            'or',
            ['<>', 'p.type', Post::TYPE_SHARED_FILE],
            ['exists', (new Query())
                ->select('sf.id')
                ->from(['sf' => '{{%shared_file}}'])
                ->where('sf.post_id = p.id')
                ->andWhere(['sf.status' => SharedFile::STATUS_ACTIVE])
                ->andWhere(['sf.deleted_at' => null])],
        ]);

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
                        ->select('sf.id')
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

    /** Legacy entry point: send callers to the type-specific publisher. */
    public function actionCreate(?string $type = null): string|Response
    {
        $routes = [
            'blog' => ['/blog/create'],
            'images' => ['/images/create'],
            'moment' => ['/moment/create'],
            'video' => ['/video/create'],
            'file' => ['/file/create'],
        ];
        if ($type === null || !isset($routes[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        return $this->redirect($routes[$type]);
    }

    /** Shared form and persistence workflow used by each type-specific controller. */
    protected function createContent(string $type): string|Response
    {
        $definitions = $this->definitions();
        if (!isset($definitions[$type])) {
            throw new \yii\web\BadRequestHttpException('未知的内容类型。');
        }

        $definition = $definitions[$type];
        $model = new $definition['class']();
        $post = new Post();
        $recipientUsernames = '';
        $selectedTagNames = [];
        $tagError = null;

        if ($this->loadContentModel($model, $definition['fields']) && $this->loadVisibility($post)) {
            $uploadedImages = $type === 'images'
                ? UploadedFile::getInstances($model, 'file_path')
                : [];
            $uploadedVideo = $type === 'video' ? UploadedFile::getInstance($model, 'video_path') : null;
            $uploadedCover = $type === 'video' ? UploadedFile::getInstance($model, 'cover_path') : null;
            $editorImages = in_array($type, ['blog', 'moment'], true)
                ? UploadedFile::getInstancesByName('editor_images') : [];
            if ($type === 'images') {
                if ($uploadedImages === []) {
                    $model->addError('file_path', '请至少选择一张图片。');
                }
                foreach ($uploadedImages as $uploadedImage) {
                    if ($uploadedImage->error !== UPLOAD_ERR_OK) {
                        $model->addError('file_path', '图片上传失败，请重新选择。');
                    } elseif (!in_array(strtolower($uploadedImage->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                        $model->addError('file_path', '仅支持 JPG、PNG、GIF 或 WebP 图片。');
                    } elseif ($uploadedImage->size > 10 * 1024 * 1024) {
                        $model->addError('file_path', '单张图片不能超过 10 MB。');
                    } elseif (@getimagesize($uploadedImage->tempName) === false) {
                        $model->addError('file_path', '所选文件不是有效的图片。');
                    }
                }
            }
            if ($type === 'video') {
                if ($uploadedVideo === null || $uploadedVideo->error !== UPLOAD_ERR_OK) {
                    $model->addError('video_path', '请至少选择一个视频文件。');
                } elseif (!in_array(strtolower($uploadedVideo->extension), ['mp4', 'webm', 'mov', 'ogv', 'ogg'], true)) {
                    $model->addError('video_path', '仅支持 MP4、WebM、MOV 或 OGG 视频。');
                } elseif ($uploadedVideo->size > 500 * 1024 * 1024) {
                    $model->addError('video_path', '视频文件不能超过 500 MB。');
                }
                if ($uploadedCover !== null && $uploadedCover->error === UPLOAD_ERR_OK
                    && (!in_array(strtolower($uploadedCover->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                        || $uploadedCover->size > 10 * 1024 * 1024
                        || @getimagesize($uploadedCover->tempName) === false)) {
                    $model->addError('cover_path', '封面必须是有效的图片，且不超过 10 MB。');
                }
            }
            $post->user_id = (int) Yii::$app->user->id;
            $post->type = $definition['postType'];
            $post->status = Post::STATUS_PUBLISHED;
            $post->title = $type === 'moment' ? null : $this->contentTitle($model);
            $model->post_id = 0;
            if ($type === 'images') {
                // The actual path is generated after validation and upload.
                $model->file_path = 'pending-upload';
            }
            if ($type === 'video') {
                $model->video_path = 'pending-upload';
                $model->duration_seconds = max(0, (int) $model->duration_seconds);
            }
            $recipientUsernames = trim((string) Yii::$app->request->post('recipient_usernames', ''));
            $recipientIds = $this->recipientIds($post, $recipientUsernames);
            $parsedTagNames = $this->submittedTagNames($type);
            $tagInputValid = $parsedTagNames !== null;
            $selectedTagNames = $parsedTagNames ?? [];
            if (!$tagInputValid) {
                $tagError = '最多添加 10 个标签，每个标签不超过 50 个字符。';
            }
            $modelIsValid = $model->validate();
            $this->validateRichEditorImages($model, $editorImages);

            if ($recipientIds !== null && $post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid
                && ($type !== 'images' || $uploadedImages !== [])
                && ($type !== 'video' || $uploadedVideo !== null)) {
                $transaction = Yii::$app->db->beginTransaction();
                $richImagePaths = [];
                try {
                    if ($editorImages !== []) {
                        $richImagePaths = $this->storeRichEditorImages($editorImages);
                        if (count($richImagePaths) !== count($editorImages)) {
                            throw new \RuntimeException('Rich-text image upload failed.');
                        }
                        $this->replaceRichEditorImageTokens($model, $richImagePaths);
                        if (!$model->validate()) {
                            $transaction->rollBack();
                            $this->deleteRichEditorImages($richImagePaths);
                            return $this->render('create', [
                                'model' => $model, 'post' => $post, 'label' => $definition['label'],
                                'fields' => $definition['fields'], 'type' => $type,
                                'recipientUsernames' => $recipientUsernames,
                                'selectedTagNames' => $selectedTagNames, 'tagError' => $tagError,
                            ]);
                        }
                    }
                    $imagePaths = $type === 'images' ? $this->storeImageUploads($uploadedImages) : [];
                    if ($type === 'images' && $imagePaths === []) {
                        throw new \RuntimeException('图片上传失败。');
                    }
                    $coverPath = $type === 'video' && $uploadedCover !== null
                        ? $this->storeUploadedFile($uploadedCover, '@frontend/web/uploads/video-covers')
                        : ($type === 'video' ? $this->generateVideoCover($uploadedVideo) : null);
                    $videoPath = $type === 'video'
                        ? $this->storeUploadedFile($uploadedVideo, '@frontend/web/uploads/videos')
                        : null;
                    if ($type === 'video' && $videoPath === null) {
                        throw new \RuntimeException('视频上传失败。');
                    }
                    if (!$post->save(false)) {
                        throw new \RuntimeException('无法保存内容记录。');
                    }
                    if ($type === 'video' && $coverPath === null) {
                        $model->addError('cover_path', '无法自动生成视频第 5 帧封面，请检查 FFmpeg 配置后重试。');
                        $transaction->rollBack();
                        if ($videoPath !== null) {
                            $storedVideo = Yii::getAlias('@frontend/web') . str_replace('/', DIRECTORY_SEPARATOR, $videoPath);
                            if (is_file($storedVideo)) {
                                @unlink($storedVideo);
                            }
                        }
                        return $this->render('create', [
                            'model' => $model,
                            'post' => $post,
                            'label' => $definition['label'],
                            'fields' => $definition['fields'],
                            'type' => $type,
                            'recipientUsernames' => $recipientUsernames,
                            'selectedTagNames' => $selectedTagNames,
                            'tagError' => $tagError,
                        ]);
                    }
                    $model->post_id = $post->id;
                    if ($type === 'images') {
                        foreach ($imagePaths as $sortOrder => $imagePath) {
                            $image = $sortOrder === 0 ? $model : new Image();
                            $image->post_id = $post->id;
                            $image->title = (string) $model->title;
                            $image->file_path = $imagePath;
                            $image->alt_text = $model->alt_text;
                            $image->sort_order = $sortOrder;
                            if (!$image->save()) {
                                throw new \RuntimeException('图片保存失败。');
                            }
                        }
                    } elseif ($type === 'video') {
                        $model->video_path = $videoPath;
                        $model->cover_path = $coverPath;
                        if (!$model->save()) {
                            throw new \RuntimeException('视频保存失败。');
                        }
                    } elseif (!$model->save()) {
                        $transaction->rollBack();
                        return $this->render('create', [
                            'model' => $model,
                            'post' => $post,
                            'label' => $definition['label'],
                            'fields' => $definition['fields'],
                            'type' => $type,
                            'recipientUsernames' => $recipientUsernames,
                            'selectedTagNames' => $selectedTagNames,
                            'tagError' => $tagError,
                        ]);
                    }
                    $this->syncPostTags($post, $selectedTagNames);
                    $this->saveRecipients($post, $recipientIds);
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', '内容已发布。');

                    return $this->redirect(['/content/view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    $this->deleteRichEditorImages($richImagePaths);
                    if ($type === 'images' && !empty($imagePaths)) {
                        foreach ($imagePaths as $imagePath) {
                            $storedPath = Yii::getAlias('@frontend/web') . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
                            if (is_file($storedPath)) {
                                @unlink($storedPath);
                            }
                        }
                    }
                    foreach ([$videoPath ?? null, $coverPath ?? null] as $storedPath) {
                        if ($storedPath !== null) {
                            $absolutePath = Yii::getAlias('@frontend/web') . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
                            if (is_file($absolutePath)) {
                                @unlink($absolutePath);
                            }
                        }
                    }
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
            'selectedTagNames' => $selectedTagNames,
            'tagError' => $tagError,
        ]);
    }

    protected function updateContent(int $id): string|Response
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
        $selectedImageIds = [];
        $selectedTagNames = $post->canHaveTags()
            ? array_map(static fn (Tag $tag): string => $tag->name, $post->tags)
            : [];
        $tagError = null;

        if ($this->loadContentModel($model, $definition['fields']) && $this->loadVisibility($post)) {
            $uploadedImages = $post->type === Post::TYPE_IMAGES
                ? UploadedFile::getInstances($model, 'file_path')
                : [];
            $editorImages = in_array($post->type, [Post::TYPE_BLOG, Post::TYPE_MOMENT], true)
                ? UploadedFile::getInstancesByName('editor_images') : [];
            $model->post_id = (int) $post->id;
            $post->type = $definition['postType'];
            $post->title = $post->type === Post::TYPE_MOMENT ? null : $this->contentTitle($model);
            $recipientUsernames = trim((string) Yii::$app->request->post('recipient_usernames', ''));
            $recipientIds = $this->recipientIds($post, $recipientUsernames);
            $parsedTagNames = $this->submittedTagNames($post->type);
            $tagInputValid = $parsedTagNames !== null;
            $selectedTagNames = $parsedTagNames ?? [];
            if (!$tagInputValid) {
                $tagError = '最多添加 10 个标签，每个标签不超过 50 个字符。';
            }
            // The first Image model carries the persisted path, while this form
            // has an optional multi-file input. Validate retained Image rows
            // individually below after their stored paths are available.
            $modelIsValid = $post->type === Post::TYPE_IMAGES || $model->validate();
            if (in_array($post->type, [Post::TYPE_BLOG, Post::TYPE_MOMENT], true)) {
                $this->validateRichEditorImages($model, $editorImages);
            }
            $imageSelectionIsValid = true;
            if ($post->type === Post::TYPE_IMAGES) {
                $requestedImageIds = Yii::$app->request->post('remove_image_ids', []);
                if (!is_array($requestedImageIds)) {
                    $imageSelectionIsValid = false;
                } else {
                    foreach ($requestedImageIds as $requestedImageId) {
                        $imageId = filter_var($requestedImageId, FILTER_VALIDATE_INT);
                        if ($imageId === false || $imageId < 1) {
                            $imageSelectionIsValid = false;
                            break;
                        }
                        $selectedImageIds[] = $imageId;
                    }
                    $selectedImageIds = array_values(array_unique($selectedImageIds));
                    if ($selectedImageIds !== []) {
                        $ownedSelectionCount = (int) Image::find()
                            ->where(['post_id' => $post->id, 'id' => $selectedImageIds])
                            ->count();
                        $imageSelectionIsValid = $ownedSelectionCount === count($selectedImageIds);
                    }
                }
                if (!$imageSelectionIsValid) {
                    $model->addError('file_path', '删除选择无效，请刷新页面后重试。');
                }
                foreach ($uploadedImages as $uploadedImage) {
                    if ($uploadedImage->error !== UPLOAD_ERR_OK) {
                        $model->addError('file_path', '图片上传失败，请重新选择。');
                    } elseif (!in_array(strtolower($uploadedImage->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                        $model->addError('file_path', '仅支持 JPG、PNG、GIF 或 WebP 图片。');
                    } elseif ($uploadedImage->size > 10 * 1024 * 1024) {
                        $model->addError('file_path', '单张图片不能超过 10 MB。');
                    } elseif (@getimagesize($uploadedImage->tempName) === false) {
                        $model->addError('file_path', '所选文件不是有效的图片。');
                    }
                }
                if ($imageSelectionIsValid && $selectedImageIds !== [] && $uploadedImages === []) {
                    $remainingImages = Image::find()->where(['post_id' => $post->id]);
                    $remainingImages->andWhere(['not in', 'id', $selectedImageIds]);
                    if ((int) $remainingImages->count() === 0) {
                        $model->addError('file_path', '图片集至少保留一张图片；如需全部替换，请同时上传新图片。');
                    }
                }
            }

            if ($recipientIds !== null && $post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid
                && ($post->type !== Post::TYPE_IMAGES || (!$model->hasErrors() && $imageSelectionIsValid))) {
                $transaction = Yii::$app->db->beginTransaction();
                $imagePaths = [];
                $richImagePaths = [];
                $removedImagePaths = [];
                try {
                    if ($editorImages !== []) {
                        $richImagePaths = $this->storeRichEditorImages($editorImages);
                        if (count($richImagePaths) !== count($editorImages)) {
                            throw new \RuntimeException('Rich-text image upload failed.');
                        }
                        $this->replaceRichEditorImageTokens($model, $richImagePaths);
                        if (!$model->validate()) {
                            $transaction->rollBack();
                            $this->deleteRichEditorImages($richImagePaths);
                            return $this->render('create', [
                                'model' => $model, 'post' => $post, 'label' => $definition['label'],
                                'fields' => $definition['fields'], 'type' => $definition['key'],
                                'recipientUsernames' => $recipientUsernames, 'isUpdate' => true,
                                'selectedImageIds' => $selectedImageIds,
                                'selectedTagNames' => $selectedTagNames, 'tagError' => $tagError,
                            ]);
                        }
                    }
                    if ($post->type === Post::TYPE_IMAGES && $uploadedImages !== []) {
                        $imagePaths = $this->storeImageUploads($uploadedImages);
                        if ($imagePaths === []) {
                            throw new \RuntimeException('图片上传失败。');
                        }
                    }
                    if (!$post->save(false)) {
                        $transaction->rollBack();
                    } elseif ($post->type === Post::TYPE_IMAGES) {
                        $images = Image::find()->where(['post_id' => $post->id])->orderBy(['sort_order' => SORT_ASC])->all();
                        if ($selectedImageIds !== []) {
                            foreach ($images as $image) {
                                if (in_array((int) $image->id, $selectedImageIds, true)) {
                                    $removedImagePaths[] = (string) $image->file_path;
                                }
                            }
                            Image::deleteAll(['post_id' => $post->id, 'id' => $selectedImageIds]);
                            $images = array_values(array_filter(
                                $images,
                                static fn (Image $image): bool => !in_array((int) $image->id, $selectedImageIds, true),
                            ));
                        }
                        foreach ($images as $sortOrder => $image) {
                            $image->title = (string) $model->title;
                            $image->alt_text = $model->alt_text;
                            $image->sort_order = $sortOrder;
                            if (!$image->save()) {
                                throw new \RuntimeException('图片保存失败。');
                            }
                        }
                        $nextSortOrder = count($images);
                        foreach ($imagePaths as $offset => $imagePath) {
                            $image = new Image();
                            $image->post_id = $post->id;
                            $image->title = (string) $model->title;
                            $image->file_path = $imagePath;
                            $image->alt_text = $model->alt_text;
                            $image->sort_order = $nextSortOrder + $offset;
                            if (!$image->save()) {
                                throw new \RuntimeException('图片保存失败。');
                            }
                        }
                        $this->syncPostTags($post, $selectedTagNames);
                        $this->saveRecipients($post, $recipientIds);
                        $transaction->commit();
                        foreach (array_unique($removedImagePaths) as $removedImagePath) {
                            if (!Image::find()->where(['file_path' => $removedImagePath])->exists()) {
                                $this->deleteStoredImage($removedImagePath);
                            }
                        }
                        Yii::$app->session->setFlash('success', '内容已更新。');

                        return $this->redirect(['/content/view', 'id' => $post->id]);
                    } elseif (!$model->save()) {
                        $transaction->rollBack();
                    } else {
                        $this->syncPostTags($post, $selectedTagNames);
                        $this->saveRecipients($post, $recipientIds);
                        $transaction->commit();
                        Yii::$app->session->setFlash('success', '内容已更新。');

                        return $this->redirect(['/content/view', 'id' => $post->id]);
                    }
                } catch (\Throwable $exception) {
                    $this->deleteRichEditorImages($richImagePaths);
                    foreach ($imagePaths as $imagePath) {
                        $storedPath = Yii::getAlias('@frontend/web') . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
                        if (is_file($storedPath)) {
                            @unlink($storedPath);
                        }
                    }
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
            'selectedImageIds' => $selectedImageIds,
            'selectedTagNames' => $selectedTagNames,
            'tagError' => $tagError,
        ]);
    }

    protected function deleteContent(int $id): Response
    {
        $post = $this->findPost($id);
        $this->ensureOwner($post);
        if ($post->delete() === false) {
            throw new \RuntimeException('无法删除内容记录。');
        }
        Yii::$app->session->setFlash('success', '内容已删除。');

        return $this->redirect(['index']);
    }

    /** @return string[]|null Null means malformed or over-limit input. */
    private function submittedTagNames(string $type): ?array
    {
        if (!in_array($type, ['blog', 'images', 'moment', 'video'], true)) {
            return [];
        }

        $submittedNames = Yii::$app->request->post('tags', []);
        if (!is_array($submittedNames)) {
            return null;
        }

        $names = [];
        $normalizedNames = [];
        foreach ($submittedNames as $submittedName) {
            if (!is_string($submittedName)) {
                return null;
            }
            $name = preg_replace('/\s+/u', ' ', trim($submittedName)) ?? trim($submittedName);
            $name = trim($name, "# \t\n\r\0\x0B");
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 50 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
                return null;
            }

            $normalizedName = mb_strtolower($name);
            if (isset($normalizedNames[$normalizedName])) {
                continue;
            }
            $normalizedNames[$normalizedName] = true;
            $names[] = $name;
            if (count($names) > 10) {
                return null;
            }
        }

        return $names;
    }

    /** Replace a post's tags inside the caller's current transaction. */
    private function syncPostTags(Post $post, array $tagNames): void
    {
        if (!$post->canHaveTags()) {
            return;
        }

        Yii::$app->db->createCommand()->delete('{{%post_tag}}', ['post_id' => $post->id])->execute();
        foreach ($tagNames as $name) {
            $tag = Tag::find()->where(['name' => $name])->one();
            if ($tag === null) {
                $tag = new Tag();
                $tag->name = $name;
                $tag->slug = Tag::slugForName($name);
                if (!$tag->save()) {
                    throw new \RuntimeException('标签保存失败：' . json_encode($tag->getErrors(), JSON_UNESCAPED_UNICODE));
                }
            }
            $post->link('tags', $tag);
        }
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
            || ($post->type === Post::TYPE_SHARED_FILE
                && ($post->sharedFile?->status !== SharedFile::STATUS_ACTIVE || $post->sharedFile->deleted_at !== null))) {
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

        $text = html_entity_decode(strip_tags((string) $model->getAttribute('content')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim($text), 0, 200);
    }

    /** @param UploadedFile[] $files */
    private function validateRichEditorImages(ActiveRecord $model, array $files): void
    {
        if (count($files) > 20) {
            $model->addError('content', 'You can upload at most 20 images at a time.');
        }
        foreach ($files as $file) {
            if ($file->error !== UPLOAD_ERR_OK
                || !in_array(strtolower($file->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                || $file->size > 10 * 1024 * 1024
                || @getimagesize($file->tempName) === false) {
                $model->addError('content', 'Each inserted image must be a valid JPG, PNG, GIF, or WebP image no larger than 10 MB.');
            }
        }
    }

    /** @param UploadedFile[] $files @return string[] */
    private function storeRichEditorImages(array $files): array
    {
        $directory = Yii::getAlias('@frontend/web/uploads/content-images');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return [];
        }
        $paths = [];
        foreach ($files as $file) {
            $name = Yii::$app->security->generateRandomString(32) . '.' . strtolower($file->extension);
            if (!$file->saveAs($directory . DIRECTORY_SEPARATOR . $name)) {
                $this->deleteRichEditorImages($paths);
                return [];
            }
            $paths[] = '/uploads/content-images/' . $name;
        }
        return $paths;
    }

    /** @param string[] $paths */
    private function replaceRichEditorImageTokens(ActiveRecord $model, array $paths): void
    {
        $content = (string) $model->getAttribute('content');
        foreach ($paths as $index => $path) {
            $safePath = Html::encode($path);
            $content = str_replace('[[EDITOR_IMAGE_' . $index . ']]', '<img src="' . $safePath . '" alt="">', $content);
        }
        $model->setAttribute('content', $content);
    }

    /** @param string[] $paths */
    private function deleteRichEditorImages(array $paths): void
    {
        foreach ($paths as $path) {
            if (preg_match('#^/uploads/content-images/([A-Za-z0-9_-]+\.(?:jpg|jpeg|png|gif|webp))$#i', $path, $matches)) {
                $file = Yii::getAlias('@frontend/web/uploads/content-images/' . $matches[1]);
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /** @param UploadedFile[] $files @return string[] */
    private function storeImageUploads(array $files): array
    {
        $directory = Yii::getAlias('@frontend/web/uploads/images');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return [];
        }

        $paths = [];
        foreach ($files as $file) {
            $name = Yii::$app->security->generateRandomString(32) . '.' . strtolower($file->extension);
            if (!$file->saveAs($directory . DIRECTORY_SEPARATOR . $name)) {
                foreach ($paths as $path) {
                    @unlink(Yii::getAlias('@frontend/web') . str_replace('/', DIRECTORY_SEPARATOR, $path));
                }
                return [];
            }
            $paths[] = '/uploads/images/' . $name;
        }

        return $paths;
    }

    private function deleteStoredImage(string $filePath): void
    {
        $prefix = '/uploads/images/';
        if (!str_starts_with($filePath, $prefix)) {
            return;
        }

        $fileName = substr($filePath, strlen($prefix));
        if ($fileName === '' || basename($fileName) !== $fileName) {
            return;
        }

        $directory = realpath(Yii::getAlias('@frontend/web/uploads/images'));
        $storedFile = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . $fileName);
        $directoryPrefix = $directory === false ? '' : $directory . DIRECTORY_SEPARATOR;
        if ($storedFile !== false
            && strncasecmp($storedFile, $directoryPrefix, strlen($directoryPrefix)) === 0
            && is_file($storedFile)) {
            @unlink($storedFile);
        }
    }

    private function storeUploadedFile(?UploadedFile $file, string $directoryAlias): ?string
    {
        if ($file === null) {
            return null;
        }
        $directory = Yii::getAlias($directoryAlias);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }
        $name = Yii::$app->security->generateRandomString(32) . '.' . strtolower($file->extension);
        if (!$file->saveAs($directory . DIRECTORY_SEPARATOR . $name)) {
            return null;
        }
        $webRoot = Yii::getAlias('@frontend/web');
        $relativeDirectory = trim(str_replace('\\', '/', str_replace($webRoot, '', $directory)), '/');

        return '/' . $relativeDirectory . '/' . $name;
    }

    private function generateVideoCover(?UploadedFile $video): ?string
    {
        if ($video === null) {
            return null;
        }
        $directory = Yii::getAlias('@frontend/web/uploads/video-covers');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }
        $name = Yii::$app->security->generateRandomString(32) . '.jpg';
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        try {
            $generated = Yii::$app->get('ffmpegService')->extractFrame($video->tempName, $target, 5);
        } catch (\Throwable $exception) {
            Yii::warning('FFmpeg cover generation failed: ' . $exception->getMessage(), __METHOD__);
            $generated = false;
        }
        if (!$generated || !is_file($target)) {
            if (is_file($target)) {
                @unlink($target);
            }
            return null;
        }
        return '/uploads/video-covers/' . $name;
    }

    private function loadContentModel(ActiveRecord $model, array $fields): bool
    {
        $attributes = Yii::$app->request->post($model->formName());
        if (!is_array($attributes)) {
            return false;
        }

        foreach ($fields as $field) {
            // A multi-file input submits file_path as an array. UploadedFile
            // handles it separately; it must never overwrite the scalar DB path.
            if ($model instanceof Image && $field === 'file_path') {
                continue;
            }
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
