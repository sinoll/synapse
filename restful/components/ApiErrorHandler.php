<?php

declare(strict_types=1);

namespace restful\components;

use yii\base\UserException;
use yii\web\ErrorHandler;
use yii\web\HttpException;
use yii\web\Response;

/**
 * 接口的异常渲染出口。
 *
 * yii\web\ErrorHandler 在 format=JSON 时会把异常换成
 * {name,message,code,status,type,stack-trace} 结构，和业务封装 {code,message,data} 是两套形状。
 * 只改写 convertExceptionToArray() 这一个方法，整个接口就只剩一种错误形状，
 * 状态码、日志与 Yii 的异常处理链都不受影响。
 */
final class ApiErrorHandler extends ErrorHandler
{
    protected function convertExceptionToArray($exception): array
    {
        return ApiResponder::fromException($exception, self::statusCodeOf($exception));
    }

    /** 与 yii\web\Response::setStatusCodeByException() 同一套映射，保证 body 与真实状态码同值。 */
    private static function statusCodeOf(\Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->statusCode;
        }

        return $exception instanceof UserException ? 400 : 500;
    }
}
