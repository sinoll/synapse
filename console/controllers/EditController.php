<?php

namespace console\controllers;

use Throwable;
use Yii;
use yii\console\Controller;

class EditController extends Controller
{
    public function actionIndex()
    {
        $this->stdout("控制器工作正常\n"."修复数据几个函数\n");
    }

    /***
     *  修复新表中文章缩略图的所有相对路径为: uploads/thumb/blog/    文件名 *.png 不修改
     *  命令：php yii edit/thumb
    ***/

    public function actionThumb()
    {
        $newDb = Yii::$app->db;
        $prefix = 'uploads/thumb/blog/';

        $rows = $newDb->createCommand("SELECT id, thumb FROM {{%blog}}")->queryAll();

        if (empty($rows)) {
            $this->stdout("blog 表没有数据\n");
            return;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $thumb = trim($row['thumb'] ?? '');

            if ($thumb === '') {
                $skipped++;
                continue;
            }

            if (preg_match('/^https?:\/\//i', $thumb)) {
                $skipped++;
                continue;
            }

            $filename = basename($thumb);
            $newThumb = $prefix . $filename;

            if ($newThumb === $thumb) {
                $skipped++;
                continue;
            }

            $newDb->createCommand()
                ->update('{{%blog}}', ['thumb' => $newThumb], ['id' => $row['id']])
                ->execute();

            $updated++;
            $this->stdout("id={$row['id']}: {$thumb} => {$newThumb}\n");
        }

        $this->stdout("修复完成，更新 {$updated} 条，跳过 {$skipped} 条\n");
    }


    /***
     *  检查 路径的图片是否存在，如果不存在，请 打印blog的 id 和具体路径，一行一个，
     *  命令：php yii edit/check-path
    ***/
    public function actionCheckPath()
    {
        $newDb = Yii::$app->db;
        $webRoot = Yii::getAlias('@frontend/web');

        $rows = $newDb->createCommand("SELECT id, thumb FROM {{%blog}}")->queryAll();

        if (empty($rows)) {
            $this->stdout("blog 表没有数据\n");
            return;
        }

        $missing = 0;

        foreach ($rows as $row) {
            $thumb = trim($row['thumb'] ?? '');

            if ($thumb === '' || preg_match('/^https?:\/\//i', $thumb)) {
                continue;
            }

            $filePath = $webRoot . '/' . $thumb;

            if (!file_exists($filePath)) {
                $this->stdout("id={$row['id']} {$thumb}\n");
                $missing++;
            }
        }

        $this->stdout("检查完成，缺失 {$missing} 张图片\n");
    }

    /***
     *  检查 blog文章中content正文中的图片，如果图片实际路径不存在，请 打印blog的 id 和具体路径，一行一个，
     *  注意： content 是text数据类型，里面有大量富文本标签等
     *  命令：php yii edit/check-content
    ***/
    public function actionCheckContent()
    {
        $newDb = Yii::$app->db;
        $webRoot = rtrim(Yii::getAlias('@frontend/web'), '/\\');

        $rows = $newDb->createCommand("SELECT id, content FROM {{%blog}}")->queryAll();

        if (empty($rows)) {
            $this->stdout("blog 表没有数据\n");
            return;
        }

        $checked = 0;
        $missing = 0;

        foreach ($rows as $row) {
            $seen = [];

            foreach ($this->extractContentImages((string) ($row['content'] ?? '')) as $src) {
                $resolved = $this->resolveWebImagePath($src, $webRoot);

                if ($resolved === null || isset($seen[$resolved['file']])) {
                    continue;
                }

                $seen[$resolved['file']] = true;
                $checked++;

                if (!is_file($resolved['file'])) {
                    $this->stdout("id={$row['id']} {$resolved['path']}\n");
                    $missing++;
                }
            }
        }

        $this->stdout("检查完成，共检查 {$checked} 张图片，缺失 {$missing} 张\n");
    }

    /**
     * 提取富文本中 <img> 的图片地址（src、data-src、srcset）。
     *
     * @return string[]
     */
    private function extractContentImages(string $content): array
    {
        if ($content === '' || stripos($content, '<img') === false) {
            return [];
        }

        preg_match_all('/<img\b[^>]*>/i', $content, $tags, PREG_SET_ORDER);

        $images = [];

        foreach ($tags as $tag) {
            foreach (['src', 'data-src'] as $attribute) {
                $value = $this->matchImageAttribute($tag[0], $attribute);

                if ($value !== null) {
                    $this->addImageCandidate($images, $value);
                }
            }

            $srcset = $this->matchImageAttribute($tag[0], 'srcset');

            if ($srcset !== null) {
                foreach (explode(',', $srcset) as $candidate) {
                    $this->addImageCandidate($images, preg_split('/\s+/', trim($candidate))[0] ?? '');
                }
            }
        }

        return array_keys($images);
    }

    private function matchImageAttribute(string $tag, string $attribute): ?string
    {
        $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';

        if (!preg_match($pattern, $tag, $match)) {
            return null;
        }

        return ($match[2] ?? '') !== ''
            ? $match[2]
            : ((($match[3] ?? '') !== '') ? $match[3] : ($match[4] ?? ''));
    }

    private function addImageCandidate(array &$images, string $value): void
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($value === '' || preg_match('/^(?:data|javascript|mailto):/i', $value)) {
            return;
        }

