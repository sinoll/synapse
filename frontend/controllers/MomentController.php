<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Cate;
use common\models\Moment;
use common\models\Post;
use common\models\Tag;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class MomentController extends Controller
{
    use ContentVisibilityTrait;

    private const FIELDS = ['content'];

    public function getViewPath(): string
    {
        return Yii::getAlias('@frontend/views/moment');
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
        $post = $this->findTypedContentPost($id, Post::TYPE_MOMENT);
        if (!$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $moment = $post->moment;
        if ($moment === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $this->render('@frontend/views/site/view-moment', ['post' => $post, 'moment' => $moment]);
    }

    public function actionIndex(?string $cate = null, int $page = 1): string
    {
        $activeCate = $this->findCateBySlug($cate);
        $page = max(1, $page);
        [$posts, $hasMore] = $this->momentFeed($activeCate?->id, $page);

        return $this->render('index', [
            'posts' => $posts,
            'hasMore' => $hasMore,
            'nextPage' => $page + 1,
            'activeCate' => $activeCate,
            'cateTabs' => $this->momentCateTabs(),
            'momentTags' => $this->momentTags(),
        ]);
    }

    /** Return the next page of the moment feed. */
    public function actionFeed(?string $cate = null, int $page = 1): Response
    {
        $activeCate = $this->findCateBySlug($cate);
        [$posts, $hasMore] = $this->momentFeed($activeCate?->id, max(1, $page));

        Yii::$app->response->format = Response::FORMAT_JSON;

        return $this->asJson([
            'html' => $this->renderPartial('_items', ['posts' => $posts]),
            'hasMore' => $hasMore,
        ]);
    }

    private function findCateBySlug(?string $slug): ?Cate
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $cate = Cate::find()->where(['slug' => $slug])->one();
        if ($cate === null) {
            throw new NotFoundHttpException('找不到该分类。');
        }

        return $cate;
    }

    /** @return array{0: Post[], 1: bool} */
    private function momentFeed(?int $cateId, int $page): array
    {
        $pageSize = 10;
        $query = $this->momentQuery($cateId);
        $total = (int) (clone $query)->count();
        $posts = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

        return [$posts, $page * $pageSize < $total];
    }

    private function momentQuery(?int $cateId): ActiveQuery
    {
        $query = Post::find()
            ->alias('p')
            ->with(['user', 'moment', 'tags', 'categories'])
            ->where(['p.type' => Post::TYPE_MOMENT, 'p.status' => Post::STATUS_PUBLISHED])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        if ($cateId !== null) {
            $query->innerJoin(['pc' => '{{%post_cate}}'], 'pc.post_id = p.id')
                ->andWhere(['pc.cate_id' => $cateId]);
        }

        $this->applyVisibilityFilter($query);

        return $query;
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

    /** @return Cate[] */
    private function momentCateTabs(): array
    {
        $query = Cate::find()
            ->alias('c')
            ->select(['c.id', 'c.name', 'c.slug', 'postCount' => 'COUNT([[p]].[[id]])'])
            ->innerJoin(['pc' => '{{%post_cate}}'], 'pc.cate_id = c.id')
            ->innerJoin(['p' => '{{%post}}'], 'p.id = pc.post_id')
            ->where(['p.type' => Post::TYPE_MOMENT, 'p.status' => Post::STATUS_PUBLISHED])
            ->groupBy(['c.id', 'c.name', 'c.slug'])
            ->orderBy(['postCount' => SORT_DESC, 'c.name' => SORT_ASC]);
        $this->applyVisibilityFilter($query);

        return $query->all();
    }

    /** Tags used by published moments, ordered by usage. @return Tag[] */
    private function momentTags(int $limit = 10): array
    {
        $query = Tag::find()
            ->alias('t')
            ->select(['t.id', 't.name', 't.slug', 'postCount' => 'COUNT([[pt]].[[post_id]])'])
            ->innerJoin(['pt' => '{{%post_tag}}'], 'pt.tag_id = t.id')
            ->innerJoin(['p' => '{{%post}}'], 'p.id = pt.post_id')
            ->where(['p.type' => Post::TYPE_MOMENT, 'p.status' => Post::STATUS_PUBLISHED])
            ->groupBy(['t.id', 't.name', 't.slug'])
            ->orderBy(['postCount' => SORT_DESC, 't.name' => SORT_ASC])
            ->limit($limit);
        $this->applyVisibilityFilter($query);

        return $query->all();
    }

    public function actionCreate(?string $type = null): string|Response
    {
        $model = new Moment();
        $post = new Post();
        $contentError = null;
        $selectedCateId = $this->defaultMomentCateId();
        $cateError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $editorImages = UploadedFile::getInstancesByName('editor_images');
            $post->user_id = (int) Yii::$app->user->id;
            $post->type = Post::TYPE_MOMENT;
            $post->status = Post::STATUS_PUBLISHED;
            $post->title = $this->contentTitle($model, 8);
            $model->post_id = 0;

            $tagNames = Tag::extractHashtags((string) $model->getAttribute('content'));
            $tagInputValid = count($tagNames) <= 10;
            if (!$tagInputValid) {
                $contentError = '正文中最多包含 10 个 #话题#。';
            }
            $submittedCateId = $this->submittedCateId();
            $cateInputValid = $submittedCateId !== null;
            $selectedCateId = $submittedCateId ?? $this->defaultMomentCateId();
            if (!$cateInputValid) {
                $cateError = '请选择分类。';
            }

            $modelIsValid = $model->validate();
            $this->validateRichEditorImages($model, $editorImages);
            $this->validateEditorImageTokens($model, $editorImages);

            if ($post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid && $cateInputValid) {
                $transaction = Yii::$app->db->beginTransaction();
                $richImagePaths = [];
                try {
                    if ($editorImages !== []) {
                        $richImagePaths = $this->storeRichEditorImages($editorImages);
                        if (count($richImagePaths) !== count($editorImages)) {
                            throw new \RuntimeException('Rich-text image upload failed.');
                        }
                        $this->replaceRichEditorImageTokens($model, $richImagePaths);
                        if (!$model->validate() || $this->hasUnresolvedImageTokens($model)) {
                            $transaction->rollBack();
                            $this->deleteRichEditorImages($richImagePaths);
                            $this->validateEditorImageTokens($model, []);
                            return $this->renderForm($model, $post, $contentError, false, $selectedCateId, $cateError);
                        }
                    }

                    if (!$post->save(false)) {
                        throw new \RuntimeException('无法保存内容记录。');
                    }
                    $model->post_id = $post->id;
                    $model->title = $post->title;
                    $model->thumb = $this->resolveMomentThumbnail($richImagePaths, (string) $model->getAttribute('content'));
                    if (!$model->save()) {
                        $transaction->rollBack();
                        return $this->renderForm($model, $post, $contentError, false, $selectedCateId, $cateError);
                    }

                    $this->syncPostCategories($post, $selectedCateId);
                    $this->syncPostTags($post, $tagNames);
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', '内容已发布。');

                    return $this->redirect(['/content/view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    $this->deleteRichEditorImages($richImagePaths);
                    if ($transaction->isActive) {
                        $transaction->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return $this->renderForm($model, $post, $contentError, false, $selectedCateId, $cateError);
    }

    public function actionUpdate(int $id): string|Response
    {
        $post = $this->findMomentPost($id);
        $this->ensureOwner($post);

        $model = $post->moment;
        if ($model === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $contentError = null;
        $selectedCateId = $this->existingMomentCateId($post);
        $cateError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $editorImages = UploadedFile::getInstancesByName('editor_images');
            $model->post_id = (int) $post->id;
            $post->title = $this->contentTitle($model, 8);

            $tagNames = Tag::extractHashtags((string) $model->getAttribute('content'));
            $tagInputValid = count($tagNames) <= 10;
            if (!$tagInputValid) {
                $contentError = '正文中最多包含 10 个 #话题#。';
            }
            $submittedCateId = $this->submittedCateId();
            $cateInputValid = $submittedCateId !== null;
            $selectedCateId = $submittedCateId ?? $this->existingMomentCateId($post);
            if (!$cateInputValid) {
                $cateError = '请选择分类。';
            }

            $modelIsValid = $model->validate();
            $this->validateRichEditorImages($model, $editorImages);
            $this->validateEditorImageTokens($model, $editorImages);

            if ($post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid && $cateInputValid) {
                $transaction = Yii::$app->db->beginTransaction();
                $richImagePaths = [];
                try {
                    if ($editorImages !== []) {
                        $richImagePaths = $this->storeRichEditorImages($editorImages);
                        if (count($richImagePaths) !== count($editorImages)) {
                            throw new \RuntimeException('Rich-text image upload failed.');
                        }
                        $this->replaceRichEditorImageTokens($model, $richImagePaths);
                        if (!$model->validate() || $this->hasUnresolvedImageTokens($model)) {
                            $transaction->rollBack();
                            $this->deleteRichEditorImages($richImagePaths);
                            $this->validateEditorImageTokens($model, []);
                            return $this->renderForm($model, $post, $contentError, true, $selectedCateId, $cateError);
                        }
                    }

                    $model->title = $post->title;
                    $model->thumb = $this->resolveMomentThumbnail($richImagePaths, (string) $model->getAttribute('content'));
                    if (!$post->save(false)) {
                        $transaction->rollBack();
                        return $this->renderForm($model, $post, $contentError, true, $selectedCateId, $cateError);
                    }
                    if (!$model->save()) {
                        $transaction->rollBack();
                        return $this->renderForm($model, $post, $contentError, true, $selectedCateId, $cateError);
                    }

                    $this->syncPostCategories($post, $selectedCateId);
                    $this->syncPostTags($post, $tagNames);
                    $transaction->commit();
                    Yii::$app->session->setFlash('success', '内容已更新。');

                    return $this->redirect(['/content/view', 'id' => $post->id]);
                } catch (\Throwable $exception) {
                    $this->deleteRichEditorImages($richImagePaths);
                    if ($transaction->isActive) {
                        $transaction->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return $this->renderForm($model, $post, $contentError, true, $selectedCateId, $cateError);
    }

    public function actionDelete(int $id): Response
    {
        $post = $this->findMomentPost($id);
        $this->ensureOwner($post);
        if ($post->delete() === false) {
            throw new \RuntimeException('无法删除内容记录。');
        }
        Yii::$app->session->setFlash('success', '内容已删除。');

        return $this->redirect(['/content/index']);
    }

    private function renderForm(
        Moment $model,
        Post $post,
        ?string $contentError,
        bool $isUpdate,
        int $selectedCateId,
        ?string $cateError,
    ): string {
        return $this->render($isUpdate ? 'update' : 'create', [
            'model' => $model,
            'post' => $post,
            'contentError' => $contentError,
            'selectedCateId' => $selectedCateId,
            'cateError' => $cateError,
        ]);
    }

    private function findMomentPost(int $id): Post
    {
        $post = Post::findOne($id);
        if ($post === null || $post->type !== Post::TYPE_MOMENT) {
            throw new NotFoundHttpException('找不到该动态。');
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

    /** 由正文前几个字生成标题。 */
    private function contentTitle(ActiveRecord $model, int $maxLength): string
    {
        $text = html_entity_decode(strip_tags((string) $model->getAttribute('content')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\[\[EDITOR_IMAGE_\d+\]\]/', '', $text) ?? $text;
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $title = trim(mb_substr($text, 0, $maxLength));

        // 标题截断时不要留下半个 #话题#。
        $lastHash = mb_strrpos($title, '#');
        if ($lastHash !== false && mb_substr_count($title, '#') % 2 === 1) {
            $title = trim(mb_substr($title, 0, $lastHash));
        }

        return $title !== '' ? $title : '动态';
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

    /** @return int Category used when a new moment has no explicit choice. */
    private function defaultMomentCateId(): int
    {
        $cateId = Cate::find()->select(['id'])->where(['name' => '生活瞬间'])->scalar();
        if ($cateId === false || $cateId === null) {
            $cateId = Cate::find()->select(['id'])->orderBy(['id' => SORT_ASC])->scalar();
        }

        return $cateId === false || $cateId === null ? 0 : (int) $cateId;
    }

    /** Keep the category already assigned when editing, otherwise use the default. */
    private function existingMomentCateId(Post $post): int
    {
        $cateId = (int) ($post->categories[0]->id ?? 0);

        return $cateId > 0 ? $cateId : $this->defaultMomentCateId();
    }

    /** @return int|null Null means the submitted category is missing or invalid. */
    private function submittedCateId(): ?int
    {
        $cateId = filter_var(Yii::$app->request->post('cate_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($cateId === false || !Cate::find()->where(['id' => $cateId])->exists()) {
            return null;
        }

        return (int) $cateId;
    }

    /** Replace a post's category inside the caller's current transaction. */
    private function syncPostCategories(Post $post, int $cateId): void
    {
        Yii::$app->db->createCommand()->delete('{{%post_cate}}', ['post_id' => $post->id])->execute();
        if ($cateId > 0) {
            Yii::$app->db->createCommand()->insert('{{%post_cate}}', [
                'post_id' => $post->id,
                'cate_id' => $cateId,
            ])->execute();
        }
    }

    /** @param string[] $newImagePaths */
    private function resolveMomentThumbnail(array $newImagePaths, string $content): string
    {
        if ($newImagePaths !== []) {
            return $this->momentThumbnailPath($newImagePaths[0]) ?? '/images/default-cover.png';
        }

        preg_match_all('#/uploads/(?:moment/\d{4}/\d{2}|blog/\d{4})/[A-Za-z0-9_-]+\.[A-Za-z0-9]+#', $content, $matches);
        foreach (array_unique($matches[0]) as $candidate) {
            $thumbnail = $this->momentThumbnailPath($candidate);
            if ($thumbnail !== null) {
                return $thumbnail;
            }
        }

        return '/images/default-cover.png';
    }

    private function momentThumbnailPath(string $imagePath): ?string
    {
        $webRoot = Yii::getAlias('@frontend/web');

        if (preg_match('#^/uploads/moment/(\d{4})/(\d{2})/([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', $imagePath, $match)) {
            $thumbnail = '/uploads/thumb/moment/' . $match[1] . '/' . $match[2] . '/' . $match[3];
        } elseif (preg_match('#^/uploads/blog/\d{4}/([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', $imagePath, $match)) {
            $thumbnail = '/uploads/thumb/blog/' . $match[1];
        } else {
            return null;
        }

        if (is_file($webRoot . $thumbnail)) {
            return $thumbnail;
        }

        return is_file($webRoot . $imagePath) ? $imagePath : null;
    }

    /** 没有实际收到图片时阻止保存残缺的 [[EDITOR_IMAGE_n]] 占位符。 */
    private function validateEditorImageTokens(ActiveRecord $model, array $files): void
    {
        if ($files === [] && $this->hasUnresolvedImageTokens($model)) {
            $model->addError('content', '图片上传失败，请重新选择图片后再发布。');
        }
    }

    private function hasUnresolvedImageTokens(ActiveRecord $model): bool
    {
        return (bool) preg_match('/\[\[EDITOR_IMAGE_\d+\]\]/', (string) $model->getAttribute('content'));
    }

    /** @param UploadedFile[] $files */
    private function validateRichEditorImages(ActiveRecord $model, array $files): void
    {
        if (count($files) > 20) {
            $model->addError('content', '最多一次上传 20 张图片。');
        }
        foreach ($files as $file) {
            if ($file->error !== UPLOAD_ERR_OK
                || !in_array(strtolower($file->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                || $file->size > 10 * 1024 * 1024
                || @getimagesize($file->tempName) === false) {
                $model->addError('content', '插入的图片必须是有效的 JPG、PNG、GIF 或 WebP 图片，且不超过 10 MB。');
            }
        }
    }

    /** @param UploadedFile[] $files @return string[] */
    private function storeRichEditorImages(array $files): array
    {
        $webRoot = Yii::getAlias('@frontend/web');
        $relativeDirectory = '/uploads/moment/' . date('Y') . '/' . date('m');
        $relativeThumbDirectory = '/uploads/thumb/moment/' . date('Y') . '/' . date('m');
        $directory = $webRoot . $relativeDirectory;
        $thumbDirectory = $webRoot . $relativeThumbDirectory;

        foreach ([$directory, $thumbDirectory] as $targetDirectory) {
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                return [];
            }
        }

        $paths = [];
        foreach ($files as $file) {
            $name = Yii::$app->security->generateRandomString(32) . '.' . strtolower($file->extension);
            $storedFile = $directory . DIRECTORY_SEPARATOR . $name;
            if (!$file->saveAs($storedFile)) {
                $this->deleteRichEditorImages($paths);
                return [];
            }

            $paths[] = $relativeDirectory . '/' . $name;

            $thumbnail = $thumbDirectory . DIRECTORY_SEPARATOR . $name;
            try {
                $generated = Yii::$app->get('ffmpegService')->createThumbnail($storedFile, $thumbnail);
            } catch (\Throwable $exception) {
                Yii::warning('Thumbnail generation failed: ' . $exception->getMessage(), __METHOD__);
                $generated = false;
            }

            if (!$generated || !is_file($thumbnail)) {
                if (is_file($thumbnail)) {
                    @unlink($thumbnail);
                }
                $this->deleteRichEditorImages($paths);
                return [];
            }
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
        $webRoot = Yii::getAlias('@frontend/web');
        foreach ($paths as $path) {
            if (preg_match('#^/uploads/moment/(\d{4})/(\d{2})/([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', $path, $matches)) {
                $files = [
                    $webRoot . $path,
                    $webRoot . '/uploads/thumb/moment/' . $matches[1] . '/' . $matches[2] . '/' . $matches[3],
                ];
            } elseif (preg_match('#^/uploads/blog/(\d{4})/([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', $path, $matches)) {
                $files = [
                    $webRoot . '/uploads/blog/' . $matches[1] . '/' . $matches[2],
                    $webRoot . '/uploads/thumb/blog/' . $matches[2],
                ];
            } else {
                continue;
            }
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
