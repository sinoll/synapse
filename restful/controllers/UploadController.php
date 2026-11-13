<?php

declare(strict_types=1);

namespace restful\controllers;

use common\models\ShortVideo;
use restful\components\ApiResponder;
use restful\exceptions\ValidationException;
use Yii;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

/**
 * POST /upload —— 把文件落到站点既有的 uploads 目录，返回可以直接提交给内容接口的路径。
 *
 * 内容接口只认路径、不收 multipart：一次请求只做一件事，写内容时出错回滚才不会留下孤儿文件。
 * 目录、扩展名、大小和缩略图规则都来自 params['api.uploadKinds']，与前台各控制器同一口径。
 */
final class UploadController extends ApiController
{
    public function actionCreate(): array
    {
        $kind = self::kind($this->payload());
        $config = Yii::$app->params['api.uploadKinds'][$kind];

        $files = UploadedFile::getInstancesByName('file');
        if ($files === []) {
            throw new ValidationException(['file' => ['请用 multipart/form-data 提交 file 字段。']]);
        }
        $files = array_values(array_filter(
            $files,
            static fn (UploadedFile $file): bool => $file->error !== UPLOAD_ERR_NO_FILE
        ));
        if ($files === []) {
            throw new ValidationException(['file' => ['请至少提交一个文件。']]);
        }

        $multipleImages = in_array($kind, ['blog', 'moment', 'images'], true);
        if (!$multipleImages && count($files) > 1) {
            throw new ValidationException(['file' => [$kind . ' 每次只能上传一个文件。']]);
        }
        foreach ($files as $file) {
            self::assertAccepted($file, $kind, $config);
        }

        $firstFile = $files[0];
        $videoMetadata = $kind === 'video'
            ? Yii::$app->get('ffmpegService')->getVideoMetadata($firstFile->tempName)
            : null;
        $webRoot = Yii::getAlias('@restful/web');
        $directory = self::makeDirectory($webRoot . self::sourceDirectory($config));
        $paths = [];
        $images = [];
        $storedFiles = [];
        foreach ($files as $file) {
            $name = Yii::$app->security->generateRandomString(32) . '.' . strtolower((string) $file->extension);
            $storedFile = $directory . DIRECTORY_SEPARATOR . $name;
            if (!$file->saveAs($storedFile)) {
                if (is_file($storedFile)) {
                    @unlink($storedFile);
                }
                foreach ($storedFiles as $storedPath) {
                    @unlink($storedPath);
                }

                return ApiResponder::fail(500, '文件写入失败，请检查 ' . self::sourceDirectory($config) . ' 的目录权限。');
            }

            $storedFiles[] = $storedFile;
            $path = self::sourceDirectory($config) . '/' . $name;
            $paths[] = $path;
            if ($multipleImages) {
                $images[] = [
                    'path' => $path,
                    'original_name' => $file->name,
                ];
            }
        }

        [$thumbnailPath, $warning] = self::makeThumbnail($config, $storedFiles[0], $paths[0]);

        if ($kind === 'video') {
            $data = [
                'kind' => $kind,
                'video_path' => $paths[0],
                'thumbnail_path' => $thumbnailPath,
                'duration_seconds' => $videoMetadata['duration_seconds'],
                'video_type' => $videoMetadata['width'] < $videoMetadata['height']
                    ? ShortVideo::ORIENTATION_VERTICAL
                    : ShortVideo::ORIENTATION_HORIZONTAL,
            ];
        } elseif ($multipleImages) {
            $data = [
                'kind' => $kind,
                'imgs' => $images,
                'thumbnail_path' => $thumbnailPath,
            ];
        } else {
            $data = [
                'kind' => $kind,
                'path' => $paths[0],
                'url' => ApiResponder::assetUrl($paths[0]),
                'size' => (int) $firstFile->size,
                'thumbnail_path' => $thumbnailPath,
                'thumbnail_url' => ApiResponder::assetUrl($thumbnailPath),
            ];
        }
        if ($warning !== null) {
            $data['warning'] = $warning;
        }

        return ApiResponder::ok($data, '文件已保存，可把返回的路径字段提交给对应的内容接口。');
    }

