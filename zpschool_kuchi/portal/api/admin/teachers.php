<?php
/**
 * GET / POST /api/admin/teachers.php
 * Admin: Manage Teachers & Class Assignments
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$in = getJsonInput();

if ($method === 'GET') {
    $teachers = $db->query("
        SELECT t.*, tc.class_id, tc.std, c.name as assigned_class_name,
               (SELECT COUNT(*) FROM students s WHERE s.teacher_id = t.id AND s.school_id = " . SCHOOL_ID . " AND s.is_active = 1) as student_count
        FROM teachers t
        LEFT JOIN teacher_classes tc ON t.id = tc.teacher_id AND tc.is_class_teacher = 1
        LEFT JOIN classes c ON tc.class_id = c.id
        WHERE t.school_id = " . SCHOOL_ID . "
        ORDER BY t.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $classes = $db->query("SELECT id, std, name, section FROM classes WHERE school_id = " . SCHOOL_ID . " ORDER BY std")->fetchAll(PDO::FETCH_ASSOC);

    apiSuccess([
        'teachers' => $teachers,
        'classes'  => $classes,
    ], 'शिक्षक यादी लोड झाली.');
}

if ($method === 'POST') {
    $action = $in['action'] ?? 'reassign';

    if ($action === 'reassign') {
        $teacher_id = (int)($in['teacher_id'] ?? 0);
        $class_id = (int)($in['class_id'] ?? 0);

        if ($teacher_id <= 0 || $class_id <= 0) {
            apiError('शिक्षक आणि वर्ग निवडणे आवश्यक आहे.', 400);
        }

        // Get std of this class
        $st = $db->prepare("SELECT std, name FROM classes WHERE id = ? AND school_id = ?");
        $st->execute([$class_id, SCHOOL_ID]);
        $cls = $st->fetch();
        if (!$cls) apiError('अवैध वर्ग.', 404);

        $std = (int)$cls['std'];

        $db->beginTransaction();
        try {
            // Update classes table
            $db->prepare("UPDATE classes SET class_teacher_id = ? WHERE id = ? AND school_id = ?")
               ->execute([$teacher_id, $class_id, SCHOOL_ID]);

            // Update teachers class_assigned string
            $db->prepare("UPDATE teachers SET class_assigned = ? WHERE id = ? AND school_id = ?")
               ->execute([$cls['name'], $teacher_id, SCHOOL_ID]);

            // Upsert teacher_classes mapping
            $db->prepare("
                INSERT INTO teacher_classes (school_id, teacher_id, class_id, std, division, academic_year, is_class_teacher)
                VALUES (?, ?, ?, ?, 'A', ?, 1)
                ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), std = VALUES(std), is_class_teacher = 1
            ")->execute([SCHOOL_ID, $teacher_id, $class_id, $std, ACADEMIC_YEAR]);

            // Update students of this class to have this teacher_id
            $db->prepare("UPDATE students SET teacher_id = ? WHERE class_id = ? AND school_id = ?")
               ->execute([$teacher_id, $class_id, SCHOOL_ID]);

            $db->commit();
            apiSuccess(['teacher_id' => $teacher_id, 'class_id' => $class_id], 'वर्ग शिक्षक नेमणूक यशस्वीरित्या बदलली!');
        } catch (Throwable $e) {
            $db->rollBack();
            apiError('बदल करताना त्रुटी: ' . $e->getMessage(), 500);
        }
    }
}
