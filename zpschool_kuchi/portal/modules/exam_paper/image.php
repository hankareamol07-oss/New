<?php
// Serves question images stored in data/images and data/competitive/images
if (!defined('EP_ROOT')) {
    define('EP_ROOT', __DIR__);
}
if (!defined('EP_IMAGE_DIR')) {
    define('EP_IMAGE_DIR', EP_ROOT . '/data/images');
}

$file = null;

if (!empty($_GET['comp'])) {
    $rawName = trim($_GET['comp']);
    $name = basename($rawName);
    $nameNoExt = preg_replace('/\.(?:png|jpg|jpeg|webp)$/i', '', $name);
    $candidates = [
        EP_ROOT . '/data/competitive/images/' . $name,
        EP_ROOT . '/data/competitive/images/' . $nameNoExt,
        EP_ROOT . '/data/images/' . $name,
        EP_ROOT . '/data/images/' . $name . '.png',
        EP_ROOT . '/data/images/' . $nameNoExt . '.png',
    ];
    foreach ($candidates as $c) {
        if (is_file($c)) {
            $file = $c;
            break;
        }
    }
} elseif (!empty($_GET['f'])) {
    $f = str_replace('\\', '/', trim($_GET['f']));
    $f = ltrim($f, '/');
    if (!str_contains($f, '..')) {
        $candidates = [
            EP_ROOT . '/data/' . $f,
            EP_ROOT . '/data/competitive/' . $f,
            EP_ROOT . '/data/competitive/images/' . basename($f),
            EP_ROOT . '/data/competitive/images/' . preg_replace('/\.(?:png|jpg|jpeg|webp)$/i', '', basename($f)),
            EP_ROOT . '/data/images/' . basename($f),
            EP_ROOT . '/data/images/' . basename($f) . '.png',
            EP_ROOT . '/data/book_figures/' . $f,
            EP_ROOT . '/data/book_pages/' . $f,
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                $file = $c;
                break;
            }
        }
    }
} else {
    $id = (int)($_GET['id'] ?? 0);
    $n = $_GET['n'] ?? '';
    $suffix = $n === '2' ? '_2' : ($n === 'a' ? '_a' : '');
    
    $candidates = [
        EP_IMAGE_DIR . '/' . $id . $suffix . '.png',
        EP_ROOT . '/data/competitive/images/' . $id . $suffix,
        EP_ROOT . '/data/competitive/images/' . $id,
        EP_ROOT . '/data/images/' . $id . '.png',
    ];
    foreach ($candidates as $c) {
        if (is_file($c)) {
            $file = $c;
            break;
        }
    }
}

if (!$file || !is_file($file)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = in_array($ext, ['jpg', 'jpeg']) ? 'image/jpeg' : ($ext === 'webp' ? 'image/webp' : 'image/png');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000');
readfile($file);
