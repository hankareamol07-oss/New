<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();

$db->prepare("INSERT INTO teacher_classes (school_id, teacher_id, class_id, std, division, academic_year, is_class_teacher) VALUES (2, 7, 15, 7, 'A', '2026-27', 0) ON DUPLICATE KEY UPDATE std=7")->execute();
$db->prepare("INSERT INTO teacher_classes (school_id, teacher_id, class_id, std, division, academic_year, is_class_teacher) VALUES (2, 8, 14, 6, 'A', '2026-27', 0) ON DUPLICATE KEY UPDATE std=6")->execute();

$st = $db->query("SELECT tc.teacher_id, t.name_mr, tc.class_id, tc.std FROM teacher_classes tc JOIN teachers t ON tc.teacher_id = t.id WHERE tc.teacher_id IN (7, 8)");
print_r($st->fetchAll(PDO::FETCH_ASSOC));
