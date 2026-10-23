<?php
declare(strict_types=1);

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$scope = getTeacherClassScope($user);
$db = getDB();

$in = getJsonInput();
$studentId = (int)($in['student_id'] ?? $_POST['student_id'] ?? 0);
$customPass = trim((string)($in['new_password'] ?? $_POST['new_password'] ?? '123456'));

if ($studentId <= 0) {
    apiError('कृपया वैध विद्यार्थी निवडा.', 400);
}

// 1. Verify student exists and belongs to school
$stmt = $db->prepare("SELECT id, school_id, class_id, std, roll_no, name, name_mr, student_pen FROM students WHERE id = ? AND school_id = ? LIMIT 1");
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
        apiError('प्रवेश नाकारला: आपण केवळ आपल्या वर्गातील विद्यार्थ्यांचा पासवर्ड रीसेट करू शकता.', 403);
    }
}

// 3. Find user account for this student
$uStmt = $db->prepare("SELECT id, username, mobile FROM users WHERE linked_id = ? AND role = 'student' AND school_id = ? LIMIT 1");
$uStmt->execute([$studentId, SCHOOL_ID]);
$uRow = $uStmt->fetch(PDO::FETCH_ASSOC);

if (!$uRow) {
    apiError('या विद्यार्थ्याचे लॉगिन खाते सापडले नाही.', 404);
}

// 4. Reset password to default '123456' or customPass
if (empty($customPass)) {
    $customPass = '123456';
}
$newHash = password_hash($customPass, PASSWORD_DEFAULT);

$up = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$up->execute([$newHash, $uRow['id']]);

$stName = $student['name_mr'] ?: $student['name'];

apiSuccess([
    'student_id'   => $student['id'],
    'name'         => $stName,
    'username'     => $uRow['username'] ?: $student['student_pen'],
    'std'          => $student['std'],
    'roll_no'      => $student['roll_no'],
    'new_password' => $customPass
], "विद्यार्थी '{$stName}' चा पासवर्ड यशस्वीरित्या '{$customPass}' वर रीसेट करण्यात आला आहे.");
