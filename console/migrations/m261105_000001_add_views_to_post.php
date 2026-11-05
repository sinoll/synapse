<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * 浏览数计数列。
 *
 * 浏览数是每条内容的属性，不是博客专有的：blog.views 只覆盖四种内容里的一种，而且站点里没有任何地方读写它。
 * 所以计数列搬到 post 上，四种内容详情页共用同一套按天去重的计数，blog.views 的值先搬过去再删列。
 */
class m261105_000001_add_views_to_post extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%post}}',
            'views',
            $this->integer()->notNull()->defaultValue(0)->after('like_count')->comment('浏览数，同一浏览器对同一条内容按天去重累计'),
        );

        $this->execute('UPDATE {{%post}} p JOIN {{%blog}} b ON b.post_id = p.id SET p.views = b.views');
        $this->dropColumn('{{%blog}}', 'views');
    }

    public function safeDown(): void
    {
        $this->addColumn(
            '{{%blog}}',
            'views',
            $this->integer()->defaultValue(0)->after('content')->comment('文章浏览数'),
        );

        $this->execute('UPDATE {{%blog}} b JOIN {{%post}} p ON p.id = b.post_id SET b.views = p.views');
        $this->dropColumn('{{%post}}', 'views');
    }
}
