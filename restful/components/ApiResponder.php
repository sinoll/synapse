<?php

declare(strict_types=1);

namespace restful\components;

use restful\exceptions\ValidationException;
use Yii;
use yii\base\UserException;
use yii\web\HttpException;

/**
 * 全站唯一的响应封装：{code, message, data}。
 *
 * code 与 HTTP 状态码同值，读 body 的老客户端和读 status 的新客户端拿到的是同一个结论。
 * 异常出口也归到这里，否则 404/401 会输出 Yii 默认的 {name,type,code} 结构，
 * 同一个 API 出现两种响应形状。
 */
final class ApiResponder
{
    /** @param array|null $data 业务数据；无数据时用 null 而不是 []，省得客户端猜两种空值 */
    public static function ok(array|null $data = null, string $message = 'ok'): array
    {
        return ['code' => 200, 'message' => $message, 'data' => $data];
    }

    /**
     * 业务失败。$code 必须是 4xx/5xx：它同时被写成 HTTP 状态码，
     * 传 200 会让「HTTP 成功、body 失败」的老毛病继续存在。
     */
    public static function fail(int $code, string $message, array|null $data = null): array
    {
        Yii::$app->getResponse()->setStatusCode($code);

        return ['code' => $code, 'message' => $message, 'data' => $data];
    }

    /** @param array<string, string[]> $fieldErrors */
    public static function validation(array $fieldErrors, string $message = '请求参数不合法。'): array
    {
        return self::fail(422, $message, ['errors' => $fieldErrors]);
    }

    /**
     * 异常 → 统一封装。
     *
     * $httpCode 由错误处理器从响应状态里带过来（那里已经按异常类型算过一次），
     * 调用方留空时按异常自身推：HttpException 用它的 statusCode，其余 500。
     */
    public static function fromException(\Throwable $exception, int|null $httpCode = null): array
    {
        $code = $httpCode ?? ($exception instanceof HttpException ? $exception->statusCode : 500);
        $body = [
            'code' => $code,
            'message' => self::publicMessage($exception, $code),
            'data' => $exception instanceof ValidationException ? ['errors' => $exception->fieldErrors()] : null,
        ];

        if (YII_DEBUG) {
            $body['debug'] = [
                'type' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, 12),
            ];
        }

        return $body;
    }

    /**
     * 库里存的媒体路径转成绝对地址。
     *
     * 站点根用 params['siteBaseUrl']，留空才回落到请求的 Host——
     * 重置/验证类邮件链接同理，不该由可伪造的 Host 头决定。
     */
    public static function assetUrl(string|null $path): string|null
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return self::baseUrl() . '/' . ltrim($path, '/');
    }

    public static function baseUrl(): string
    {
        $configured = trim((string) (Yii::$app->params['siteBaseUrl'] ?? ''));
        if ($configured !== '' && $configured !== 'http://localhost') {
            return rtrim($configured, '/');
        }

        return rtrim(Yii::$app->getRequest()->getHostInfo(), '/');
    }

    /** 表里存的是 unix 时间戳；对外给带时区的 ISO 8601，避免客户端各自按本地时区猜。 */
    public static function timestamp(int $unix): string|null
    {
        return $unix > 0 ? date(DATE_ATOM, $unix) : null;
    }

    private static function publicMessage(\Throwable $exception, int $httpCode): string
    {
        // UserException（含各类 HttpException）的 message 是给人看的文案；
        // 其余异常可能带 SQL、文件路径等实现细节，生产环境一律换成通用文案。
        if ($exception instanceof UserException) {
            return self::localized($exception->getMessage());
        }
        if (YII_DEBUG) {
            return self::localized($exception->getMessage());
        }

        return $httpCode === 500 ? '服务器内部错误，请稍后重试。' : '请求无法处理。';
    }

    /**
     * Yii 的 zh-CN 译文表没覆盖框架内部的少数句子（请求体解析失败就是其一），
     * 在这里补一次，免得整站接口只有这一句是英文。
     */
    private static function localized(string $message): string
    {
        return str_starts_with($message, 'Invalid JSON data in request body')
            ? '请求体不是合法的 JSON。'
            : $message;
    }
}
