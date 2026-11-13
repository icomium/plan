<?php
declare(strict_types=1);

namespace App;

use App\Controllers\StructureController;

final class App
{
    public static function run(): void
    {
        try {
            Env::load();
            self::startSession();

            $c = new StructureController();
            $r = new Router();
            $r->add('GET', '/', fn() => redirect('/structure'));
            $r->add('GET', '/structure', fn($a) => $c->overview(false));

            if (Guard::canEdit()) {
                $r->add('GET', '/admin/structure', fn($a) => $c->overview(true));
                $r->add('GET', '/admin/structure/export', fn($a) => $c->export());
                $r->add('GET', '/admin/structure/{entity}/new', fn($a) => $c->form($a['entity'], null));
                $r->add('GET', '/admin/structure/{entity}/{id}/edit', fn($a) => $c->form($a['entity'], (int)$a['id']));
                $r->add('POST', '/admin/structure/{entity}/save', fn($a) => $c->save($a['entity']));
                $r->add('POST', '/admin/structure/{entity}/{id}/toggle', fn($a) => $c->toggle($a['entity'], (int)$a['id']));
            }

            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', rawurldecode($path));
        } catch (\Throwable $e) {
            error_log((string)$e);
            http_response_code(500);
            if (Env::get('APP_DEBUG') === '1') {
                echo '<pre>' . e((string)$e) . '</pre>';
            } else {
                echo 'Внутренняя ошибка сервера.';
            }
        }
    }

    private static function startSession(): void
    {
        $dir = Env::sharedDir() . '/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        session_save_path($dir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_maxlifetime', '86400');
        session_name('plan_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}
