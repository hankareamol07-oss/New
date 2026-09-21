<?php
// Serves question images stored in data/images as <question_id>.png, <question_id>_2.png, <question_id>_a.png
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$n = $_GET['n'] ?? '';
$suffix = $n === '2' ? '_2' : ($n === 'a' ? '_a' : '');
$file = EP_IMAGE_DIR . '/' . $id . $suffix . '.png';
if ($id <= 0 || !is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000');
readfile($file);
