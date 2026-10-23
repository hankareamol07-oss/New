<?php
/**
 * GET / POST /api/teacher/attendance.php
 * Daily Attendance Entry for Class Teacher
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$scope = getTeacherClassScope($user);
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

$in = getJsonInput();
$date = trim((string)($in['date'] ?? date('Y-m-d')));
$class_id = isset($in['class_id']) && $in['class_id'] !== '' ? (int)$in['class_id'] : ($scope['class_ids'][0] ?? 0);

if (!$scope['is_admin'] && !in_array($class_id, $scope['class_ids'])) {
    apiError('प्रवेश नाकारला: आपण केवळ आपल्या नेमून दिलेल्या वर्गाची हजेरी नोंदवू शकता.', 403);
}

if ($method === 'GET') {
    // Fetch class students and their attendance status for this date
    $sql = "
        SELECT s.id as student_id, s.name, s.name_mr, s.roll_no, s.grade, s.section,
               COALESCE(da.status, 'P') as status
        FROM students s
        LEFT JOIN daily_attendance da ON s.id = da.student_id AND da.date = ? AND da.school_id = ?
        WHERE s.school_id = ? AND s.class_id = ? AND s.is_active = 1
        ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$date, SCHOOL_ID, SCHOOL_ID, $class_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiSuccess([
        'date'     => $date,
        'class_id' => $class_id,
        'total'    => count($records),
        'records'  => $records,
    ], 'हजेरी यादी लोड झाली.');
}

if ($method === 'POST') {
    // Array of { student_id: 123, status: "P" | "A" | "L" }
    $attendance_list = $in['attendance'] ?? [];
    if (!is_array($attendance_list) || empty($attendance_list)) {
        apiError('हजेरी डेटा पाठवणे आवश्यक आहे.', 400);
    }

    $db->beginTransaction();
    try {
        $st_ins = $db->prepare("
            INSERT INTO daily_attendance (school_id, student_id, date, status)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");

        $present_count = 0;
        $total_count = 0;

        foreach ($attendance_list as $item) {
            $sid = (int)($item['student_id'] ?? 0);
            $status = strtoupper(trim((string)($item['status'] ?? 'P')));
            if (!in_array($status, ['P', 'A', 'L'])) $status = 'P';

            // Verify student belongs to this teacher
            assertTeacherStudentAccess($user, $sid);

            $st_ins->execute([SCHOOL_ID, $sid, $date, $status]);
            if ($status === 'P') $present_count++;
            $total_count++;
        }

        $db->commit();

        apiSuccess([
            'date'          => $date,
            'total_marked'  => $total_count,
            'present_count' => $present_count,
            'absent_count'  => $total_count - $present_count,
        ], 'हजेरी यशस्वीरित्या सेव्ह झाली!');

    } catch (Throwable $e) {
        $db->rollBack();
        apiError('हजेरी नोंदवताना त्रुटी: ' . $e->getMessage(), 500);
    }
}
