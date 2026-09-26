<?php
/**
 * Native Android API — CCE (सातत्यपूर्ण सर्वंकष मूल्यमापन).
 * Mirrors the cce/*.php pages one-to-one; every action = one PHP page (load) or its POST handler (save).
 *
 * GET/POST ?action=...   (JSON body or form fields; bearer token auth)
 */
require_once __DIR__ . '/native_bootstrap.php';
require_once __DIR__ . '/../includes/cce_functions.php';

$ctx = native_boot();
$school_id = $ctx['school_id'];
$in = native_input();
$action = native_action($in);
$db = cce_db($school_id);
$mysql = getMainDB();
$year = academic_year();
$school = getSchool();

$std = native_int($in, 'std', 0);
$semester = native_int($in, 'semester', 1) === 2 ? 2 : 1;

function cce_n_standards(int $school_id): array
{
    $stds = cce_school_standards($school_id);
    if (empty($stds)) {
        for ($i = 1; $i <= 8; $i++) $stds[$i] = std_label($i);
    }
    $out = [];
    foreach ($stds as $k => $v) $out[] = ['std' => (int) $k, 'label' => (string) $v];
    return $out;
}

function cce_n_students(int $school_id, int $std, string $division = ''): array
{
    $rows = cce_students($school_id, $std, $division);
    $out = [];
    foreach ($rows as $s) {
        $out[] = [
            'id' => (int) $s['id'],
            'roll_no' => (string) ($s['roll_no'] ?? ''),
            'name_mr' => (string) (($s['name_mr'] ?? '') ?: ($s['name'] ?? '')),
            'name' => (string) ($s['name'] ?? ''),
            'gender' => (string) ($s['gender'] ?? ''),
            'section' => (string) ($s['section'] ?? ''),
            'gr_no' => (string) ($s['gr_no'] ?? ($s['register_no'] ?? '')),
        ];
    }
    return $out;
}

function cce_n_months(): array
{
    $out = [];
    foreach (cce_months() as $n => $l) $out[] = ['month' => (int) $n, 'label' => $l];
    return $out;
}

function cce_n_subject_row(array $s): array
{
    $keys = ['id', 'std', 'subject_code', 'name_mr', 'short_name', 'fe_max', 'se_max', 'sort_order', 'is_active',
        'fe1_max', 'fe2_max', 'fe3_max', 'fe4_max', 'fe5_max', 'fe6_max', 'fe7_max', 'fe8_max', 'se1_max', 'se2_max', 'se3_max'];
    $o = [];
    foreach ($keys as $k) {
        $v = $s[$k] ?? null;
        $o[$k] = in_array($k, ['subject_code', 'name_mr', 'short_name'], true) ? (string) $v : ($v === null ? null : (int) $v);
    }
    return $o;
}

