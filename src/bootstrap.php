<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

umask(0002);
mb_internal_encoding('UTF-8');

spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require ROOT . '/src/helpers.php';
