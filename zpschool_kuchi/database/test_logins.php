<?php
require_once __DIR__ . '/../../new/portal/config/database.php';

$db = getDB();

$tests = [
    ['role' => 'admin',   'mobile' => '9988990000', 'password' => 'Admin@Kuchi2026',   'desc' => 'Demo Admin'],
    ['role' => 'admin',   'mobile' => '9405599703', 'password' => 'Admin@Kuchi2026',   'desc' => 'Sangita Patil (Headmaster)'],
    ['role' => 'teacher', 'mobile' => '9960967574', 'password' => 'Teacher@Kuchi2026', 'desc' => 'Amol Hankare (Class 6)'],
    ['role' => 'teacher', 'mobile' => '9766000001', 'password' => 'Teacher@Kuchi2026', 'desc' => 'Aparna Patil/Pol (Class 1)'],
    ['role' => 'teacher', 'mobile' => '9000000010', 'password' => 'Teacher@Kuchi2026', 'desc' => 'Demo Teacher (Std 6)'],
    ['role' => 'student', 'mobile' => '9876543210', 'password' => 'Student@Kuchi2026', 'desc' => 'Demo Student (Std 1)'],
];

echo "=== VERIFYING DEMO & REAL LOGINS ===\n";
foreach ($tests as $t) {
    $stmt = $db->prepare("SELECT * FROM users WHERE mobile = ? AND role = ? AND school_id = 2 AND is_active = 1 LIMIT 1");
    $stmt->execute([$t['mobile'], $t['role']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        echo "[FAIL] User not found: {$t['desc']} ({$t['mobile']}, role: {$t['role']})\n";
        continue;
    }
    if (password_verify($t['password'], $u['password_hash'])) {
        echo "[PASS] {$t['desc']} ({$t['mobile']}) verified successfully! Linked ID: {$u['linked_id']}\n";
    } else {
        echo "[FAIL] Password verification failed for: {$t['desc']}\n";
    }
}

echo "\n=== TEACHER STRICT CLASS VISIBILITY CHECK ===\n";
$stmt = $db->query("SELECT t.id, t.name_mr, t.phone, tc.std, tc.division, c.name as class_name, 
                    (SELECT COUNT(*) FROM students s WHERE s.teacher_id = t.id AND s.school_id = 2) as student_count
                    FROM teachers t
                    JOIN teacher_classes tc ON t.id = tc.teacher_id AND tc.is_class_teacher = 1
                    JOIN classes c ON tc.class_id = c.id
                    WHERE t.school_id = 2 ORDER BY tc.std");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Teacher: {$row['name_mr']} | Class: {$row['class_name']} ('{$row['division']}') | Only Sees: {$row['student_count']} Students\n";
}
