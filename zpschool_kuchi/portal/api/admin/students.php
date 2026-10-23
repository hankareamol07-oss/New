<?php
/**
 * GET / POST /api/admin/students.php
 * Admin: Student Management
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$in = getJsonInput();

if ($method === 'GET') {
    $std = isset($in['std']) && $in['std'] !== '' ? (int)$in['std'] : null;
    $q = trim((string)($in['q'] ?? ''));

    $where = ["s.school_id = ?", "s.is_active = 1"];
    $params = [SCHOOL_ID];

    if ($std) {
        $where[] = "s.std = ?";
        $params[] = $std;
    }
    if (!empty($q)) {
        $where[] = "(s.name LIKE ? OR s.student_pen LIKE ? OR s.roll_no = ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = $q;
    }

    $sql = "
        SELECT s.*, c.name as class_name, t.name_mr as class_teacher_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN teachers t ON s.teacher_id = t.id 
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY s.std ASC, CAST(s.roll_no AS UNSIGNED) ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiSuccess([
        'total'    => count($students),
        'students' => $students,
    ], 'विद्यार्थी यादी लोड झाली.');
}

if ($method === 'POST') {
    $name = trim((string)($in['name'] ?? ''));
    $std = (int)($in['std'] ?? 1);
    $section = trim((string)($in['section'] ?? 'A'));
    $gender = trim((string)($in['gender'] ?? 'मुलगा'));
    $pen = trim((string)($in['student_pen'] ?? ''));
    $roll_no = trim((string)($in['roll_no'] ?? '1'));
    $father = trim((string)($in['father_name'] ?? ''));
    $mother = trim((string)($in['mother_name'] ?? ''));
    $cat = trim((string)($in['caste_category'] ?? 'General'));

    if (empty($name)) {
        apiError('विद्यार्थ्याचे नाव आवश्यक आहे.', 400);
    }

    // Find class_id & teacher_id for this std
    $st = $db->prepare("SELECT id, class_teacher_id, name FROM classes WHERE std = ? AND school_id = ? LIMIT 1");
    $st->execute([$std, SCHOOL_ID]);
    $cls = $st->fetch();
    $class_id = $cls ? (int)$cls['id'] : 1;
    $teacher_id = $cls ? (int)$cls['class_teacher_id'] : null;
    $grade = $cls ? $cls['name'] : "इयत्ता {$std} ली";

    $ins = $db->prepare("
        INSERT INTO students (
            school_id, class_id, teacher_id, name, name_mr, roll_no, grade, std, 
            section, gender, student_pen, father_name, mother_name, caste_category, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $ins->execute([
        SCHOOL_ID, $class_id, $teacher_id, $name, $name, $roll_no, $grade, $std,
        $section, $gender, $pen, $father, $mother, $cat
    ]);

    $new_id = (int)$db->lastInsertId();

    // Create user login if PEN provided
    if (!empty($pen)) {
        $hash = password_hash('Student@Kuchi2026', PASSWORD_DEFAULT);
        try {
            $db->prepare("INSERT INTO users (school_id, role, name, mobile, password_hash, linked_id, is_active)
                          VALUES (?, 'student', ?, ?, ?, ?, 1)")
               ->execute([SCHOOL_ID, $name, $pen, $hash, $new_id]);
        } catch (Throwable $e) {}
    }

    apiSuccess(['id' => $new_id], 'विद्यार्थी यशस्वीरित्या जोडला गेला!');
}
