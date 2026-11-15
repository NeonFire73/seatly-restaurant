<?php

namespace App\Support\Install;

/**
 * Читання й точкова правка файлу .env для команди встановлення: змінюється лише значення потрібного ключа,
 * коментарі й порядок рядків лишаються як були.
 */
final class EnvFile
{
    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($this->path), $match) !== 1) {
            return null;
        }

        return self::unquote(trim($match[1]));
    }

    public function getOr(string $key, string $default): string
    {
        $value = $this->get($key);

        if (blank($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $content = (string) file_get_contents($this->path);

        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content) === 1) {
                // Заміна через функцію: у паролі можуть бути «$1» чи «\», які preg_replace сприйняв би як посилання
                $content = (string) preg_replace_callback($pattern, fn () => $line, $content, 1);
            } else {
                $content = rtrim($content, "\r\n").PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($this->path, $content);
    }

    /**
     * Лапки потрібні лише значенням із пробілами чи службовими символами: інакше .env обрізав би їх на «#» чи пробілі.
     */
    public static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return stripcslashes(substr($value, 1, -1));
        }

        if ($value === 'null') {
            return '';
        }

        return $value;
    }
}
