<?php

declare(strict_types=1);

namespace console\controllers;

use common\services\SmtpMailSender;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * 发信自检工具。
 *
 * doctor 按 配置 -> PHP 扩展 -> CA 证书 -> DNS -> TCP 的顺序逐层探测，
 * test 用线上同一个 mailSender 真发一封，并把原始 SMTP 响应链打出来。
 * 网页上报"邮件发送失败"时先跑这两条，能一次定位到哪一层断了。
 */
class MailController extends Controller
{
    public string $to = '';

    /**
     * Yii 只把 options() 列出的属性当命令行选项，否则 --to 会被判成未知选项。
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['to']);
    }

    /**
     * php yii mail/doctor
     */
    public function actionDoctor(): int
    {
        $sender = $this->sender();
        if (!$sender instanceof SmtpMailSender) {
            return ExitCode::CONFIG;
        }

        $this->stdout(sprintf(
            "环境：%s   PHP %s   本次读的 ini：%s\n",
            php_sapi_name(),
            PHP_VERSION,
            php_ini_loaded_file() ?: '(未找到 php.ini)',
        ));

        $failed = $this->checkConfig($sender);
        $failed = $this->checkOpenssl() || $failed;
        $failed = $this->checkCas() || $failed;
        $failed = $this->checkDns($sender->host) || $failed;
        $failed = $this->checkTcp($sender->host, $sender->port, $sender->encryption) || $failed;

        if (!$failed) {
            $this->stdout("\n以上全绿，接着真发一封：php yii mail/test --to=你的邮箱\n");
            $this->stdout("若 CLI 全绿但网页仍失败，是 FPM 读了另一份 php.ini："
                . "Ubuntu 下 /etc/php/8.3/fpm/php.ini 与 /etc/php/8.3/cli/php.ini 是分开的，改完要 restart php8.3-fpm。\n");
        }

        return $failed ? ExitCode::UNAVAILABLE : ExitCode::OK;
    }

    /**
     * php yii mail/test --to=someone@example.com
     */
    public function actionTest(): int
    {
        if (!filter_var($this->to, FILTER_VALIDATE_EMAIL)) {
            $this->stdout("用法：php yii mail/test --to=收件地址\n");

            return ExitCode::USAGE;
        }

        $sender = $this->sender();
        if (!$sender instanceof SmtpMailSender) {
            return ExitCode::CONFIG;
        }

        try {
            $sender->send(
                $this->to,
                sprintf('[%s] 发信自检', Yii::$app->name),
                '<p>这是一封由 <code>php yii mail/test</code> 发出的自检邮件，收到即表示发信通道通畅。</p>',
                '这是一封由 php yii mail/test 发出的自检邮件，收到即表示发信通道通畅。',
            );
        } catch (Throwable $exception) {
            $this->stdout("[FAIL] 投递失败，错误链（最内层才是 SMTP 的真实响应）：\n");
            for ($item = $exception; $item !== null; $item = $item->getPrevious()) {
                $this->stdout(sprintf("  %s: %s\n", get_class($item), $item->getMessage()));
            }

            return ExitCode::UNAVAILABLE;
        }

        $this->stdout(sprintf("[ OK ] 已投递到 %s，请查收（也看一下垃圾箱）。\n", $this->to));

        return ExitCode::OK;
    }