try {
    switch ($action) {

        /* ------------------------------------------------------------------ dashboard (cce/index.php) */
        case 'dashboard':
        case '': {
            $standards = cce_n_standards($school_id);
            $first = $standards[0]['std'] ?? 1;
            $students = cce_students($school_id, $first);
            $subj = cce_subjects($school_id, $first);
            $sections = [
                ['key' => 'manage', 'title' => 'व्यवस्थापन', 'items' => [
                    ['key' => 'class_scope', 'title' => 'वर्ग/तुकडी निवडा', 'subtitle' => 'CCE मध्ये कोणत्या तुकड्या दिसाव्यात', 'icon' => 'check2-circle', 'color' => '#7C3AED'],
                    ['key' => 'settings', 'title' => 'शैक्षणिक वर्ष सेटिंग्ज', 'subtitle' => 'मुख्याध्यापक, निकाल दिनांक, कामाचे दिवस', 'icon' => 'gear', 'color' => '#0F766E'],
                    ['key' => 'attendance', 'title' => 'मासिक उपस्थिती', 'subtitle' => 'कामाचे दिवस व उपस्थित दिवस', 'icon' => 'calendar-check', 'color' => '#0284C7'],
                    ['key' => 'subjects', 'title' => 'विषय व्यवस्थापन', 'subtitle' => 'इयत्तानिहाय विषय व क्रम', 'icon' => 'journal-bookmark', 'color' => '#D97706'],
                    ['key' => 'teachers', 'title' => 'विषय शिक्षक', 'subtitle' => 'विषयनिहाय शिक्षक नेमणूक', 'icon' => 'person-badge', 'color' => '#059669'],
                    ['key' => 'marks_config', 'title' => 'भारांश (कमाल गुण) सेटअप', 'subtitle' => 'आकारिक ८ व संकलित ३ साधनांचे कमाल गुण', 'icon' => 'sliders', 'color' => '#DC2626'],
                ]],
                ['key' => 'marks', 'title' => 'गुण नोंदणी', 'items' => [
                    ['key' => 'marks_entry', 'title' => 'गुण नोंदणी', 'subtitle' => 'आकारिक / संकलित साधननिहाय गुण', 'icon' => 'pencil-square', 'color' => '#1D4ED8'],
                    ['key' => 'outcomes', 'title' => 'अध्ययन निष्पत्ती', 'subtitle' => 'स्तर १–४ नोंद', 'icon' => 'bullseye', 'color' => '#7C3AED'],
                    ['key' => 'coscholastic', 'title' => 'सहशालेय मूल्यमापन', 'subtitle' => 'शा.शि., कार्यानुभव, कला, अभिवृत्ती', 'icon' => 'palette', 'color' => '#DB2777'],
                ]],
                ['key' => 'notes', 'title' => 'वर्णनात्मक नोंदी', 'items' => [
                    ['key' => 'remarks_entry', 'title' => 'वर्णनात्मक नोंदी', 'subtitle' => '१३ घटकांच्या नोंदी (सूचना बँकसह)', 'icon' => 'chat-left-text', 'color' => '#0284C7'],
                    ['key' => 'bank', 'title' => 'तयार वर्णनात्मक नोंदी बँक', 'subtitle' => 'SCERT डेटासेट — संच / कामगिरी / अभिप्राय', 'icon' => 'journal-text', 'color' => '#475569'],
                    ['key' => 'extra', 'title' => 'अतिरिक्त माहिती', 'subtitle' => 'उंची/वजन, जात, धर्म, SARAL, PEN, निकाल', 'icon' => 'card-list', 'color' => '#0F766E'],
                ]],
                ['key' => 'results', 'title' => 'निकालपत्रके', 'items' => [
                    ['key' => 'report:result', 'title' => 'वर्गस्तर निकालपत्रके', 'subtitle' => 'सत्रनिहाय निकाल', 'icon' => 'file-earmark-text', 'color' => '#059669'],
                    ['key' => 'report:annual', 'title' => 'वार्षिक निकालपत्रके', 'subtitle' => 'दोन्ही सत्रांचा एकत्रित निकाल', 'icon' => 'file-earmark-check', 'color' => '#D97706'],
                ]],
                ['key' => 'register', 'title' => 'नोंदवही', 'items' => [
                    ['key' => 'report:nondvahi', 'title' => 'नोंदवही', 'subtitle' => 'सातत्यपूर्ण मूल्यमापन नोंदवही', 'icon' => 'book', 'color' => '#7C3AED'],
                    ['key' => 'reports', 'title' => 'सर्व अहवाल केंद्र', 'subtitle' => '२०+ अहवाल व रजिस्टर', 'icon' => 'printer', 'color' => '#475569'],
                ]],
                ['key' => 'progress', 'title' => 'प्रगती पत्रक', 'items' => [
                    ['key' => 'report:pragati_pustak', 'title' => 'प्रगती पुस्तक', 'subtitle' => 'A4 / A5 प्रगती पुस्तक', 'icon' => 'journal-richtext', 'color' => '#DC2626'],
                    ['key' => 'report:progress_card_term1', 'title' => 'प्रगती पत्रक छापा', 'subtitle' => 'प्रथम सत्र प्रगती पत्रक', 'icon' => 'printer-fill', 'color' => '#0284C7'],
                ]],
            ];
            apiSuccess([
                'school' => ['name_mr' => (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? '')), 'udise' => (string) ($school['udise_code'] ?? '')],
                'academic_year' => $year,
                'standards' => $standards,
                'stats' => ['students' => count($students), 'subjects' => count($subj), 'standards' => count($standards)],
                'sections' => $sections,
                'technique_labels' => cce_technique_labels(),
            ]);
        }

        /* ------------------------------------------------------------------ students */
        case 'students': {
            apiSuccess(['students' => cce_n_students($school_id, $std, native_str($in, 'division'))]);
        }

        /* ------------------------------------------------------------------ class scope (cce/class_scope.php) */
        case 'class_scope': {
            $stmt = $mysql->prepare("SELECT c.*, t.name_mr AS teacher_name_mr, t.name AS teacher_name FROM classes c LEFT JOIN teachers t ON c.class_teacher_id = t.id WHERE c.school_id = ? ORDER BY c.std ASC, c.section ASC");
            $stmt->execute([$school_id]);
            $classes = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $classes[] = [
                    'id' => (int) $c['id'], 'std' => (int) ($c['std'] ?? 0), 'section' => (string) ($c['section'] ?? ''),
                    'medium' => (string) ($c['medium'] ?? ''), 'name' => (string) ($c['name'] ?? ($c['class_name'] ?? '')),
                    'teacher' => (string) (($c['teacher_name_mr'] ?? '') ?: ($c['teacher_name'] ?? '')),
                    'students' => count(cce_students($school_id, (int) ($c['std'] ?? 0), (string) ($c['section'] ?? ''))),
                ];
            }
            apiSuccess(['classes' => $classes, 'standards' => cce_n_standards($school_id), 'academic_year' => $year]);
        }

        /* ------------------------------------------------------------------ settings (cce/settings.php) */
        case 'settings_get': {
            $st = $mysql->prepare("SELECT * FROM school_defaults WHERE school_id = ?");
            $st->execute([$school_id]);
            $d = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $wd = json_decode((string) ($school['working_days_monthly'] ?? ''), true) ?: [];
            $months = [];
            foreach (cce_months() as $n => $l) $months[] = ['month' => (int) $n, 'label' => $l, 'working_days' => (int) ($wd[$n] ?? 0)];
            apiSuccess([
                'sem1_headmaster_name' => (string) ($d['sem1_headmaster_name'] ?? ''),
                'sem2_headmaster_name' => (string) ($d['sem2_headmaster_name'] ?? ''),
                'result_date' => (string) ($d['result_date'] ?? ''),
                'next_year_notice' => (string) ($d['next_year_notice'] ?? ''),
                'months' => $months,
                'academic_year' => $year,
            ]);
        }
        case 'settings_save': {
            $sem1 = native_str($in, 'sem1_headmaster_name');
            $sem2 = native_str($in, 'sem2_headmaster_name');
            $res = native_str($in, 'result_date') ?: null;
            $notice = native_str($in, 'next_year_notice');
            $st = $mysql->prepare("SELECT id FROM school_defaults WHERE school_id = ?");
            $st->execute([$school_id]);
            if ($st->fetch()) {
                $mysql->prepare("UPDATE school_defaults SET sem1_headmaster_name = ?, sem2_headmaster_name = ?, result_date = ?, next_year_notice = ?, updated_at = NOW() WHERE school_id = ?")
                    ->execute([$sem1, $sem2, $res, $notice, $school_id]);
            } else {
                $mysql->prepare("INSERT INTO school_defaults (school_id, sem1_headmaster_name, sem2_headmaster_name, result_date, next_year_notice, updated_at) VALUES (?, ?, ?, ?, ?, NOW())")
                    ->execute([$school_id, $sem1, $sem2, $res, $notice]);
            }
            $working = $in['working'] ?? null;
            if (is_array($working)) {
                $wd = [];
                foreach (cce_months() as $mn => $l) $wd[$mn] = max(0, (int) ($working[$mn] ?? 0));
                $mysql->prepare("UPDATE schools SET working_days_monthly = ? WHERE id = ?")->execute([json_encode($wd), $school_id]);
            }
            apiSuccess([], 'सेटिंग्ज जतन केल्या.');
        }

        /* ------------------------------------------------------------------ attendance (cce/attendance.php) */
        case 'attendance_get': {
            $students = cce_n_students($school_id, $std);
            $wd = json_decode((string) ($school['working_days_monthly'] ?? ''), true) ?: [];
            $rows = [];
            foreach ($students as $s) {
                $att = cce_attendance($s['id'], $year);
                $byMonth = [];
                foreach ($att as $m => $r) {
                    $byMonth[(string) (int) $m] = ['working_days' => (int) ($r['working_days'] ?? 0), 'days_present' => (int) ($r['days_present'] ?? 0)];
                }
                $rows[] = $s + ['attendance' => $byMonth];
            }
            $months = [];
            foreach (cce_months() as $n => $l) $months[] = ['month' => (int) $n, 'label' => $l, 'working_days' => (int) ($wd[$n] ?? 0)];
            apiSuccess(['students' => $rows, 'months' => $months, 'academic_year' => $year]);
        }
        case 'attendance_save': {
            $working = $in['working'] ?? null;
            $wd = json_decode((string) ($school['working_days_monthly'] ?? ''), true) ?: [];
            if (is_array($working)) {
                foreach (cce_months() as $mn => $l) $wd[$mn] = max(0, (int) ($working[$mn] ?? ($wd[$mn] ?? 0)));
                $mysql->prepare("UPDATE schools SET working_days_monthly = ? WHERE id = ?")->execute([json_encode($wd), $school_id]);
            }
            $allowed = array_column(cce_students($school_id, $std), 'id');
            $ins = $db->prepare("INSERT OR REPLACE INTO attendance (student_id, academic_year, month, working_days, days_present) VALUES (?,?,?,?,?)");
            $n = 0;
            foreach ((array) ($in['p'] ?? []) as $sid => $row) {
                $sid = (int) $sid;
                if (!in_array($sid, array_map('intval', $allowed), true) || !is_array($row)) continue;
                foreach ($row as $mn => $val) {
                    $mn = (int) $mn;
                    if ($val === '' || $val === null) continue;
                    $ins->execute([$sid, $year, $mn, (int) ($wd[$mn] ?? 0), max(0, (int) $val)]);
                    $n++;
                }
            }
            apiSuccess(['saved' => $n], 'विद्यार्थ्यांची उपस्थिती जतन केली.');
        }

        /* ------------------------------------------------------------------ subjects (cce/subjects.php) */
        case 'subjects_get': {
            $st = $db->prepare("SELECT * FROM cce_subjects WHERE school_id = ? AND std = ? ORDER BY sort_order, id");
            $st->execute([$school_id, $std]);
            apiSuccess(['subjects' => array_map('cce_n_subject_row', $st->fetchAll(PDO::FETCH_ASSOC)), 'std' => $std]);
        }
        case 'subjects_seed': {
            cce_seed_subjects($school_id, $std);
            apiSuccess([], 'डिफॉल्ट विषय तयार केले.');
        }
        case 'subject_add': {
            $name = native_str($in, 'name_mr');
            if ($name === '') apiError('विषयाचे नाव आवश्यक');
            $db->prepare("INSERT INTO cce_subjects (school_id, std, subject_code, name_mr, short_name, fe_max, se_max, sort_order) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$school_id, $std, native_str($in, 'subject_code'), $name, native_str($in, 'short_name') ?: $name,
                    max(0, native_int($in, 'fe_max', 40)), max(0, native_int($in, 'se_max', 60)), native_int($in, 'sort_order', 99)]);
            apiSuccess([], 'विषय जोडला.');
        }
        case 'subjects_update': {
            $upd = $db->prepare("UPDATE cce_subjects SET name_mr = ?, short_name = ?, fe_max = ?, se_max = ?, sort_order = ?, is_active = ? WHERE id = ? AND school_id = ?");
            foreach ((array) ($in['subject'] ?? []) as $id => $row) {
                if (!is_array($row)) continue;
                $upd->execute([trim((string) ($row['name_mr'] ?? '')), trim((string) ($row['short_name'] ?? '')),
                    max(0, (int) ($row['fe_max'] ?? 0)), max(0, (int) ($row['se_max'] ?? 0)),
                    (int) ($row['sort_order'] ?? 99), !empty($row['is_active']) ? 1 : 0, (int) $id, $school_id]);
            }
            apiSuccess([], 'विषय अद्ययावत केले.');
        }
        case 'subject_delete': {
            $db->prepare("DELETE FROM cce_subjects WHERE id = ? AND school_id = ?")->execute([native_int($in, 'id'), $school_id]);
            apiSuccess([], 'विषय हटवला.');
        }

        /* ------------------------------------------------------------------ teachers (cce/teachers.php) */
        case 'teachers_get': {
            $division = native_str($in, 'division');
            $t = $mysql->prepare('SELECT id, name_mr, name, teacher_code FROM teachers WHERE school_id = ? AND is_active = 1 ORDER BY id');
            $t->execute([$school_id]);
            $teachers = [];
            foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $teachers[] = ['id' => (int) $r['id'], 'name' => (string) (($r['name_mr'] ?? '') ?: ($r['name'] ?? '')), 'teacher_code' => (string) ($r['teacher_code'] ?? '')];
            }
            $m = $db->prepare('SELECT subject_id, teacher_id FROM cce_subject_teachers WHERE school_id=? AND academic_year=? AND std=? AND division=?');
            $m->execute([$school_id, $year, $std, $division]);
            $map = [];
            foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $r) $map[(string) (int) $r['subject_id']] = (int) $r['teacher_id'];
            $divs = [];
            foreach (cce_students($school_id, $std) as $s) {
                $d = (string) ($s['section'] ?? '');
                $divs[$d] = true;
            }
            apiSuccess(['teachers' => $teachers, 'subjects' => array_map('cce_n_subject_row', cce_subjects($school_id, $std)),
                'mapping' => $map, 'divisions' => array_keys($divs), 'academic_year' => $year]);
        }
        case 'teachers_save': {
            $division = native_str($in, 'division');
            $del = $db->prepare('DELETE FROM cce_subject_teachers WHERE school_id=? AND academic_year=? AND std=? AND division=? AND subject_id=?');
            $ins = $db->prepare('INSERT INTO cce_subject_teachers (school_id, academic_year, std, division, subject_id, teacher_id) VALUES (?,?,?,?,?,?)');
            $map = (array) ($in['teacher'] ?? []);
            foreach (cce_subjects($school_id, $std) as $s) {
                $tid = (int) ($map[$s['id']] ?? ($map[(string) $s['id']] ?? 0));
                $del->execute([$school_id, $year, $std, $division, (int) $s['id']]);
                if ($tid > 0) $ins->execute([$school_id, $year, $std, $division, (int) $s['id'], $tid]);
            }
            apiSuccess([], 'शिक्षक नेमणूक जतन केली.');
        }

        /* ------------------------------------------------------------------ marks config (cce/marks_config.php) */
        case 'marks_config_get': {
            apiSuccess(['subjects' => array_map('cce_n_subject_row', cce_subjects($school_id, $std)), 'technique_labels' => cce_technique_labels(), 'std' => $std]);
        }
        case 'marks_config_save': {
            $upd = $db->prepare("UPDATE cce_subjects SET fe1_max=?, fe2_max=?, fe3_max=?, fe4_max=?, fe5_max=?, fe6_max=?, fe7_max=?, fe8_max=?, se1_max=?, se2_max=?, se3_max=?, fe_max=?, se_max=? WHERE id = ? AND school_id = ?");
            foreach ((array) ($in['subject'] ?? []) as $id => $row) {
                if (!is_array($row)) continue;
                $fe = [];
                $se = [];
                for ($i = 1; $i <= 8; $i++) $fe[] = max(0, (int) ($row['fe' . $i] ?? 0));
                for ($i = 1; $i <= 3; $i++) $se[] = max(0, (int) ($row['se' . $i] ?? 0));
                $upd->execute(array_merge($fe, $se, [array_sum($fe), array_sum($se), (int) $id, $school_id]));
            }
            apiSuccess([], 'भारांश जतन केला.');
        }

        /* ------------------------------------------------------------------ marks entry (cce/marks_entry.php) */
        case 'marks_get': {
            $subjects = array_map('cce_n_subject_row', cce_subjects($school_id, $std));
            $subject_id = native_int($in, 'subject_id', (int) ($subjects[0]['id'] ?? 0));
            $students = cce_n_students($school_id, $std);
            $marks = [];
            if ($students && $subject_id) {
                $ids = array_column($students, 'id');
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $st = $db->prepare("SELECT * FROM cce_marks WHERE school_id=? AND subject_id=? AND academic_year=? AND semester=? AND student_id IN ($ph)");
                $st->execute(array_merge([$school_id, $subject_id, $year, $semester], $ids));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $row = [];
                    foreach (['fe1', 'fe2', 'fe3', 'fe4', 'fe5', 'fe6', 'fe7', 'fe8', 'se1', 'se2', 'se3', 'fe_total', 'se_total', 'grand_total'] as $k) {
                        $row[$k] = $r[$k] === null ? null : (float) $r[$k];
                    }
                    $row['percent'] = (float) ($r['percent'] ?? 0);
                    $row['grade'] = (string) ($r['grade'] ?? '');
                    $marks[(string) (int) $r['student_id']] = $row;
                }
            }
            apiSuccess(['subjects' => $subjects, 'subject_id' => $subject_id, 'students' => $students, 'marks' => $marks,
                'technique_labels' => cce_technique_labels(), 'semester' => $semester, 'academic_year' => $year]);
        }
        case 'marks_save': {
            $subject_id = native_int($in, 'subject_id');
            $subject = null;
            foreach (cce_subjects($school_id, $std) as $s) if ((int) $s['id'] === $subject_id) $subject = $s;
            if (!$subject) apiError('विषय सापडला नाही');
            $allowed = array_map('intval', array_column(cce_students($school_id, $std), 'id'));
            $rows = (array) ($in['marks'] ?? []);
            if (isset($in['student_id'])) {
                $rows = [(int) $in['student_id'] => $in];
            }
            $sql = "INSERT OR REPLACE INTO cce_marks (school_id, student_id, subject_id, academic_year, semester, fe1,fe2,fe3,fe4,fe5,fe6,fe7,fe8, se1,se2,se3,fe_total,se_total,grand_total,percent,grade) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $ins = $db->prepare($sql);
            $result = [];
            foreach ($rows as $sid => $row) {
                $sid = (int) $sid;
                if (!in_array($sid, $allowed, true) || !is_array($row)) continue;
                $vals = [];
                foreach (['fe1', 'fe2', 'fe3', 'fe4', 'fe5', 'fe6', 'fe7', 'fe8', 'se1', 'se2', 'se3'] as $k) {
                    $v = $row[$k] ?? null;
                    $vals[$k] = ($v === '' || $v === null) ? null : (float) $v;
                }
                $t = cce_compute_totals($vals, $subject['fe_max'], $subject['se_max'], $subject);
                $ins->execute(array_merge([$school_id, $sid, $subject_id, $year, $semester], array_values($vals),
                    [$t['fe_total'], $t['se_total'], $t['grand_total'], $t['percent'], $t['grade']]));
                $result[(string) $sid] = $t;
            }
            apiSuccess(['totals' => $result], 'गुण जतन केले.');
        }

        /* ------------------------------------------------------------------ learning outcomes (cce/outcomes.php) */
        case 'outcomes_get': {
            $student_id = native_int($in, 'student_id');
            $sub = native_str($in, 'sub');
            $students = cce_n_students($school_id, $std);
            if (!$student_id && $students) $student_id = $students[0]['id'];
            $outcomes = [];
            foreach (cce_learning_outcomes($std, $sub === '' ? null : $sub) as $o) {
                $outcomes[] = ['id' => (int) $o['id'], 'text' => (string) ($o['learningoutcome'] ?? ''), 'sub' => (string) ($o['sub'] ?? '')];
            }
            $subs = [];
            try {
                $st = $mysql->prepare("SELECT DISTINCT sub FROM cce_learning_outcomes WHERE std = ? AND medium = 'MA' ORDER BY sub");
                $st->execute([$std]);
                $subs = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'sub');
            } catch (PDOException $e) {
            }
            $selected = [];
            if ($student_id) {
                $st = $db->prepare("SELECT outcome_id, level FROM cce_student_outcomes WHERE student_id = ? AND academic_year = ? AND semester = ? AND achieved = 1");
                $st->execute([$student_id, $year, $semester]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $selected[(string) (int) $r['outcome_id']] = (int) ($r['level'] ?? 4);
            }
            apiSuccess(['students' => $students, 'student_id' => $student_id, 'outcomes' => $outcomes, 'subjects' => $subs, 'selected' => $selected,
                'levels' => [1 => 'स्तर १ — प्रारंभिक', 2 => 'स्तर २ — विकसनशील', 3 => 'स्तर ३ — प्रगत', 4 => 'स्तर ४ — उत्कृष्ट']]);
        }
        case 'outcomes_save': {
            $student_id = native_int($in, 'student_id');
            $allowed = array_map('intval', array_column(cce_students($school_id, $std), 'id'));
            if (!in_array($student_id, $allowed, true)) apiError('विद्यार्थी सापडला नाही');
            $visible = array_values(array_filter(array_map('intval', native_list($in, 'visible_ids'))));
            $levels = (array) ($in['level'] ?? []);
            if ($visible) {
                $ph = implode(',', array_fill(0, count($visible), '?'));
                $db->prepare("DELETE FROM cce_student_outcomes WHERE student_id = ? AND academic_year = ? AND semester = ? AND outcome_id IN ($ph)")
                    ->execute(array_merge([$student_id, $year, $semester], $visible));
            }
            $chosen = [];
            foreach ($visible as $oid) {
                $lvl = (int) ($levels[$oid] ?? ($levels[(string) $oid] ?? 0));
                if ($lvl >= 1 && $lvl <= 4) $chosen[$oid] = $lvl;
            }
            if ($chosen) {
                $ph = implode(',', array_fill(0, count($chosen), '?'));
                $st = $mysql->prepare("SELECT id, learningoutcome FROM cce_learning_outcomes WHERE id IN ($ph)");
                $st->execute(array_keys($chosen));
                $texts = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $texts[(int) $r['id']] = $r['learningoutcome'];
                $ins = $db->prepare("INSERT OR REPLACE INTO cce_student_outcomes (school_id, student_id, academic_year, semester, outcome_id, outcome_text, achieved, level) VALUES (?,?,?,?,?,?,1,?)");
                foreach ($chosen as $oid => $lvl) $ins->execute([$school_id, $student_id, $year, $semester, $oid, $texts[$oid] ?? '', $lvl]);
            }
            apiSuccess(['saved' => count($chosen)], 'अध्ययन निष्पत्ती जतन केल्या.');
        }

        /* ------------------------------------------------------------------ descriptive notes (cce/remarks_entry.php) */
        case 'notes_get': {
            $student_id = native_int($in, 'student_id');
            $students = cce_students($school_id, $std);
            if (!$student_id && $students) $student_id = (int) $students[0]['id'];
            $student = null;
            foreach ($students as $s) if ((int) $s['id'] === $student_id) $student = $s;
            if (!$student) apiError('विद्यार्थी सापडला नाही');
            $saved = [];
            $st = $db->prepare("SELECT slot_no, note_text FROM cce_student_notes WHERE student_id = ? AND academic_year = ? AND semester = ?");
            $st->execute([$student_id, $year, $semester]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $saved[(int) $r['slot_no']] = (string) $r['note_text'];
            $areas = [];
            foreach (cce_class_note_areas($school_id, $std) as $slot => $label) {
                $sugs = array_values(cce_note_suggestions($std, $semester, $slot, $student));
                $default = '';
                if ($sugs) $default = $sugs[((int) ($student['roll_no'] ?? 1) + $slot) % count($sugs)];
                $areas[] = ['slot' => (int) $slot, 'label' => (string) $label, 'saved' => $saved[$slot] ?? '', 'default' => $default, 'suggestions' => $sugs];
            }
            apiSuccess(['students' => cce_n_students($school_id, $std), 'student_id' => $student_id, 'is_girl' => cce_is_female_student($student), 'areas' => $areas]);
        }
        case 'notes_save': {
            $student_id = native_int($in, 'student_id');
            $student = null;
            foreach (cce_students($school_id, $std) as $s) if ((int) $s['id'] === $student_id) $student = $s;
            if (!$student) apiError('विद्यार्थी सापडला नाही');
            $is_girl = cce_is_female_student($student);
            $all_areas = cce_class_note_areas($school_id, $std);
            $st = $db->prepare("INSERT OR REPLACE INTO cce_student_notes (school_id, student_id, academic_year, semester, slot_no, note_text) VALUES (?,?,?,?,?,?)");
            $n = 0;
            foreach ((array) ($in['note'] ?? []) as $slot => $text) {
                $slot = (int) $slot;
                if (!isset($all_areas[$slot])) continue;
                $clean = trim((string) $text);
                if ($clean !== '' && $is_girl && function_exists('cce_apply_gender_inflection')) {
                    $clean = cce_apply_gender_inflection($clean, $student, $slot === 3 ? 3 : 1);
                }
                $st->execute([$school_id, $student_id, $year, $semester, $slot, $clean]);
                $n++;
            }
            apiSuccess(['saved' => $n], 'नोंदी जतन केल्या.');
        }

        /* ------------------------------------------------------------------ bank (cce/bank.php) */
        case 'bank': {
            $sources = [
                'sanch' => ['label' => 'संच — क्षमतानिहाय कृती व पातळी', 'table' => 'cce_sanch'],
                'sanch2' => ['label' => 'संच — क्षमतानिहाय कृती व पातळी (मुलगी)', 'table' => 'cce_sanch2'],
                'kamgiri' => ['label' => 'कामगिरी विधाने', 'table' => 'cce_kamgiri'],
                'abhipray' => ['label' => 'अभिप्राय (क्षेत्रनिहाय)', 'table' => 'cce_abhipray'],
                'abhipray2' => ['label' => 'अभिप्राय — क्षमतानिहाय', 'table' => 'cce_abhipray2'],
            ];
            $src = native_str($in, 'src', 'sanch');
            if (!isset($sources[$src])) $src = 'sanch';
            $q = native_str($in, 'q');
            $rows = [];
            try {
                $table = $sources[$src]['table'];
                $where = [];
                $args = [];
                if (in_array($src, ['sanch', 'sanch2'], true)) {
                    $cols = 'std, vikas AS gat, capacity AS kod, nirzar1, nirzar2, nirzar3, parvat1, parvat2, parvat3, akash1, akash2, akash3';
                    if ($std) { $where[] = 'std = ?'; $args[] = $std; }
                    if ($q !== '') { $where[] = '(capacity LIKE ? OR vikas LIKE ? OR nirzar1 LIKE ? OR parvat1 LIKE ? OR akash1 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
                } elseif ($src === 'kamgiri') {
                    $cols = 'cg AS kod, domain AS gat, types AS std, vidhan AS nirzar1, vidhan2 AS nirzar2';
                    if ($q !== '') { $where[] = '(cg LIKE ? OR domain LIKE ? OR vidhan LIKE ? OR vidhan2 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
                } elseif ($src === 'abhipray') {
                    $cols = 'kshetra AS std, vishay AS gat, notes AS nirzar1, notes2 AS nirzar2';
                    if ($q !== '') { $where[] = '(vishay LIKE ? OR notes LIKE ? OR notes2 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
                } else {
                    $cols = 'domain AS std, capacity AS kod, num AS gat, notes AS nirzar1, notes2 AS nirzar2';
                    if ($q !== '') { $where[] = '(capacity LIKE ? OR notes LIKE ? OR notes2 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
                }
                $st = $mysql->prepare("SELECT $cols FROM `$table`" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' LIMIT 400');
                $st->execute($args);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                apiError('ही बँक उपलब्ध नाही.');
            }
            $srcList = [];
            foreach ($sources as $k => $v) $srcList[] = ['key' => $k, 'label' => $v['label']];
            apiSuccess(['sources' => $srcList, 'src' => $src, 'rows' => $rows]);
        }

        /* ------------------------------------------------------------------ co-scholastic (cce/coscholastic.php) */
        case 'coscholastic_get': {
            $students = cce_n_students($school_id, $std);
            $existing = [];
            if ($students) {
                try {
                    $ids = array_column($students, 'id');
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $st = $mysql->prepare("SELECT * FROM cce_coscholastic WHERE school_id=? AND academic_year=? AND semester=? AND student_id IN ($ph)");
                    $st->execute(array_merge([$school_id, $year, $semester], $ids));
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $existing[(string) (int) $r['student_id']] = ['health_pe' => (string) ($r['health_pe'] ?? ''), 'work_exp' => (string) ($r['work_exp'] ?? ''),
                            'art_ed' => (string) ($r['art_ed'] ?? ''), 'attitude' => (string) ($r['attitude'] ?? '')];
                    }
                } catch (Throwable $e) {
                }
            }
            apiSuccess(['students' => $students, 'grades' => $existing, 'valid_grades' => ['A+', 'A', 'B', 'C', 'D'],
                'areas' => [['key' => 'health_pe', 'label' => 'शारीरिक शिक्षण व आरोग्य'], ['key' => 'work_exp', 'label' => 'कार्यानुभव'],
                    ['key' => 'art_ed', 'label' => 'कला शिक्षण'], ['key' => 'attitude', 'label' => 'अभिवृत्ती व मूल्ये']]]);
        }
        case 'coscholastic_save': {
            $allowed = array_map('intval', array_column(cce_students($school_id, $std), 'id'));
            $valid = ['A+', 'A', 'B', 'C', 'D'];
            $mysql->exec("CREATE TABLE IF NOT EXISTS cce_coscholastic (
                id INT AUTO_INCREMENT PRIMARY KEY, school_id INT NOT NULL, student_id INT NOT NULL, academic_year VARCHAR(20) NOT NULL, semester TINYINT NOT NULL DEFAULT 1,
                health_pe VARCHAR(5) NULL, work_exp VARCHAR(5) NULL, art_ed VARCHAR(5) NULL, attitude VARCHAR(5) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cs (school_id, student_id, academic_year, semester)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $st = $mysql->prepare("INSERT INTO cce_coscholastic (school_id, student_id, academic_year, semester, health_pe, work_exp, art_ed, attitude) VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE health_pe=VALUES(health_pe), work_exp=VALUES(work_exp), art_ed=VALUES(art_ed), attitude=VALUES(attitude)");
            $n = 0;
            foreach ((array) ($in['cs'] ?? []) as $sid => $row) {
                $sid = (int) $sid;
                if (!in_array($sid, $allowed, true) || !is_array($row)) continue;
                $g = fn($k) => in_array($row[$k] ?? '', $valid, true) ? $row[$k] : null;
                $st->execute([$school_id, $sid, $year, $semester, $g('health_pe'), $g('work_exp'), $g('art_ed'), $g('attitude')]);
                $n++;
            }
            apiSuccess(['saved' => $n], 'सहशालेय गुण जतन केले.');
        }

        /* ------------------------------------------------------------------ extra info (cce/extra.php) */
        case 'extra_get': {
            $students = cce_n_students($school_id, $std);
            $extra = [];
            if ($students) {
                try {
                    $ids = array_column($students, 'id');
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $st = $mysql->prepare("SELECT * FROM cce_student_extra WHERE student_id IN ($ph)");
                    $st->execute($ids);
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $o = [];
                        foreach (['height_1', 'weight_1', 'height_2', 'weight_2', 'cast_cat', 'religion', 'saral_id', 'pen', 'remark', 'result_status', 'next_std'] as $k) $o[$k] = (string) ($r[$k] ?? '');
                        $extra[(string) (int) $r['student_id']] = $o;
                    }
                } catch (Throwable $e) {
                }
            }
            apiSuccess(['students' => $students, 'extra' => $extra,
                'result_options' => ['उत्तीर्ण', 'अनुत्तीर्ण', 'वर्गोन्नत', 'पुनर्परीक्षा'], 'academic_year' => $year]);
        }
        case 'extra_save': {
            $allowed = array_map('intval', array_column(cce_students($school_id, $std), 'id'));
            $st = $mysql->prepare("INSERT INTO cce_student_extra (student_id, school_id, academic_year, height_1, weight_1, height_2, weight_2, cast_cat, religion, saral_id, pen, remark, result_status, next_std)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE academic_year=VALUES(academic_year), height_1=VALUES(height_1), weight_1=VALUES(weight_1), height_2=VALUES(height_2), weight_2=VALUES(weight_2),
                    cast_cat=VALUES(cast_cat), religion=VALUES(religion), saral_id=VALUES(saral_id), pen=VALUES(pen), remark=VALUES(remark), result_status=VALUES(result_status), next_std=VALUES(next_std)");
            $n = 0;
            foreach ((array) ($in['ex'] ?? []) as $sid => $row) {
                $sid = (int) $sid;
                if (!in_array($sid, $allowed, true) || !is_array($row)) continue;
                $v = fn($k) => trim((string) ($row[$k] ?? ''));
                $st->execute([$sid, $school_id, $year, $v('height_1'), $v('weight_1'), $v('height_2'), $v('weight_2'), $v('cast_cat'), $v('religion'),
                    $v('saral_id'), $v('pen'), $v('remark'), $v('result_status'), $v('next_std')]);
                $n++;
            }
            apiSuccess(['saved' => $n], 'अतिरिक्त माहिती जतन केली.');
        }

        /* ------------------------------------------------------------------ report center (cce/reports.php) */
        case 'reports': {
            $s = $std ?: 1;
            $sem = $semester;
            $groups = [
                ['title' => 'निकालपत्रके', 'items' => [
                    ['title' => 'वर्गस्तर निकालपत्रक', 'page' => 'cce/result.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'साधननिहाय गुणपत्रक (विद्यार्थी)', 'page' => 'cce/tool_marksheet_student.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'साधननिहाय गुणपत्रक (विषय)', 'page' => 'cce/tool_marksheet_subject.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'गुण–श्रेणी रजिस्टर', 'page' => 'cce/marks_grade_register.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'श्रेणी तक्ता (पोर्ट्रेट)', 'page' => 'cce/grade_table_portrait.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'विषयनिहाय श्रेणी सारांश', 'page' => 'cce/subject_grade_summary.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'लिंगनिहाय श्रेणी तक्ता', 'page' => 'cce/gender_grade_table.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'जातनिहाय श्रेणी तक्ता', 'page' => 'cce/caste_grade_table.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'वर्ग श्रेणी सारांश', 'page' => 'cce/class_grade_summary.php', 'params' => ['std' => $s, 'semester' => $sem]],
                ]],
                ['title' => 'वार्षिक', 'items' => [
                    ['title' => 'वार्षिक निकालपत्रक', 'page' => 'cce/annual.php', 'params' => ['std' => $s]],
                    ['title' => 'वार्षिक सर्वंकष रजिस्टर', 'page' => 'cce/annual_comprehensive_register.php', 'params' => ['std' => $s]],
                    ['title' => 'वार्षिक एकत्रित रजिस्टर', 'page' => 'cce/annual_combined_register.php', 'params' => ['std' => $s]],
                    ['title' => 'वार्षिक श्रेणी तक्ता', 'page' => 'cce/annual_grade_table.php', 'params' => ['std' => $s, 'view' => 'detailed']],
                    ['title' => 'वार्षिक गुणपत्रक (सर्व)', 'page' => 'cce/annual_marksheet.php', 'params' => ['std' => $s, 'all' => 1]],
                    ['title' => 'इ. ५–८ प्रगती पत्रक', 'page' => 'cce/std5_8_progress_card.php', 'params' => ['std' => max(5, $s)]],
                    ['title' => 'इ. ५–८ वार्षिक रजिस्टर', 'page' => 'cce/std5_8_annual_register.php', 'params' => ['std' => max(5, $s)]],
                ]],
                ['title' => 'नोंदवही व प्रगती पत्रक', 'items' => [
                    ['title' => 'नोंदवही', 'page' => 'cce/nondvahi.php', 'params' => ['std' => $s, 'semester' => $sem]],
                    ['title' => 'नोंदवही मुखपृष्ठ', 'page' => 'cce/register_cover.php', 'params' => ['std' => $s]],
                    ['title' => 'नोंदवही अनुक्रमणिका', 'page' => 'cce/register_index.php', 'params' => ['std' => $s]],
                    ['title' => 'प्रगती पुस्तक (A4)', 'page' => 'cce/pragati_pustak.php', 'params' => ['std' => $s, 'all' => 1, 'size' => 'a4']],
                    ['title' => 'प्रगती पुस्तक (A5)', 'page' => 'cce/pragati_pustak.php', 'params' => ['std' => $s, 'all' => 1, 'size' => 'a5']],
                    ['title' => 'प्रथम सत्र प्रगती पत्रक', 'page' => 'cce/progress_card_term1.php', 'params' => ['std' => $s, 'all' => 1]],
                    ['title' => 'वर्णनात्मक नोंदी अहवाल', 'page' => 'cce/narrative_report.php', 'params' => ['std' => $s, 'all' => 1]],
                    ['title' => 'अध्ययन निष्पत्ती अहवाल', 'page' => 'cce/outcomes_report.php', 'params' => ['std' => $s, 'semester' => $sem]],
                ]],
            ];
            apiSuccess(['groups' => $groups, 'standards' => cce_n_standards($school_id), 'std' => $s, 'semester' => $sem]);
        }

        default:
            apiError('Unknown action: ' . $action, 404);
    }
} catch (Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
