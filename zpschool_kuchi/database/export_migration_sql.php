<?php
require_once __DIR__ . '/../../new/portal/config/database.php';

try {
    $db = getDB();
    $sqlOut = __DIR__ . '/migration_single_school.sql';
    
    $out = "-- ============================================================================\n";
    $out .= "-- MIGRATION SCRIPT: Z. P. SCHOOL NO. 2 KUCHI (SINGLE-SCHOOL PRO VERSION)\n";
    $out .= "-- UDISE: 27350302306 | TALUKA: KAVATHE MAHANKAL | DISTRICT: SANGLI\n";
    $out .= "-- ============================================================================\n\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Table DDLs with CREATE TABLE IF NOT EXISTS
    $tables = ['schools', 'classes', 'teachers', 'teacher_classes', 'students', 'users'];
    foreach ($tables as $tbl) {
        $row = $db->query("SHOW CREATE TABLE `$tbl`")->fetch(PDO::FETCH_ASSOC);
        $ddl = str_replace("CREATE TABLE `", "CREATE TABLE IF NOT EXISTS `", $row['Create Table']);
        $out .= "-- Table structure for `$tbl`\n";
        $out .= $ddl . ";\n\n";
    }

    // Dump records for school_id = 2
    $out .= "-- Data for `schools` (Z. P. School No 2 Kuchi)\n";
    $s_row = $db->query("SELECT * FROM `schools` WHERE `id` = 2")->fetch(PDO::FETCH_ASSOC);
    $cols = array_keys($s_row);
    $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); }, array_values($s_row));
    $out .= "INSERT INTO `schools` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ") ON DUPLICATE KEY UPDATE `is_active`=1;\n\n";

    // Dump classes
    $out .= "-- Data for `classes`\n";
    $c_rows = $db->query("SELECT * FROM `classes` WHERE `school_id` = 2")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($c_rows as $cr) {
        $cols = array_keys($cr);
        $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); }, array_values($cr));
        $out .= "INSERT INTO `classes` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ") ON DUPLICATE KEY UPDATE `class_teacher_id`=VALUES(`class_teacher_id`);\n";
    }
    $out .= "\n";

    // Dump teachers
    $out .= "-- Data for `teachers`\n";
    $t_rows = $db->query("SELECT * FROM `teachers` WHERE `school_id` = 2")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($t_rows as $tr) {
        $cols = array_keys($tr);
        $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); }, array_values($tr));
        $out .= "INSERT INTO `teachers` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ") ON DUPLICATE KEY UPDATE `phone`=VALUES(`phone`), `class_assigned`=VALUES(`class_assigned`);\n";
    }
    $out .= "\n";

    // Dump teacher_classes
    $out .= "-- Data for `teacher_classes`\n";
    $tc_rows = $db->query("SELECT * FROM `teacher_classes` WHERE `school_id` = 2")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tc_rows as $tcr) {
        $cols = array_keys($tcr);
        $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); }, array_values($tcr));
        $out .= "INSERT INTO `teacher_classes` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ") ON DUPLICATE KEY UPDATE `is_class_teacher`=VALUES(`is_class_teacher`);\n";
    }
    $out .= "\n";

    // Dump students
    $out .= "-- Data for `students` (190 students)\n";
    $out .= "DELETE FROM `students` WHERE `school_id` = 2;\n";
    $st_rows = $db->query("SELECT * FROM `students` WHERE `school_id` = 2")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($st_rows as $str) {
        $cols = array_keys($str);
        $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); }, array_values($str));
        $out .= "INSERT INTO `students` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
    }
    $out .= "\n";

    // Dump users
    $out .= "-- Data for `users` (Admin, Teachers, Students)\n";
    $out .= "DELETE FROM `users` WHERE `school_id` = 2;\n";
    $u_rows = $db->query("SELECT * FROM `users` WHERE `school_id` = 2")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($u_rows as $ur) {
        $cols = array_keys($ur);
        $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->quote($v); }, array_values($ur));
        $out .= "INSERT INTO `users` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ") ON DUPLICATE KEY UPDATE `password_hash`=VALUES(`password_hash`);\n";
    }
    $out .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

    file_put_contents($sqlOut, $out);
    echo "Exported full SQL migration file: $sqlOut (" . number_format(filesize($sqlOut)) . " bytes)\n";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