    /**
     * 取发信组件。配置里没有 mailSender 时 Yii 会抛 "Getting unknown property"，
     * 那句话对排障没有信息量，这里换成能照着改的提示。
     */
    private function sender(): SmtpMailSender|null
    {
        if (!Yii::$app->has('mailSender')) {
            $this->stdout(<<<TXT
                [FAIL] 当前应用的配置里没有 mailSender 组件。
                  检查 common/config/main.php 的 components.mailSender 是否存在，
                  以及它引用的 common/config/params.php 里有没有 smtp.* 这些键。
                  两边都 grep 不到的话，说明服务器上的代码不是本次改造后的版本：
                    grep -rn "mailSender" common/config/*.php
                    git log --oneline -1
                代码与配置必须一起部署，只覆盖其中几个文件就会出现这种"组件不存在"。

                TXT);

            return null;
        }

        $sender = Yii::$app->get('mailSender');
        if (!$sender instanceof SmtpMailSender) {
            $this->stdout(sprintf(
                "[FAIL] mailSender 组件是 %s，不是 SmtpMailSender。"
                . "常见原因是某个 *-local.php 里还留着旧的覆盖：grep -rn mailSender common/config/*.php\n",
                get_class($sender),
            ));

            return null;
        }

        return $sender;
    }

    /**
     * 打印生效中的配置，并提示几个最常见的填法错误。
     */
    private function checkConfig(SmtpMailSender $sender): bool
    {
        $this->stdout(sprintf(
            "配置：host=%s port=%d encryption=%s username=%s%s fromEmail=%s\n",
            $sender->host !== '' ? $sender->host : '(空)',
            $sender->port,
            $sender->encryption !== '' ? $sender->encryption : '(不加密)',
            $sender->username !== '' ? $sender->username : '(空)',
            $sender->password !== '' ? ' password=***(已设置)' : ' password=(空)',
            $sender->fromEmail !== '' ? $sender->fromEmail : '(空)',
        ));

        $missing = [];
        foreach (['host' => 'smtp.host', 'username' => 'smtp.username', 'password' => 'smtp.password'] as $property => $key) {
            if ($sender->$property === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            $this->stdout(sprintf(
                "[FAIL] 缺 %s —— 在 common/config/params-local.php 里补齐。该文件被 git 忽略，"
                . "php init 会按 environments/* 模板重新生成，之后需要重填凭据。\n",
                implode('、', $missing),
            ));

            return true;
        }

        if ($sender->fromEmail !== '' && strcasecmp($sender->fromEmail, $sender->username) !== 0) {
            $this->stdout(sprintf(
                "[WARN] fromEmail(%s) 与 SMTP 账号(%s) 不同址，163/QQ 会按 550 退回。\n",
                $sender->fromEmail,
                $sender->username,
            ));
        }

        if ($sender->port === 25) {
            $this->stdout("[WARN] 端口 25：云主机与家宽普遍封锁出方向 25，改用 465(encryption=ssl) 或 587(encryption=tls)。\n");
        }

        return false;
    }

    private function checkOpenssl(): bool
    {
        if (extension_loaded('openssl')) {
            $this->stdout("[ OK ] openssl 扩展已加载（TLS 与证书校验可用）\n");

            return false;
        }

        $this->stdout("[FAIL] openssl 扩展未加载：加密 SMTP 必然连不上。去掉当前 ini 中 `;extension=openssl` 的分号，"
            . "注意 cli 与 fpm 两份 ini 都要开。\n");

        return true;
    }

    private function checkCas(): bool
    {
        if (!extension_loaded('openssl')) {
            // 上一步已经报过 openssl 缺失，这里再调 openssl_* 函数会直接 fatal。
            $this->stdout("[SKIP] openssl 未加载，跳过 CA 检查\n");

            return true;
        }

        $caFile = (string) (openssl_get_cert_locations()['default_cert_file'] ?? '');
        if ($caFile === '' || !is_readable($caFile)) {
            $this->stdout(sprintf(
                "[FAIL] 读不到 CA 根证书（default_cert_file=%s），TLS 证书校验会失败。"
                . "Ubuntu：sudo apt-get install ca-certificates，或在 php.ini 设 "
                . "openssl.cafile=/etc/ssl/certs/ca-certificates.crt；"
                . "Windows：在 php.ini 把 openssl.cafile 指向 curl 的 ca bundle。\n",
                $caFile === '' ? '(空)' : $caFile,
            ));

            return true;
        }

        $this->stdout(sprintf("[ OK ] CA 根证书：%s\n", $caFile));

        return false;
    }

    private function checkDns(string $host): bool
    {
        if ($host === '') {
            $this->stdout("[SKIP] host 为空，跳过 DNS 检查\n");

            return false;
        }

        $addresses = gethostbynamel($host);
        if ($addresses === false) {
            $this->stdout(sprintf(
                "[FAIL] 解析 %s 失败。Ubuntu server 上先查 /etc/resolv.conf——"
                . "systemd-resolved 没接管时会出现桌面能解析、服务器不能。\n",
                $host,
            ));

            return true;
        }

        $this->stdout(sprintf("[ OK ] DNS：%s -> %s\n", $host, implode(', ', $addresses)));

        return false;
    }

    /**
     * 只探 TCP 层；TLS 握手与 AUTH 交给 mail/test，那里的报错更贴近实际投递。
     */
    private function checkTcp(string $host, int $port, string $encryption): bool
    {
        if ($host === '') {
            $this->stdout("[SKIP] host 为空，跳过端口检查\n");

            return false;
        }

        $timeout = 8;
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket === false) {
            $reason = $errno === 0 && $errstr === ''
                ? sprintf('%ds 内无响应，多半是出方向 %d 端口被云厂商或防火墙拦掉', $timeout, $port)
                : sprintf('%s (errno %d)', $errstr, $errno);
            $this->stdout(sprintf("[FAIL] 连接 %s:%d 失败：%s\n", $host, $port, $reason));

            return true;
        }

        fclose($socket);
        $this->stdout(sprintf(
            "[ OK ] TCP %s:%d 可连通（%s）\n",
            $host,
            $port,
            $encryption === 'ssl' ? '隐式 TLS' : '明文后 STARTTLS',
        ));

        return false;
    }
}
