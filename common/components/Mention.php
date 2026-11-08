<?php

declare(strict_types=1);

namespace common\components;

use common\models\User;
use yii\helpers\Html;

/**
 * 把正文里的 @用户名 / @昵称 转成用户主页链接。
 *
 * 与 Tag::linkifyHashtags 一样按 HTML 标签分段、跳过已有 <a> 内部，
 * 因此既可用于富文本（净化后），也可用于 Html::encode 之后的纯文本。
 * 解析结果按请求缓存，列表页整页只查一次用户表。
 */
final class Mention
{
    /** @ 前面不能紧跟字母/数字/下划线，避免把邮箱地址的后半段当成提及。 */
    private const PATTERN = '/(?<![\p{L}\p{N}_])@([\p{L}\p{N}_-]{1,80})/u';

    /** @var array<string,int|null> 小写名称 => 用户 ID，null 表示查无此人。 */
    private static array $resolved = [];

    public static function linkify(string $html): string
    {
        if (!str_contains($html, '@')) {
            return $html;
        }

        $segments = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            return $html;
        }

        $names = [];
        $insideAnchor = false;
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (str_starts_with($segment, '<')) {
                if (preg_match('/^<a\b/i', $segment)) {
                    $insideAnchor = true;
                } elseif (preg_match('/^<\/a\s*>$/i', $segment)) {
                    $insideAnchor = false;
                }
                continue;
            }
            if ($insideAnchor) {
                continue;
            }
            if (preg_match_all(self::PATTERN, $segment, $matches)) {
                foreach ($matches[1] as $rawName) {
                    $names[] = self::decodeName($rawName);
                }
            }
        }

        $map = self::resolve($names);
        if ($map === []) {
            return $html;
        }

        $insideAnchor = false;
        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }
            if (str_starts_with($segment, '<')) {
                if (preg_match('/^<a\b/i', $segment)) {
                    $insideAnchor = true;
                } elseif (preg_match('/^<\/a\s*>$/i', $segment)) {
                    $insideAnchor = false;
                }
                continue;
            }
            if ($insideAnchor) {
                continue;
            }
            $segments[$index] = (string) preg_replace_callback(
                self::PATTERN,
                static function (array $match) use ($map): string {
                    $id = $map[mb_strtolower(self::decodeName($match[1]))] ?? null;
                    if ($id === null) {
                        return $match[0];
                    }

                    return Html::a('@' . $match[1], ['/users/profile', 'id' => $id], ['class' => 'mention-link']);
                },
                $segment
            );
        }

        return implode('', $segments);
    }

    /**
     * @param string[] $names
     * @return array<string,int> 小写名称 => 用户 ID
     */
    private static function resolve(array $names): array
    {
        $pending = [];
        foreach ($names as $name) {
            $key = mb_strtolower($name);
            if ($key !== '' && !array_key_exists($key, self::$resolved)) {
                $pending[$key] = $name;
            }
        }

        if ($pending !== []) {
            $users = User::find()
                ->select(['id', 'username', 'nickname'])
                ->where(['status' => User::STATUS_ACTIVE])
                ->andWhere(['or', ['username' => array_values($pending)], ['nickname' => array_values($pending)]])
                ->all();

            foreach ($pending as $key => $name) {
                self::$resolved[$key] = null;
            }
            foreach ($users as $user) {
                // 用户名唯一，优先于可能重名的昵称。
                self::$resolved[mb_strtolower((string) $user->username)] = (int) $user->id;
                $nicknameKey = mb_strtolower((string) $user->nickname);
                if ($nicknameKey !== '' && (self::$resolved[$nicknameKey] ?? null) === null) {
                    self::$resolved[$nicknameKey] = (int) $user->id;
                }
            }
        }

        $map = [];
        foreach (array_unique(array_map('mb_strtolower', $names)) as $key) {
            if ((self::$resolved[$key] ?? null) !== null) {
                $map[$key] = self::$resolved[$key];
            }
        }

        return $map;
    }

    /** 文本段可能已经过 Html::encode，查库前先还原实体。 */
    private static function decodeName(string $name): string
    {
        return html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}