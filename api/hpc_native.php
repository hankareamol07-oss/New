<?php
/**
 * Native Android API — HPC (सर्वांगीण प्रगती पत्रक, इ. १ ते ८).
 * Mirrors hpc/index.php, wizard.php, step1_rubric/step2_abhipray/step3_kamgiri (+save_rubric.php),
 * create.php (card-level fields + attendance), stage_form.php (+save_stage.php), delete.php.
 */
require_once __DIR__ . '/native_bootstrap.php';
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/hpc_curriculum.php';
require_once __DIR__ . '/../hpc/rubric_lib.php';

$ctx = native_boot();
$school_id = $ctx['school_id'];
$in = native_input();
$action = native_action($in);
$db = getDB();
hpc_rb_ensure_schema($db);
$year = academic_year();
$school = getSchool();

$STAGES = [
    'foundational' => ['key' => 'foundational', 'name_mr' => 'पायाभूत स्तर', 'name_en' => 'Foundational Stage', 'stds' => [1, 2], 'default_std' => 1,
        'summary_mr' => '६ विकास क्षेत्रे · १३ ध्येये · ६९ क्षमता', 'color' => '#f59e0b',
        'desc_mr' => 'इयत्ता १ व २ साठी पायाभूत स्तरावरील समग्र मूल्यमापन, ३-स्टेप रुब्रिक, शिक्षक अभिप्राय, कामगिरी आणि अधिकृत १९-पानी रंगीत प्रगतिपत्रक.',
        'has_wheel' => false, 'has_ncrf' => false],
    'preparatory' => ['key' => 'preparatory', 'name_mr' => 'पूर्वतयारी स्तर', 'name_en' => 'Preparatory Stage', 'stds' => [3, 4, 5], 'default_std' => 3,
        'summary_mr' => '६ विषय · ४८ ध्येये · १४४ क्षमता', 'color' => '#0284c7',
        'desc_mr' => 'इयत्ता ३ ते ५ साठी विषयनिहाय मूल्यमापन (६ विषय), AI रुब्रिक बँक, नवीन स्टेज फॉर्म आणि अधिकृत SCERT A4 अहवाल.',
        'has_wheel' => false, 'has_ncrf' => true],
    'middle' => ['key' => 'middle', 'name_mr' => 'पूर्व-माध्यमिक स्तर', 'name_en' => 'Middle Stage', 'stds' => [6, 7, 8], 'default_std' => 6,
        'summary_mr' => '९ विषय · ७४ ध्येये · १८१ क्षमता · प्रोग्रेस व्हील', 'color' => '#10b981',
        'desc_mr' => 'इयत्ता ६ ते ८ साठी ९ विषय, बहुआयामी प्रोग्रेस व्हील (शिक्षक-स्वयं-सहाध्यायी), NCrF क्रेडिट्स, स्टेज फॉर्म व SCERT अहवाल.',
        'has_wheel' => true, 'has_ncrf' => true],
];

function hpc_n_student(array $s): array
{
    return [
        'id' => (int) $s['id'],
        'roll_no' => (string) ($s['roll_no'] ?? ''),
        'name_mr' => (string) (($s['name_mr'] ?? '') ?: ($s['name'] ?? '')),
        'name' => (string) ($s['name'] ?? ''),
        'gender' => (string) ($s['gender'] ?? ''),
        'section' => (string) ($s['section'] ?? ''),
        'grade' => (string) ($s['grade'] ?? ''),
        'std' => (int) (($s['std'] ?? 0) ?: hpc_std_from_grade($s['grade'] ?? '')),
        'dob' => (string) ($s['dob'] ?? ''),
        'mother_name' => (string) ($s['mother_name'] ?? ''),
        'father_name' => (string) ($s['father_name'] ?? ''),
    ];
}

function hpc_n_student_or_fail(PDO $db, int $school_id, int $student_id): array
{
    $student = hpc_rb_student($db, $school_id, $student_id);
    if (!$student) apiError('विद्यार्थी सापडला नाही.', 404);
    return $student;
}

