<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\BlogPost;
use common\models\Cate;
use common\models\Post;
use common\models\Tag;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class BlogController extends Controller
{
    use ContentVisibilityTrait;

    private const FIELDS = ['title', 'summary', 'content'];

    public function getViewPath(): string
    {
        return Yii::getAlias('@frontend/views/blog');
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
        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;

        $query = Post::find()
            ->alias('p')
            ->with(['user', 'blog', 'tags', 'categories'])
            ->where(['p.type' => Post::TYPE_BLOG, 'p.status' => Post::STATUS_PUBLISHED])
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        if ($userId === null) {
            $query->andWhere(['p.visibility' => Post::VISIBILITY_PUBLIC]);
        } else {
            $query->andWhere([
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                ['and', ['p.visibility' => Post::VISIBILITY_PRIVATE], ['p.user_id' => $userId]],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 10],
            'sort' => false,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'tagCounts' => $this->tagPostCounts($dataProvider->getModels()),
            'blogCategories' => $this->blogCategories(),
        ]);
    }

    /** Categories used by published blog posts. @return Cate[] */
    private function blogCategories(int $limit = 10): array
    {
        $userId = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;

        $query = Cate::find()
            ->alias('c')
            ->select(['c.id', 'c.name', 'c.slug', 'postCount' => 'COUNT([[p]].[[id]])'])
            ->innerJoin(['pc' => '{{%post_cate}}'], 'pc.cate_id = c.id')
            ->innerJoin(['p' => '{{%post}}'], 'p.id = pc.post_id')
            ->where(['p.type' => Post::TYPE_BLOG, 'p.status' => Post::STATUS_PUBLISHED])
            ->groupBy(['c.id', 'c.name', 'c.slug'])
            ->orderBy(['postCount' => SORT_DESC, 'c.name' => SORT_ASC])
            ->limit($limit);

        if ($userId === null) {
            $query->andWhere(['p.visibility' => Post::VISIBILITY_PUBLIC]);
        } else {
            $query->andWhere([
                'or',
                ['p.visibility' => Post::VISIBILITY_PUBLIC],
                ['p.visibility' => Post::VISIBILITY_REGISTERED_USERS],
                ['and', ['p.visibility' => Post::VISIBILITY_PRIVATE], ['p.user_id' => $userId]],
            ]);
        }

        return $query->all();
    }

    /** @param Post[] $posts @return array<int,int> */
    private function tagPostCounts(array $posts): array
    {
        $tagIds = [];
        foreach ($posts as $post) {
            foreach ($post->tags as $tag) {
                $tagIds[(int) $tag->id] = true;
            }
        }
        if ($tagIds === []) {
            return [];
        }

        $rows = Tag::find()
            ->alias('t')
            ->select(['t.id', 'postCount' => 'COUNT([[pt]].[[post_id]])'])
            ->innerJoin(['pt' => '{{%post_tag}}'], 'pt.tag_id = t.id')
            ->where(['t.id' => array_keys($tagIds)])
            ->groupBy('t.id')
            ->asArray()
            ->all();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['postCount'];
        }

        return $counts;
    }

    public function actionView(int $id): string
    {
        $post = $this->findTypedContentPost($id, Post::TYPE_BLOG);
        if (!$this->canViewContent($post)) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $blog = $post->blog;
        if ($blog === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }

        return $this->render('@frontend/views/site/view-blog', ['post' => $post, 'blog' => $blog]);
    }

    public function actionCreate(?string $type = null): string|Response
    {
        $model = new BlogPost();
        $post = new Post();
        $selectedTagNames = [];
        $tagError = null;
        $selectedCateId = 0;
        $cateError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $editorImages = UploadedFile::getInstancesByName('editor_images');
            $post->user_id = (int) Yii::$app->user->id;
            $post->type = Post::TYPE_BLOG;
            $post->status = Post::STATUS_PUBLISHED;
            $post->title = $this->contentTitle($model);
            $model->post_id = 0;

            $parsedTagNames = $this->submittedTagNames();
            $tagInputValid = $parsedTagNames !== null;
            $selectedTagNames = $parsedTagNames ?? [];
            if (!$tagInputValid) {
                $tagError = '最多添加 10 个标签，每个标签不超过 50 个字符。';
            }
            $submittedCateId = $this->submittedCateId();
            $cateInputValid = $submittedCateId !== null;
            $selectedCateId = $submittedCateId ?? 0;
            if (!$cateInputValid) {
                $cateError = '请选择分类。';
            }

            $modelIsValid = $model->validate();
            $this->validateRichEditorImages($model, $editorImages);

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
                        if (!$model->validate()) {
                            $transaction->rollBack();
                            $this->deleteRichEditorImages($richImagePaths);
                            return $this->renderForm($model, $post, $selectedCateId, $cateError, $selectedTagNames, $tagError, false);
                        }
                    }

                    if (!$post->save(false)) {
                        throw new \RuntimeException('无法保存内容记录。');
                    }
                    $model->post_id = $post->id;
                    $this->applyRichEditorThumbnail($model, $richImagePaths);
                    if (!$model->save()) {
                        $transaction->rollBack();
                        return $this->renderForm($model, $post, $selectedCateId, $cateError, $selectedTagNames, $tagError, false);
                    }

                    $this->syncPostCategories($post, $selectedCateId);
                    $this->syncPostTags($post, $selectedTagNames);
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

        return $this->renderForm($model, $post, $selectedCateId, $cateError, $selectedTagNames, $tagError, false);
    }

    public function actionUpdate(int $id): string|Response
    {
        $post = $this->findBlogPost($id);
        $this->ensureOwner($post);

        $model = $post->blog;
        if ($model === null) {
            throw new NotFoundHttpException('找不到该内容。');
        }
        $selectedTagNames = array_map(static fn (Tag $tag): string => $tag->name, $post->tags);
        $tagError = null;
        $selectedCateId = (int) ($post->categories[0]->id ?? 0);
        $cateError = null;

        if ($this->loadContentModel($model) && $this->loadVisibility($post)) {
            $editorImages = UploadedFile::getInstancesByName('editor_images');
            $model->post_id = (int) $post->id;
            $post->title = $this->contentTitle($model);

            $parsedTagNames = $this->submittedTagNames();
            $tagInputValid = $parsedTagNames !== null;
            $selectedTagNames = $parsedTagNames ?? [];
            if (!$tagInputValid) {
                $tagError = '最多添加 10 个标签，每个标签不超过 50 个字符。';
            }
            $submittedCateId = $this->submittedCateId();
            $cateInputValid = $submittedCateId !== null;
            $selectedCateId = $submittedCateId ?? 0;
            if (!$cateInputValid) {
                $cateError = '请选择分类。';
            }

            $modelIsValid = $model->validate();
            $this->validateRichEditorImages($model, $editorImages);

            if ($post->validate() && $modelIsValid && !$model->hasErrors() && $tagInputValid && $cateInputValid) {
                $transaction = Yii::$app->db->beginTransaction();
                $richImagePaths = [];
                $replacedThumb = null;
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
                            return $this->renderForm($model, $post, $selectedCateId, $cateError, $selectedTagNames, $tagError, true);
                        }
                    }

                    $replacedThumb = $this->applyRichEditorThumbnail($model, $richImagePaths);
                    if (!$post->save(false)) {
                        $transaction->rollBack();
                        return $this->renderForm($model, $post, $selectedCateId, $cateError, $selectedTagNames, $tagError, true);
                    }
                    if (!$model->save()) {
                        $transaction->rollBack();
                        return $this->renderForm($model, $post, $selectedCateId, $cateError, $selectedTagNames, $tagError, true);
                    }

                    $this->syncPostCategories($post, $selectedCateId);
                    $this->syncPostTags($post, $selectedTagNames);
                    $transaction->commit();
                    if ($replacedThumb !== null) {
                        $this->deleteBlogThumbnail($replacedThumb);
                    }
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

        return $this->renderForm($model, $post, $selectedCateId, $cateError, $selectedTagNames, $tagError, true);
    }

    public function actionDelete(int $id): Response
    {
        $post = $this->findBlogPost($id);
        $this->ensureOwner($post);
        if ($post->delete() === false) {
            throw new \RuntimeException('无法删除内容记录。');
        }
        Yii::$app->session->setFlash('success', '内容已删除。');

        return $this->redirect(['/content/index']);
    }

    private function renderForm(
        BlogPost $model,
        Post $post,
        int $selectedCateId,
        ?string $cateError,
        array $selectedTagNames,
        ?string $tagError,
        bool $isUpdate,
    ): string {
        return $this->render('create', [
            'model' => $model,
            'post' => $post,
            'isUpdate' => $isUpdate,
            'selectedCateId' => $selectedCateId,
            'cateError' => $cateError,
            'selectedTagNames' => $selectedTagNames,
            'tagError' => $tagError,
        ]);
    }

    private function findBlogPost(int $id): Post
    {
        $post = Post::findOne($id);
        if ($post === null || $post->type !== Post::TYPE_BLOG) {
            throw new NotFoundHttpException('找不到该博客。');
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

    private function contentTitle(ActiveRecord $model): string
    {
        if (trim((string) $model->getAttribute('title')) !== '') {
            return trim((string) $model->getAttribute('title'));
        }

        $text = html_entity_decode(strip_tags((string) $model->getAttribute('content')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = mb_substr(trim($text), 0, 200);

        return $title !== '' ? $title : '博客';
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
        $year = date('Y');
        $directory = $webRoot . '/uploads/blog/' . $year;
        $thumbDirectory = $webRoot . '/uploads/thumb/blog';

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

            $paths[] = '/uploads/blog/' . $year . '/' . $name;

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

    /**
     * Sync a blog cover with its body images: new uploads always become the cover,
     * otherwise an empty cover is filled from the first stored body image.
     * Returns the previous cover path when it was replaced.
     */
    private function applyRichEditorThumbnail(ActiveRecord $model, array $paths): ?string
    {
        if (!$model->hasAttribute('thumb')) {
            return null;
        }

        $current = (string) ($model->getAttribute('thumb') ?? '');
        $thumb = null;

        if ($paths !== []) {
            $thumb = $this->blogThumbnailPath($paths[0]);
        } elseif ($current === '') {
            preg_match_all(
                '#/uploads/blog/\d{4}/[A-Za-z0-9_-]+\.[A-Za-z0-9]+#',
                (string) $model->getAttribute('content'),
                $matches,
            );
            foreach (array_unique($matches[0]) as $candidate) {
                $thumb = $this->blogThumbnailPath($candidate);
                if ($thumb !== null) {
                    break;
                }
            }
        }

        if ($thumb === null || $thumb === $current) {
            return null;
        }

        $model->setAttribute('thumb', $thumb);

        return $current !== '' ? $current : null;
    }

    private function blogThumbnailPath(string $imagePath): ?string
    {
        if (!preg_match('#^/uploads/blog/\d{4}/([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', $imagePath, $match)) {
            return null;
        }

        $thumb = 'uploads/thumb/blog/' . $match[1];

        return is_file(Yii::getAlias('@frontend/web') . '/' . $thumb) ? $thumb : null;
    }

    private function deleteBlogThumbnail(string $thumbPath): void
    {
        if (!preg_match('#^uploads/thumb/blog/[A-Za-z0-9_-]+\.[A-Za-z0-9]+$#', $thumbPath)) {
            return;
        }

        if (BlogPost::find()->where(['thumb' => $thumbPath])->exists()) {
            return;
        }

        $file = Yii::getAlias('@frontend/web') . '/' . $thumbPath;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** @param string[] $paths */
    private function deleteRichEditorImages(array $paths): void
    {
        $webRoot = Yii::getAlias('@frontend/web');
        foreach ($paths as $path) {
            if (!preg_match('#^/uploads/blog/(\d{4})/([A-Za-z0-9_-]+\.[A-Za-z0-9]+)$#', $path, $matches)) {
                continue;
            }
            $files = [
                $webRoot . '/uploads/blog/' . $matches[1] . '/' . $matches[2],
                $webRoot . '/uploads/thumb/blog/' . $matches[2],
            ];
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
