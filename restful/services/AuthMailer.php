<?php

declare(strict_types=1);

namespace restful\services;

use common\services\MailSenderInterface;
use Throwable;
use Yii;
use yii\helpers\Html;

/**
 * 投递 REST 认证验证码邮件。
 */
final class AuthMailer
{
    public static function sendCode(
        MailSenderInterface $mailer,
        string $to,
        string $purpose,
        string $code,
    ): bool {
        $isPasswordReset = $purpose === 'password_reset';
        $subject = $isPasswordReset ? '重置你的密码 - ' : '验证你的邮箱 - ';
        $action = $isPasswordReset ? '重置密码' : '验证邮箱';
        $safeName = Html::encode(Yii::$app->name);
        $safeAction = Html::encode($action);
        $safeCode = Html::encode($code);
        $html = sprintf(
            '<p>你好：</p><p>你正在%s %s。验证码为：</p>'
                . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px">%s</p>'
                . '<p>验证码 10 分钟内有效，最多可尝试 3 次。如果不是本人操作，请忽略此邮件。</p>',
            $safeAction,
            $safeName,
            $safeCode,
        );
        $text = sprintf(
            "你好：\n\n你正在%s %s。\n验证码：%s\n验证码 10 分钟内有效，最多可尝试 3 次。如果不是本人操作，请忽略此邮件。\n",
            $action,
            Yii::$app->name,
            $code,
        );

        try {
            $mailer->send(
                $to,
                $subject . Yii::$app->name,
                $html,
                $text,
            );
        } catch (Throwable $exception) {
            Yii::error('RESTful 认证验证码邮件投递失败：' . $exception->getMessage(), 'mail');

            return false;
        }

        return true;
    }
}
