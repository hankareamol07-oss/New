<?php
require_once __DIR__ . '/../../new/portal/config/database.php';
$db = getDB();
echo "Classes:\n";
print_r($db->query("SELECT id, school_id, std, name, section, class_teacher_id FROM classes")->fetchAll(PDO::FETCH_ASSOC));
echo "Teachers:\n";
print_r($db->query("SELECT id, school_id, name, phone, class_assigned FROM teachers")->fetchAll(PDO::FETCH_ASSOC));
