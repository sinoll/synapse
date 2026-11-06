<?php

declare(strict_types=1);

namespace restful\exceptions;

use yii\web\HttpException;

/**
 * 字段级校验失败：HTTP 422，错误明细由 ApiResponder 放进同一个响应封装。
 *
 * 没有复用 yii\web\UnprocessableEntityHttpException 是因为它不带字段错误载体，
 * 而控制器层不想为每种资源各自 try/catch 再把错误拼成响应。
 */
final class ValidationException extends HttpException
{
    /** @param array<string, string[]> $fieldErrors 字段名 => 错误文案列表 */
    public function __construct(private readonly array $fieldErrors, string $message = '请求参数不合法。')
    {
        parent::__construct(422, $message);
    }

    /** @return array<string, string[]> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
