<?php
/**
 * GET /api/auth/me.php
 * Fetch authenticated profile & assigned scope
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireAuth();
$db = getDB();

$data = [
    'user'   => $user,
    'school' => getSchool(),
];

if ($user['role'] === 'teacher') {
    $scope = getTeacherClassScope($user);
    $data['scope'] = $scope;
} elseif ($user['role'] === 'student') {
    $st = $db->prepare("SELECT s.*, c.name as class_name, t.name_mr as class_teacher_name 
                        FROM students s 
                        LEFT JOIN classes c ON s.class_id = c.id 
                        LEFT JOIN teachers t ON s.teacher_id = t.id 
                        WHERE s.id = ? AND s.school_id = ?");
    $st->execute([(int)$user['linked_id'], SCHOOL_ID]);
    $data['student'] = $st->fetch(PDO::FETCH_ASSOC);
}

apiSuccess($data, 'वापरकर्ता तपशील यशस्वी.');
