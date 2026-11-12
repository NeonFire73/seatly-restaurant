<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Очищення форматованого тексту з адмінки перед збереженням: лишаються заголовки, списки,
 * таблиці, посилання; скрипти, обробники подій і javascript:-посилання вирізаються.
 */
final class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->dropElement('img'),
        );
    }
}
