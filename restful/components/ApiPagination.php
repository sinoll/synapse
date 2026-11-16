<?php

declare(strict_types=1);

namespace restful\components;

use restful\exceptions\ValidationException;
use Yii;

final class ApiPagination
{
    /** @return array{0: int, 1: int} */
    public static function parse(array $params): array
    {
        $page = self::positiveInteger($params, 'page', 1);
        $perPage = self::positiveInteger($params, 'per_page', (int) Yii::$app->params['api.pageSizeDefault']);
        $max = (int) Yii::$app->params['api.pageSizeMax'];
        if ($perPage > $max) {
            throw new ValidationException(['per_page' => ["per_page 只能在 1 到 $max 之间。"]]);
        }

        return [$page, $perPage];
    }

    private static function positiveInteger(array $params, string $key, int $default): int
    {
        if (!array_key_exists($key, $params)) {
            return $default;
        }
        $value = filter_var($params[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new ValidationException([$key => [$key . ' 必须是正整数。']]);
        }

        return $value;
    }
}
