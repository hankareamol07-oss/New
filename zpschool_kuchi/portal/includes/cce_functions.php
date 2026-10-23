<?php
/**
 * CCE module helpers (सातत्यपूर्ण सर्वंकष मूल्यमापन)
 * Shared by every page under /cce.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/tenant_db.php';

/** Marathi digits -> ASCII digits */
function mr_to_ascii_digits($s)
{
    return strtr($s, [
        '०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4',
        '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9',
    ]);
}

/** ASCII digits -> Marathi digits (for printed cards) */
function to_mr_digits($s)
{
    return strtr((string)$s, [
        '0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४',
        '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९',
    ]);
}

/** Standard number (1..8) from the students.grade text, e.g. "इयत्ता ३" -> 3 */
function std_from_grade($grade)
{
    $g = mr_to_ascii_digits((string)$grade);
    if (preg_match('/(\d+)/', $g, $m)) {
        $n = (int)$m[1];
        if ($n >= 1 && $n <= 8) return $n;
    }
    $words = [
        'पहिली' => 1, 'दुसरी' => 2, 'तिसरी' => 3, 'चौथी' => 4,
        'पाचवी' => 5, 'सहावी' => 6, 'सातवी' => 7, 'आठवी' => 8,
    ];
    foreach ($words as $w => $n) {
        if (mb_strpos((string)$grade, $w) !== false) return $n;
    }
    return 0;
}

/** Marathi label for a standard number */
function std_label($std)
{
    $names = [1 => 'पहिली', 2 => 'दुसरी', 3 => 'तिसरी', 4 => 'चौथी',
              5 => 'पाचवी', 6 => 'सहावी', 7 => 'सातवी', 8 => 'आठवी'];
    return $names[(int)$std] ?? ('इयत्ता ' . to_mr_digits($std));
}

function semester_label($sem)
{
    return ((int)$sem === 2) ? 'द्वितीय सत्र' : 'प्रथम सत्र';
}

function cce_db($school_id = null)
{
    if (function_exists('getTenantDB')) {
        try {
            return getTenantDB($school_id);
        } catch (Exception $e) {
            return getDB();
        }
    }
    return getDB();
}

/** Grade text for a percentage, read from the imported cce_grades master */
function cce_grade_for_percent($percent)
{
    static $grades = null;
    $db = getMainDB();
    if ($grades === null) {
        try {
            $grades = $db->query("SELECT gradetext, marks FROM cce_grades ORDER BY marks DESC")->fetchAll();
        } catch (PDOException $e) {
            $grades = [];
        }
    }
    foreach ($grades as $g) {
        if ($percent >= (float)$g['marks']) return $g['gradetext'];
    }
    return $grades ? end($grades)['gradetext'] : '-';
}

/** Alias for cce_grade_for_percent */
function cce_grade_from_percent($percent)
{
    return cce_grade_for_percent($percent);
}

/** आठ आकारिक व तीन संकलित तंत्रांची (techniques) क्रमवार नावे */
function cce_technique_labels()
{
    return [
        'fe' => [
            1 => 'दैनंदिन निरीक्षण', 2 => 'तोंडी काम', 3 => 'प्रात्य./प्रयोग', 4 => 'उपक्रम/कृती',
            5 => 'प्रकल्प', 6 => 'चाचणी (लेखी)', 7 => 'स्वाध्याय/वर्गकार्य', 8 => 'इतर',
        ],
        'se' => [1 => 'तोंडी', 2 => 'प्रात्यक्षिक', 3 => 'लेखी'],
    ];
}

/**
 * Recompute totals/percent/grade for one marks row payload.
 * When $subject is given, each fe1..fe8/se1..se3 component is first clamped
 * against that technique's own max (feN_max/seN_max) from भारांश निश्चिती,
 * before being summed and clamped again against the subject's group max.
 */
function cce_compute_totals(array $row, $fe_max, $se_max, ?array $subject = null)
{
    $fe = 0; $se = 0;
    for ($i = 1; $i <= 8; $i++) {
        $v = max(0, (float)($row['fe' . $i] ?? 0));
        if ($subject !== null && isset($subject['fe' . $i . '_max']) && $subject['fe' . $i . '_max'] !== null && (float)$subject['fe' . $i . '_max'] > 0) {
            $v = min($v, (float)$subject['fe' . $i . '_max']);
        }
        $fe += $v;
    }
    for ($i = 1; $i <= 3; $i++) {
        $v = max(0, (float)($row['se' . $i] ?? 0));
        if ($subject !== null && isset($subject['se' . $i . '_max']) && $subject['se' . $i . '_max'] !== null && (float)$subject['se' . $i . '_max'] > 0) {
            $v = min($v, (float)$subject['se' . $i . '_max']);
        }
        $se += $v;
    }
    // Totals clamped against subject group maxima (with safe fallback if max is missing/0)
    $fe_limit = (float)$fe_max > 0 ? (float)$fe_max : ($subject && !empty($subject['fe_max']) ? (float)$subject['fe_max'] : 0);
    $se_limit = (float)$se_max > 0 ? (float)$se_max : ($subject && !empty($subject['se_max']) ? (float)$subject['se_max'] : 0);
    if ($fe_limit > 0) $fe = min($fe, $fe_limit);
    if ($se_limit > 0) $se = min($se, $se_limit);
    $total = $fe + $se;
    $max = ($fe_limit + $se_limit) > 0 ? ($fe_limit + $se_limit) : 100;
    $percent = min(100, round(($total / $max) * 100, 2));
    return [
        'fe_total' => round($fe, 1),
        'se_total' => round($se, 1),
        'grand_total' => round($total, 1),
        'percent' => $percent,
        'grade' => cce_grade_for_percent($percent),
    ];
}

