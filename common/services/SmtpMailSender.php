<?php

declare(strict_types=1);

namespace common\services;

use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;
use Yii;

/**
 * 通过需要鉴权的 SMTP 服务投递（smtp.163.com:465/SSL 之类）。
 *
 * 这是站点唯一的投递路径：不依赖 PHP 内置 mail() 或本机 MTA，凭据与加密方式都在
 * params['smtp.*'] 里配置（见 common/config/main.php 的映射），因此 Windows 与
 * Linux 的行为一致。排查用 php yii mail/doctor，真发一封用 php yii mail/test。
 */
class SmtpMailSender implements MailSenderInterface
{
    public string $host = '';
    public string $username = '';
    public string $password = '';
    public int $port = 465;
    /** ssl=隐式 TLS（465），tls=先明文再 STARTTLS（587/25），留空=不加密，仅本机调试用。 */
    public string $encryption = 'ssl';
    public string $fromEmail = '';
    public string $fromName = '';

    private Mailer|null $mailer = null;

    public function send(string $to, string $subject, string $htmlBody, string $textBody): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('收件地址不合法。');
        }

        $mailer = $this->mailer();
        $from = $this->fromAddress();
        $email = (new Email())
            ->from(new Address($from, $this->fromName !== '' ? $this->fromName : (string) Yii::$app->name))
            ->to($to)
            ->subject($subject)
            ->text($textBody)
            ->html($htmlBody);

        try {
            $mailer->send($email);
        } catch (Throwable $exception) {
            Yii::error('SMTP 投递失败: ' . $exception->getMessage(), 'mail');
            throw new RuntimeException('邮件服务暂时不可用，请稍后再试。', 0, $exception);
        }
    }

    private function mailer(): Mailer
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }

        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('SMTP 发信服务未配置，请补齐 params-local.php 的 smtp.host/username/password（php yii mail/doctor 可自检）。');
        }

        $encryption = strtolower($this->encryption);
        $implicitTls = $encryption === 'ssl' || $encryption === 'smtps' || $this->port === 465;

        // 连接超时默认取 default_socket_timeout（60s），发信卡住会拖垮整个请求，这里压到 15s。
        $stream = (new SocketStream())->setTimeout(15);
        $transport = new EsmtpTransport($this->host, $this->port, $implicitTls, null, null, $stream);
        $transport->setUsername($this->username)->setPassword($this->password);
        if (!$implicitTls && $encryption !== '') {
            // 明文端口必须升到 STARTTLS，否则宁可不发，也不把授权码裸奔出去。
            $transport->setRequireTls(true);
        }

        return $this->mailer = new Mailer($transport);
    }

    /**
     * 163 等公共服务要求 MAIL FROM 与登录账号一致，不一致会被 550 退回，
     * 所以配置里没写 fromEmail 时直接取 username。
     */
    private function fromAddress(): string
    {
        $from = $this->fromEmail !== '' ? $this->fromEmail : $this->username;
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('站点发信地址未配置，请在 params-local.php 设置 smtp.fromEmail。');
        }

        if ($this->username !== '' && strcasecmp($from, $this->username) !== 0) {
            Yii::warning("发信地址 {$from} 与 SMTP 账号 {$this->username} 不一致，可能被供应商退回", 'mail');
        }

        return $from;
    }
}
