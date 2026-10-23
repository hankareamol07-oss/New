<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function flash_set(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if (!is_array($items) || isset($items['message'])) {
        $items = [$items];
    }
    $out = [];
    foreach ($items as $item) {
        if (is_array($item) && isset($item['message'])) {
            $out[] = ['type' => (string) ($item['type'] ?? 'success'), 'message' => (string) $item['message']];
        } elseif (is_string($item) && $item !== '') {
            $out[] = ['type' => 'success', 'message' => $item];
        }
    }
    return $out;
}
