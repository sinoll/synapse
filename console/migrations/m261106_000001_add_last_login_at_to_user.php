<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * 登录时间戳。
 *
 * "长时间没登录要验证码"需要一个判定基准，而 user 表里只有 created_at/updated_at：
 * updated_at 会被改资料、改密码刷写，用它当登录时间会让这层校验形同虚设，所以单独立一列。
 * 存量账户留 NULL，代表"没有历史可依"，第一次登录放行、之后才纳入阈值判断。
 */
class m261106_000001_add_last_login_at_to_user extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%user}}',
            'last_login_at',
            $this->integer()->null()->after('phone_verified_at')->comment('最近一次登录成功时间；NULL 表示启用统计前从未登录过'),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%user}}', 'last_login_at');
    }
}
