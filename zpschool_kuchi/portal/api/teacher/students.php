<?php
/**
 * GET /api/teacher/students.php
 * Returns students strictly belonging to the teacher's assigned class/division.
 * Query parameters:
 *   ?class_id=12 or ?std=6 (must belong to teacher's scope)
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$scope = getTeacherClassScope($user);
$db = getDB();

$requested_class_id = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$requested_std = isset($_GET['std']) && $_GET['std'] !== '' ? (int)$_GET['std'] : null;

// Enforce teacher scope
if (!$scope['is_admin']) {
    if (empty($scope['class_ids']) && empty($scope['stds'])) {
        apiSuccess(['students' => [], 'total' => 0], 'आपल्याला कोणताही वर्ग नेमून दिलेला नाही.');
    }

    if ($requested_class_id && !in_array($requested_class_id, $scope['class_ids'])) {
        apiError('प्रवेश नाकारला: आपण केवळ आपल्या नेमून दिलेल्या वर्गातील विद्यार्थी पाहू शकता.', 403);
    }
    if ($requested_std && !in_array($requested_std, $scope['stds'])) {
        apiError('प्रवेश नाकारला: आपण केवळ आपल्या नेमून दिलेल्या इयत्तेतील विद्यार्थी पाहू शकता.', 403);
    }
}

// Build query
$where = ["s.school_id = ?", "s.is_active = 1"];
$params = [SCHOOL_ID];

if ($requested_class_id) {
    $where[] = "s.class_id = ?";
    $params[] = $requested_class_id;
} elseif ($requested_std) {
    $where[] = "s.std = ?";
    $params[] = $requested_std;
} elseif (!$scope['is_admin']) {
    // Restrict to teacher's class IDs
    $inPlaceholders = implode(',', array_fill(0, count($scope['class_ids']), '?'));
    $where[] = "s.class_id IN ($inPlaceholders)";
    $params = array_merge($params, $scope['class_ids']);
}

$sql = "
    SELECT s.id, s.class_id, s.teacher_id, s.name, s.name_mr, s.roll_no, 
           s.grade, s.std, s.section, s.gender, s.student_pen, s.apaar_id,
           s.father_name, s.mother_name, s.caste_category, s.phone,
           c.name as class_name,
           u.username as student_username,
           (SELECT status FROM hpc_cards WHERE student_id = s.id AND school_id = ? AND academic_year = ? ORDER BY id DESC LIMIT 1) as hpc_status
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN users u ON u.linked_id = s.id AND u.role = 'student'
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.std ASC, CAST(s.roll_no AS UNSIGNED) ASC, s.name ASC
";

$execParams = array_merge([SCHOOL_ID, ACADEMIC_YEAR], $params);
$stmt = $db->prepare($sql);
$stmt->execute($execParams);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

apiSuccess([
    'total'    => count($students),
    'students' => $students,
    'scope'    => $scope['classes'],
], 'विद्यार्थी यादी लोड झाली.');
