<?php
require_once __DIR__ . '/config/config.php';

$_SESSION['user_id'] = 7;
$_SESSION['role'] = 'teacher';
$_SESSION['linked_id'] = 7;

ob_start();
$_REQUEST['std'] = 6;
include __DIR__ . '/cce/marks_entry.php';
$out6 = ob_get_clean();

ob_start();
$_REQUEST['std'] = 7;
include __DIR__ . '/cce/marks_entry.php';
$out7 = ob_get_clean();

echo "Teacher 7 (Amol Hankare):\n";
echo "  Std 6 Marks entry page: " . strlen($out6) . " bytes | Std 6 in page: " . (strpos($out6, 'इयत्ता सहावी') !== false ? 'YES' : 'NO') . "\n";
echo "  Std 7 Marks entry page: " . strlen($out7) . " bytes | Std 7 in page: " . (strpos($out7, 'इयत्ता सातवी') !== false ? 'YES' : 'NO') . "\n";