function hpc_n_json($v)
{
    if ($v === null || $v === '') return null;
    if (is_array($v)) return $v;
    $d = json_decode((string) $v, true);
    return $d === null ? $v : $d;
}

function hpc_n_level(?string $v): ?string
{
    return in_array((string) $v, ['prarambhik', 'praveen', 'pragat'], true) ? $v : null;
}

try {
    switch ($action) {

        /* ------------------------------------------------------------- dashboard (hpc/index.php + wizard.php) */
        case 'dashboard':
        case '': {
            $range = get_school_std_range($school_id);
            $min = array_key_first($range) ?: 1;
            $max = array_key_last($range) ?: 8;
            $stages = [];
            foreach ($STAGES as $k => $stg) {
                $stg['stds'] = array_values(array_filter($stg['stds'], fn($s) => $s >= $min && $s <= $max));
                if (!$stg['stds']) continue;
                if (!in_array($stg['default_std'], $stg['stds'], true)) $stg['default_std'] = $stg['stds'][0];
                $acc = can_access_hpc_class($stg['stds'][0], $school_id);
                $stg['allowed'] = (bool) $acc['allowed'];
                $stg['access_message'] = (string) ($acc['message'] ?? '');
                $stages[] = $stg;
            }
            if (!$stages) {
                $stg = $STAGES['foundational'];
                $stg['stds'] = [$min];
                $stg['default_std'] = $min;
                $stg['allowed'] = true;
                $stg['access_message'] = '';
                $stages[] = $stg;
            }
            $cnt = $db->prepare("SELECT status, COUNT(*) c FROM hpc_cards WHERE school_id = ? AND academic_year = ? GROUP BY status");
            $cnt->execute([$school_id, $year]);
            $stats = ['completed' => 0, 'draft' => 0];
            foreach ($cnt->fetchAll(PDO::FETCH_ASSOC) as $r) $stats[$r['status'] === 'completed' ? 'completed' : 'draft'] += (int) $r['c'];
            $grade_labels = [1 => 'इयत्ता १ (पहिली)', 2 => 'इयत्ता २ (दुसरी)', 3 => 'इयत्ता ३ (तिसरी)', 4 => 'इयत्ता ४ (चौथी)',
                5 => 'इयत्ता ५ (पाचवी)', 6 => 'इयत्ता ६ (सहावी)', 7 => 'इयत्ता ७ (सातवी)', 8 => 'इयत्ता ८ (आठवी)'];
            $labels = [];
            foreach ($grade_labels as $s => $l) if ($s >= $min && $s <= $max) $labels[] = ['std' => $s, 'label' => $l, 'stage' => hpc_stage_for_std($s)];
            apiSuccess([
                'school' => ['name_mr' => (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''))],
                'academic_year' => $year,
                'stages' => $stages,
                'standards' => $labels,
                'stats' => $stats,
            ]);
        }

        /* ------------------------------------------------------------- student list for a std (index.php list + wizard progress) */
        case 'students': {
            $std = native_int($in, 'std', 1);
            $acc = can_access_hpc_class($std, $school_id);
            $students = cce_students($school_id, $std, native_str($in, 'division'));
            $card_stmt = $db->prepare("SELECT id, student_id, status, stage_key, std, print_format, updated_at FROM hpc_cards WHERE school_id = ? AND academic_year = ?");
            $card_stmt->execute([$school_id, $year]);
            $cards = [];
            foreach ($card_stmt->fetchAll(PDO::FETCH_ASSOC) as $c) $cards[(int) $c['student_id']] = $c;
            $is_stage = $std >= 3;
            $prog = [];
            if (!$is_stage && $cards) {
                $ids = array_map(fn($c) => (int) $c['id'], $cards);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $q = $db->prepare("SELECT hpc_card_id, domain_id, competencies, competencies_term2 FROM hpc_domain_assessments WHERE hpc_card_id IN ($ph)");
                $q->execute(array_values($ids));
                $byCard = [];
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $byCard[(int) $r['hpc_card_id']][(int) $r['domain_id']] = $r;
                foreach ($byCard as $cid => $rows) $prog[$cid] = hpc_rb_progress($rows);
            }
            $out = [];
            $divs = [];
            $done = 0;
            $draft = 0;
            $search = mb_strtolower(native_str($in, 'search'));
            $status_filter = native_str($in, 'status');
            foreach ($students as $s) {
                $row = hpc_n_student($s);
                $divs[$row['section']] = true;
                $c = $cards[$row['id']] ?? null;
                $row['card_id'] = $c ? (int) $c['id'] : 0;
                $row['status'] = $c ? (string) $c['status'] : 'pending';
                $row['updated_at'] = $c ? (string) ($c['updated_at'] ?? '') : '';
                $p = $c ? ($prog[(int) $c['id']] ?? [1 => 0, 2 => 0]) : [1 => 0, 2 => 0];
                $row['progress_sem1'] = (int) $p[1];
                $row['progress_sem2'] = (int) $p[2];
                if ($row['status'] === 'completed') $done++; elseif ($row['status'] === 'draft') $draft++;
                if ($search !== '' && mb_strpos(mb_strtolower($row['name_mr'] . ' ' . $row['name'] . ' ' . $row['roll_no']), $search) === false) continue;
                if ($status_filter !== '' && $row['status'] !== $status_filter) continue;
                $out[] = $row;
            }
            apiSuccess(['students' => $out, 'divisions' => array_keys($divs), 'std' => $std, 'stage' => hpc_stage_for_std($std),
                'allowed' => (bool) $acc['allowed'], 'access_message' => (string) ($acc['message'] ?? ''),
                'stats' => ['total' => count($students), 'completed' => $done, 'draft' => $draft, 'pending' => count($students) - $done - $draft]]);
        }

        /* ------------------------------------------------------------- foundational card (steps 1-3 + create.php card fields) */
        case 'foundational_get': {
            $student = hpc_n_student_or_fail($db, $school_id, native_int($in, 'student_id'));
            $std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade'] ?? '');
            if ($std >= 3) apiError('इयत्ता ३ ते ८ साठी stage_get वापरा.', 400);
            $card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
            $assess = hpc_rb_assessments($db, (int) $card['id']);
            $kam = hpc_rb_kamgiri_rows($db, (int) $card['id']);
            $girl = hpc_rb_is_girl($student);
            $domains = [];
            foreach (hpc_fs_domains() as $did => $dom) {
                $row = $assess[$did] ?? [];
                $comps = [];
                foreach ($dom['competencies'] as $code => $txt) $comps[] = ['code' => $code, 'text' => $txt];
                $goals = [];
                foreach (($dom['goals'] ?? []) as $code => $txt) $goals[] = ['code' => $code, 'text' => $txt];
                $sems = [];
                foreach ([1, 2] as $sem) {
                    $code = $row ? hpc_rb_selected_code($row, $sem) : '';
                    $selIds = array_map('intval', hpc_rb_json_list($row[$sem === 2 ? 'abhipray_ids_term2' : 'abhipray_ids'] ?? ''));
                    $opts = [];
                    foreach (hpc_rb_abhipray_options($db, $code, $girl) as $id => $t) $opts[] = ['id' => (int) $id, 'text' => $t, 'selected' => in_array((int) $id, $selIds, true)];
                    $sems[] = ['semester' => $sem, 'code' => $code, 'code_text' => (string) ($dom['competencies'][$code] ?? ''),
                        'abhipray_options' => $opts, 'abhipray_selected' => $selIds,
                        'teacher_feedback' => (string) ($row[$sem === 2 ? 'teacher_feedback_mr_term2' : 'teacher_feedback_mr'] ?? '')];
                }
                $k = $kam[$did] ?? [];
                $selS = array_map('intval', hpc_rb_json_list($k['strengths'] ?? ''));
                $selI = array_map('intval', hpc_rb_json_list($k['improvements'] ?? ''));
                $optS = [];
                foreach (hpc_rb_kamgiri_options($db, $did, 'strength', $girl) as $id => $t) $optS[] = ['id' => (int) $id, 'text' => $t, 'selected' => in_array((int) $id, $selS, true)];
                $optI = [];
                foreach (hpc_rb_kamgiri_options($db, $did, 'improvement', $girl) as $id => $t) $optI[] = ['id' => (int) $id, 'text' => $t, 'selected' => in_array((int) $id, $selI, true)];
                $domains[] = ['id' => (int) $did, 'name_mr' => $dom['name_mr'], 'name' => $dom['name'], 'goals' => $goals, 'competencies' => $comps,
                    'semesters' => $sems, 'strengths' => $optS, 'improvements' => $optI,
                    'summary' => (string) ($card['summary_domain_' . $did] ?? '')];
            }
            $att = [];
            $st = $db->prepare("SELECT month, working_days, days_present FROM attendance WHERE student_id = ? AND academic_year = ?");
            $st->execute([(int) $student['id'], $year]);
            $byM = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) $byM[(int) $a['month']] = $a;
            foreach (cce_months() as $m => $l) {
                $att[] = ['month' => (int) $m, 'label' => $l, 'working_days' => (int) ($byM[$m]['working_days'] ?? 0), 'days_present' => (int) ($byM[$m]['days_present'] ?? 0)];
            }
            $levels = [];
            foreach (hpc_rubric_levels('foundational') as $lv) $levels[] = ['key' => $lv['level_key'], 'name_mr' => $lv['name_mr'], 'name_en' => $lv['name_en'] ?? ''];
            apiSuccess([
                'student' => hpc_n_student($student),
                'card' => ['id' => (int) $card['id'], 'status' => (string) $card['status'], 'teacher_code' => (string) ($card['teacher_code'] ?? ''),
                    'final_annual_feedback' => (string) ($card['final_annual_feedback'] ?? ''),
                    'summary_awareness' => (string) ($card['summary_awareness'] ?? ''), 'summary_sensitivity' => (string) ($card['summary_sensitivity'] ?? ''),
                    'summary_creativity' => (string) ($card['summary_creativity'] ?? '')],
                'domains' => $domains,
                'attendance' => $att,
                'levels' => $levels,
                'is_girl' => $girl,
                'progress' => hpc_rb_progress($assess),
                'academic_year' => $year,
            ]);
        }

        /* save_rubric.php: action code | abhipray | kamgiri */
        case 'save_code':
        case 'save_abhipray':
        case 'save_kamgiri': {
            $student = hpc_n_student_or_fail($db, $school_id, native_int($in, 'student_id'));
            $card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
            $girl = hpc_rb_is_girl($student);
            $did = native_int($in, 'domain_id');
            if ($did < 1 || $did > 6) apiError('domain_id अवैध');
            if ($action === 'save_code') {
                $sem = native_int($in, 'semester', 1) === 2 ? 2 : 1;
                $ok = hpc_rb_save_code($db, (int) $card['id'], $did, $sem, native_str($in, 'capacity_code'));
                if (!$ok) apiError('जतन झाले नाही');
                apiSuccess([], 'जतन झाले');
            }
            if ($action === 'save_abhipray') {
                $sem = native_int($in, 'semester', 1) === 2 ? 2 : 1;
                $ids = array_map('intval', native_list($in, 'ids'));
                $n = hpc_rb_save_abhipray($db, (int) $card['id'], $did, $sem, $ids, $girl);
                apiSuccess(['count' => $n], 'जतन झाले');
            }
            $r = hpc_rb_save_kamgiri($db, (int) $card['id'], $did, array_map('intval', native_list($in, 'strengths')), array_map('intval', native_list($in, 'improvements')), $girl);
            apiSuccess(['strengths' => (int) ($r['strengths'] ?? 0), 'improvements' => (int) ($r['improvements'] ?? 0)], 'जतन झाले');
        }

        /* create.php: card-level fields + attendance + status (domain rows are handled by the rubric steps) */
        case 'foundational_card_save': {
            $student = hpc_n_student_or_fail($db, $school_id, native_int($in, 'student_id'));
            $std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade'] ?? '');
            $card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
            $status = native_str($in, 'status') === 'completed' ? 'completed' : (string) ($card['status'] ?: 'draft');
            $vals = [];
            foreach (['teacher_code', 'final_annual_feedback', 'summary_awareness', 'summary_sensitivity', 'summary_creativity',
                'summary_domain_1', 'summary_domain_2', 'summary_domain_3', 'summary_domain_4', 'summary_domain_5', 'summary_domain_6'] as $k) {
                $vals[$k] = array_key_exists($k, $in) ? trim((string) $in[$k]) : (string) ($card[$k] ?? '');
            }
            $db->prepare("UPDATE hpc_cards SET stage_key = 'foundational', std = ?, print_format = 'hpc-19', teacher_code = ?, status = ?, final_annual_feedback = ?,
                summary_awareness = ?, summary_sensitivity = ?, summary_creativity = ?, summary_domain_1 = ?, summary_domain_2 = ?, summary_domain_3 = ?,
                summary_domain_4 = ?, summary_domain_5 = ?, summary_domain_6 = ? WHERE id = ? AND school_id = ?")
                ->execute(array_merge([$std ?: 1], [$vals['teacher_code'], $status, $vals['final_annual_feedback'], $vals['summary_awareness'], $vals['summary_sensitivity'],
                    $vals['summary_creativity'], $vals['summary_domain_1'], $vals['summary_domain_2'], $vals['summary_domain_3'], $vals['summary_domain_4'],
                    $vals['summary_domain_5'], $vals['summary_domain_6'], (int) $card['id'], $school_id]));
            $att = $in['attendance'] ?? null;
            if (is_array($att)) {
                $up = $db->prepare("INSERT INTO attendance (student_id, academic_year, month, working_days, days_present) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE working_days = VALUES(working_days), days_present = VALUES(days_present)");
                $del = $db->prepare("DELETE FROM attendance WHERE student_id = ? AND academic_year = ? AND month = ?");
                foreach ($att as $row) {
                    if (!is_array($row)) continue;
                    $m = (int) ($row['month'] ?? 0);
                    if ($m < 1 || $m > 12) continue;
                    $w = max(0, (int) ($row['working_days'] ?? 0));
                    $p = max(0, (int) ($row['days_present'] ?? 0));
                    if ($w > 0 || $p > 0) $up->execute([(int) $student['id'], $year, $m, $w, $p]);
                    else $del->execute([(int) $student['id'], $year, $m]);
                }
            }
            apiSuccess(['card_id' => (int) $card['id'], 'status' => $status], 'HPC कार्ड जतन केले.');
        }

        /* ------------------------------------------------------------- stage form (इ. ३–८) */
        case 'stage_get': {
            $student = hpc_n_student_or_fail($db, $school_id, native_int($in, 'student_id'));
            $std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade'] ?? '');
            if ($std < 3 || $std > 8) apiError('इयत्ता १–२ साठी foundational_get वापरा.', 400);
            $acc = can_access_hpc_class($std, $school_id);
            if (!$acc['allowed']) apiError((string) ($acc['message'] ?? 'उच्च वर्गांच्या (इ. ३ ते ८) HPC मूल्यमापनासाठी सशुल्क योजना आवश्यक आहे.'), 403);
            $stage = hpc_stage_for_std($std);
            $mid = $stage === 'middle';
            $card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
            $subjects = hpc_subjects_for_std($std);
            $A = [];
            $q = $db->prepare("SELECT * FROM hpc_subject_assessments WHERE hpc_card_id = ?");
            $q->execute([(int) $card['id']]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $A[(int) $r['subject_id']][(int) $r['term']] = $r;
            $S = [];
            $q = $db->prepare("SELECT * FROM hpc_subject_summary WHERE hpc_card_id = ?");
            $q->execute([(int) $card['id']]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $S[(int) $r['subject_id']] = $r;
            $parta = null;
            try {
                $q = $db->prepare("SELECT * FROM hpc_card_parta WHERE hpc_card_id = ?");
                $q->execute([(int) $card['id']]);
                $pr = $q->fetch(PDO::FETCH_ASSOC);
                if ($pr) $parta = ['intro_json' => hpc_n_json($pr['intro_json']), 'parent_json' => hpc_n_json($pr['parent_json']), 'parent_teacher_json' => hpc_n_json($pr['parent_teacher_json'])];
            } catch (Throwable $e) {
            }
            $jsonCols = ['goal_codes', 'comp_codes', 'activity_approach', 'rubric_json', 'self_reflection', 'self_progress', 'self_progress_count',
                'peer_reflection', 'peer_progress', 'peer_progress_count', 'strengths', 'hurdles', 'wheel_json'];
            $textCols = ['activity_mr', 'assessment_questions_mr', 'level_awareness', 'level_sensitivity', 'level_creativity', 'teacher_notes_mr', 'challenges_mr',
                'support_mr', 'my_learning_mr', 'liked_most_mr', 'need_practice_mr', 'need_help_mr', 'peer_name', 'peer_need_practice_mr', 'peer_need_help_mr',
                'teacher_help_mr', 'teacher_observation_mr'];
            $subs = [];
            foreach ($subjects as $sub) {
                $sid = (int) $sub['id'];
                $terms = [];
                foreach ([1, 2] as $t) {
                    $r = $A[$sid][$t] ?? [];
                    $o = ['term' => $t];
                    foreach ($jsonCols as $c) $o[$c] = hpc_n_json($r[$c] ?? null);
                    foreach ($textCols as $c) $o[$c] = (string) ($r[$c] ?? '');
                    $terms[] = $o;
                }
                $sm = $S[$sid] ?? [];
                $subs[] = [
                    'id' => $sid, 'subject_key' => (string) $sub['subject_key'], 'name_mr' => (string) $sub['name_mr'],
                    'goals' => array_values(array_map(fn($g) => ['code' => $g['goal_code'], 'text' => $g['text_mr']], hpc_goals($sub))),
                    'comps' => array_values(array_map(fn($c) => ['code' => $c['comp_code'], 'goal' => $c['goal_code'] ?? hpc_goal_code_of($c['comp_code']), 'text' => $c['text_mr'], 'review' => (int) ($c['needs_review'] ?? 0)], hpc_competencies($sub))),
                    'rubric_bank' => hpc_rubric_bank($std, $sub['subject_key']),
                    'terms' => $terms,
                    'summary' => ['level_awareness' => (string) ($sm['level_awareness'] ?? ''), 'level_sensitivity' => (string) ($sm['level_sensitivity'] ?? ''),
                        'level_creativity' => (string) ($sm['level_creativity'] ?? ''), 'remark_mr' => (string) ($sm['remark_mr'] ?? ''),
                        'credit_points_earned' => $sm['credit_points_earned'] ?? null],
                ];
            }
            $stmts = fn($k) => array_values(array_map(fn($s) => ['text' => (string) $s['text_mr'], 'value' => (string) ($s['option_set'] ?? $s['text_mr'])], hpc_statements($stage, $k)));
            apiSuccess([
                'student' => hpc_n_student($student),
                'card' => ['id' => (int) $card['id'], 'status' => (string) $card['status']],
                'stage' => $stage, 'std' => $std, 'is_middle' => $mid,
                'levels' => array_values(array_map(fn($l) => ['key' => $l['level_key'], 'name_mr' => $l['name_mr'], 'name_en' => $l['name_en'] ?? ''], hpc_rubric_levels($stage))),
                'abilities' => array_values(array_map(fn($a) => ['key' => $a['ability_key'], 'name_mr' => $a['name_mr'], 'name_en' => $a['name_en'] ?? '', 'color' => $a['color_hex'] ?? ''], hpc_rubric_abilities($stage))),
                'statements' => ['strength' => $stmts('strength'), 'hurdle' => $stmts('hurdle'), 'self' => $stmts('self'),
                    'peer' => $mid ? $stmts('peer') : [], 'approach' => $mid ? $stmts('approach') : [], 'self_progress' => $mid ? $stmts('self_progress') : []],
                'credits' => hpc_credit_std($std),
                'subjects' => $subs,
                'parta' => $parta,
                'academic_year' => $year,
            ]);
        }

        /* save_stage.php: action subject | summary | parta | status  (passed as stage_action) */
        case 'stage_save': {
            $student = hpc_n_student_or_fail($db, $school_id, native_int($in, 'student_id'));
            $std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade'] ?? '');
            if ($std < 3 || $std > 8) apiError('stage', 400);
            $stage = hpc_stage_for_std($std);
            $acc = can_access_hpc_class($std, $school_id);
            if (!$acc['allowed']) apiError((string) ($acc['message'] ?? 'उच्च वर्गांसाठी सशुल्क योजना आवश्यक आहे.'), 403);
            $card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
            if (empty($card['stage_key']) || (int) $card['std'] !== $std) {
                $db->prepare("UPDATE hpc_cards SET stage_key = ?, std = ?, print_format = ? WHERE id = ?")
                    ->execute([$stage, $std, $stage === 'middle' ? 'scert-middle' : 'scert-prep', $card['id']]);
            }
            $sa = native_str($in, 'stage_action', 'subject');
            if ($sa === 'status') {
                $status = native_str($in, 'status') === 'completed' ? 'completed' : 'draft';
                $db->prepare("UPDATE hpc_cards SET status = ? WHERE id = ?")->execute([$status, $card['id']]);
                apiSuccess(['card_id' => (int) $card['id'], 'status' => $status], $status === 'completed' ? 'कार्ड पूर्ण' : 'मसुदा जतन');
            }
            if ($sa === 'parta') {
                $vals = [];
                foreach (['intro_json', 'parent_json', 'parent_teacher_json'] as $c) $vals[] = isset($in[$c]) ? json_encode($in[$c], JSON_UNESCAPED_UNICODE) : null;
                $db->prepare("INSERT INTO hpc_card_parta (hpc_card_id, intro_json, parent_json, parent_teacher_json) VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE intro_json = COALESCE(VALUES(intro_json), intro_json), parent_json = COALESCE(VALUES(parent_json), parent_json),
                    parent_teacher_json = COALESCE(VALUES(parent_teacher_json), parent_teacher_json)")
                    ->execute(array_merge([(int) $card['id']], $vals));
                apiSuccess(['card_id' => (int) $card['id']], 'जतन झाले');
            }
            $subject = hpc_subject_by_key($stage, native_str($in, 'subject_key'));
            if (!$subject) apiError('विषय सापडला नाही');
            $sid = (int) $subject['id'];
            if ($sa === 'summary') {
                $db->prepare("INSERT INTO hpc_subject_summary (hpc_card_id, subject_id, level_awareness, level_sensitivity, level_creativity, remark_mr, credit_points_earned) VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE level_awareness = VALUES(level_awareness), level_sensitivity = VALUES(level_sensitivity), level_creativity = VALUES(level_creativity),
                    remark_mr = VALUES(remark_mr), credit_points_earned = VALUES(credit_points_earned)")
                    ->execute([(int) $card['id'], $sid, hpc_n_level($in['level_awareness'] ?? null), hpc_n_level($in['level_sensitivity'] ?? null),
                        hpc_n_level($in['level_creativity'] ?? null), native_str($in, 'remark_mr'),
                        isset($in['credit_points_earned']) && $in['credit_points_earned'] !== '' ? (float) $in['credit_points_earned'] : null]);
                apiSuccess(['card_id' => (int) $card['id']], 'सारांश जतन झाला');
            }
            $ALLOW = ['goal_codes' => 'json', 'comp_codes' => 'json', 'activity_approach' => 'json', 'activity_mr' => 'text', 'assessment_questions_mr' => 'text',
                'rubric_json' => 'json', 'level_awareness' => 'level', 'level_sensitivity' => 'level', 'level_creativity' => 'level', 'teacher_notes_mr' => 'text',
                'challenges_mr' => 'text', 'support_mr' => 'text', 'self_reflection' => 'json', 'self_progress' => 'json', 'self_progress_count' => 'json',
                'my_learning_mr' => 'text', 'liked_most_mr' => 'text', 'need_practice_mr' => 'text', 'need_help_mr' => 'text', 'peer_name' => 'text',
                'peer_reflection' => 'json', 'peer_progress' => 'json', 'peer_progress_count' => 'json', 'peer_need_practice_mr' => 'text', 'peer_need_help_mr' => 'text',
                'strengths' => 'json', 'hurdles' => 'json', 'teacher_help_mr' => 'text', 'teacher_observation_mr' => 'text', 'wheel_json' => 'json'];
            $term = native_int($in, 'term', 1) === 2 ? 2 : 1;
            $validGoals = array_column(hpc_goals($subject), 'goal_code');
            $validComps = array_column(hpc_competencies($subject), 'comp_code');
            foreach (native_list($in, 'goal_codes') as $g) if (!in_array((string) $g, $validGoals, true)) apiError('goal:' . $g);
            foreach (native_list($in, 'comp_codes') as $c) if (!in_array((string) $c, $validComps, true)) apiError('comp:' . $c);
            $set = [];
            $vals = [];
            foreach ($ALLOW as $col => $type) {
                if (!array_key_exists($col, $in)) continue;
                $v = $in[$col];
                if ($type === 'json') $v = $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE);
                elseif ($type === 'level') $v = hpc_n_level($v);
                else $v = trim((string) $v);
                $set[] = "$col = ?";
                $vals[] = $v;
            }
            if (isset($in['rubric_json']) && is_array($in['rubric_json'])) {
                foreach (['awareness', 'sensitivity', 'creativity'] as $ab) {
                    if (!array_key_exists('level_' . $ab, $in) && isset($in['rubric_json'][$ab]['level'])) {
                        $set[] = "level_$ab = ?";
                        $vals[] = hpc_n_level($in['rubric_json'][$ab]['level']);
                    }
                }
            }
            $db->prepare("INSERT IGNORE INTO hpc_subject_assessments (hpc_card_id, subject_id, term) VALUES (?,?,?)")->execute([(int) $card['id'], $sid, $term]);
            if ($set) {
                $vals[] = (int) $card['id'];
                $vals[] = $sid;
                $vals[] = $term;
                $db->prepare("UPDATE hpc_subject_assessments SET " . implode(', ', $set) . " WHERE hpc_card_id = ? AND subject_id = ? AND term = ?")->execute($vals);
            }
            apiSuccess(['card_id' => (int) $card['id'], 'subject_id' => $sid, 'term' => $term], 'जतन झाले');
        }

        /* ------------------------------------------------------------- view / delete */
        case 'card_view': {
            $id = native_int($in, 'card_id');
            $st = $db->prepare("SELECT * FROM hpc_cards WHERE id = ? AND school_id = ?");
            $st->execute([$id, $school_id]);
            $card = $st->fetch(PDO::FETCH_ASSOC);
            if (!$card) apiError('कार्ड सापडले नाही', 404);
            $std = (int) $card['std'];
            $page = $std >= 3 ? 'cards/print_hpc_stage.php' : 'cards/print_hpc_19.php';
            $params = $std >= 3 ? ['id' => $id] : ['id' => $id, 'format' => 'official19'];
            apiSuccess(['card' => ['id' => $id, 'status' => $card['status'], 'std' => $std, 'stage_key' => $card['stage_key'], 'student_id' => (int) $card['student_id']],
                'print' => ['page' => $page, 'params' => $params], 'print_compact' => $std >= 3 ? ['page' => $page, 'params' => ['id' => $id, 'mode' => 'compact']] : null]);
        }
        case 'card_delete': {
            $id = native_int($in, 'card_id');
            $st = $db->prepare("DELETE FROM hpc_cards WHERE id = ? AND school_id = ?");
            $st->execute([$id, $school_id]);
            if ($st->rowCount() < 1) apiError('कार्ड सापडले नाही', 404);
            apiSuccess([], 'HPC कार्ड हटवला गेला.');
        }

        default:
            apiError('Unknown action: ' . $action, 404);
    }
} catch (Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
