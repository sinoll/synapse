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
        $frameIndex = max(0, $frameNumber - 1);
        foreach (array_unique([$frameIndex, 0]) as $index) {
            $filter = 'select=eq(n\\,' . $index . '),scale=\'min(1280,iw)\':-2';
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

            if ($exitCode === 0 && is_file($outputPath) && @getimagesize($outputPath) !== false) {
                return true;
            }
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }

        return false;
    }
}
