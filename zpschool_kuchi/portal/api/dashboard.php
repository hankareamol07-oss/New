<?php
/**
 * GET /api/dashboard.php
 * Returns school dashboard stats for Android app.
 * Requires: Authorization: Bearer <token>
 */

require_once __DIR__ . '/helpers.php';

$schoolId = getAuthSchoolId();
$db       = getDB();

// School info
$school = $db->prepare(
    'SELECT s.*, p.name AS plan_name, p.price AS plan_price
     FROM schools s LEFT JOIN plans p ON s.plan_id = p.id
     WHERE s.id = ? LIMIT 1'
);
$school->execute([$schoolId]);
$schoolData = $school->fetch(PDO::FETCH_ASSOC);

if (!$schoolData) {
    apiError('School not found', 404);
}

// Get tenant DB for this school
require_once __DIR__ . '/../includes/tenant_db.php';
$tdb = getTenantDB($schoolId);

// Stats
$studentCount = 0;
$hpcCount     = 0;
$hpcCompleted = 0;
$teacherCount = 0;
$classesCount = 0;
$cceCount     = 0;

if ($tdb) {
    $studentCount = (int) $tdb->query('SELECT COUNT(*) FROM students')->fetchColumn();
    $teacherCount = (int) $tdb->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
    $classesCount = (int) $tdb->query('SELECT COUNT(*) FROM classes')->fetchColumn();

    // HPC
    try {
        // HPC cards are stored in the main DB (same as hpc_native.php / portal HPC module).
        $hpcSql = 'SELECT COUNT(*) AS c, SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) AS d FROM hpc_cards WHERE school_id = ?';
        $hpcArgs = [$schoolId];
        if (function_exists('academic_year')) {
            $hpcSql .= ' AND academic_year = ?';
            $hpcArgs[] = academic_year();
        }
        $hst = $db->prepare($hpcSql);
        $hst->execute($hpcArgs);
        $hr = $hst->fetch(PDO::FETCH_ASSOC) ?: [];
        $hpcCount     = (int) ($hr['c'] ?? 0);
        $hpcCompleted = (int) ($hr['d'] ?? 0);
    } catch (Exception $e) {
        $hpcCount = $hpcCompleted = 0;
    }

    // CCE
    try {
        $cceCount = (int) $tdb->query('SELECT COUNT(DISTINCT student_id) FROM cce_marks')->fetchColumn();
    } catch (Exception $e) {
        $cceCount = 0;
    }
}

// Subscription check
$isSubscribed = ($schoolData['plan_price'] > 0)
    && !empty($schoolData['subscription_end'])
    && (strtotime($schoolData['subscription_end']) > time());

// Today's suvichar (simple static for API, load from paripath bank)
$today         = date('Y-m-d');
$todayMarathi  = getMarathiDate($today);
$academicYear  = academicYear();

apiSuccess([
    'school_name'        => $schoolData['name'],
    'school_name_mr'     => $schoolData['name_mr'] ?? '',
    'udise_code'         => $schoolData['udise_code'] ?? '',
    'academic_year'      => $academicYear,
    'student_count'      => $studentCount,
    'hpc_count'          => $hpcCount,
    'hpc_completed'      => $hpcCompleted,
    'teacher_count'      => $teacherCount,
    'classes_count'      => $classesCount,
    'cce_evaluated_count'=> $cceCount,
    'is_subscribed'      => $isSubscribed,
    'plan_name'          => $schoolData['plan_name'] ?? 'मोफत',
    'plan_id'            => (int) $schoolData['plan_id'],
    'today_marathi'      => $todayMarathi,
    'panchang_var'       => getPanchangVar($today),
    'suvichar'           => getTodaySuvichar($today),
    'suvichar_source'    => getTodaySuvicharSource($today),
]);

// ─── Helper functions ─────────────────────────────────────────────────────────

function academicYear() {
    $month = (int) date('n');
    $year  = (int) date('Y');
    if ($month >= 6) {
        return $year . '-' . ($year + 1);
    }
    return ($year - 1) . '-' . $year;
}

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
