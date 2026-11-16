<?php

declare(strict_types=1);

namespace common\services;

use common\models\Post;
use yii\db\ActiveQuery;

final class PostSearch
{
    public const MAX_KEYWORD_LENGTH = 60;

    public static function keyword(string $value): string
    {
        return mb_substr(trim($value), 0, self::MAX_KEYWORD_LENGTH);
    }

    public static function query(string $keyword, ?string $postType, ?int $viewerId): ActiveQuery
    {
        $query = Post::find()
            ->alias('p')
            ->select('p.*')
            ->with(['user', 'blog', 'images', 'moment', 'shortVideo', 'tags', 'categories'])
            ->leftJoin(['b' => '{{%blog}}'], 'b.post_id = p.id')
            ->leftJoin(['m' => '{{%moment}}'], 'm.post_id = p.id')
            ->leftJoin(['v' => '{{%short_video}}'], 'v.post_id = p.id')
            ->leftJoin(['i' => '{{%images}}'], 'i.post_id = p.id')
            ->leftJoin(['pt' => '{{%post_tag}}'], 'pt.post_id = p.id')
            ->leftJoin(['t' => '{{%tag}}'], 't.id = pt.tag_id')
            ->leftJoin(['pc' => '{{%post_cate}}'], 'pc.post_id = p.id')
            ->leftJoin(['c' => '{{%cate}}'], 'c.id = pc.cate_id')
            ->leftJoin(['u' => '{{%user}}'], 'u.id = p.user_id')
            ->where(['p.status' => Post::STATUS_PUBLISHED])
            ->andWhere([
                'or',
                ['like', 'p.title', $keyword],
                ['like', 'b.title', $keyword],
                ['like', 'b.summary', $keyword],
                ['like', 'b.content', $keyword],
                ['like', 'm.title', $keyword],
                ['like', 'm.content', $keyword],
                ['like', 'v.title', $keyword],
                ['like', 'v.description', $keyword],
                ['like', 'i.title', $keyword],
                ['like', 'i.alt_text', $keyword],
                ['like', 't.name', $keyword],
                ['like', 'c.name', $keyword],
                ['like', 'u.nickname', $keyword],
                ['like', 'u.username', $keyword],
            ])
            ->distinct()
            ->orderBy(['p.created_at' => SORT_DESC, 'p.id' => SORT_DESC]);

        if ($postType !== null) {
            $query->andWhere(['p.type' => $postType]);
        }
        Post::applyVisibilityScope($query, $viewerId);

        return $query;
    }
}
