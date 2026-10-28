<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes HTML coming out of the rich-text editor before it is persisted,
 * so stored content can be rendered raw (formatting preserved) without
 * being vulnerable to stored XSS.
 */
class RichText
{
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', implode(',', [
            'p[style]', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
            'h1[style]', 'h2[style]', 'h3[style]', 'h4[style]', 'h5[style]', 'h6[style]',
            'ul', 'ol', 'li[style]', 'blockquote',
            'a[href|target|rel]',
        ]));
        $config->set('CSS.AllowedProperties', ['text-align']);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('Cache.DefinitionImpl', null);

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }
}
