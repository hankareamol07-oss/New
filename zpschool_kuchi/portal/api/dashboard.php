<?php
/**
 * GET /api/dashboard.php
 * Returns school dashboard stats for Android app.
 * Requires: Authorization: Bearer <token>
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$schoolId = getAuthSchoolId();
$db       = getDB();

// School info
$schoolData = [
    'id' => $schoolId,
    'name' => 'जि. प. शाळा कुची',
    'name_mr' => 'जि. प. शाळा नं. २ कुची',
    'udise_code' => '27350302306',
    'plan_name' => 'कायमस्वरूपी सक्रिय',
    'plan_id' => 1,
];

try {
    $stmt = $db->prepare('SELECT id, name, name_mr, udise_code FROM schools WHERE id = ? LIMIT 1');
    $stmt->execute([$schoolId]);
    $sRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sRow) {
        $schoolData = array_merge($schoolData, $sRow);
    }
} catch (Throwable $e) {}

// Get user / teacher info if token has user
$currentUser = null;
if (function_exists('requireAuth')) {
    try {
        $currentUser = requireAuth();
    } catch (Throwable $e) {}
}

$tdb = getTenantDB($schoolId);

$studentCount = 0;
$teacherCount = 0;
$classesCount = 0;
$lessonPlansCount = 0;
$hpcCount = 0;
$hpcCompleted = 0;
$cceCount = 0;
$teacherClass = null;

if ($tdb) {
    try {
        $studentCount = (int)$tdb->query('SELECT COUNT(*) FROM students')->fetchColumn();
    } catch (Throwable $e) {}
    try {
        $teacherCount = (int)$tdb->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
    } catch (Throwable $e) {}
    try {
        $classesCount = (int)$tdb->query('SELECT COUNT(*) FROM classes')->fetchColumn();
    } catch (Throwable $e) {}
    try {
        $lessonPlansCount = (int)$tdb->query('SELECT COUNT(*) FROM lesson_plans')->fetchColumn();
    } catch (Throwable $e) {}

    // If teacher, get their assigned class & student count in their class
    if ($currentUser && !empty($currentUser['linked_id'])) {
        try {
            $tStmt = $tdb->prepare('SELECT * FROM teachers WHERE id = ? LIMIT 1');
            $tStmt->execute([(int)$currentUser['linked_id']]);
            $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
            if ($tRow) {
                $teacherClass = $tRow;
                // find student count in assigned class
                $cStmt = $tdb->prepare('SELECT COUNT(*) FROM students WHERE class_id = ? OR std = ?');
                $cStmt->execute([(int)($tRow['class_id'] ?? 0), (int)($tRow['assigned_class'] ?? 0)]);
                $teacherClass['class_students_count'] = (int)$cStmt->fetchColumn();
            }
        } catch (Throwable $e) {}
    }

    try {
        $hpcSql = "SELECT COUNT(*) AS c, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS d FROM hpc_cards WHERE school_id = ?";
        $hpcArgs = [$schoolId];
        $hpc = $db->prepare($hpcSql);
        $hpc->execute($hpcArgs);
        $hpcData = $hpc->fetch(PDO::FETCH_ASSOC);
        $hpcCount = (int)($hpcData['c'] ?? 0);
        $hpcCompleted = (int)($hpcData['d'] ?? 0);
    } catch (Throwable $e) {}

    try {
        $cce = $tdb->query('SELECT COUNT(DISTINCT student_id) FROM cce_evaluations');
        if ($cce) $cceCount = (int)$cce->fetchColumn();
    } catch (Throwable $e) {}
}

// Fallback to MySQL if SQLite didn't return students
if ($studentCount === 0) {
    try {
        $studentCount = (int)$db->query("SELECT COUNT(*) FROM students WHERE school_id = $schoolId")->fetchColumn();
    } catch (Throwable $e) {}
}

$today = date('Y-m-d');
$todayMarathi = getMarathiDate($today);
$academicYear = '2026-27';

apiSuccess([
    'school_name'         => $schoolData['name'],
    'school_name_mr'      => $schoolData['name_mr'] ?? '',
    'udise_code'          => $schoolData['udise_code'] ?? '27350302306',
    'academic_year'       => $academicYear,
    'student_count'       => $studentCount,
    'hpc_count'           => $hpcCount,
    'hpc_completed'       => $hpcCompleted,
    'teacher_count'       => $teacherCount,
    'classes_count'       => $classesCount,
    'lesson_plans_count'  => $lessonPlansCount,
    'cce_evaluated_count' => $cceCount,
    'is_subscribed'       => true,
    'plan_name'           => 'कायमस्वरूपी सक्रिय',
    'plan_id'             => 1,
    'today_marathi'       => $todayMarathi,
    'panchang_var'        => getPanchangVar($today),
    'suvichar'            => getTodaySuvichar($today),
    'suvichar_source'     => getTodaySuvicharSource($today),
    'teacher_class'       => $teacherClass,
]);

// ─── Helper functions ─────────────────────────────────────────────────────────

function getMarathiDate($date) {
    $marathiMonths = [
        1 => 'जानेवारी', 2 => 'फेब्रुवारी', 3 => 'मार्च',
        4 => 'एप्रिल', 5 => 'मे', 6 => 'जून',
        7 => 'जुलै', 8 => 'ऑगस्ट', 9 => 'सप्टेंबर',
        10 => 'ऑक्टोबर', 11 => 'नोव्हेंबर', 12 => 'डिसेंबर'
    ];
    $t   = strtotime($date);
    $day = date('j', $t);
    $mon = (int) date('n', $t);
    $yr  = date('Y', $t);
    return "$day {$marathiMonths[$mon]} $yr";
}

function getPanchangVar($date) {
    $days = ['रविवार','सोमवार','मंगळवार','बुधवार','गुरुवार','शुक्रवार','शनिवार'];
    return $days[(int) date('w', strtotime($date))];
}

function getTodaySuvichar($date) {
    $suvichars = [
        'शिक्षण हे सर्वात शक्तिशाली शस्त्र आहे.',
        'जे मेहनत करतात ते नेहमी यशस्वी होतात.',
        'ज्ञान हीच संपत्ती आहे.',
        'विद्यार्थी हे देशाचे भविष्य आहेत.',
        'प्रत्येक दिवस नवीन संधी घेऊन येतो.',
    ];
    $idx = (int) date('j', strtotime($date)) % count($suvichars);
    return $suvichars[$idx];
}

function getTodaySuvicharSource($date) {
    $sources = ['अब्राहम लिंकन', 'स्वामी विवेकानंद', 'डॉ. बाबासाहेब आंबेडकर', 'APJ अब्दुल कलाम', 'महात्मा गांधी'];
    $idx     = (int) date('j', strtotime($date)) % count($sources);
    return $sources[$idx];
}
