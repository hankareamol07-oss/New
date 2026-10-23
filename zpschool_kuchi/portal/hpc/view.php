<?php
/**
 * HPC View router — routes to foundational (std 1–2), or stage print for std 3–8.
 */
require_once __DIR__ . '/../config/database.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT id, stage_key, std FROM hpc_cards WHERE id = ? AND school_id = ?');
$stmt->execute([$id, (int) $_SESSION['school_id']]);
$card = $stmt->fetch();

if (!$card) {
    flash('error', 'HPC कार्ड सापडले नाही.');
    redirect(APP_URL . '/hpc/index.php');
}

$std = (int) ($card['std'] ?? 0);
$stage = $card['stage_key'] ?: ($std >= 3 ? 'stage' : 'foundational');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($stage === 'foundational' || $std < 3) {
    redirect(APP_URL . '/cards/print_hpc_19.php?id=' . $id . '&format=official19');
} else {
    redirect(APP_URL . '/cards/print_hpc_stage.php?id=' . $id);
}