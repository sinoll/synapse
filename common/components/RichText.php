<?php

declare(strict_types=1);

namespace common\components;

use yii\helpers\HtmlPurifier;

/**
 * Single source of truth for sanitising user-authored rich text.
 *
 * Storage (model beforeValidate), the editor surface and every render path must go through
 * purify(), otherwise a tag or style accepted on one side silently disappears on another.
 */
final class RichText
{
    /**
     * Inline styles the editor may produce. Anything outside this list is dropped by HTMLPurifier,
     * including position, url() values and event handlers.
     */
    private const ALLOWED_STYLE_PROPERTIES = ['color', 'font-size', 'text-align'];

    private const HTML_ALLOWED = 'p[style],div[style],span[style],h2[style],h3[style],'
        . 'blockquote[style],br,strong,b,em,i,u,s,ul,ol,li,'
        . 'a[href|title],img[src|alt|title],pre,code';

    public static function purify(?string $html): string
    {
        return HtmlPurifier::process((string) $html, [
            'HTML.Allowed' => self::HTML_ALLOWED,
            'CSS.AllowedProperties' => self::ALLOWED_STYLE_PROPERTIES,
        ]);
    }
}
