<?php

return [
    // 发信 SMTP。公共邮箱（163/QQ）的 password 填"客户端授权码"，不是登录密码。
    // fromEmail 必须与 username 同址，否则供应商按 550 退回。
    // 本机联调可指向免鉴权转发器（papercut/smtp4dev）：encryption 留空、port 填其监听端口。
    'smtp.host' => '',
    'smtp.port' => 465,
    'smtp.encryption' => 'ssl',
    'smtp.username' => '',
    'smtp.password' => '',
    'smtp.fromEmail' => '',
    'smtp.fromName' => '',
];