/**
 * Loads student marks matrix with seamless dual-database fallback and bidirectional synchronization.
 * Returns: array [ student_id => [ subject_id => marks_row ] ]
 */
function cce_student_marks_matrix($school_id, $year, $semester, array $student_ids): array
{
    if (empty($student_ids)) return [];
    $student_ids = array_map('intval', array_unique($student_ids));
    $in = implode(',', array_fill(0, count($student_ids), '?'));
    $params = array_merge([$school_id, $year, $semester], $student_ids);

    $marks = [];
    $sqlite_rows = [];

    // 1. Fetch from MySQL
    try {
        $mysql = getMainDB();
        $stmtM = $mysql->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in)");
        $stmtM->execute($params);
        $mysql_rows = $stmtM->fetchAll(PDO::FETCH_ASSOC);
        foreach ($mysql_rows as $r) {
            $sid = (int)$r['student_id'];
            $subid = (int)$r['subject_id'];
            $marks[$sid][$subid] = $r;
        }
    } catch (Throwable $e) {
        error_log("cce_student_marks_matrix MySQL error: " . $e->getMessage());
    }

    // 2. Fetch from SQLite Tenant DB
    try {
        $sqlite = cce_db($school_id);
        $stmtS = $sqlite->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in)");
        $stmtS->execute($params);
        $sqlite_rows = $stmtS->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sqlite_rows as $r) {
            $sid = (int)$r['student_id'];
            $subid = (int)$r['subject_id'];
            if (!isset($marks[$sid][$subid])) {
                $marks[$sid][$subid] = $r;
            } else {
                $cur = $marks[$sid][$subid];
                $cur_has_fe = isset($cur['fe_total']) && (float)$cur['fe_total'] > 0;
                $sqlite_has_fe = isset($r['fe_total']) && (float)$r['fe_total'] > 0;
                if (!$cur_has_fe && $sqlite_has_fe) {
                    $marks[$sid][$subid] = $r;
                }
            }
        }
    } catch (Throwable $e) {
        error_log("cce_student_marks_matrix SQLite error: " . $e->getMessage());
    }

    // 3. Background sync from SQLite to MySQL for any missing rows
    if (!empty($sqlite_rows)) {
        try {
            $mysql = getMainDB();
            $mysql_sql = "INSERT INTO cce_marks (school_id, student_id, subject_id, academic_year, semester,
                        fe1,fe2,fe3,fe4,fe5,fe6,fe7,fe8,se1,se2,se3,fe_total,se_total,grand_total,percent,grade)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        fe1=VALUES(fe1), fe2=VALUES(fe2), fe3=VALUES(fe3), fe4=VALUES(fe4),
                        fe5=VALUES(fe5), fe6=VALUES(fe6), fe7=VALUES(fe7), fe8=VALUES(fe8),
                        se1=VALUES(se1), se2=VALUES(se2), se3=VALUES(se3),
                        fe_total=VALUES(fe_total), se_total=VALUES(se_total),
                        grand_total=VALUES(grand_total), percent=VALUES(percent), grade=VALUES(grade)";
            $insM = $mysql->prepare($mysql_sql);
            foreach ($sqlite_rows as $sr) {
                try {
                    $insM->execute([
                        $sr['school_id'], $sr['student_id'], $sr['subject_id'], $sr['academic_year'], $sr['semester'],
                        $sr['fe1'], $sr['fe2'], $sr['fe3'], $sr['fe4'], $sr['fe5'], $sr['fe6'], $sr['fe7'], $sr['fe8'],
                        $sr['se1'], $sr['se2'], $sr['se3'],
                        $sr['fe_total'], $sr['se_total'], $sr['grand_total'], $sr['percent'], $sr['grade']
                    ]);
                } catch (Throwable $e2) {}
            }
        } catch (Throwable $e) {}
    }

    return $marks;
}

