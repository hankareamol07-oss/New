<?php
require_once __DIR__ . '/config/config.php';

foreach ([16 => 'Std 1 Teacher', 6 => 'Std 5 Teacher', 7 => 'Amol Hankare (Std 6)', 8 => 'Padamaja Posane (Std 7)'] as $tid => $name) {
    $_SESSION['user_id'] = $tid;
    $_SESSION['role'] = 'teacher';
    $_SESSION['linked_id'] = $tid;
    $s = get_current_teacher_scope();
    echo "Teacher $tid ($name) -> stds: " . json_encode($s['stds']) . "\n";
}
