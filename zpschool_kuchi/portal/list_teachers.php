<?php
require __DIR__ . '/config/config.php';
$db = getDB();
$rows = $db->query("
    SELECT u.id, u.name, u.mobile, u.role, u.linked_id, t.name as t_name, tc.class_id, tc.std, tc.division 
    FROM users u 
    LEFT JOIN teachers t ON u.linked_id = t.id 
    LEFT JOIN teacher_classes tc ON t.id = tc.teacher_id 
    WHERE u.school_id = 2 AND u.role = 'teacher'
    ORDER BY tc.std
")->fetchAll();

foreach ($rows as $r) {
    echo "Mobile: {$r['mobile']} | User: {$r['name']} | Teacher: {$r['t_name']} | Std: {$r['std']} | Div: {$r['division']} | TeacherID: {$r['linked_id']}\n";
}