/** Subjects configured by this school for a standard (active only) */
function cce_subjects($school_id, $std)
{
    require_once __DIR__ . '/tenant_db.php';
    $rows = [];

    // 1. Check MySQL first
    try {
        $mysql = getMainDB();
        $stmt = $mysql->prepare("SELECT * FROM cce_subjects WHERE school_id = ? AND std = ? AND is_active = 1 ORDER BY sort_order, id");
        $stmt->execute([$school_id, $std]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // If MySQL has subjects, sync them into the tenant SQLite DB
    if (!empty($rows)) {
        if (function_exists('sync_subjects_to_sqlite')) {
            sync_subjects_to_sqlite($school_id, $rows);
        }
        return $rows;
    }

    // 2. If MySQL returned empty, check SQLite tenant DB
    try {
        $sqlite = cce_db($school_id);
        $stmt = $sqlite->prepare("SELECT * FROM cce_subjects WHERE school_id = ? AND std = ? AND is_active = 1 ORDER BY sort_order, id");
        $stmt->execute([$school_id, $std]);
        $rows = $sqlite->fetchAll(PDO::FETCH_ASSOC);

        // If SQLite has subjects, sync them back into MySQL
        if (!empty($rows) && function_exists('sync_subjects_to_mysql')) {
            sync_subjects_to_mysql($school_id, $rows);
        }
    } catch (Throwable $e) {}

    return $rows;
}

/** All subjects for management/editing (including inactive) with bidirectional sync */
function cce_all_subjects($school_id, $std)
{
    require_once __DIR__ . '/tenant_db.php';
    $rows = [];

    // 1. Check MySQL first
    try {
        $mysql = getMainDB();
        $stmt = $mysql->prepare("SELECT * FROM cce_subjects WHERE school_id = ? AND std = ? ORDER BY sort_order, id");
        $stmt->execute([$school_id, $std]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // If MySQL has subjects, sync them into the tenant SQLite DB
    if (!empty($rows)) {
        if (function_exists('sync_subjects_to_sqlite')) {
            sync_subjects_to_sqlite($school_id, $rows);
        }
        return $rows;
    }

    // 2. If MySQL returned empty, check SQLite tenant DB
    try {
        $sqlite = cce_db($school_id);
        $stmt = $sqlite->prepare("SELECT * FROM cce_subjects WHERE school_id = ? AND std = ? ORDER BY sort_order, id");
        $stmt->execute([$school_id, $std]);
        $rows = $sqlite->fetchAll(PDO::FETCH_ASSOC);

        // If SQLite has subjects, sync them back into MySQL
        if (!empty($rows) && function_exists('sync_subjects_to_mysql')) {
            sync_subjects_to_mysql($school_id, $rows);
        }
    } catch (Throwable $e) {}

    return $rows;
}

/**
 * Create the default subject list for a standard from the imported
 * cce_subjects_master table (tblCode of the CCE software).
 * Seeds into both MySQL and SQLite tenant databases synchronously.
 * Returns the number of subjects created.
 */
function cce_seed_subjects($school_id, $std)
{
    require_once __DIR__ . '/tenant_db.php';
    $mysql = getMainDB();
    $sqlite = cce_db($school_id);

    $cntM = 0;
    try {
        $stmtM = $mysql->prepare("SELECT COUNT(*) FROM cce_subjects WHERE school_id = ? AND std = ?");
        $stmtM->execute([$school_id, $std]);
        $cntM = (int)$stmtM->fetchColumn();
    } catch (Throwable $e) {}

    $cntS = 0;
    try {
        $stmtS = $sqlite->prepare("SELECT COUNT(*) FROM cce_subjects WHERE school_id = ? AND std = ?");
        $stmtS->execute([$school_id, $std]);
        $cntS = (int)$stmtS->fetchColumn();
    } catch (Throwable $e) {}

    if ($cntM > 0 || $cntS > 0) {
        cce_all_subjects($school_id, $std);
        return 0;
    }

    $col = 'std' . $std; // Std1, std2 ... normalised to std1..std8
    try {
        $rows = $mysql->query("SELECT subjectid, subjectcode, subject, shortsubjectname, `$col` AS applicable
                            FROM cce_subjects_master ORDER BY subjectid")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return 0;
    }

    $insM = $mysql->prepare("INSERT INTO cce_subjects (school_id, std, subject_code, name_mr, short_name, fe_max, se_max, sort_order, is_active)
                             VALUES (?,?,?,?,?,?,?,?,1)");
    $insS = $sqlite->prepare("INSERT OR REPLACE INTO cce_subjects (id, school_id, std, subject_code, name_mr, short_name, fe_max, se_max, sort_order, is_active)
                             VALUES (?,?,?,?,?,?,?,?,?,1)");

    $n = 0;
    foreach ($rows as $r) {
        $applicable = trim((string)($r['applicable'] ?? ''));
        if ($applicable === '' || $applicable === '0') continue;
        $name = trim((string)$r['subject']);
        if ($name === '') continue;

        $fe_max = ($std <= 2 ? 25 : 40);
        $se_max = ($std <= 2 ? 25 : 60);
        $sort = (int)$r['subjectid'];
        $code = trim((string)($r['subjectcode'] ?? ''));
        $sname = trim((string)($r['shortsubjectname'] ?? '')) ?: $name;

        $new_id = null;
        try {
            $insM->execute([$school_id, $std, $code, $name, $sname, $fe_max, $se_max, $sort]);
            $new_id = (int)$mysql->lastInsertId();
        } catch (Throwable $e) {}

        try {
            $insS->execute([$new_id, $school_id, $std, $code, $name, $sname, $fe_max, $se_max, $sort]);
        } catch (Throwable $e) {}

        $n++;
    }
    return $n;
}

/**
 * Configured standard range for the school (e.g. 1 to 4, 1 to 5, 1 to 7, 1 to 8)
 * Governs standard display across Class Management, CCE, HPC, and Tachan.
 */
function get_school_std_range($school_id = null): array
{
    // Strict Teacher Scope: restrict to teacher's assigned class standards
    if (function_exists('get_current_teacher_scope')) {
        $tScope = get_current_teacher_scope();
        if ($tScope && !empty($tScope['stds'])) {
            $range = [];
            foreach ($tScope['stds'] as $s) {
                $range[$s] = std_label($s);
            }
            return $range;
        }
    }

    if (!$school_id && !empty($_SESSION['school_id'])) {
        $school_id = (int)$_SESSION['school_id'];
    }
    $lower = 1;
    $upper = 8;
    if ($school_id > 0) {
        $db = getMainDB();
        try {
            $stmt = $db->prepare("SELECT lower_class, upper_class FROM schools WHERE id = ?");
            $stmt->execute([$school_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $lower = (int)($row['lower_class'] ?: 1);
                $upper = (int)($row['upper_class'] ?: 8);
                if ($lower < 1) $lower = 1;
                if ($upper < $lower) $upper = 8;
                if ($upper > 12) $upper = 8;
            }
        } catch (Exception $e) {}
    }
    $range = [];
    for ($i = $lower; $i <= $upper; $i++) {
        $range[$i] = std_label($i);
    }
    return $range;
}

/** Distinct standards that this school actually has students in, bounded by configured range */
function cce_school_standards($school_id)
{
    // Strict Teacher Scope Check
    if (function_exists('get_current_teacher_scope')) {
        $tScope = get_current_teacher_scope();
        if ($tScope && !empty($tScope['stds'])) {
            $out = [];
            foreach ($tScope['stds'] as $s) {
                $out[$s] = std_label($s);
            }
            return $out;
        }
    }

    $allowed = get_school_std_range($school_id);
    $mysql = getMainDB();
    $stmt = $mysql->prepare("SELECT DISTINCT grade, std FROM students WHERE school_id = ? AND is_active = 1");
    $stmt->execute([$school_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        try {
            $sqlite = cce_db($school_id);
            $stmt = $sqlite->prepare("SELECT DISTINCT grade FROM students WHERE school_id = ? AND is_active = 1");
            $stmt->execute([$school_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
    }

    $out = [];
    foreach ($rows as $r) {
        $n = (isset($r['std']) && (int)$r['std'] > 0) ? (int)$r['std'] : std_from_grade($r['grade']);
        if ($n && isset($allowed[$n])) {
            $out[$n] = std_label($n);
        }
    }
    if (empty($out)) {
        return $allowed;
    }
    ksort($out);
    return $out;
}

/** Students of a standard (optionally a division) */
function cce_students($school_id, $std, $division = '')
{
    // Strict Teacher Scope: force teacher's assigned class only
    if (function_exists('get_current_teacher_scope')) {
        $tScope = get_current_teacher_scope();
        if ($tScope && !empty($tScope['stds'])) {
            if (!in_array((int)$std, $tScope['stds'], true)) {
                $std = $tScope['std'];
            }
        }
    }

    $mysql = getMainDB();
    $stmt = $mysql->prepare("SELECT * FROM students WHERE school_id = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY CAST(roll_no AS UNSIGNED), name_mr");
    $stmt->execute([$school_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        try {
            $sqlite = cce_db($school_id);
            $stmt = $sqlite->prepare("SELECT * FROM students WHERE school_id = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY CAST(roll_no AS UNSIGNED), name_mr");
            $stmt->execute([$school_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
    } else {
        if (function_exists('sync_students_to_sqlite')) {
            sync_students_to_sqlite($school_id, $rows);
        }
    }

    $div_map = ['a' => 'अ', 'b' => 'ब', 'c' => 'क', 'd' => 'ड', 'e' => 'इ', 'f' => 'फ', 'अ' => 'अ', 'ब' => 'ब', 'क' => 'क', 'ड' => 'ड'];
    $out = [];
    foreach ($rows as $s) {
        $s_std = (isset($s['std']) && (int)$s['std'] > 0) ? (int)$s['std'] : std_from_grade($s['grade'] ?? '');
        if ($s_std !== (int)$std) continue;
        if ($division !== '') {
            $s_sec = trim((string)($s['section'] ?? ''));
            $s_norm = $div_map[strtolower($s_sec)] ?? $s_sec;
            $d_norm = $div_map[strtolower($division)] ?? $division;
            if ($s_sec !== $division && $s_norm !== $d_norm) continue;
        }
        $out[] = $s;
    }
    return $out;
}

/** The 13 descriptive-note areas of the CCE software, in order */
function cce_note_areas()
{
    return [
        1 => 'प्रथम भाषा', 2 => 'द्वितीय भाषा', 3 => 'तृतीय भाषा (इंग्रजी)',
        4 => 'गणित', 5 => 'परिसर अभ्यास / विज्ञान', 6 => 'परिसर अभ्यास भाग २ / इतिहास',
        7 => 'कला', 8 => 'कार्यानुभव', 9 => 'शारीरिक शिक्षण',
        10 => 'विशेष प्रगती', 11 => 'आवड / छंद', 12 => 'सुधारणा आवश्यक',
        13 => 'व्यक्तिमत्त्व गुणविशेष',
    ];
}

/** Extracts the 1..9 slot number from subject code (e.g., 's1' -> 1, 's3' -> 3) */
function cce_subject_slot($subject_or_code): int
{
    $code = is_array($subject_or_code) ? ($subject_or_code['subject_code'] ?? '') : (string)$subject_or_code;
    if (preg_match('/(?:s|sub)(\d+)/i', $code, $m)) {
        return (int)$m[1];
    }
    if (is_array($subject_or_code) && !empty($subject_or_code['sort_order'])) {
        return (int)$subject_or_code['sort_order'];
    }
    return 0;
}

/**
 * Return only the active subjects for this school & standard mapped to their correct slots (1..9),
 * plus the 4 holistic areas (10..13). This ensures only selected/active subjects are shown in वर्णनात्मक नोंदी.
 */
function cce_class_note_areas($school_id, $std): array
{
    $active_subjects = cce_subjects($school_id, $std);
    $areas = [];

    foreach ($active_subjects as $sub) {
        $slot = cce_subject_slot($sub);
        if ($slot >= 1 && $slot <= 9) {
            $areas[$slot] = $sub['name_mr'] ?: $sub['short_name'];
        }
    }

    // Fallback to defaults if no subjects configured
    if (empty($areas)) {
        $default_areas = cce_note_areas();
        for ($i = 1; $i <= 9; $i++) {
            if (isset($default_areas[$i])) $areas[$i] = $default_areas[$i];
        }
    }

    // Always append the 4 holistic non-subject evaluation areas
    $areas[10] = 'विशेष प्रगती';
    $areas[11] = 'आवड / छंद';
    $areas[12] = 'सुधारणा आवश्यक';
    $areas[13] = 'व्यक्तिमत्त्व गुणविशेष';

    ksort($areas);
    return $areas;
}

/** Suggestion pool for a note slot, from the imported master lists, optionally inflected for student gender */
function cce_note_suggestions($std, $semester, $slot, $student = null)
{
    $db = getMainDB();
    $col = 's' . (int)$slot;
    $out = [];
    foreach (['cce_note_list', 'cce_note_prelist', 'cce_note_dropdown'] as $tbl) {
        try {
            $stmt = $db->prepare("SELECT DISTINCT `$col` AS t FROM `$tbl`
                                  WHERE std = ? AND `$col` IS NOT NULL AND `$col` <> '' LIMIT 300");
            $stmt->execute([$std]);
            foreach ($stmt->fetchAll() as $r) {
                $text = trim((string)$r['t']);
                if ($text !== '') {
                    if ($student !== null) {
                        $text = cce_apply_gender_inflection($text, $student);
                    }
                    $out[$text] = true;
                }
            }
        } catch (PDOException $e) {
            // table not imported yet - ignore
        }
    }
    return array_keys($out);
}

/** Check if student or gender string indicates a female student */
function cce_is_female_student($student_or_gender): bool
{
    $g = is_array($student_or_gender) ? ($student_or_gender['gender'] ?? '') : (string)$student_or_gender;
    $g = mb_strtolower(trim($g));
    return in_array($g, ['मुलगी', 'f', 'female', 'girl', 'स्त्री', '2', 'girl/मुलगी'], true);
}

/**
 * Gender inflection helper for Maharashtra CCE evaluation
 * Transforms masculine remarks to feminine if student is female.
 */
function cce_apply_gender_inflection(string $text, $student_or_gender, ?int $medium = null): string
{
    if (empty($text)) return '';
    if (!cce_is_female_student($student_or_gender)) return $text;

    // Auto-detect English medium/text if not specified
    if ($medium === 3 || ($medium === null && preg_match('/\b(He|he|His|his|Him|him|boy)\b/', $text))) {
        $en = [
            '/\bHe\b/' => 'She',
            '/\bhe\b/' => 'she',
            '/\bHis\b/' => 'Her',
            '/\bhis\b/' => 'her',
            '/\bHim\b/' => 'Her',
            '/\bhim\b/' => 'her',
            '/\bboy\b/i' => 'girl',
        ];
        return preg_replace(array_keys($en), array_values($en), $text);
    }

    // Auto-detect Hindi medium/text if not specified
    if ($medium === 2 || ($medium === null && preg_match('/\b(ता है|है।|करता है|उसका)\b/u', $text))) {
        $hi = [
            '/\bता है\b/u' => 'ती है',
            '/\bता है।\b/u' => 'ती है।',
            '/\bउसका\b/u' => 'उसकी',
            '/\bछात्र\b/u' => 'छात्रा',
        ];
        $text = preg_replace(array_keys($hi), array_values($hi), $text);
        if ($medium === 2) return $text;
    }

    // Marathi (Medium 1 / default Devanagari)
    $pronouns = [
        'त्याच्या' => 'तिच्या',
        'त्याचा' => 'तिचा',
        'त्याची' => 'तिची',
        'त्याचे' => 'तिचे',
        'त्याला' => 'तिला',
        'त्याने' => 'तिने',
        'त्यास' => 'तिस',
        'तो' => 'ती',
        'हा' => 'ही',
        'हुशार विद्यार्थी' => 'हुशार विद्यार्थिनी',
        'विद्यार्थी' => 'विद्यार्थिनी',
    ];

    foreach ($pronouns as $m => $f) {
        $text = preg_replace('/(?<=[\s,।\.]|^)' . preg_quote($m, '/') . '(?=[\s,।\.]|$)/u', $f, $text);
    }

    $phrases = [
        'करू लागतो' => 'करू लागते',
        'सहभागी होतो' => 'सहभागी होते',
        'मदत करतो' => 'मदत करते',
        'लक्ष देतो' => 'लक्ष देते',
        'दिसून येतो' => 'दिसून येते',
    ];
    foreach ($phrases as $m => $f) {
        $text = str_replace($m, $f, $text);
    }

    // General Marathi verb inflection: any Marathi verb ending with 'तो' before punctuation or space
    // e.g., करतो -> करते, सांगतो -> सांगते, ओळखतो -> ओळखते, लिहितो -> लिहिते, काढतो -> काढते
    $text = preg_replace('/([\x{0900}-\x{097F}]{2,})तो([\.।!,\s]|$)/u', '$1ते$2', $text);

    return $text;
}

/** Learning outcomes (अध्ययन निष्पत्ती) for a standard, optionally a subject */
function cce_learning_outcomes($std, $subject_no = null, $medium = 'MA')
{
    $db = getMainDB();
    $sql = "SELECT * FROM cce_learning_outcomes WHERE std = ? AND medium = ?";
    $params = [$std, $medium];
    if ($subject_no !== null && $subject_no !== '') {
        $sql .= " AND sub = ?";
        $params[] = $subject_no;
    }
    $sql .= " ORDER BY sub, `no` LIMIT 500";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Official subject name for learning outcomes (अध्ययन निष्पत्ती) by standard and sub_no
 */
function cce_outcome_subject_name($std, $sub_no)
{
    $std = (int)$std;
    $sub_no = (int)$sub_no;
    $map = [
        1 => 'मराठी (प्रथम भाषा)',
        2 => 'हिंदी (द्वितीय भाषा)',
        3 => 'इंग्रजी (तृतीय भाषा)',
        4 => 'गणित',
        5 => ($std >= 6 ? 'सामान्य विज्ञान' : 'परिसर अभ्यास'),
        6 => 'सामाजिक शास्त्रे (इतिहास / भूगोल)',
        7 => 'कला',
        8 => 'कार्यानुभव',
        9 => 'शारीरिक शिक्षण',
    ];
    return $map[$sub_no] ?? ('विषय ' . to_mr_digits($sub_no));
}

/** Monthly attendance rows keyed by month number for one student */
function cce_attendance($student_id, $academic_year)
{
    $db = cce_db();
    $stmt = $db->prepare("SELECT * FROM attendance WHERE student_id = ? AND academic_year = ?");
    $stmt->execute([$student_id, $academic_year]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[(int)$r['month']] = $r;
    return $out;
}

/** School year months in Indian academic order */
function cce_months()
{
    return [
        6 => 'जून', 7 => 'जुलै', 8 => 'ऑगस्ट', 9 => 'सप्टेंबर', 10 => 'ऑक्टोबर',
        11 => 'नोव्हेंबर', 12 => 'डिसेंबर', 1 => 'जानेवारी', 2 => 'फेब्रुवारी',
        3 => 'मार्च', 4 => 'एप्रिल', 5 => 'मे',
    ];
}

/** Class teacher's Marathi name for a standard, via classes.class_teacher_id, teachers.class_assigned, students.teacher_id or active school teachers */
function cce_class_teacher_name($school_id, $std, $section = '')
{
    $db = getDB();
    $std = (int)$std;
    $std_mr = to_mr_digits($std);
    $std_lbl = std_label($std);

    try {
        // Tier 1: Check teachers table where class_assigned specifically designates this standard
        $stmt = $db->prepare("SELECT name_mr, name FROM teachers 
                              WHERE school_id = ? AND (is_active = 1 OR is_active IS NULL) 
                              AND (
                                class_assigned = ? 
                                OR class_assigned = ? 
                                OR class_assigned = ? 
                                OR class_assigned = ? 
                                OR class_assigned = ? 
                                OR class_assigned LIKE ? 
                                OR class_assigned LIKE ?
                              )
                              ORDER BY id DESC LIMIT 1");
        $stmt->execute([
            $school_id,
            (string)$std,
            $std_mr,
            $std_lbl,
            'इयत्ता ' . $std,
            'इयत्ता ' . $std_mr,
            '%इयत्ता ' . $std_mr . '%',
            '%इयत्ता ' . $std . '%'
        ]);
        $row = $stmt->fetch();
        if ($row && (!empty($row['name_mr']) || !empty($row['name']))) {
            return $row['name_mr'] ?: $row['name'];
        }

        // Tier 2: Check classes table by class_teacher_id
        $sql = "SELECT t.name_mr, t.name FROM classes c JOIN teachers t ON t.id = c.class_teacher_id 
                WHERE c.school_id = ? AND c.std = ? AND (t.is_active = 1 OR t.is_active IS NULL)";
        $params = [$school_id, $std];
        if ($section !== '' && $section !== '-') {
            $st = $db->prepare($sql . " AND c.section = ? LIMIT 1");
            $st->execute(array_merge($params, [$section]));
            $r = $st->fetch();
            if ($r && (!empty($r['name_mr']) || !empty($r['name']))) {
                return $r['name_mr'] ?: $r['name'];
            }
        }
        $st = $db->prepare($sql . " ORDER BY c.id ASC LIMIT 1");
        $st->execute($params);
        $r = $st->fetch();
        if ($r && (!empty($r['name_mr']) || !empty($r['name']))) {
            return $r['name_mr'] ?: $r['name'];
        }

        // Tier 3: Check students table if any student in this grade has teacher_id assigned
        $st_stu = $db->prepare("SELECT t.name_mr, t.name FROM students s
                                JOIN teachers t ON t.id = s.teacher_id
                                WHERE s.school_id = ? AND (s.grade = ? OR s.grade = ? OR s.grade = ? OR s.grade = ?)
                                AND (t.is_active = 1 OR t.is_active IS NULL)
                                LIMIT 1");
        $st_stu->execute([
            $school_id,
            (string)$std,
            $std_mr,
            'इयत्ता ' . $std_mr,
            'इयत्ता ' . $std
        ]);
        $r_stu = $st_stu->fetch();
        if ($r_stu && (!empty($r_stu['name_mr']) || !empty($r_stu['name']))) {
            return $r_stu['name_mr'] ?: $r_stu['name'];
        }

        return '';
    } catch (PDOException $e) {
        return '';
    }
}

/** Headmaster name for a semester (falls back to the other semester), from school_defaults */
function cce_headmaster_name($school_id, $semester = null)
{
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT sem1_headmaster_name, sem2_headmaster_name FROM school_defaults WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $row = $stmt->fetch();
        if (!$row) return '';
        if ((int)$semester === 2) return $row['sem2_headmaster_name'] ?: $row['sem1_headmaster_name'];
        return $row['sem1_headmaster_name'] ?: $row['sem2_headmaster_name'];
    } catch (PDOException $e) {
        return '';
    }
}

function cce_check_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('अवैध विनंती (CSRF).');
    }
}

function cce_csrf_field()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

/** Calculate Maharashtra State Board CCE 9-point grading scale (A1 to E2) from percentage */
function cce_calc_grade(float $pct): string
{
    if ($pct >= 91) return 'अ-१';
    if ($pct >= 81) return 'अ-२';
    if ($pct >= 71) return 'ब-१';
    if ($pct >= 61) return 'ब-२';
    if ($pct >= 51) return 'क-१';
    if ($pct >= 41) return 'क-२';
    if ($pct >= 33) return 'ड';
    if ($pct >= 21) return 'इ-१';
    return 'इ-२';
}

/** Standardize grade string to Marathi numeral format */
function cce_normalize_grade(string $g): string
{
    $g = trim($g);
    $map = [
        'A1' => 'अ-१', 'A-1' => 'अ-१', 'अ1' => 'अ-१', 'अ-1' => 'अ-१', 'अ-१' => 'अ-१',
        'A2' => 'अ-२', 'A-2' => 'अ-२', 'अ2' => 'अ-२', 'अ-2' => 'अ-२', 'अ-२' => 'अ-२',
        'B1' => 'ब-१', 'B-1' => 'ब-१', 'ब1' => 'ब-१', 'ब-1' => 'ब-१', 'ब-१' => 'ब-१',
        'B2' => 'ब-२', 'B-2' => 'ब-२', 'ब2' => 'ब-२', 'ब-2' => 'ब-२', 'ब-२' => 'ब-२',
        'C1' => 'क-१', 'C-1' => 'क-१', 'क1' => 'क-१', 'क-1' => 'क-१', 'क-१' => 'क-१',
        'C2' => 'क-२', 'C-2' => 'क-२', 'क2' => 'क-२', 'क-2' => 'क-२', 'क-२' => 'क-२',
        'D'  => 'ड',   'ड'   => 'ड',
        'E1' => 'इ-१', 'E-1' => 'इ-१', 'इ1' => 'इ-१', 'इ-1' => 'इ-१', 'इ-१' => 'इ-१',
        'E2' => 'इ-२', 'E-2' => 'इ-२', 'इ2' => 'इ-२', 'इ-2' => 'इ-२', 'इ-२' => 'इ-२',
    ];
    return $map[$g] ?? ($g ?: 'ड');
}

/**
 * Compute student data completeness progress (महत्त्वाची माहिती vs अतिरिक्त माहिती)
 * Imp Fields (60% weight): name, roll_no, std, section, gender, dob, parent details, phone, aadhar/pen
 * Extra Fields (40% weight): height/weight, blood group, caste/category, address, bank, hobbies, photo
 */
if (!function_exists('computeStudentDataProgress')) {
    function computeStudentDataProgress($s): array
    {
        // 1. Important fields (Count 10)
        $imp_fields = [
            !empty($s['name_mr']) || !empty($s['name']),
            !empty($s['gender']),
            !empty($s['grade']) || !empty($s['std']),
            !empty($s['section']),
            !empty($s['roll_no']),
            !empty($s['gr_no']),
            !empty($s['date_of_birth']) || !empty($s['dob']),
            !empty($s['father_name']) || !empty($s['mother_name']),
            !empty($s['phone']) || !empty($s['father_mobile']) || !empty($s['mother_mobile']),
            !empty($s['aadhar_no']) || !empty($s['apaar_id']) || !empty($s['student_pen']) || !empty($s['saral_id'])
        ];
        $imp_filled = count(array_filter($imp_fields));
        $imp_total = count($imp_fields);
        $imp_pct = round(($imp_filled / $imp_total) * 100);

        // 2. Extra fields (Count 10)
        $extra_fields = [
            (!empty($s['height_sem1']) && (float)$s['height_sem1'] > 0) || (!empty($s['weight_sem1']) && (float)$s['weight_sem1'] > 0),
            (!empty($s['height_sem2']) && (float)$s['height_sem2'] > 0) || (!empty($s['weight_sem2']) && (float)$s['weight_sem2'] > 0),
            !empty($s['religion']) || !empty($s['caste']),
            !empty($s['caste_category']),
            !empty($s['birth_place']) || !empty($s['address_line1']) || !empty($s['address']),
            !empty($s['bank_name']) || !empty($s['bank_account']),
            !empty($s['favourite_color']) || !empty($s['favourite_food']),
            !empty($s['favourite_sport']) || !empty($s['favourite_subject']),
            !empty($s['aspiration']),
            !empty($s['best_friend1']) || !empty($s['photo'])
        ];
        $extra_filled = count(array_filter($extra_fields));
        $extra_total = count($extra_fields);
        $extra_pct = round(($extra_filled / $extra_total) * 100);

        // Overall total: Weighted 60% Important, 40% Extra
        $overall_pct = (int)round(($imp_pct * 0.6) + ($extra_pct * 0.4));

        return [
            'overall' => $overall_pct,
            'imp_pct' => (int)$imp_pct,
            'imp_filled' => $imp_filled,
            'imp_total' => $imp_total,
            'extra_pct' => (int)$extra_pct,
            'extra_filled' => $extra_filled,
            'extra_total' => $extra_total,
            'is_complete' => ($overall_pct >= 85),
            'is_extra_sparse' => ($extra_pct < 60)
        ];
    }
}


