<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/hpc/list.php');
}

// CSRF token validation
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid CSRF token');
    redirect(APP_URL . '/hpc/list.php');
}

$db = getDB();
$id = intval($_POST['id'] ?? 0);
$stmt = $db->prepare("DELETE FROM hpc_cards WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $_SESSION['school_id']]);
flash('success', 'HPC कार्ड हटवला गेला.');
redirect(APP_URL . '/hpc/list.php');
