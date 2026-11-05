<?php

return [
    // 发信 SMTP。公共邮箱（163/QQ）的 password 填"客户端授权码"，不是登录密码。
    // fromEmail 必须与 username 同址，否则供应商按 550 退回。
    // 服务器出网方向通常封 25，用 465（encryption=ssl）或 587（encryption=tls）。
    'smtp.host' => '',
    'smtp.port' => 465,
    'smtp.encryption' => 'ssl',
    'smtp.username' => '',
    'smtp.password' => '',
    'smtp.fromEmail' => '',
    'smtp.fromName' => '',
];
