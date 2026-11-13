<?php
declare(strict_types=1);

namespace App;

final class Env
{
    private static array $v = [];

    public static function load(?string $file = null): void
    {
        $file ??= (getenv('PLAN_ENV_FILE') ?: self::detect());
        if (!is_file($file)) {
            throw new \RuntimeException('Env file not found: ' . $file);
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $val] = explode('=', $line, 2);
            self::$v[trim($k)] = trim(trim($val), "\"'");
        }
    }

    private static function detect(): string
    {
        $shared = dirname(ROOT, 2) . '/shared/.env';
        return is_file($shared) ? $shared : ROOT . '/.env';
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$v[$key] ?? $default;
    }

    public static function sharedDir(): string
    {
        return dirname((string)self::get('DB_PATH'), 2);
    }
}
