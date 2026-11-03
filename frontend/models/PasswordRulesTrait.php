<?php

declare(strict_types=1);

namespace frontend\models;

use Yii;

/**
 * 注册、重置密码等公开入口共用的口令策略。
 *
 * Why: 长度下限来自 params；上限 64 避开 bcrypt 在第 72 字节静默截断密码；
 * 字母+数字的要求挡掉纯字典口令，同时不至于把中文密码用户挡在门外。
 */
trait PasswordRulesTrait
{
    /**
     * @return array[] 可直接并入 rules() 的校验规则
     */
    protected function passwordRules(string $attribute, string $label = '密码'): array
    {
        $minLength = (int) Yii::$app->params['user.passwordMinLength'];

        return [
            [$attribute, 'required'],
            [$attribute, 'string', 'min' => $minLength, 'max' => 64],
            [
                $attribute,
                'match',
                'pattern' => '/^(?=.*[A-Za-z])(?=.*\d).+$/s',
                'message' => $label . '至少 ' . $minLength . ' 位，且需同时包含字母和数字。',
            ],
        ];
    }
}
