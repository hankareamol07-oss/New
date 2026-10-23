<?php
// Serves textbook page images stored in data/book_pages/<book_id>/<page>.jpg
// and cropped figures in data/book_figures/<book_id>/<page>_<n>.jpg
require_once __DIR__ . '/config.php';

$f = str_replace('\\', '/', trim((string)($_GET['f'] ?? '')));
$f = ltrim($f, '/');

// Allow book_id/page.jpg and book_id/page_item.jpg (e.g. 53/050_103.jpg, 10/158.jpg, 2/013_101.jpg)
if (!preg_match('~^\d+/[\w\-.]+\.(?:jpg|jpeg|png|webp)$~i', $f)) {
    http_response_code(400);
    exit;
}

$candidates = [
    EP_ROOT . '/data/book_figures/' . $f,
    EP_ROOT . '/data/book_pages/' . $f,
    EP_ROOT . '/data/' . $f,
];

$file = null;
foreach ($candidates as $c) {
    if (is_file($c)) {
        $file = $c;
        break;
    }
}

if (!$file) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = in_array($ext, ['jpg', 'jpeg']) ? 'image/jpeg' : ($ext === 'png' ? 'image/png' : 'image/jpeg');

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000');
readfile($file);
