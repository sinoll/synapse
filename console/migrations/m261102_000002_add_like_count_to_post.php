<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * 点赞数计数列。
 *
 * post_like 是 (post_id, user_id) 主键的关联表，列表页每行都要显示点赞数，
 * 逐条 COUNT 会变成 N+1，所以在 post 上放一份冗余计数，由 PostLike::toggle() 在同一事务里维护。
 */
class m261102_000002_add_like_count_to_post extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%post}}',
            'like_count',
            $this->integer()->notNull()->defaultValue(0)->after('visibility')->comment('点赞数，由 post_like 增删同步维护'),
        );

        $this->execute(
            'UPDATE {{%post}} SET like_count = ('
            . 'SELECT COUNT(*) FROM {{%post_like}} WHERE {{%post_like}}.post_id = {{%post}}.id'
            . ')',
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%post}}', 'like_count');
    }
}
