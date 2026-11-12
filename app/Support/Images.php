<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Зменшені копії завантажених зображень у WebP для srcset.
 * Для dishes/abc.jpg створюються dishes/abc-480.webp, dishes/abc-960.webp, dishes/abc-1600.webp.
 */
final class Images
{
    /** @var list<int> */
    public const WIDTHS = [480, 960, 1600];

    private const DISK = 'public';

    private const QUALITY = 80;

    public static function generateVariants(string $path): void
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            return;
        }

        $source = @imagecreatefromstring((string) $disk->get($path));

        if (! $source instanceof GdImage) {
            return;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        foreach (self::WIDTHS as $index => $targetWidth) {
            // Не збільшуємо: якщо оригінал вужчий, робимо лише одну копію в його розмірі
            if ($targetWidth > $width && $index > 0) {
                break;
            }

            $newWidth = min($targetWidth, $width);
            $newHeight = (int) round($height * $newWidth / $width);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            ob_start();
            imagewebp($resized, null, self::QUALITY);
            $disk->put(self::variantPath($path, $targetWidth), (string) ob_get_clean());

            imagedestroy($resized);
        }

        imagedestroy($source);
    }

    public static function deleteWithVariants(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        $disk->delete([$path, ...array_map(fn (int $w) => self::variantPath($path, $w), self::WIDTHS)]);
    }

    public static function variantPath(string $path, int $width): string
    {
        $info = pathinfo($path);
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

        return "{$dir}{$info['filename']}-{$width}.webp";
    }

    /**
     * @return array<int, string> ширина → адреса, лише для копій, що існують
     */
    public static function variants(?string $path): array
    {
        if (blank($path)) {
            return [];
        }

        $disk = Storage::disk(self::DISK);
        $result = [];

        foreach (self::WIDTHS as $width) {
            $variant = self::variantPath($path, $width);

            if ($disk->exists($variant)) {
                $result[$width] = $disk->url($variant);
            }
        }

        return $result;
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
