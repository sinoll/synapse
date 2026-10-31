<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use common\models\ShortVideo;
use common\models\Tag;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class VideoController extends Controller
{
    use ContentVisibilityTrait;

    private const FIELDS = ['title', 'description', 'video_path', 'cover_path', 'duration_seconds', 'video_type'];

    public function getViewPath(): string
    {
        return Yii::getAlias('@frontend/views/video');
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

    public function actionIndex(): string
    {
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'shortVideo'])
            ->where(['p.type' => Post::TYPE_SHORT_VIDEO, 'p.status' => Post::STATUS_PUBLISHED])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);
        $this->applyVisibilityFilter($query);

        $horizontalVideos = [];
        $verticalVideos = [];
        foreach ($query->all() as $post) {
            $video = $post->shortVideo;
            if ($video === null) {
                continue;
            }
            if ($video->isVertical()) {
                $verticalVideos[] = $post;
            } else {
                $horizontalVideos[] = $post;
            }
        }

        return $this->render('index', [
            'horizontalVideos' => $horizontalVideos,
            'verticalVideos' => $verticalVideos,
            'videoTags' => $this->videoTags(),
        ]);
    }

    public function actionView(int $id): string
    {
        $post = $this->findTypedContentPost($id, Post::TYPE_SHORT_VIDEO);
        if (!$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $video = $post->shortVideo;
        if ($video === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        $viewData = ['post' => $post, 'video' => $video];
        if ($video->isVertical()) {
            [$previousVideo, $nextVideo] = $this->verticalNeighbors($post);
            $viewData['previousVideo'] = $previousVideo;
            $viewData['nextVideo'] = $nextVideo;
        } else {
            $viewData['relatedVideos'] = $this->relatedVideos($post);
        }

        return $this->render('@frontend/views/site/view-video', $viewData);
    }

    /** @return Post[] Same-orientation videos first, then the rest. */
    private function relatedVideos(Post $current, int $limit = 8): array
    {
        $horizontal = [];
        $vertical = [];
        foreach ($this->visibleVideoQuery()->andWhere(['<>', 'p.id', $current->id])->all() as $post) {
            if ($post->shortVideo === null) {
                continue;
            }
            if ($post->shortVideo->isVertical()) {
                $vertical[] = $post;
            } else {
                $horizontal[] = $post;
            }
        }

        return array_slice(array_merge($horizontal, $vertical), 0, $limit);
    }

    /** @return array{0: Post|null, 1: Post|null} [newer, older] vertical videos. */
    private function verticalNeighbors(Post $current): array
    {
        $vertical = [];
        foreach ($this->visibleVideoQuery()->all() as $post) {
            if ($post->shortVideo !== null && $post->shortVideo->isVertical()) {
                $vertical[] = $post;
            }
        }

        foreach ($vertical as $index => $post) {
            if ((int) $post->id === (int) $current->id) {
                return [$vertical[$index - 1] ?? null, $vertical[$index + 1] ?? null];
            }
        }

        return [null, null];
    }

    private function visibleVideoQuery(): ActiveQuery
    {
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'shortVideo'])
            ->where(['p.type' => Post::TYPE_SHORT_VIDEO, 'p.status' => Post::STATUS_PUBLISHED])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);
        $this->applyVisibilityFilter($query);

        return $query;
    }


    public function actionCreate(?string $type = null): string|Response
    {
        $model = new ShortVideo();
        $model->video_type = (int) array_key_first(ShortVideo::videoTypeOptions());
        $post = new Post();
        $tagError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $uploadedVideo = UploadedFile::getInstance($model, 'video_path');
            $uploadedCover = UploadedFile::getInstance($model, 'cover_path');
            $post->user_id = (int) Yii::$app->user->id;
            $post->type = Post::TYPE_SHORT_VIDEO;
            $post->status = Post::STATUS_PUBLISHED;
            $post->title = $this->contentTitle($model);
            $model->post_id = 0;
            $model->video_path = 'pending-upload';
            $model->duration_seconds = max(0, (int) $model->duration_seconds);

            $tagNames = $this->extractTagNames($model);
            $tagInputValid = count($tagNames) <= 10;
            if (!$tagInputValid) {
                $tagError = '标题和简介中最多包含 10 个 #话题#。';
            }

            $modelIsValid = $model->validate();

            // validate() clears errors first, so upload errors must be added after it.
            if ($uploadedVideo === null) {
                $model->addError('video_path', '请至少选择一个视频文件。');
            } elseif ($uploadedVideo->error !== UPLOAD_ERR_OK) {
                $model->addError('video_path', '视频上传失败，请重新选择。');
            } elseif (!in_array(strtolower($uploadedVideo->extension), ['mp4', 'webm', 'mov', 'ogv', 'ogg'], true)) {
                $model->addError('video_path', '仅支持 MP4、WebM、MOV 或 OGG 视频。');
            } elseif ($uploadedVideo->size > 500 * 1024 * 1024) {
                $model->addError('video_path', '视频文件不能超过 500 MB。');
            }
            if ($uploadedCover !== null) {
                if ($uploadedCover->error !== UPLOAD_ERR_OK) {
                    $model->addError('cover_path', '封面上传失败，请重新选择。');
                } elseif (!in_array(strtolower($uploadedCover->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                    || $uploadedCover->size > 10 * 1024 * 1024
                    || @getimagesize($uploadedCover->tempName) === false) {
                    $model->addError('cover_path', '封面必须是有效的图片，且不超过 10 MB。');
                }
            }

            if ($post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid) {
                $transaction = Yii::$app->db->beginTransaction();
                $videoPath = null;
                $coverPath = null;
                try {
                    $coverPath = $uploadedCover !== null
                        ? $this->storeUploadedFile($uploadedCover, '@frontend/web/uploads/video-covers')
                        : $this->generateVideoCover($uploadedVideo);
                    $videoPath = $this->storeUploadedFile($uploadedVideo, '@frontend/web/uploads/videos');
                    if ($videoPath === null) {
                        throw new \RuntimeException('视频上传失败。');
                    }
                    if (!$post->save(false)) {
                        throw new \RuntimeException('无法保存内容记录。');
                    }
                    if ($coverPath === null) {
                        $model->addError('cover_path', $uploadedCover !== null
                            ? '封面上传失败，请重试。'
                            : '无法自动生成视频第 5 帧封面，请检查 FFmpeg 配置后重试。');
                        $transaction->rollBack();
                        $this->deleteStoredMedia([$videoPath, $coverPath]);
                        return $this->renderForm($model, $post, $tagError, false);
                    }

                    $model->post_id = $post->id;
                    $model->video_path = $videoPath;
                    $model->cover_path = $coverPath;
                    if (!$model->save()) {
                        $transaction->rollBack();
                        $this->deleteStoredMedia([$videoPath, $coverPath]);
                        return $this->renderForm($model, $post, $tagError, false);
                    }

                    $this->syncPostTags($post, $tagNames);
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', '内容已发布。');

                    return $this->redirect(['/content/view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    foreach ([$videoPath, $coverPath] as $storedPath) {
                        if ($storedPath !== null) {
                            $absolutePath = Yii::getAlias('@frontend/web') . $storedPath;
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

        return $this->renderForm($model, $post, $tagError, false);
    }

    public function actionUpdate(int $id): string|Response
    {
        $post = $this->findVideoPost($id);
        $this->ensureOwner($post);

        $model = $post->shortVideo;
        if ($model === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $tagError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $uploadedVideo = UploadedFile::getInstance($model, 'video_path');
            $uploadedCover = UploadedFile::getInstance($model, 'cover_path');
            $oldVideoPath = (string) $model->video_path;
            $oldCoverPath = (string) $model->cover_path;
            $model->post_id = (int) $post->id;
            $post->title = $this->contentTitle($model);
            $model->duration_seconds = max(0, (int) $model->duration_seconds);

            $tagNames = $this->extractTagNames($model);
            $tagInputValid = count($tagNames) <= 10;
            if (!$tagInputValid) {
                $tagError = '标题和简介中最多包含 10 个 #话题#。';
            }

            // validate() clears errors first, so upload errors must be added after it.
            $modelIsValid = $model->validate();

            $replaceVideo = false;
            if ($uploadedVideo !== null) {
                if ($uploadedVideo->error !== UPLOAD_ERR_OK) {
                    $model->addError('video_path', '视频上传失败，请重新选择。');
                } elseif (!in_array(strtolower($uploadedVideo->extension), ['mp4', 'webm', 'mov', 'ogv', 'ogg'], true)) {
                    $model->addError('video_path', '仅支持 MP4、WebM、MOV 或 OGG 视频。');
                } elseif ($uploadedVideo->size > 500 * 1024 * 1024) {
                    $model->addError('video_path', '视频文件不能超过 500 MB。');
                } else {
                    $replaceVideo = true;
                }
            }

            $replaceCover = false;
            if ($uploadedCover !== null) {
                if ($uploadedCover->error !== UPLOAD_ERR_OK) {
                    $model->addError('cover_path', '封面上传失败，请重新选择。');
                } elseif (!in_array(strtolower($uploadedCover->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                    || $uploadedCover->size > 10 * 1024 * 1024
                    || @getimagesize($uploadedCover->tempName) === false) {
                    $model->addError('cover_path', '封面必须是有效的图片，且不超过 10 MB。');
                } else {
                    $replaceCover = true;
                }
            }

            if ($post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid) {
                $transaction = Yii::$app->db->beginTransaction();
                $videoPath = null;
                $coverPath = null;
                try {
                    if ($replaceVideo) {
                        $coverPath = $replaceCover
                            ? $this->storeUploadedFile($uploadedCover, '@frontend/web/uploads/video-covers')
                            : $this->generateVideoCover($uploadedVideo);
                        if ($replaceCover && $coverPath === null) {
                            throw new \RuntimeException('封面上传失败。');
                        }
                        $videoPath = $this->storeUploadedFile($uploadedVideo, '@frontend/web/uploads/videos');
                        if ($videoPath === null) {
                            throw new \RuntimeException('视频上传失败。');
                        }
                        $model->video_path = $videoPath;
                        // A thumbnail taken from the old video no longer matches the
                        // new one, so drop it even when generation fails.
                        $model->cover_path = $coverPath;
                    } elseif ($replaceCover) {
                        $coverPath = $this->storeUploadedFile($uploadedCover, '@frontend/web/uploads/video-covers');
                        if ($coverPath === null) {
                            throw new \RuntimeException('封面上传失败。');
                        }
                        $model->cover_path = $coverPath;
                    }

                    if (!$post->save(false)) {
                        $transaction->rollBack();
                        $this->deleteStoredMedia([$videoPath, $coverPath]);
                        $model->video_path = $oldVideoPath;
                        $model->cover_path = $oldCoverPath;
                        return $this->renderForm($model, $post, $tagError, true);
                    }
                    if (!$model->save()) {
                        $transaction->rollBack();
                        $this->deleteStoredMedia([$videoPath, $coverPath]);
                        $model->video_path = $oldVideoPath;
                        $model->cover_path = $oldCoverPath;
                        return $this->renderForm($model, $post, $tagError, true);
                    }

                    $this->syncPostTags($post, $tagNames);
                    $transaction->commit();
                    if ($videoPath !== null) {
                        $this->deleteStoredMedia([$oldVideoPath], [$videoPath]);
                    }
                    if ($coverPath !== null || $replaceVideo) {
                        $this->deleteStoredMedia([$oldCoverPath], $coverPath !== null ? [$coverPath] : []);
                    }
                    Yii::$app->session->setFlash('success', '内容已更新。');

                    return $this->redirect(['/content/view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    $this->deleteStoredMedia([$videoPath, $coverPath]);
                    if ($transaction->isActive) {
                        $transaction->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return $this->renderForm($model, $post, $tagError, true);
    }

    public function actionDelete(int $id): Response
    {
        $post = $this->findVideoPost($id);
        $this->ensureOwner($post);
        $video = $post->shortVideo;
        $mediaPaths = $video === null
            ? []
            : [(string) $video->video_path, (string) $video->cover_path];
        if ($post->delete() === false) {
            throw new \RuntimeException('无法删除内容记录。');
        }
        $this->deleteStoredMedia($mediaPaths);
        Yii::$app->session->setFlash('success', '内容已删除。');

        return $this->redirect(['/content/index']);
    }

    private function renderForm(
        ShortVideo $model,
        Post $post,
        ?string $tagError,
        bool $isUpdate,
    ): string {
        return $this->render('create', [
            'model' => $model,
            'post' => $post,
            'isUpdate' => $isUpdate,
            'tagError' => $tagError,
        ]);
    }

    private function findVideoPost(int $id): Post
    {
        $post = Post::findOne($id);
        if ($post === null || $post->type !== Post::TYPE_SHORT_VIDEO) {
            throw new NotFoundHttpException('找不到该视频。');
        }

        return $post;
    }

    private function ensureOwner(Post $post): void
    {
        if (Yii::$app->user->isGuest || (int) $post->user_id !== (int) Yii::$app->user->id) {
            throw new ForbiddenHttpException('你无权修改此内容。');
        }
    }

    private function applyVisibilityFilter(ActiveQuery $query, string $alias = 'p'): void
    {
        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        if ($userId === null) {
            $query->andWhere([$alias . '.visibility' => Post::VISIBILITY_PUBLIC]);
            return;
        }

        $query->andWhere([
            'or',
            [$alias . '.visibility' => Post::VISIBILITY_PUBLIC],
            [$alias . '.visibility' => Post::VISIBILITY_REGISTERED_USERS],
            ['and', [$alias . '.visibility' => Post::VISIBILITY_PRIVATE], [$alias . '.user_id' => $userId]],
        ]);
    }

    /** Tags used by published short videos, ordered by usage. @return Tag[] */
    private function videoTags(int $limit = 10): array
    {
        $query = Tag::find()
            ->alias('t')
            ->select(['t.id', 't.name', 't.slug', 'postCount' => 'COUNT([[pt]].[[post_id]])'])
            ->innerJoin(['pt' => '{{%post_tag}}'], 'pt.tag_id = t.id')
            ->innerJoin(['p' => '{{%post}}'], 'p.id = pt.post_id')
            ->where(['p.type' => Post::TYPE_SHORT_VIDEO, 'p.status' => Post::STATUS_PUBLISHED])
            ->groupBy(['t.id', 't.name', 't.slug'])
            ->orderBy(['postCount' => SORT_DESC, 't.name' => SORT_ASC])
            ->limit($limit);
        $this->applyVisibilityFilter($query);

        return $query->all();
    }

    private function loadContentModel(ActiveRecord $model): bool
    {
        $attributes = Yii::$app->request->post($model->formName());
        if (!is_array($attributes)) {
            return false;
        }

        foreach (self::FIELDS as $field) {
            // File inputs are handled through UploadedFile; an empty POST value
            // must never overwrite the stored path on update.
            if (in_array($field, ['video_path', 'cover_path'], true)) {
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

        return $title !== '' ? $title : '短视频';
    }

    /** @return string[] #话题# names found in the title and description. */
    private function extractTagNames(ShortVideo $model): array
    {
        return Tag::extractHashtags(trim((string) $model->title) . "\n" . (string) $model->description);
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

    /** @param string[] $paths @param string[] $keepPaths */
    private function deleteStoredMedia(array $paths, array $keepPaths = []): void
    {
        $webRoot = Yii::getAlias('@frontend/web');
        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path === '' || in_array($path, $keepPaths, true) || !str_starts_with($path, '/uploads/')) {
                continue;
            }
            $absolute = $webRoot . $path;
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }
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
}