    /** @param array<string, mixed> $config @param array<string, mixed> $payload */
    private static function kind(array $payload): string
    {
        $kinds = array_keys(Yii::$app->params['api.uploadKinds']);
        $kind = is_string($payload['kind'] ?? null) ? trim($payload['kind']) : '';
        if ($kind === '') {
            // 图集目录是最通用的图片落点：不写 kind 就等于「上传一张普通图片」。
            return 'images';
        }
        if (!in_array($kind, $kinds, true)) {
            throw new ValidationException(['kind' => ['kind 只能是 ' . implode('、', $kinds) . '。']]);
        }

        return $kind;
    }

    /** @param array<string, mixed> $config */
    private static function assertAccepted(UploadedFile $file, string $kind, array $config): void
    {
        if ($file->error !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => [in_array($file->error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? '文件超过了服务器允许的上传大小，请调整 php.ini 的 upload_max_filesize 与 post_max_size。'
                : '文件上传失败（PHP 错误码 ' . $file->error . '），请重试。']]);
        }

        $extensions = (array) $config['extensions'];
        if (!in_array(strtolower((string) $file->extension), $extensions, true)) {
            throw new ValidationException(['file' => [$kind . ' 只接受 ' . implode('、', $extensions) . ' 文件。']]);
        }

        if (isset($config['max_size']) && $file->size > (int) $config['max_size']) {
            $maxMb = max(1, (int) round(((int) $config['max_size']) / 1048576));
            throw new ValidationException(['file' => ["$kind 的文件不能超过 $maxMb MB。"]]);
        }

        // 视频之外的 kind 全是图片：扩展名可以伪造，只有真解得开才能保证站点的封面和卡片不破图。
        if ($config['thumb_from'] !== 'video' && @getimagesize($file->tempName) === false) {
            throw new ValidationException(['file' => ['文件不是有效的图片。']]);
        }
        if (
            $config['thumb_from'] === 'video'
            && !Yii::$app->get('ffmpegService')->isPlayableVideo($file->tempName, (string) $file->extension)
        ) {
            throw new ValidationException(['file' => ['文件不是有效的视频，或视频内容无法解码播放。']]);
        }
    }

    /** @param array<string, mixed> $config 形如 /uploads/images/202611；dated=false 的分类图不带年月分层。 */
    private static function sourceDirectory(array $config): string
    {
        $directory = (string) $config['dir'];

        return $config['dated'] === true ? $directory . '/' . date('Ym') : $directory;
    }

    private static function makeDirectory(string $absoluteDirectory): string
    {
        if (is_dir($absoluteDirectory)) {
            return rtrim($absoluteDirectory, DIRECTORY_SEPARATOR);
        }
        if (!mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new ServerErrorHttpException('目录创建失败，请检查 uploads 的写入权限。');
        }

        return $absoluteDirectory;
    }

    /**
     * 缩略图尽力生成：前台的列表与卡片在缺图时会回落到原图或默认封面，
     * 所以这里失败不该让整次上传失败，只把原因随 warning 返回给调用方。
     *
     * @param array<string, mixed> $config
     * @param string $path 刚存下的原图根相对路径
     * @return array{0: string|null, 1: string|null} [缩略图路径, 警告文案]
     */
    private static function makeThumbnail(array $config, string $storedFile, string $path): array
    {
        $thumbDirectory = $config['thumb'];
        $mode = $config['thumb_from'];
        if ($thumbDirectory === null || $mode === null) {
            return [null, null];
        }

        $webRoot = Yii::getAlias('@restful/web');
        $directory = self::makeDirectory($webRoot . $thumbDirectory);
        // 缩略图一律平铺成 <原名>.jpg：Image::thumbnailForPath() 与前台的封面解析都按这个名字找。
        $thumbnail = $directory . DIRECTORY_SEPARATOR . pathinfo($path, PATHINFO_FILENAME) . '.jpg';
        $generated = false;
        try {
            $service = Yii::$app->get('ffmpegService');
            $generated = $mode === 'video'
                ? $service->extractFrame($storedFile, $thumbnail, 5)
                : $service->createThumbnail($storedFile, $thumbnail);
        } catch (\Throwable $exception) {
            Yii::warning('API 缩略图生成失败：' . $exception->getMessage(), __METHOD__);
            $generated = false;
        }

        if (!$generated || !is_file($thumbnail)) {
            if (is_file($thumbnail)) {
                @unlink($thumbnail);
            }

            return [null, '缩略图生成失败，请检查 params 里的 ffmpegPath 是否可用；原文件已正常保存。'];
        }

        return [$thumbDirectory . '/' . pathinfo($path, PATHINFO_FILENAME) . '.jpg', null];
    }
}
