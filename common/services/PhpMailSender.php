<?php

declare(strict_types=1);

namespace common\services;

use RuntimeException;
use Yii;

/**
 * 通过 PHP 内置 mail() 投递。
 *
 * Windows 本地开发通常没有 MTA，mail() 会失败；此时仍把信件写入 runtime/mail，
 * 只为了开发者能取到链接完成本地验证，生产环境（YII_DEBUG=false）不写任何副本。
 */
class PhpMailSender implements MailSenderInterface
{
    public function send(string $to, string $subject, string $htmlBody, string $textBody): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('收件地址不合法。');
        }

        $from = $this->fromAddress();
        $this->applySmtpSettings();
        $boundary = 'b_' . Yii::$app->security->generateRandomString(24);
        $encodedSubject = $this->encodeHeader($this->sanitize($subject));
        $headers = implode("\r\n", [
            'From: ' . $this->encodeHeader($this->sanitize(Yii::$app->name)) . ' <' . $this->sanitize($from) . '>',
            'To: ' . $this->sanitize($to),
            'Subject: ' . $encodedSubject,
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . Yii::$app->security->generateRandomString(16) . '@'
                . $this->sanitize((string) (parse_url($from, PHP_URL_HOST) ?: 'localhost')) . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ]);
        $body = $this->multipart($boundary, $textBody, $htmlBody);

        $this->writeDebugCopy($to, $subject, $headers, $body);

        if (!@mail($to, $encodedSubject, $body, $headers)) {
            Yii::error('邮件投递失败: ' . (error_get_last()['message'] ?? '未知错误'), 'mail');
            throw new RuntimeException('邮件服务暂时不可用，请稍后再试。');
        }
    }

    private function multipart(string $boundary, string $textBody, string $htmlBody): string
    {
        $text = $this->encodePart($textBody, 'text/plain');
        $html = $this->encodePart($htmlBody, 'text/html');

        return "--{$boundary}\r\n{$text}\r\n\r\n--{$boundary}\r\n{$html}\r\n\r\n--{$boundary}--\r\n";
    }

    private function encodePart(string $body, string $type): string
    {
        return "Content-Type: {$type}; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($body), 76, "\r\n");
    }

    /**
     * 去掉换行与空字节，避免用户可控内容注入 Cc/Bcc 等额外头部。
     */
    private function sanitize(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }

    private function encodeHeader(string $value): string
    {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', '');
    }

    private function fromAddress(): string
    {
        $from = (string) (Yii::$app->params['mailSupportEmail'] ?? '');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('站点发信地址未配置，请在 params 中设置 mailSupportEmail。');
        }

        return $from;
    }

    /**
     * PHP 在 Windows 上只能通过 php.ini 的 SMTP/smtp_port 指定转发器，
     * 这里允许用 params 覆盖，免去改 PHP 安装目录；留空则沿用 php.ini。
     * 注意 PHP 的 mail() 不支持带鉴权的 SMTP，需要接一个本机免鉴权转发器。
     */
    private function applySmtpSettings(): void
    {
        $host = trim((string) (Yii::$app->params['mailSmtpHost'] ?? ''));
        if ($host !== '') {
            ini_set('SMTP', $host);
        }

        $port = trim((string) (Yii::$app->params['mailSmtpPort'] ?? ''));
        if ($port !== '') {
            ini_set('smtp_port', $port);
        }
    }

    private function writeDebugCopy(string $to, string $subject, string $headers, string $body): void
    {
        if (!YII_DEBUG) {
            return;
        }

        $directory = Yii::getAlias('@runtime/mail');
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            return;
        }
        $path = $directory . DIRECTORY_SEPARATOR . date('Ymd-His') . '_'
            . substr(hash('sha256', $to . $subject), 0, 12) . '.eml';
        file_put_contents($path, $headers . "\r\n\r\n" . $body);
    }
}
