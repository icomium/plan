<?php
declare(strict_types=1);

function e(mixed $s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    $h = bin2hex($d);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

function d(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '—';
    }
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    return $t ? $t->format('d.m.Y') : e($iso);
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals((string)($_SESSION['csrf'] ?? ''), $t)) {
        render('error', ['code' => 419, 'message' => 'Сессия устарела. Обновите страницу и повторите действие.'], 419);
        exit;
    }
}

function render(string $view, array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Cache-Control: no-store');
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    $flashes = take_flash();
    require ROOT . '/views/layout.php';
}
