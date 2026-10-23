<?php
declare(strict_types=1);

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function initial(string $value): string
{
    return preg_match('/^./u', $value, $match) ? $match[0] : substr($value, 0, 1);
}

function url(string $path = ''): string
{
    $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    if ($path === '' || $path === '/') {
        return $base !== '' ? $base : '/';
    }
    return $base . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function valid_csrf(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return is_string($token) && hash_equals((string) ($_SESSION['csrf'] ?? ''), $token);
}

function store_jsonl(string $file, array $payload): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
}
