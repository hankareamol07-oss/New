<?php
// Serves textbook page images (data/book_pages/<book_id>/<page>.jpg) and cropped figures (data/book_figures/<book_id>/<page>_<n>.jpg)
require_once __DIR__ . '/config.php';

$f = (string)($_GET['f'] ?? '');
if (!preg_match('~^\d+/\d{3}(_\d+)?\.jpg$~', $f, $m)) {
    http_response_code(400);
    exit;
}
$file = EP_ROOT . '/data/' . (empty($m[1]) ? 'book_pages' : 'book_figures') . '/' . $f;
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000');
readfile($file);
