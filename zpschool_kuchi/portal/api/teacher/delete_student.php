<?php
declare(strict_types=1);

/**
 * POST /api/teacher/delete_student.php
 * Soft-deletes (deactivates) a student and disables their user account.
 * Only allowed for the assigned class teacher or school admin.
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$scope = getTeacherClassScope($user);
$db = getDB();

$in = getJsonInput();
$studentId = (int)($in['student_id'] ?? $_POST['student_id'] ?? 0);

if ($studentId <= 0) {
    apiError('कृपया योग्य विद्यार्थी निवडा.', 400);
}

// 1. Verify student exists and belongs to this school
$stmt = $db->prepare("SELECT id, school_id, class_id, std, roll_no, name, name_mr FROM students WHERE id = ? AND school_id = ? LIMIT 1");
$stmt->execute([$studentId, SCHOOL_ID]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    apiError('विद्यार्थी सापडला नाही.', 404);
}

// 2. Enforce teacher class scope
if (!$scope['is_admin']) {
    $std = (int)$student['std'];
    $classId = (int)$student['class_id'];
    if (!in_array($std, $scope['stds']) && !in_array($classId, $scope['class_ids'])) {
        apiError('प्रवेश नाकारला: आपण केवळ आपल्या वर्गातील विद्यार्थी काढू शकता.', 403);
    }
}

// 3. Soft-delete student record
$u1 = $db->prepare("UPDATE students SET is_active = 0 WHERE id = ? AND school_id = ?");
$u1->execute([$studentId, SCHOOL_ID]);

// 4. Deactivate associated user login if any
$u2 = $db->prepare("UPDATE users SET is_active = 0 WHERE linked_id = ? AND role = 'student' AND school_id = ?");
$u2->execute([$studentId, SCHOOL_ID]);

$stName = $student['name_mr'] ?: $student['name'];

apiSuccess([
    'student_id' => $student['id'],
    'name'       => $stName,
    'std'        => $student['std'],
    'roll_no'    => $student['roll_no']
], "विद्यार्थी '{$stName}' वर्ग यादीतून यशस्वीरित्या काढण्यात आला आहे.");
