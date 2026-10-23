<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/students/list.php');
}

// CSRF token validation
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid CSRF token');
    redirect(APP_URL . '/students/list.php');
}

$db = getDB();
$id = intval($_POST['id'] ?? 0);
$stmt = $db->prepare("UPDATE students SET is_active = 0 WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $_SESSION['school_id']]);
flash('success', 'विद्यार्थी हटवला गेला.');
redirect(APP_URL . '/students/list.php');
