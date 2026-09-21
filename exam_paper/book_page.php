<?php
// Serves textbook page images stored in data/book_pages/<book_id>/<page>.jpg
require_once __DIR__ . '/config.php';

$f = (string)($_GET['f'] ?? '');
if (!preg_match('~^\d+/\d{3}\.jpg$~', $f)) {
    http_response_code(400);
    exit;
}
$file = EP_ROOT . '/data/book_pages/' . $f;
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000');
readfile($file);
