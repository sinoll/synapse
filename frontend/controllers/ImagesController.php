<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Image;
use common\models\Post;
use common\models\Tag;
use Yii;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ImagesController extends Controller
{
    use ContentVisibilityTrait;

    private const FIELDS = ['title', 'file_path', 'alt_text'];

    public function getViewPath(): string
    {
        return Yii::getAlias('@frontend/views/images');
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

    public function actionView(int $id): string
    {
        $post = $this->findTypedContentPost($id, Post::TYPE_IMAGES);
        if (!$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $images = $post->images;
        if ($images === []) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $this->render('view', ['post' => $post, 'images' => $images]);
    }

    public function actionCreate(?string $type = null): string|Response
    {
        $model = new Image();
        $post = new Post();
        $selectedTagNames = [];
        $tagError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $uploadedImages = UploadedFile::getInstances($model, 'file_path');
            $post->user_id = (int) Yii::$app->user->id;
            $post->type = Post::TYPE_IMAGES;
            $post->status = Post::STATUS_PUBLISHED;
            $post->title = $this->contentTitle($model);
            $model->post_id = 0;
            // The real path is generated after validation and upload.
            $model->file_path = 'pending-upload';

            $parsedTagNames = $this->submittedTagNames();
            $tagInputValid = $parsedTagNames !== null;
            $selectedTagNames = $parsedTagNames ?? [];
            if (!$tagInputValid) {
                $tagError = '最多添加 10 个标签，每个标签不超过 50 个字符。';
            }

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

            $modelIsValid = $model->validate();

            if ($post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid && $uploadedImages !== []) {
                $transaction = Yii::$app->db->beginTransaction();
                $imagePaths = [];
                try {
                    $imagePaths = $this->storeImageUploads($uploadedImages);
                    if ($imagePaths === []) {
                        throw new \RuntimeException('图片上传失败。');
                    }
                    if (!$post->save(false)) {
                        throw new \RuntimeException('无法保存内容记录。');
                    }

                    $model->post_id = $post->id;
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

                    $this->syncPostTags($post, $selectedTagNames);
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', '内容已发布。');

                    return $this->redirect(['/content/view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    foreach ($imagePaths as $imagePath) {
                        $this->deleteStoredImage($imagePath);
                    }
                    if ($transaction->isActive) {
                        $transaction->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return $this->renderForm($model, $post, [], $selectedTagNames, $tagError, false);
    }

    public function actionUpdate(int $id): string|Response
    {
        $post = $this->findImagePost($id);
        $this->ensureOwner($post);

        $model = $post->images[0] ?? null;
        if ($model === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $selectedImageIds = [];
        $selectedTagNames = array_map(static fn (Tag $tag): string => $tag->name, $post->tags);
        $tagError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $uploadedImages = UploadedFile::getInstances($model, 'file_path');
            $model->post_id = (int) $post->id;
            $post->title = $this->contentTitle($model);

            $parsedTagNames = $this->submittedTagNames();
            $tagInputValid = $parsedTagNames !== null;
            $selectedTagNames = $parsedTagNames ?? [];
            if (!$tagInputValid) {
                $tagError = '最多添加 10 个标签，每个标签不超过 50 个字符。';
            }

            $requestedImageIds = Yii::$app->request->post('remove_image_ids', []);
            $imageSelectionIsValid = true;
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

            $modelIsValid = $model->validate();

            if ($post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid && $imageSelectionIsValid) {
                $transaction = Yii::$app->db->beginTransaction();
                $imagePaths = [];
                $removedImagePaths = [];
                try {
                    if ($uploadedImages !== []) {
                        $imagePaths = $this->storeImageUploads($uploadedImages);
                        if ($imagePaths === []) {
                            throw new \RuntimeException('图片上传失败。');
                        }
                    }
                    if (!$post->save(false)) {
                        $transaction->rollBack();
                        return $this->renderForm($model, $post, $selectedImageIds, $selectedTagNames, $tagError, true);
                    }

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
                    $transaction->commit();
                    foreach (array_unique($removedImagePaths) as $removedImagePath) {
                        if (!Image::find()->where(['file_path' => $removedImagePath])->exists()) {
                            $this->deleteStoredImage($removedImagePath);
                        }
                    }
                    Yii::$app->session->setFlash('success', '内容已更新。');

                    return $this->redirect(['/content/view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    foreach ($imagePaths as $imagePath) {
                        $this->deleteStoredImage($imagePath);
                    }
                    if ($transaction->isActive) {
                        $transaction->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return $this->renderForm($model, $post, $selectedImageIds, $selectedTagNames, $tagError, true);
    }

    public function actionDelete(int $id): Response
    {
        $post = $this->findImagePost($id);
        $this->ensureOwner($post);
        if ($post->delete() === false) {
            throw new \RuntimeException('无法删除内容记录。');
        }
        Yii::$app->session->setFlash('success', '内容已删除。');

        return $this->redirect(['/content/index']);
    }

    private function renderForm(
        Image $model,
        Post $post,
        array $selectedImageIds,
        array $selectedTagNames,
        ?string $tagError,
        bool $isUpdate,
    ): string {
        return $this->render('create', [
            'model' => $model,
            'post' => $post,
            'isUpdate' => $isUpdate,
            'selectedImageIds' => $selectedImageIds,
            'selectedTagNames' => $selectedTagNames,
            'tagError' => $tagError,
        ]);
    }

    private function findImagePost(int $id): Post
    {
        $post = Post::findOne($id);
        if ($post === null || $post->type !== Post::TYPE_IMAGES) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $post;
    }

    private function ensureOwner(Post $post): void
    {
        if (Yii::$app->user->isGuest || (int) $post->user_id !== (int) Yii::$app->user->id) {
            throw new ForbiddenHttpException('你无权修改此内容。');
        }
    }

    private function loadContentModel(ActiveRecord $model): bool
    {
        $attributes = Yii::$app->request->post($model->formName());
        if (!is_array($attributes)) {
            return false;
        }

        foreach (self::FIELDS as $field) {
            // A multi-file input submits file_path as an array. UploadedFile
            // handles it separately; it must never overwrite the scalar DB path.
            if ($field === 'file_path') {
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

    private function contentTitle(ActiveRecord $model): string
    {
        $title = trim((string) $model->getAttribute('title'));

        return $title !== '' ? $title : '图片';
    }

    /** @return string[]|null Null means malformed or over-limit input. */
    private function submittedTagNames(): ?array
    {
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
}
