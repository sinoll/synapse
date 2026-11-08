<?php

declare(strict_types=1);

namespace frontend\components;

use common\services\MailSenderInterface;
use Throwable;
use Yii;

/**
 * 渲染 @frontend/mail 下的 *-html / *-text 模板并交给 mailSender 投递。
 *
 * 站点没有装 yii2 邮件扩展（没有 mailer 组件），模板直接用 view 组件渲染成信件正文片段；
 * 投递失败统一返回 false，由调用方决定怎么提示用户，真实原因在日志 mail 分类里查。
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
            // 外层是给用户看的兜底文案，日志要留最内层的真实原因（SMTP 响应、DNS、证书……），
            // 否则线上只看得到"邮件服务暂时不可用"，无从定位。
            $root = $exception;
            while ($root->getPrevious() !== null) {
                $root = $root->getPrevious();
            }
            Yii::error(sprintf(
                '邮件发送失败 -> %s: %s (%s)',
                get_class($root),
                $root->getMessage(),
                $exception->getMessage(),
            ), 'mail');

            return false;
        }

        return true;
    }
}
