<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/**
 * QR-коди столиків (частина 2, п. 7.6). Бібліотека chillerlan/php-qrcode входить до залежностей Filament.
 */
final class QrCode
{
    /**
     * Розмітка SVG без заголовка XML — її можна вставляти просто в сторінку.
     * Світлі модулі не малюються, тож код треба показувати на білому тлі.
     */
    public static function svg(string $text): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            // Рівень M відновлює до 15 % коду: табличка на столику затирається й дряпається
            'eccLevel' => EccLevel::M,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
        ]);

        return (new Generator($options))->render($text);
    }
}
