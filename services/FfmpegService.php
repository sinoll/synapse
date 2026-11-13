<?php

namespace services;

use Yii;
use RuntimeException;

class FfmpegService
{
    /**
     * 获取 FFmpeg 可执行文件路径
     */
    public function getBinaryPath(): string
    {
        $ffmpeg = Yii::$app->params['ffmpegPath'] ?? '';

        if ($ffmpeg === '' || !is_file($ffmpeg)) {
            throw new RuntimeException(
                'FFmpeg 不存在，请检查 config/params.php：' . $ffmpeg
            );
        }

        return $ffmpeg;
    }

    /**
     * 获取 FFmpeg 版本信息
     */
    public function getVersion(): array
    {
        $ffmpeg = $this->getBinaryPath();

        $command = escapeshellarg($ffmpeg)
            . ' -version 2>&1';

        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                'FFmpeg 执行失败：' . implode("\n", $output)
            );
        }

        return $output;
    }

    /** Generate a fixed-size thumbnail, preserving the source aspect ratio with padding. */
    public function createThumbnail(string $imagePath, string $outputPath): bool
    {
        if (!is_file($imagePath)) {
            throw new RuntimeException('Image file does not exist: ' . $imagePath);
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is disabled; FFmpeg cannot be started.');
        }

        $ffmpeg = $this->getBinaryPath();
        [$width, $height] = $this->thumbnailDimensions();
        $filter = $this->thumbnailFilter($width, $height);

        try {
            $process = @proc_open(
                [$ffmpeg, '-nostdin', '-y', '-v', 'error', '-i', $imagePath, '-vf', $filter, '-frames:v', '1', $outputPath],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to start FFmpeg: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start FFmpeg.');
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return $exitCode === 0 && $this->hasThumbnailDimensions($outputPath, $width, $height);
    }

    /**
     * Verify that the input contains a decodable video stream.
     */
    public function isPlayableVideo(string $videoPath, string $extension): bool
    {
        if (!is_file($videoPath)) {
            throw new RuntimeException('Video file does not exist: ' . $videoPath);
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is disabled; FFmpeg cannot be started.');
        }

        $inputFormat = match (strtolower($extension)) {
            'mp4', 'mov' => 'mov',
            'webm' => 'matroska',
            'ogv', 'ogg' => 'ogg',
            default => throw new RuntimeException('Unsupported video extension: ' . $extension),
        };
        $ffmpeg = $this->getBinaryPath();
        try {
            $process = @proc_open(
                [$ffmpeg, '-nostdin', '-v', 'error', '-f', $inputFormat, '-i', $videoPath, '-map', '0:v:0', '-frames:v', '1', '-f', 'null', '-'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to start FFmpeg: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start FFmpeg.');
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    /**
     * Read the video duration and display dimensions using FFprobe.
     *
     * @return array{duration_seconds: int, width: int, height: int}
     */
    public function getVideoMetadata(string $videoPath): array
    {
        if (!is_file($videoPath)) {
            throw new RuntimeException('Video file does not exist: ' . $videoPath);
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is disabled; FFprobe cannot be started.');
        }

        $ffmpeg = $this->getBinaryPath();
        $ffprobe = dirname($ffmpeg) . DIRECTORY_SEPARATOR
            . (PHP_OS_FAMILY === 'Windows' ? 'ffprobe.exe' : 'ffprobe');
        if (!is_file($ffprobe)) {
            throw new RuntimeException('FFprobe does not exist next to FFmpeg: ' . $ffprobe);
        }

        try {
            $process = @proc_open(
                [
                    $ffprobe,
                    '-v',
                    'error',
                    '-select_streams',
                    'v:0',
                    '-show_entries',
                    'format=duration:stream=width,height,duration:stream_tags=rotate:stream_side_data=rotation',
                    '-of',
                    'json',
                    $videoPath,
                ],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to start FFprobe: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start FFprobe.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('FFprobe failed: ' . trim((string) $errorOutput));
        }

        try {
            $metadata = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('FFprobe returned invalid metadata.', 0, $exception);
        }

        if (!is_array($metadata)) {
            throw new RuntimeException('FFprobe returned invalid metadata.');
        }
        $stream = $metadata['streams'][0] ?? null;
        if (!is_array($stream)) {
            throw new RuntimeException('FFprobe could not determine the video duration and dimensions.');
        }
        $duration = $metadata['format']['duration'] ?? $stream['duration'] ?? null;
        $width = (int) ($stream['width'] ?? 0);
        $height = (int) ($stream['height'] ?? 0);
        if (!is_numeric($duration) || !is_finite((float) $duration) || $width <= 0 || $height <= 0) {
            throw new RuntimeException('FFprobe could not determine the video duration and dimensions.');
        }

        $rotation = $stream['tags']['rotate'] ?? null;
        foreach ($stream['side_data_list'] ?? [] as $sideData) {
            if (isset($sideData['rotation']) && is_numeric($sideData['rotation'])) {
                $rotation = $sideData['rotation'];
                break;
            }
        }
        if (is_numeric($rotation) && abs((int) round((float) $rotation)) % 180 === 90) {
            [$width, $height] = [$height, $width];
        }

        return [
            'duration_seconds' => max(0, (int) round((float) $duration)),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Extract a frame from a video. Frame numbering is one-based for callers.
     * Falls back to the first frame when the requested frame does not exist.
     */
    public function extractFrame(string $videoPath, string $outputPath, int $frameNumber = 5): bool
    {
        if (!is_file($videoPath)) {
            throw new RuntimeException('Video file does not exist: ' . $videoPath);
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is disabled; FFmpeg cannot be started.');
        }

        $ffmpeg = $this->getBinaryPath();
        [$width, $height] = $this->thumbnailDimensions();
        $frameIndex = max(0, $frameNumber - 1);
        foreach (array_unique([$frameIndex, 0]) as $index) {
            $filter = 'select=eq(n\\,' . $index . '),' . $this->thumbnailFilter($width, $height);
            try {
                $process = @proc_open(
                    [$ffmpeg, '-nostdin', '-y', '-v', 'error', '-i', $videoPath, '-vf', $filter, '-frames:v', '1', '-q:v', '2', $outputPath],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                );
            } catch (\Throwable $exception) {
                throw new RuntimeException('Unable to start FFmpeg: ' . $exception->getMessage(), 0, $exception);
            }

            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start FFmpeg.');
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode === 0 && $this->hasThumbnailDimensions($outputPath, $width, $height)) {
                return true;
            }
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }

        return false;
    }

    /** @return array{0: int, 1: int} */
    private function thumbnailDimensions(): array
    {
        $width = (int) (Yii::$app->params['thumbnail.width'] ?? 0);
        $height = (int) (Yii::$app->params['thumbnail.height'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('Thumbnail dimensions must be configured as positive integers.');
        }

        return [$width, $height];
    }

    private function thumbnailFilter(int $width, int $height): string
    {
        return sprintf(
            "scale=w='min(%d,iw)':h='min(%d,ih)':force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2",
            $width,
            $height,
            $width,
            $height
        );
    }

    private function hasThumbnailDimensions(string $path, int $width, int $height): bool
    {
        $imageSize = is_file($path) ? @getimagesize($path) : false;

        return $imageSize !== false && $imageSize[0] === $width && $imageSize[1] === $height;
    }
}
