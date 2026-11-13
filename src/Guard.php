<?php
declare(strict_types=1);

namespace App;

final class Guard
{
    public static function canEdit(): bool
    {
        return Env::get('APP_ENV') === 'test'
            && ($_SERVER['REMOTE_USER'] ?? '') === Env::get('EDITOR_USER', 'godoftasks')
            && strtolower($_SERVER['SERVER_NAME'] ?? '') === strtolower((string)Env::get('APP_HOST'));
    }

    public static function actor(): string
    {
        return $_SERVER['REMOTE_USER'] ?? 'anonymous';
    }
}
