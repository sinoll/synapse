<?php

declare(strict_types=1);

namespace frontend\components;

use common\services\MailSenderInterface;
use Throwable;
use Yii;

/**
 * 渲染 @common/mail 下的 *-html / *-text 模板并交给 mailSender 投递。
 *
 * 站点没有装 yii2 邮件扩展（没有 mailer 组件），模板直接用 view 组件渲染成信件正文片段，
 * 不经过 mail/layouts；投递失败统一返回 false，由调用方决定怎么提示用户。
 */
final class MailMessage
{
    public static function send(
        MailSenderInterface $mailSender,
        string $to,
        string $subject,
        string $templateAlias,
        array $params,
    ): bool {
        try {
            $mailSender->send(
                $to,
                $subject,
                Yii::$app->view->renderFile($templateAlias . '-html.php', $params),
                Yii::$app->view->renderFile($templateAlias . '-text.php', $params),
            );
        } catch (Throwable $exception) {
            Yii::error('邮件发送失败: ' . $exception->getMessage(), 'mail');

            return false;
        }

        return true;
    }
}
