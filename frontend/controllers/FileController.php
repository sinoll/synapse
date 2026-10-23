<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\Post;
use common\models\SharedFile;
use common\models\SharedFileFolder;
use common\models\SharedFileRecipient;
use common\models\User;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class FileController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [['allow' => true, 'roles' => ['@']]]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'delete' => ['POST'], 'restore' => ['POST'], 'create-folder' => ['POST'], 'share' => ['POST'],
            ]],
        ];
    }

    public function actionIndex(?int $folder = null, bool $trash = false): string
    {
        $userId = (int) Yii::$app->user->id;
        $folderModel = $folder === null ? null : SharedFileFolder::findOne(['id' => $folder, 'user_id' => $userId]);
        if ($folder !== null && $folderModel === null) {
            throw new NotFoundHttpException('文件夹不存在。');
        }
        $this->syncOwnedFileSizes($userId);
        $files = SharedFile::find()->joinWith('post')
            ->andWhere(['post.user_id' => $userId, 'shared_file.status' => $trash ? SharedFile::STATUS_TRASH : SharedFile::STATUS_ACTIVE])
            ->andWhere($trash ? ['not', ['shared_file.deleted_at' => null]] : ['shared_file.deleted_at' => null]);
        if (!$trash) {
            $files->andWhere(['shared_file.folder_id' => $folder]);
        }
        $folders = SharedFileFolder::find()->where(['user_id' => $userId, 'parent_id' => $folder, 'status' => SharedFileFolder::STATUS_ACTIVE])->orderBy(['name' => SORT_ASC])->all();
        $fileList = $files->with('post')->orderBy(['shared_file.updated_at' => SORT_DESC])->all();
        return $this->render('index', [
            'files' => $fileList,
            'folders' => $folders, 'folderModel' => $folderModel, 'folderId' => $folder, 'trash' => $trash,
        ]);
    }

    public function actionCreate(): string|Response
    {
        $folders = SharedFileFolder::find()->where(['user_id' => (int) Yii::$app->user->id, 'status' => SharedFileFolder::STATUS_ACTIVE])->orderBy(['name' => SORT_ASC])->all();
        $phpUploadLimit = (string) ini_get('upload_max_filesize');
        $phpPostLimit = (string) ini_get('post_max_size');
        $maxUploadBytes = 100 * 1024 * 1024;
        foreach ([$phpUploadLimit, $phpPostLimit] as $phpLimit) {
            $bytes = self::iniSizeToBytes($phpLimit);
            if ($bytes !== null) $maxUploadBytes = min($maxUploadBytes, $bytes);
        }
        $postLimitBytes = self::iniSizeToBytes($phpPostLimit);
        if ($postLimitBytes !== null && $postLimitBytes <= $maxUploadBytes) {
            $maxUploadBytes = max(1, $postLimitBytes - 256 * 1024);
        }
        $uploadLimits = [
            'maxFileBytes' => $maxUploadBytes,
            'maxFileLabel' => Yii::$app->formatter->asShortSize($maxUploadBytes),
            'uploadMax' => $phpUploadLimit,
            'postMax' => $phpPostLimit,
            'maxFiles' => (int) ini_get('max_file_uploads'),
            'uploadsEnabled' => (bool) ini_get('file_uploads'),
        ];
        if (Yii::$app->request->isPost) {
            $upload = UploadedFile::getInstanceByName('upload');
            $title = trim((string) Yii::$app->request->post('title', ''));
            $description = trim((string) Yii::$app->request->post('description', ''));
            $folderId = Yii::$app->request->post('folder_id');
            $visibility = filter_var(Yii::$app->request->post('visibility', Post::VISIBILITY_PRIVATE), FILTER_VALIDATE_INT);
            $recipientsRaw = trim((string) Yii::$app->request->post('recipients', ''));
            $errors = [];
            $allowed = ['pdf','txt','md','csv','zip','7z','rar','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','jpg','jpeg','png','gif','webp','mp3','wav','mp4','webm'];
            if (!$uploadLimits['uploadsEnabled']) $errors[] = 'PHP 配置已禁用文件上传。';
            elseif ($upload === null) $errors[] = '服务器没有收到文件；如果已选择文件，请确认文件与表单总大小没有超过 PHP post_max_size (' . $phpPostLimit . ')。';
            elseif ($upload->error === UPLOAD_ERR_INI_SIZE) $errors[] = '文件超过 PHP upload_max_filesize 限制 (' . $phpUploadLimit . ')。';
            elseif ($upload->error !== UPLOAD_ERR_OK) $errors[] = '文件上传失败，请重试。';
            elseif ($upload->size > $maxUploadBytes) $errors[] = '单个文件超过当前服务器允许的上限 ' . $uploadLimits['maxFileLabel'] . '。';
            elseif (!in_array(strtolower($upload->extension), $allowed, true)) $errors[] = '该文件类型暂不支持上传。';
            if ($title === '') $title = $upload?->name ?? '';
            if (mb_strlen($title) > 200) $errors[] = '文件标题不能超过 200 个字符。';
            if ($folderId !== '' && $folderId !== null && !SharedFileFolder::find()->where(['id' => (int) $folderId, 'user_id' => (int) Yii::$app->user->id, 'status' => SharedFileFolder::STATUS_ACTIVE])->exists()) $errors[] = '目标文件夹无效。';
            if (!in_array($visibility, [0, 1, 2, 3], true)) $errors[] = '分享权限无效。';
            $recipientIds = [];
            if ($visibility === Post::VISIBILITY_SELECTED_USERS) {
                $names = array_values(array_unique(preg_split('/[\s,;]+/', $recipientsRaw, -1, PREG_SPLIT_NO_EMPTY) ?: []));
                $users = $names ? User::find()->where(['username' => $names, 'status' => User::STATUS_ACTIVE])->andWhere(['not', ['phone_verified_at' => null]])->all() : [];
                if (!$names || count($users) !== count($names)) $errors[] = '指定用户模式下请输入有效且已验证的用户名。';
                else $recipientIds = array_map(static fn (User $user): int => (int) $user->id, $users);
            }
            if (!$errors && $upload !== null) {
                $relative = 'shared-files/' . Yii::$app->security->generateRandomString(40) . '.' . strtolower($upload->extension);
                $directory = Yii::getAlias('@runtime/shared-files');
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) $errors[] = '无法创建文件存储目录。';
                elseif (!$upload->saveAs($directory . DIRECTORY_SEPARATOR . basename($relative))) $errors[] = '文件保存失败，请重试。';
                else {
                    $post = new Post(['user_id' => (int) Yii::$app->user->id, 'type' => Post::TYPE_SHARED_FILE, 'title' => $title, 'status' => Post::STATUS_PUBLISHED, 'visibility' => $visibility]);
                    $file = new SharedFile(['post_id' => 0, 'folder_id' => $folderId ? (int) $folderId : null, 'title' => $title, 'description' => $description, 'file_path' => $relative, 'original_name' => $upload->name, 'mime_type' => $upload->type ?: 'application/octet-stream', 'size_bytes' => $upload->size]);
                    $tx = Yii::$app->db->beginTransaction();
                    try {
                        if (!$post->save()) throw new \RuntimeException('无法保存分享信息。');
                        $file->post_id = $post->id;
                        if (!$file->save()) throw new \RuntimeException('无法保存文件信息。');
                        foreach ($recipientIds as $recipientId) {
                            (new SharedFileRecipient(['shared_file_id' => $file->id, 'user_id' => $recipientId, 'created_at' => time()]))->save(false);
                        }
                        $tx->commit();
                        Yii::$app->session->setFlash('success', '文件上传完成。');
                        return $this->redirect(['/file/index', 'folder' => $file->folder_id]);
                    } catch (\Throwable $e) {
                        $tx->rollBack();
                        @unlink($directory . DIRECTORY_SEPARATOR . basename($relative));
                        Yii::error($e->getMessage(), __METHOD__);
                        $errors[] = '保存分享记录失败，请稍后重试。';
                    }
                }
            }
            foreach ($errors as $error) Yii::$app->session->addFlash('error', $error);
        }
        return $this->render('upload', ['folders' => $folders, 'uploadLimits' => $uploadLimits]);
    }

    public function actionCreateFolder(): Response
    {
        $name = trim((string) Yii::$app->request->post('name', ''));
        $parent = Yii::$app->request->post('parent_id');
        $folder = new SharedFileFolder(['user_id' => (int) Yii::$app->user->id, 'parent_id' => $parent ? (int) $parent : null, 'name' => $name]);
        if ($folder->save()) Yii::$app->session->setFlash('success', '文件夹已创建。');
        else Yii::$app->session->setFlash('error', $folder->getFirstError('name') ?? '文件夹创建失败。');
        return $this->redirect(['/file/index', 'folder' => $parent ?: null]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $file = $this->ownedFile($id);
        if (Yii::$app->request->isPost) {
            $title = trim((string) Yii::$app->request->post('title', ''));
            $description = trim((string) Yii::$app->request->post('description', ''));
            $folderId = Yii::$app->request->post('folder_id');
            $folderId = $folderId === '' || $folderId === null ? null : (int) $folderId;
            if ($title === '' || mb_strlen($title) > 200) Yii::$app->session->setFlash('error', '文件名称不能为空，且不能超过 200 个字符。');
            elseif ($folderId !== null && !SharedFileFolder::find()->where(['id' => $folderId, 'user_id' => (int) Yii::$app->user->id, 'status' => SharedFileFolder::STATUS_ACTIVE])->exists()) Yii::$app->session->setFlash('error', '目标文件夹无效。');
            else {
                $file->title = $title;
                $file->description = $description;
                $file->folder_id = $folderId;
                $file->post->title = $title;
                $tx = Yii::$app->db->beginTransaction();
                try {
                    if (!$file->post->save(false, ['title']) || !$file->save(false, ['title', 'description', 'folder_id'])) throw new \RuntimeException('save failed');
                    $tx->commit();
                    Yii::$app->session->setFlash('success', '文件信息已更新。');
                    return $this->redirect(['/file/index', 'folder' => $folderId]);
                } catch (\Throwable $e) {
                    $tx->rollBack();
                    Yii::error($e->getMessage(), __METHOD__);
                    Yii::$app->session->setFlash('error', '更新失败，请重试。');
                }
            }
        }
        $folders = SharedFileFolder::find()->where(['user_id' => (int) Yii::$app->user->id, 'status' => SharedFileFolder::STATUS_ACTIVE])->orderBy(['name' => SORT_ASC])->all();
        return $this->render('edit', ['file' => $file, 'folders' => $folders]);
    }

    public function actionShare(int $id): Response
    {
        $file = $this->ownedFile($id);
        $post = $file->post;
        $visibility = filter_var(Yii::$app->request->post('visibility'), FILTER_VALIDATE_INT);
        $names = array_values(array_unique(preg_split('/[\s,;]+/', trim((string) Yii::$app->request->post('recipients', '')), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        $users = $visibility === Post::VISIBILITY_SELECTED_USERS && $names ? User::find()->where(['username' => $names, 'status' => User::STATUS_ACTIVE])->andWhere(['not', ['phone_verified_at' => null]])->all() : [];
        if (!in_array($visibility, [0,1,2,3], true) || ($visibility === 3 && (!$names || count($users) !== count($names)))) {
            Yii::$app->session->setFlash('error', '分享权限或指定用户名无效。');
        } else {
            $post->visibility = $visibility;
            $post->save(false, ['visibility']);
            SharedFileRecipient::deleteAll(['shared_file_id' => $file->id]);
            foreach ($users as $user) {
                (new SharedFileRecipient(['shared_file_id' => $file->id, 'user_id' => $user->id, 'created_at' => time()]))->save(false);
            }
            Yii::$app->session->setFlash('success', '分享权限已更新。');
        }
        return $this->redirect(['/file/index', 'folder' => $file->folder_id]);
    }

    public function actionDelete(int $id): Response
    {
        $file = $this->ownedFile($id);
        $file->status = SharedFile::STATUS_TRASH;
        $file->deleted_at = time();
        $file->save(false, ['status', 'deleted_at']);
        return $this->redirect(['/file/index', 'folder' => $file->folder_id]);
    }

    public function actionRestore(int $id): Response
    {
        $file = $this->ownedFile($id);
        $file->status = SharedFile::STATUS_ACTIVE;
        $file->deleted_at = null;
        $file->save(false, ['status', 'deleted_at']);
        return $this->redirect(['/file/index', 'trash' => true]);
    }

    public function actionDownload(int $id): Response
    {
        $file = $this->ownedFile($id);
        $path = $this->storedFilePath($file);
        if (!is_file($path)) throw new NotFoundHttpException('文件不存在。');
        return Yii::$app->response->sendFile($path, $file->original_name);
    }

    public function actionPreview(int $id): Response
    {
        $file = $this->ownedFile($id);
        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        $previewTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
            'txt' => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8', 'csv' => 'text/plain; charset=utf-8',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        ];
        if (!isset($previewTypes[$extension])) {
            throw new \yii\web\BadRequestHttpException('此文件类型暂不支持在线预览，请下载后查看。');
        }
        $path = $this->storedFilePath($file);
        if (!is_file($path)) throw new NotFoundHttpException('文件不存在。');

        Yii::$app->response->headers->set('X-Content-Type-Options', 'nosniff');
        Yii::$app->response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'");
        return Yii::$app->response->sendFile($path, $file->original_name, [
            'inline' => true,
            'mimeType' => $previewTypes[$extension],
        ]);
    }

    private function ownedFile(int $id): SharedFile
    {
        $file = SharedFile::find()->joinWith('post')->where(['shared_file.id' => $id, 'post.user_id' => (int) Yii::$app->user->id])->one();
        if ($file === null) throw new NotFoundHttpException('文件不存在。');
        return $file;
    }

    private function storedFilePath(SharedFile $file): string
    {
        return $this->pathForStoredFile($file->file_path);
    }

    private function pathForStoredFile(string $filePath): string
    {
        if (str_starts_with($filePath, 'shared-files/')) {
            return Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . 'shared-files' . DIRECTORY_SEPARATOR . basename($filePath);
        }
        return Yii::getAlias('@frontend/web') . str_replace('/', DIRECTORY_SEPARATOR, $filePath);
    }

    private function syncOwnedFileSizes(int $userId): void
    {
        $rows = (new \yii\db\Query())
            ->select(['sf.id', 'sf.file_path', 'sf.size_bytes'])
            ->from(['sf' => SharedFile::tableName()])
            ->innerJoin(['p' => Post::tableName()], 'p.id = sf.post_id')
            ->where(['p.user_id' => $userId])
            ->all();
        foreach ($rows as $row) {
            $path = $this->pathForStoredFile((string) $row['file_path']);
            clearstatcache(true, $path);
            $actualSize = is_file($path) ? filesize($path) : false;
            if ($actualSize !== false && (int) $row['size_bytes'] !== $actualSize) {
                Yii::$app->db->createCommand()->update(SharedFile::tableName(), ['size_bytes' => $actualSize], ['id' => $row['id']])->execute();
            }
        }
    }

    private static function iniSizeToBytes(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '0' || $value === '-1') return null;
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) ($number * match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        });
    }
}
