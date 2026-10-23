<?php
/**
 * GET /api/student/profile.php
 * Student Profile (Strictly Read-Only)
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole('student');
$studentId = (int)$user['linked_id'];

$db = getDB();
$stmt = $db->prepare("
    SELECT s.*, c.name as class_name, t.name_mr as class_teacher_name, t.phone as teacher_phone,
           (SELECT COUNT(*) FROM daily_attendance WHERE student_id = s.id AND school_id = ? AND status = 'P') as days_present,
           (SELECT COUNT(*) FROM daily_attendance WHERE student_id = s.id AND school_id = ?) as total_days
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.id = ? AND s.school_id = ? AND s.is_active = 1
    LIMIT 1
");
$stmt->execute([SCHOOL_ID, SCHOOL_ID, $studentId, SCHOOL_ID]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    apiError('विद्यार्थी तपशील आढळले नाहीत.', 404);
}

$present = (int)($student['days_present'] ?? 0);
$total = (int)($student['total_days'] ?? 0);
$attendance_percent = $total > 0 ? round(($present / $total) * 100, 1) : 100.0;

apiSuccess([
    'student' => $student,
    'attendance' => [
        'present_days' => $present,
        'total_days'   => $total,
        'percentage'   => $attendance_percent,
    ],
    'school' => getSchool(),
], 'विद्यार्थी प्रोफाईल यशस्वी.');
