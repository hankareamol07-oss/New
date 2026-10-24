<?php
/**
 * GET & POST /api/students.php
 * Native Student Management API for Android app.
 *
 * GET:
 *   ?id=X                -> single student details
 *   ?std=Y&section=Z     -> filtered list of students
 *
 * POST:
 *   Add or update student
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$schoolId = getAuthSchoolId();
$tdb = getTenantDB($schoolId);

if (!$tdb) {
    apiSuccess(['students' => [], 'total' => 0]);
}

// Auto-hydrate students from MySQL into SQLite if empty
try {
    if ((int)$tdb->query("SELECT COUNT(*) FROM students")->fetchColumn() === 0) {
        require_once __DIR__ . '/../includes/cce_functions.php';
        if (function_exists('sync_students_to_sqlite')) {
            sync_students_to_sqlite($schoolId);
        }
    }
} catch (Throwable $ignore) {}

// ─── GET: Fetch Student(s) ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Single student details
    if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
        $studentId = (int)$_GET['id'];
        $stmt = $tdb->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            apiError('विद्यार्थी आढळला नाही', 404);
        }

        $student['id'] = (int)$student['id'];

        // Get student's recent attendance summary
        $attStmt = $tdb->prepare(
            "SELECT status, COUNT(*) as cnt
             FROM daily_attendance
             WHERE student_id = ?
             GROUP BY status"
        );
        $attStmt->execute([$studentId]);
        $attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);
        $attSummary = ['P' => 0, 'A' => 0, 'L' => 0];
        foreach ($attRows as $ar) {
            $attSummary[$ar['status']] = (int)$ar['cnt'];
        }

        apiSuccess([
            'student' => $student,
            'attendance_summary' => $attSummary
        ]);
    }

    // List students
    $std     = trim($_GET['std'] ?? '');
    $section = trim($_GET['section'] ?? '');
    $search  = trim($_GET['q'] ?? '');
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(100, (int) ($_GET['per_page'] ?? 500));
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];

    if (!empty($std)) {
        $where[]  = 'grade = ?';
        $params[] = $std;
    }
    if (!empty($section)) {
        $where[]  = 'section = ?';
        $params[] = $section;
    }
    if (!empty($search)) {
        $where[] = '(name LIKE ? OR name_mr LIKE ? OR roll_no LIKE ? OR gr_no LIKE ?)';
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereClause = implode(' AND ', $where);

    try {
        $countStmt = $tdb->prepare("SELECT COUNT(*) FROM students WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $tdb->prepare(
            "SELECT id, name, name_mr, roll_no, gr_no, grade, section, gender, date_of_birth,
                    photo, father_name, mother_name, phone, apaar_id, student_pen, aadhar_no, caste, blood_group
             FROM students
             WHERE $whereClause
             ORDER BY grade ASC, CAST(roll_no AS INTEGER) ASC, name ASC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as &$s) {
            $s['id'] = (int) $s['id'];
        }

        apiSuccess([
            'students' => $students,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    } catch (Exception $e) {
        apiError('विद्यार्थी यादी मिळवताना त्रुटी: ' . $e->getMessage(), 500);
    }
}

// ─── POST: Add or Update Student ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();

    $name        = trim($body['name'] ?? '');
    $nameMr      = trim($body['name_mr'] ?? '');
    $grNo        = trim($body['gr_no'] ?? '');
    $rollNo      = trim($body['roll_no'] ?? '1');
    $grade       = trim($body['grade'] ?? '1');
    $section     = trim($body['section'] ?? 'A');
    $gender      = trim($body['gender'] ?? 'M');
    $dob         = trim($body['date_of_birth'] ?? '');
    $fatherName  = trim($body['father_name'] ?? '');
    $motherName  = trim($body['mother_name'] ?? '');
    $phone       = trim($body['phone'] ?? '');
    $aadharNo    = trim($body['aadhar_no'] ?? '');
    $apaarId     = trim($body['apaar_id'] ?? '');
    $caste       = trim($body['caste'] ?? '');
    $bloodGroup  = trim($body['blood_group'] ?? '');

    if (empty($name) && !empty($nameMr)) {
        $name = $nameMr;
    }
    if (empty($name)) {
        apiError('विद्यार्थ्याचे नाव आवश्यक आहे');
    }

    try {
        $stmt = $tdb->prepare(
            "INSERT INTO students (
                school_id, name, name_mr, gr_no, roll_no, grade, section,
                gender, date_of_birth, father_name, mother_name, phone,
                aadhar_no, apaar_id, caste, blood_group, status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([
            $schoolId, $name, $nameMr, $grNo, $rollNo, $grade, $section,
            $gender, $dob, $fatherName, $motherName, $phone,
            $aadharNo, $apaarId, $caste, $bloodGroup
        ]);

        $newId = (int)$tdb->lastInsertId();

        if (function_exists('syncTenantRecordToMySQL')) {
            syncTenantRecordToMySQL('students', [
                'id' => $newId,
                'school_id' => $schoolId,
                'name' => $name,
                'name_mr' => $nameMr,
                'gr_no' => $grNo,
                'roll_no' => $rollNo,
                'grade' => $grade,
                'section' => $section,
                'gender' => $gender,
                'date_of_birth' => $dob ?: null,
                'father_name' => $fatherName,
                'mother_name' => $motherName,
                'phone' => $phone,
                'aadhar_no' => $aadharNo,
                'apaar_id' => $apaarId,
                'caste' => $caste,
                'blood_group' => $bloodGroup
            ], ['id']);
        }

        apiSuccess([
            'id' => $newId,
            'name' => $name,
            'roll_no' => $rollNo,
            'grade' => $grade
        ], 'विद्यार्थी यशस्वीरित्या नोंदवला गेला!');
    } catch (Exception $e) {
        apiError('विद्यार्थी नोंदवताना त्रुटी: ' . $e->getMessage(), 500);
    }
}