        $images[$value] = true;
    }

    /**
     * 将正文中的图片地址解析为 web 根目录下的真实文件路径。外部链接返回 null。
     *
     * @return array{path: string, file: string}|null
     */
    private function resolveWebImagePath(string $src, string $webRoot): ?array
    {
        // http(s)、协议相对地址及其他协议不属于本站文件。
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $src)) {
            return null;
        }

        $path = parse_url($src, PHP_URL_PATH);

        if (!is_string($path)) {
            $path = strtok($src, '?#');
        }

        $path = rawurldecode(str_replace('\\', '/', (string) $path));

        if ($path === '' || str_starts_with($path, '#')) {
            return null;
        }

        return [
            'path' => $path,
            'file' => $webRoot . '/' . ltrim($path, '/'),
        ];
    }


    /***
     *  修改 blog文章content字段 正文中的图片路径，/uploads/blog/按年保存  
     *  注意： content 是text数据类型，里面有大量富文本标签等 不要修改出错
     *  命令：php yii edit/content-img
    ***/

    public function actionContentImg()
    {
        $newDb = Yii::$app->db;
        $webRoot = rtrim(Yii::getAlias('@frontend/web'), '/\\');
        $oldDir = $webRoot . '/upload/image';
        $newBase = $webRoot . '/uploads/blog';

        $rows = $newDb->createCommand("SELECT id, content FROM {{%blog}}")->queryAll();

        if (empty($rows)) {
            $this->stdout("blog 表没有数据\n");
            return;
        }

        $map = [];
        $moved = 0;
        $deduped = 0;
        $failed = 0;

        if (is_dir($oldDir)) {
            foreach (glob($oldDir . '/*/*') ?: [] as $source) {
                if (!is_file($source)) {
                    continue;
                }

                $subdir = basename(dirname($source));
                $filename = basename($source);
                $year = $this->imageYear($subdir, $source);
                $targetDir = $newBase . '/' . $year;
                $target = $targetDir . '/' . $filename;

                if (is_file($target)) {
                    if (filesize($source) === filesize($target) && sha1_file($source) === sha1_file($target)) {
                        @unlink($source);
                        $deduped++;
                    } else {
                        $this->stderr("冲突，目标已存在不同文件：{$target}\n");
                        $failed++;
                        continue;
                    }
                } else {
                    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                        $this->stderr("无法创建目录：{$targetDir}\n");
                        $failed++;
                        continue;
                    }

                    if (!rename($source, $target)) {
                        $this->stderr("移动失败：{$source} => {$target}\n");
                        $failed++;
                        continue;
                    }

                    $moved++;
                }

                $map['/upload/image/' . $subdir . '/' . $filename] = '/uploads/blog/' . $year . '/' . $filename;
            }
        }

        // 同时匹配不带开头斜杠的写法，例如 https://域名/upload/image/...
        foreach ($map as $old => $new) {
            $map[ltrim($old, '/')] = ltrim($new, '/');
        }

        $updated = 0;
        $missing = 0;
        $transaction = $newDb->beginTransaction();

        try {
            foreach ($rows as $row) {
                $content = (string) ($row['content'] ?? '');

                foreach (array_unique($this->extractContentImages($content)) as $src) {
                    $path = parse_url($src, PHP_URL_PATH);

                    if (!is_string($path)) {
                        $path = strtok($src, '?#');
                    }

                    $path = str_replace('\\', '/', rawurldecode((string) $path));

                    if ($path === '' || stripos($path, '/upload/image/') === false) {
                        continue;
                    }

                    $old = '/' . ltrim($path, '/');

                    if (isset($map[$old])) {
                        continue;
                    }

                    // 源文件可能已在之前的执行中移动到目标目录，按文件名找回实际年份。
                    $found = glob($newBase . '/*/' . basename($old)) ?: [];

                    if ($found === []) {
                        $this->stdout("缺失 id={$row['id']} {$old}\n");
                        $missing++;
                        continue;
                    }

                    $target = '/uploads/blog/' . basename(dirname(reset($found))) . '/' . basename($old);
                    $map[$old] = $target;
                    $map[ltrim($old, '/')] = ltrim($target, '/');
                }

                $newContent = strtr($content, $map);

                if ($newContent !== $content) {
                    $newDb->createCommand()
                        ->update('{{%blog}}', ['content' => $newContent], ['id' => $row['id']])
                        ->execute();
                    $updated++;
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            $this->stderr("更新失败：" . $e->getMessage() . "\n");
            return;
        }

        foreach (glob($oldDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            @rmdir($dir);
        }
        @rmdir($oldDir);

        $this->stdout("图片移动完成：移动 {$moved} 个，重复清理 {$deduped} 个，失败 {$failed} 个\n");
        $this->stdout("正文路径更新完成：更新 {$updated} 篇文章，缺失 {$missing} 张图片\n");
    }

    /**
     * 根据旧目录名或文件名时间戳推断存放年份。
     */
    private function imageYear(string $subdir, ?string $file = null): string
    {
        if (preg_match('/^(\d{4})/', $subdir, $match)) {
            return $match[1];
        }

        if ($file !== null && preg_match('/^t?(\d{10})/', basename($file), $match)) {
            return date('Y', (int) $match[1]);
        }

        if ($file !== null && is_file($file)) {
            return date('Y', (int) filemtime($file));
        }

        return date('Y');
    }




}