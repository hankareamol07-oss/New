<?php
/**
 * Native Android API — टाचण (Lesson Planner).
 * Mirrors modules/tachan/index.php, day.php, add.php, edit.php, list.php, weekly.php, annual.php,
 * timetable.php, holidays.php, class_pref.php. Data lives in the tenant SQLite DB (db()) with the same
 * dual-sync to MySQL (syncTenantRecordToMySQL / syncTenantDeleteToMySQL) the web pages use.
 */
require_once __DIR__ . '/native_bootstrap.php';
require_once __DIR__ . '/../modules/lib/db.php';
require_once __DIR__ . '/../modules/lib/helpers.php';
require_once __DIR__ . '/../modules/lib/module_helpers.php';
require_once __DIR__ . '/../modules/lib/planner_seed.php';
require_once __DIR__ . '/../modules/lib/tachan_engine.php';
require_once __DIR__ . '/../modules/data/planner_data.php';

$ctx = native_boot();
$sid = $ctx['school_id'];
$user = $ctx['user'];
$teacherId = (int) $user['id'];
$in = native_input();
$action = native_action($in);
$pdo = db();
$classes = school_classes($sid);
$byId = [];
foreach ($classes as $c) $byId[(int) $c['id']] = $c;
$school = getSchool();
$schoolName = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$MEDIUMS = ['marathi' => 'मराठी माध्यम', 'semi_english' => 'सेमी-इंग्रजी'];
$DNAMES = [1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार', 7 => 'रविवार'];
$MN = [1 => 'जानेवारी', 2 => 'फेब्रुवारी', 3 => 'मार्च', 4 => 'एप्रिल', 5 => 'मे', 6 => 'जून', 7 => 'जुलै', 8 => 'ऑगस्ट', 9 => 'सप्टेंबर', 10 => 'ऑक्टोबर', 11 => 'नोव्हेंबर', 12 => 'डिसेंबर'];

function tn_class(array $c): array
{
    return ['id' => (int) $c['id'], 'name' => (string) $c['name'], 'std' => planner_class_std($c), 'section' => (string) ($c['section'] ?? ''),
        'medium' => tachan_norm_medium((string) ($c['medium'] ?? 'marathi')), 'label' => class_label(planner_class_std($c))];
}

function tn_plan(array $r): array
{
    $keys = ['id', 'date', 'period', 'class_id', 'class_name', 'subject', 'topic', 'objectives', 'activities', 'eval_tool', 'materials',
        'learning_outcome', 'homework', 'notes', 'src', 'medium', 'teacher_id'];
    $o = [];
    foreach ($keys as $k) $o[$k] = isset($r[$k]) ? (in_array($k, ['id', 'period', 'class_id', 'teacher_id'], true) ? (int) $r[$k] : (string) $r[$k]) : (in_array($k, ['id', 'period', 'class_id', 'teacher_id'], true) ? 0 : '');
    return $o;
}

function tn_date(array $in, string $key = 'date'): string
{
    $d = native_str($in, $key);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : date('Y-m-d');
}

function tn_require_classes(array $classes): void
{
    if (!$classes) apiError('आधी वर्ग व तुकड्या तयार करा.', 412);
}

function tn_periods_list(int $dow): array
{
    $out = [];
    foreach (planner_periods($dow === 6 ? 6 : 1) as $p => $t) $out[] = ['period' => (int) $p, 'time' => $t];
    return $out;
}

try {
    switch ($action) {

        /* ---------------------------------------------------------------- index.php */
        case 'dashboard':
        case '': {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM lesson_plans WHERE school_id=? AND teacher_id=?');
            $cnt->execute([$sid, $teacherId]);
            $h = (int) date('G');
            $cards = [
                ['key' => 'day', 'title' => 'दैनिक टाचण', 'subtitle' => 'शिक्षकाचे व वर्गाचे तासिकानिहाय पाठ', 'badge' => 'दैनिक', 'color' => '#0284C7', 'bg' => '#E0F2FE'],
                ['key' => 'weekly', 'title' => 'साप्ताहिक टाचण', 'subtitle' => 'संपूर्ण आठवड्याचे एकत्रित नियोजन', 'badge' => 'आठवडा', 'color' => '#7C3AED', 'bg' => '#EDE9FE'],
                ['key' => 'timetable', 'title' => 'वेळापत्रक (माझे / वर्ग)', 'subtitle' => 'शिक्षकाचे व वर्गाचे तासिका नियोजन', 'badge' => 'वेळापत्रक', 'color' => '#059669', 'bg' => '#D1FAE5'],
                ['key' => 'annual', 'title' => 'वार्षिक नियोजन', 'subtitle' => 'महिनानिहाय शासकीय अभ्यासक्रम घटक', 'badge' => 'वार्षिक', 'color' => '#D97706', 'bg' => '#FEF3C7'],
                ['key' => 'holidays', 'title' => 'सुट्ट्या व दिनविशेष', 'subtitle' => 'शासकीय व स्थानिक सुट्ट्यांचे कॅलेंडर', 'badge' => 'कॅलेंडर', 'color' => '#DC2626', 'bg' => '#FEE2E2'],
                ['key' => 'list', 'title' => 'जुने टाचण व शोध', 'subtitle' => 'मागील सर्व नोंदींचा शोध व अहवाल', 'badge' => 'संग्रह', 'color' => '#475569', 'bg' => '#F1F5F9'],
                ['key' => 'settings', 'title' => 'टाचण सेटिंग्ज', 'subtitle' => 'माझा वर्ग, माध्यम व जोडवर्ग निवड', 'badge' => 'सेटिंग्ज', 'color' => '#0F766E', 'bg' => '#CCFBF1'],
                ['key' => 'add', 'title' => 'नवीन टाचण नोंद', 'subtitle' => 'स्वतःची स्वतंत्र टाचण नोंद जोडा', 'badge' => 'नवीन', 'color' => '#E65100', 'bg' => '#FFEDD5'],
            ];
            apiSuccess(['school_name' => $schoolName, 'teacher_name' => (string) ($user['name'] ?? ''), 'greeting' => $h < 12 ? 'सुप्रभात' : ($h < 17 ? 'शुभ दुपार' : 'शुभ संध्याकाळ'),
                'total_plans' => (int) $cnt->fetchColumn(), 'cards' => $cards, 'classes' => array_map('tn_class', $classes), 'has_classes' => !empty($classes)]);
        }

        /* ---------------------------------------------------------------- day.php */
        case 'day': {
            tn_require_classes($classes);
            $pref = tachan_prefs_get($pdo, $sid, $teacherId);
            $teacherTT = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
            $hasTeacherTT = !empty($teacherTT);
            $modeIn = native_str($in, 'mode');
            $mode = in_array($modeIn, ['teacher', 'class'], true) ? $modeIn
                : ((($pref['teach_type'] ?? '') === 'single' && isset($in['class_id'])) ? 'class' : ($hasTeacherTT || ($pref['teach_type'] ?? '') === 'periodwise' ? 'teacher' : 'class'));
            $prefClass = $pref['class_id'] && isset($byId[$pref['class_id']]) ? (int) $pref['class_id'] : (int) $classes[0]['id'];
            $sel = isset($byId[native_int($in, 'class_id')]) ? native_int($in, 'class_id') : $prefClass;
            $class = $byId[$sel];
            $date = tn_date($in);
            $medium = isset($in['medium']) && $in['medium'] !== '' ? tachan_norm_medium((string) $in['medium']) : ($pref['medium'] ?: tachan_norm_medium((string) ($class['medium'] ?? 'marathi')));
            $prefJod = ($pref['teach_type'] === 'jod' && $pref['jod_class_id'] && isset($byId[$pref['jod_class_id']]) && (int) $pref['jod_class_id'] !== $sel) ? (int) $pref['jod_class_id'] : 0;
            $jod = isset($in['jod']) ? ((isset($byId[(int) $in['jod']]) && (int) $in['jod'] !== $sel) ? (int) $in['jod'] : 0) : $prefJod;
            $dayLen = in_array(native_str($in, 'dl'), ['full', 'half', 'upto'], true) ? native_str($in, 'dl') : (string) $pref['day_length'];
            $upto = isset($in['upto']) ? max(1, min(9, (int) $in['upto'])) : (int) $pref['upto_period'];

            if (!empty($in['regen'])) {
                $rm = tachan_norm_medium((string) ($in['regen_medium'] ?? $medium));
                if ($mode === 'teacher') {
                    $pdo->prepare("DELETE FROM lesson_plans WHERE school_id=? AND teacher_id=? AND date=? AND src IN ('teacher_tt', 'bank')")->execute([$sid, $teacherId, $date]);
                    syncTenantDeleteToMySQL('lesson_plans', ['school_id' => $sid, 'teacher_id' => $teacherId, 'date' => $date]);
                    tachan_teacher_day_rows($pdo, $sid, $teacherId, $date, true, $rm);
                    $msg = 'शिक्षकाच्या वेळापत्रकानुसार आजचे टाचण अधिकृत बँकेतून पुन्हा तयार झाले.';
                } else {
                    $t = strtotime($date);
                    tachan_regenerate_month($pdo, $sid, $teacherId, $class, (int) date('Y', $t), (int) date('n', $t), $rm);
                    if ($jod && isset($byId[$jod])) {
                        tachan_regenerate_month($pdo, $sid, $teacherId, $byId[$jod], (int) date('Y', $t), (int) date('n', $t), tachan_norm_medium((string) ($byId[$jod]['medium'] ?? 'marathi')));
                    }
                    $msg = 'या महिन्याचे टाचण (' . $MEDIUMS[$rm] . ') यशस्वीरित्या पुन्हा तयार झाले.';
                }
                $medium = $rm;
            }

            $dow = (int) date('N', strtotime($date));
            $holq = $pdo->prepare('SELECT name FROM holidays WHERE school_id=? AND date=?');
            $holq->execute([$sid, $date]);
            $holName = $holq->fetchColumn();
            $closed = ($dow === 7 || $holName !== false);
            $suvichar = tachan_fetch_suvichar($date);
            $upakram = tachan_fetch_upakram($pdo, $sid, $date);
            $last = tachan_last_period($dayLen, $upto);
            $periods = [];
            $slots = [];
            $jodSlots = [];
            if ($mode === 'teacher') {
                $rows = $closed ? [] : tachan_apply_day_length(tachan_teacher_day_rows($pdo, $sid, $teacherId, $date, true, $medium), $dayLen, $upto);
                $have = [];
                foreach ($rows as $r) $have[(int) $r['period']] = $r;
                $todaySlots = $teacherTT[$dow] ?? [];
                foreach (planner_periods($dow === 6 ? 6 : 1) as $p => $time) {
                    if ($p > $last) continue;
                    $slot = $todaySlots[$p] ?? null;
                    $periods[] = ['period' => (int) $p, 'time' => $time,
                        'timetable_subject' => $slot ? tachan_display_subject((string) $slot['subject'], $medium) : '',
                        'timetable_class' => $slot ? (string) ($slot['class_name'] ?: ('इ. ' . $slot['std'])) : '',
                        'plan' => isset($have[$p]) ? tn_plan($have[$p]) : null];
                }
            } else {
                $tt = tachan_timetable($pdo, $sid, $class);
                $ttToday = $tt[$dow] ?? [];
                $rows = $closed ? [] : tachan_apply_day_length(tachan_day_rows($pdo, $sid, $teacherId, $class, $date, true, $medium), $dayLen, $upto);
                $have = [];
                foreach ($rows as $r) $have[(int) $r['period']] = $r;
                $jodRows = [];
                $jodTT = [];
                if ($jod && isset($byId[$jod])) {
                    $jm = tachan_norm_medium((string) ($byId[$jod]['medium'] ?? 'marathi'));
                    if (!$closed) foreach (tachan_apply_day_length(tachan_day_rows($pdo, $sid, $teacherId, $byId[$jod], $date, true, $jm), $dayLen, $upto) as $r) $jodRows[(int) $r['period']] = $r;
                    $jodTT = tachan_timetable($pdo, $sid, $byId[$jod])[$dow] ?? [];
                }
                foreach (planner_periods($dow === 6 ? 6 : 1) as $p => $time) {
                    if ($p > $last) continue;
                    $periods[] = ['period' => (int) $p, 'time' => $time,
                        'timetable_subject' => isset($ttToday[$p]) ? tachan_display_subject((string) $ttToday[$p], $medium) : '',
                        'timetable_class' => (string) $class['name'],
                        'plan' => isset($have[$p]) ? tn_plan($have[$p]) : null,
                        'jod_timetable_subject' => isset($jodTT[$p]) ? (string) $jodTT[$p] : '',
                        'jod_plan' => isset($jodRows[$p]) ? tn_plan($jodRows[$p]) : null];
                }
            }
            $today = date('Y-m-d');
            apiSuccess([
                'message_flash' => $msg ?? '',
                'school_name' => $schoolName,
                'teacher_name' => resolve_teacher_name($mode === 'class' ? $class : null, $sid, $user),
                'mode' => $mode, 'has_teacher_timetable' => $hasTeacherTT,
                'class' => tn_class($class), 'class_id' => $sel, 'jod' => $jod, 'jod_class' => $jod ? tn_class($byId[$jod]) : null,
                'classes' => array_map('tn_class', $classes),
                'date' => $date, 'day_name' => $DNAMES[$dow], 'date_label' => (int) date('j', strtotime($date)) . ' ' . $MN[(int) date('n', strtotime($date))] . ' ' . date('Y', strtotime($date)),
                'prev' => date('Y-m-d', strtotime($date . ' -1 day')), 'next' => date('Y-m-d', strtotime($date . ' +1 day')),
                'tabs' => [['label' => 'काल', 'date' => date('Y-m-d', strtotime($today . ' -1 day'))], ['label' => 'आज', 'date' => $today], ['label' => 'उद्या', 'date' => date('Y-m-d', strtotime($today . ' +1 day'))]],
                'medium' => $medium, 'mediums' => $MEDIUMS,
                'day_length' => $dayLen, 'upto' => $upto, 'last_period' => $last,
                'lengths' => ['full' => 'पूर्ण दिवस (सर्व तासिका)', 'half' => 'अर्धा दिवस (तासिका १–४)', 'upto' => 'तासिका ' . $upto . ' पर्यंत'],
                'is_saturday' => $dow === 6, 'closed' => $closed, 'holiday_name' => $holName !== false ? (string) $holName : ($dow === 7 ? 'रविवार' : ''),
                'paripath_time' => planner_pariphath_time($dow === 6 ? 6 : 1),
                'suvichar' => $suvichar, 'upakram' => $upakram,
                'periods' => $periods,
                'print' => ['page' => 'modules/tachan/day_print.php', 'params' => ['mode' => $mode, 'class_id' => $sel, 'date' => $date, 'medium' => $medium, 'jod' => $jod, 'dl' => $dayLen, 'upto' => $upto]],
            ]);
        }

        /* ---------------------------------------------------------------- add.php / edit.php / delete */
        case 'plan_get': {
            $q = $pdo->prepare('SELECT * FROM lesson_plans WHERE id=? AND school_id=?');
            $q->execute([native_int($in, 'id'), $sid]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) apiError('नोंद सापडली नाही', 404);
            apiSuccess(['plan' => tn_plan($row), 'classes' => array_map('tn_class', $classes), 'periods' => tn_periods_list(1)]);
        }
        case 'plan_save': {
            tn_require_classes($classes);
            $id = native_int($in, 'id');
            $date = tn_date($in);
            $period = max(1, min(9, native_int($in, 'period', 1)));
            $cid = native_int($in, 'class_id');
            $className = native_str($in, 'class_name');
            if ($className === '' && isset($byId[$cid])) $className = (string) $byId[$cid]['name'];
            $f = [];
            foreach (['subject', 'topic', 'objectives', 'activities', 'eval_tool', 'materials', 'learning_outcome', 'homework', 'notes'] as $k) $f[$k] = native_str($in, $k);
            if ($id > 0) {
                $q = $pdo->prepare('SELECT * FROM lesson_plans WHERE id=? AND school_id=?');
                $q->execute([$id, $sid]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if (!$row) apiError('नोंद सापडली नाही', 404);
                $pdo->prepare('UPDATE lesson_plans SET date = ?, period = ?, class_id = ?, class_name = ?, subject = ?, topic = ?, objectives = ?, activities = ?, eval_tool = ?, materials = ?, learning_outcome = ?, homework = ?, notes = ? WHERE id = ? AND school_id = ?')
                    ->execute([$date, $period, $cid, $className, $f['subject'], $f['topic'], $f['objectives'], $f['activities'], $f['eval_tool'], $f['materials'], $f['learning_outcome'], $f['homework'], $f['notes'], $id, $sid]);
                $rec = array_merge(['id' => $id, 'school_id' => $sid, 'teacher_id' => (int) ($row['teacher_id'] ?: $teacherId), 'date' => $date, 'period' => $period, 'class_id' => $cid, 'class_name' => $className], $f);
                syncTenantRecordToMySQL('lesson_plans', $rec, ['id']);
                apiSuccess(['id' => $id], 'टाचण नोंद अद्ययावत झाली.');
            }
            $pdo->prepare('INSERT INTO lesson_plans(school_id, teacher_id, date, period, class_id, class_name, subject, topic, objectives, activities, eval_tool, materials, learning_outcome, homework, notes, src) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "manual")')
                ->execute([$sid, $teacherId, $date, $period, $cid, $className, $f['subject'], $f['topic'], $f['objectives'], $f['activities'], $f['eval_tool'], $f['materials'], $f['learning_outcome'], $f['homework'], $f['notes']]);
            $lastId = (int) $pdo->lastInsertId();
            syncTenantRecordToMySQL('lesson_plans', array_merge(['id' => $lastId, 'school_id' => $sid, 'teacher_id' => $teacherId, 'date' => $date, 'period' => $period, 'class_id' => $cid, 'class_name' => $className, 'src' => 'manual'], $f), ['id']);
            apiSuccess(['id' => $lastId], 'नवीन टाचण नोंद जतन झाली.');
        }
        case 'plan_delete': {
            $id = native_int($in, 'id');
            $d = $pdo->prepare('DELETE FROM lesson_plans WHERE id=? AND school_id=?');
            $d->execute([$id, $sid]);
            if ($d->rowCount() < 1) apiError('नोंद सापडली नाही', 404);
            syncTenantDeleteToMySQL('lesson_plans', ['id' => $id, 'school_id' => $sid]);
            apiSuccess([], 'टाचण नोंद हटवली.');
        }

        /* ---------------------------------------------------------------- list.php */
        case 'list': {
            $months = planner_months();
            if (!empty($in['gen'])) {
                $m = native_int($in, 'month');
                $y = native_int($in, 'year');
                if (!isset($months[$m]) || $y < 2000) apiError('महिना/वर्ष अवैध');
                $n = planner_import_tachan($pdo, $sid, $teacherId, $classes, $y, $m);
                apiSuccess(['count' => $n], "{$months[$m]} {$y} चे संपूर्ण टाचण तयार झाले ({$n} नोंदी शाळा डेटाबेस व सर्व्हरवर जतन).");
            }
            $fm = native_int($in, 'month');
            $fy = native_int($in, 'year');
            $fc = native_int($in, 'class_id');
            $search = native_str($in, 'q');
            $sql = 'SELECT l.*, c.name as class_name_c FROM lesson_plans l LEFT JOIN classes c ON c.id = l.class_id AND c.school_id = l.school_id WHERE l.school_id = ?';
            $par = [$sid];
            if ($fm > 0) { $sql .= " AND CAST(strftime('%m', l.date) AS INTEGER) = ?"; $par[] = $fm; }
            if ($fy > 0) { $sql .= " AND CAST(strftime('%Y', l.date) AS INTEGER) = ?"; $par[] = $fy; }
            if ($fc > 0) { $sql .= ' AND l.class_id = ?'; $par[] = $fc; }
            if ($search !== '') { $sql .= ' AND (l.topic LIKE ? OR l.subject LIKE ? OR l.learning_outcome LIKE ?)'; $like = '%' . $search . '%'; array_push($par, $like, $like, $like); }
            $sql .= ' ORDER BY l.date DESC, l.period ASC LIMIT 200';
            $s = $pdo->prepare($sql);
            $s->execute($par);
            $rows = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $p = tn_plan($r);
                if ($p['class_name'] === '' && !empty($r['class_name_c'])) $p['class_name'] = (string) $r['class_name_c'];
                $rows[] = $p;
            }
            $ml = [];
            foreach ($months as $k => $v) $ml[] = ['month' => (int) $k, 'name' => $v];
            apiSuccess(['rows' => $rows, 'months' => $ml, 'classes' => array_map('tn_class', $classes), 'year' => (int) date('Y')]);
        }

        /* ---------------------------------------------------------------- weekly.php */
        case 'weekly': {
            tn_require_classes($classes);
            $pref = tachan_prefs_get($pdo, $sid, $teacherId);
            $prefClass = ($pref['class_id'] && isset($byId[$pref['class_id']])) ? (int) $pref['class_id'] : (int) $classes[0]['id'];
            $sel = isset($byId[native_int($in, 'class_id')]) ? native_int($in, 'class_id') : $prefClass;
            $class = $byId[$sel];
            $medium = isset($in['medium']) && $in['medium'] !== '' ? tachan_norm_medium((string) $in['medium']) : ($pref['medium'] ?: tachan_norm_medium((string) ($class['medium'] ?? 'marathi')));
            $base = tn_date($in);
            $monday = date('Y-m-d', strtotime('monday this week', strtotime($base)));
            $holq = $pdo->prepare('SELECT name FROM holidays WHERE school_id = ? AND date = ?');
            $days = [];
            for ($i = 0; $i < 6; $i++) {
                $d = date('Y-m-d', strtotime($monday . ' +' . $i . ' day'));
                $holq->execute([$sid, $d]);
                $hol = $holq->fetchColumn();
                $rows = $hol !== false ? [] : tachan_day_rows($pdo, $sid, $teacherId, $class, $d, true, $medium);
                $days[] = ['date' => $d, 'dow' => $i + 1, 'day_name' => $DNAMES[$i + 1], 'holiday' => $hol !== false ? (string) $hol : '',
                    'date_label' => (int) date('j', strtotime($d)) . ' ' . $MN[(int) date('n', strtotime($d))],
                    'rows' => array_map('tn_plan', $rows)];
            }
            apiSuccess(['class' => tn_class($class), 'classes' => array_map('tn_class', $classes), 'medium' => $medium, 'mediums' => $MEDIUMS,
                'monday' => $monday, 'prev' => date('Y-m-d', strtotime($monday . ' -7 day')), 'next' => date('Y-m-d', strtotime($monday . ' +7 day')),
                'this_week' => date('Y-m-d', strtotime('monday this week')), 'days' => $days, 'school_name' => $schoolName,
                'print' => ['page' => 'modules/tachan/weekly_print.php', 'params' => ['class_id' => $sel, 'medium' => $medium, 'date' => $monday]]]);
        }

        /* ---------------------------------------------------------------- annual.php */
        case 'annual': {
            tn_require_classes($classes);
            planner_annual_ensure_columns($pdo);
            $months = planner_months();
            $pref = tachan_prefs_get($pdo, $sid, $teacherId);
            $sel = isset($byId[native_int($in, 'class_id')]) ? native_int($in, 'class_id') : (int) ($pref['class_id'] ?: $classes[0]['id']);
            if (!isset($byId[$sel])) $sel = (int) $classes[0]['id'];
            $mSel = isset($in['month']) ? (int) $in['month'] : (int) date('n');
            if ($mSel !== 0 && !isset($months[$mSel])) $mSel = 6;
            $q = $pdo->prepare('SELECT subject, month, topics, week1, week2, week3, week4 FROM annual_plans WHERE school_id = ? AND class_id = ? ORDER BY id');
            $q->execute([$sid, $sel]);
            $by = [];
            $subjects = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $by[$r['subject']][(int) $r['month']] = $r; $subjects[$r['subject']] = true; }
            $std = planner_class_std($byId[$sel]);
            $order = [];
            foreach (array_merge(planner_subjects_for_std($std), planner_extra_subjects($std)) as $s) if (isset($subjects[$s])) $order[] = $s;
            foreach (array_keys($subjects) as $s) if (!in_array($s, $order, true)) $order[] = $s;
            $medium = (string) $pref['medium'];
            $cell = static function (array $row, int $i): string {
                $v = (string) ($row['week' . $i] ?? '');
                if ($v !== '' || $i > 1) return $v;
                for ($k = 2; $k <= 4; $k++) if ((string) ($row['week' . $k] ?? '') !== '') return '';
                return (string) ($row['topics'] ?? '');
            };
            $out = [];
            foreach ($order as $s) {
                $row = ['subject' => $s, 'display' => tachan_display_subject($s, $medium), 'months' => []];
                foreach ($months as $m => $mname) {
                    if ($mSel !== 0 && $m !== $mSel) continue;
                    $r = $by[$s][$m] ?? null;
                    $row['months'][] = ['month' => (int) $m, 'name' => $mname, 'topics' => (string) ($r['topics'] ?? ''),
                        'weeks' => $r ? [$cell($r, 1), $cell($r, 2), $cell($r, 3), $cell($r, 4)] : ['', '', '', '']];
                }
                $out[] = $row;
            }
            $ml = [];
            foreach ($months as $k => $v) $ml[] = ['month' => (int) $k, 'name' => $v];
            apiSuccess(['class' => tn_class($byId[$sel]), 'classes' => array_map('tn_class', $classes), 'month' => $mSel, 'months' => $ml, 'view' => $mSel === 0 ? 'year' : 'month',
                'subjects' => $out, 'suggested_subjects' => array_values(array_unique(array_merge(planner_subjects_for_std($std), planner_extra_subjects($std)))),
                'print' => ['page' => 'modules/tachan/annual_print.php', 'params' => ['class_id' => $sel, 'month' => $mSel]]]);
        }
        case 'annual_import': {
            tn_require_classes($classes);
            planner_annual_ensure_columns($pdo);
            $n = planner_import_annual($pdo, $sid, $teacherId, $classes);
            apiSuccess(['count' => $n], "शासकीय वार्षिक नियोजन यशस्वीरीत्या आयात झाले ({$n} नोंदी अद्ययावत).");
        }
        case 'annual_save_weeks': {
            tn_require_classes($classes);
            planner_annual_ensure_columns($pdo);
            $months = planner_months();
            $cid = native_int($in, 'class_id');
            if (!isset($byId[$cid])) apiError('वर्ग अवैध');
            $m = native_int($in, 'month');
            if (!isset($months[$m])) apiError('महिना अवैध');
            $w = $in['w'] ?? [];
            if (!is_array($w)) apiError('w आवश्यक');
            $find = $pdo->prepare('SELECT id FROM annual_plans WHERE school_id = ? AND class_id = ? AND subject = ? AND month = ? ORDER BY id LIMIT 1');
            $upd = $pdo->prepare('UPDATE annual_plans SET topics = ?, week1 = ?, week2 = ?, week3 = ?, week4 = ? WHERE id = ? AND school_id = ?');
            $ins = $pdo->prepare('INSERT INTO annual_plans(school_id, teacher_id, class_id, subject, month, topics, week1, week2, week3, week4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $n = 0;
            foreach ($w as $sub => $ws) {
                if (is_array($ws) && isset($ws['subject'])) { $sub = $ws['subject']; $ws = $ws['weeks'] ?? []; }
                $sub = trim((string) $sub);
                if ($sub === '' || !is_array($ws)) continue;
                $ws = array_values($ws);
                $wk = [];
                for ($i = 0; $i < 4; $i++) $wk[] = trim((string) ($ws[$i] ?? ''));
                $topics = trim(implode(' | ', array_filter($wk)));
                $find->execute([$sid, $cid, $sub, $m]);
                $id = (int) $find->fetchColumn();
                if ($id) $upd->execute([$topics !== '' ? $topics : $sub, $wk[0], $wk[1], $wk[2], $wk[3], $id, $sid]);
                elseif ($topics !== '') $ins->execute([$sid, $teacherId, $cid, $sub, $m, $topics, $wk[0], $wk[1], $wk[2], $wk[3]]);
                syncTenantRecordToMySQL('annual_plans', ['school_id' => $sid, 'teacher_id' => $teacherId, 'class_id' => $cid, 'subject' => $sub, 'month' => $m,
                    'topics' => $topics !== '' ? $topics : $sub, 'week1' => $wk[0], 'week2' => $wk[1], 'week3' => $wk[2], 'week4' => $wk[3]], ['school_id', 'class_id', 'subject', 'month']);
                $n++;
            }
            apiSuccess(['count' => $n], "{$months[$m]} : {$n} विषयांचे आठवडी नियोजन जतन झाले.");
        }
        case 'annual_add_subject': {
            tn_require_classes($classes);
            planner_annual_ensure_columns($pdo);
            $months = planner_months();
            $cid = native_int($in, 'class_id');
            if (!isset($byId[$cid])) apiError('वर्ग अवैध');
            $sub = native_str($in, 'subject');
            $m = native_int($in, 'month');
            if ($sub === '' || !isset($months[$m])) apiError('विषय व महिना आवश्यक');
            $have = $pdo->prepare('SELECT COUNT(*) FROM annual_plans WHERE school_id = ? AND class_id = ? AND subject = ? AND month = ?');
            $have->execute([$sid, $cid, $sub, $m]);
            if ((int) $have->fetchColumn()) apiError('हा विषय या महिन्यात आधीच जोडलेला आहे.');
            $pdo->prepare('INSERT INTO annual_plans(school_id, teacher_id, class_id, subject, month, topics, week1, week2, week3, week4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$sid, $teacherId, $cid, $sub, $m, $sub, '', '', '', '']);
            syncTenantRecordToMySQL('annual_plans', ['school_id' => $sid, 'teacher_id' => $teacherId, 'class_id' => $cid, 'subject' => $sub, 'month' => $m, 'topics' => $sub,
                'week1' => '', 'week2' => '', 'week3' => '', 'week4' => ''], ['school_id', 'class_id', 'subject', 'month']);
            apiSuccess([], 'विषय जोडला — आता आठवड्याचे नियोजन भरा.');
        }

        /* ---------------------------------------------------------------- timetable.php */
        case 'timetable': {
            tn_require_classes($classes);
            $prefs = tachan_prefs_get($pdo, $sid, $teacherId);
            $modeIn = native_str($in, 'mode');
            $mode = in_array($modeIn, ['teacher', 'class'], true) ? $modeIn : ((($prefs['teach_type'] ?? '') === 'single') ? 'class' : 'teacher');
            $medium = isset($in['medium']) && $in['medium'] !== '' ? tachan_norm_medium((string) $in['medium']) : (string) $prefs['medium'];
            $stdLabels = [1 => 'इ. १ ली', 2 => 'इ. २ री', 3 => 'इ. ३ री', 4 => 'इ. ४ थी', 5 => 'इ. ५ वी', 6 => 'इ. ६ वी', 7 => 'इ. ७ वी', 8 => 'इ. ८ वी'];
            $classChoices = [
                '1|0|इ. १ ली' => 'इ. १ ली (पहिली)', '2|0|इ. २ री' => 'इ. २ री (दुसरी)', '3|0|इ. ३ री' => 'इ. ३ री (तिसरी)', '4|0|इ. ४ थी' => 'इ. ४ थी (चौथी)',
                '5|0|इ. ५ वी' => 'इ. ५ वी (पाचवी)', '6|0|इ. ६ वी' => 'इ. ६ वी (सहावी)', '7|0|इ. ७ वी' => 'इ. ७ वी (सातवी)', '8|0|इ. ८ वी' => 'इ. ८ वी (आठवी)',
                '1,2|0|जोडवर्ग १+२' => 'जोडवर्ग १+२ (पहिली व दुसरी)', '1,3|0|जोडवर्ग १+३' => 'जोडवर्ग १+३ (पहिली व तिसरी)', '1,4|0|जोडवर्ग १+४' => 'जोडवर्ग १+४ (पहिली व चौथी)',
                '2,3|0|जोडवर्ग २+३' => 'जोडवर्ग २+३ (दुसरी व तिसरी)', '2,4|0|जोडवर्ग २+४' => 'जोडवर्ग २+४ (दुसरी व चौथी)', '3,4|0|जोडवर्ग ३+४' => 'जोडवर्ग ३+४ (तिसरी व चौथी)',
                '3,5|0|जोडवर्ग ३+५' => 'जोडवर्ग ३+५ (तिसरी व पाचवी)', '4,5|0|जोडवर्ग ४+५' => 'जोडवर्ग ४+५ (चौथी व पाचवी)', '5,6|0|जोडवर्ग ५+६' => 'जोडवर्ग ५+६ (पाचवी व सहावी)',
                '6,7|0|जोडवर्ग ६+७' => 'जोडवर्ग ६+७ (सहावी व सातवी)', '6,8|0|जोडवर्ग ६+८' => 'जोडवर्ग ६+८ (सहावी व आठवी)', '7,8|0|जोडवर्ग ७+८' => 'जोडवर्ग ७+८ (सातवी व आठवी)',
            ];
            foreach ($classes as $c) {
                $cstd = planner_class_std($c);
                $key = "{$cstd}|{$c['id']}|{$c['name']}";
                if (!isset($classChoices[$key])) $classChoices[$key] = "शाळा वर्ग: {$c['name']} (" . class_label($cstd) . ")";
            }
            $commonSubjects = ['मराठी', 'इंग्रजी', 'Maths', 'गणित', 'Gen Science', 'सामान्य विज्ञान', 'सामाजिक शास्त्रे', 'इतिहास', 'भूगोल', 'हिंदी', 'परिसर अभ्यास', 'खेळू,करू,शिकू',
                'शारीरिक शिक्षण', 'कला शिक्षण', 'कार्यानुभव', 'आनंददायी शनिवार उपक्रम', 'AEP', 'वाचन/ग्रंथालय', 'संगणक', 'व्यावसायिक शिक्षण', 'संस्कृत'];
            $daysOut = [];
            foreach (planner_days() as $d => $n) $daysOut[] = ['day' => (int) $d, 'name' => $n, 'period_count' => planner_period_count($d), 'periods' => tn_periods_list($d)];
            $choices = [];
            foreach ($classChoices as $k => $v) $choices[] = ['key' => $k, 'label' => $v];
            if ($mode === 'teacher') {
                $grid = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
                $isTemplate = empty($grid);
                $defTpl = (($prefs['teach_type'] ?? '') === 'jod') ? 'jod_6_7' : '6';
                if ($isTemplate) $grid = planner_get_teacher_template_grid($defTpl, $medium);
                $cells = [];
                foreach ($grid as $d => $ps) foreach ($ps as $p => $slot) {
                    $cells[] = ['day' => (int) $d, 'period' => (int) $p, 'subject' => (string) ($slot['subject'] ?? ''), 'class_id' => (int) ($slot['class_id'] ?? 0),
                        'class_name' => (string) ($slot['class_name'] ?? ''), 'std' => (string) ($slot['std'] ?? ''),
                        'class_info' => (string) ($slot['std'] ?? '') . '|' . (int) ($slot['class_id'] ?? 0) . '|' . (string) ($slot['class_name'] ?? ($stdLabels[(int) ($slot['std'] ?? 0)] ?? ''))];
                }
                $tpls = [];
                foreach (planner_teacher_timetable_templates() as $k => $t) $tpls[] = ['key' => (string) $k, 'name' => (string) ($t['name'] ?? $k)];
                apiSuccess(['mode' => 'teacher', 'medium' => $medium, 'mediums' => $MEDIUMS, 'is_template' => $isTemplate, 'default_template' => $defTpl, 'templates' => $tpls,
                    'days' => $daysOut, 'max_period' => 9, 'cells' => $cells, 'class_choices' => $choices, 'subjects' => $commonSubjects,
                    'print' => ['page' => 'modules/tachan/timetable_print.php', 'params' => ['mode' => 'teacher', 'medium' => $medium]]]);
            }
            $sel = native_int($in, 'class_id') ?: (int) ($prefs['class_id'] ?? ($classes[0]['id'] ?? 0));
            if (!isset($byId[$sel])) $sel = (int) $classes[0]['id'];
            $s = $pdo->prepare('SELECT * FROM timetable WHERE school_id = ? AND class_id = ?');
            $s->execute([$sid, $sel]);
            $grid = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $grid[(int) $r['day']][(int) $r['period']] = $r['subject'];
            $selStd = planner_class_std($byId[$sel]);
            $isTemplate = empty($grid);
            if ($isTemplate) $grid = planner_timetable_for_std($selStd);
            $cells = [];
            foreach ($grid as $d => $ps) foreach ($ps as $p => $sub) $cells[] = ['day' => (int) $d, 'period' => (int) $p, 'subject' => (string) $sub, 'display' => tachan_display_subject((string) $sub, $medium)];
            apiSuccess(['mode' => 'class', 'medium' => $medium, 'mediums' => $MEDIUMS, 'class' => tn_class($byId[$sel]), 'classes' => array_map('tn_class', $classes), 'is_template' => $isTemplate,
                'days' => $daysOut, 'cells' => $cells,
                'subjects' => array_values(array_unique(array_merge(planner_subjects_for_std($selStd), planner_extra_subjects($selStd), $commonSubjects))),
                'print' => ['page' => 'modules/tachan/timetable_print.php', 'params' => ['mode' => 'class', 'class_id' => $sel, 'medium' => $medium]]]);
        }
        case 'timetable_teacher_template': {
            $medium = tachan_norm_medium(native_str($in, 'medium', 'marathi'));
            $tplKey = native_str($in, 'template_key', '6');
            $n = tachan_teacher_timetable_save($pdo, $sid, $teacherId, planner_get_teacher_template_grid($tplKey, $medium), $medium);
            $tpls = planner_teacher_timetable_templates();
            apiSuccess(['count' => $n], "शिक्षकाच्या वेळापत्रकाला '" . ($tpls[$tplKey]['name'] ?? $tplKey) . "' साचा यशस्वीरीत्या लागू झाला ({$n} तासिका जतन).");
        }
        case 'timetable_teacher_save': {
            $medium = tachan_norm_medium(native_str($in, 'medium', 'marathi'));
            $stdLabels = [1 => 'इ. १ ली', 2 => 'इ. २ री', 3 => 'इ. ३ री', 4 => 'इ. ४ थी', 5 => 'इ. ५ वी', 6 => 'इ. ६ वी', 7 => 'इ. ७ वी', 8 => 'इ. ८ वी'];
            $cells = [];
            foreach (native_list($in, 'cells') as $c) {
                if (!is_array($c)) continue;
                $d = (int) ($c['day'] ?? 0);
                $p = (int) ($c['period'] ?? 0);
                if ($d < 1 || $d > 6 || $p < 1 || $p > 9) continue;
                $sub = trim((string) ($c['subject'] ?? ''));
                $info = trim((string) ($c['class_info'] ?? ''));
                if ($sub === '' || $info === '') { $cells[$d][$p] = ['subject' => '']; continue; }
                $parts = explode('|', $info);
                $std = $parts[0] ?? '6';
                $cells[$d][$p] = ['std' => $std, 'class_id' => (int) ($parts[1] ?? 0), 'class_name' => $parts[2] ?? ($stdLabels[(int) $std] ?? "इ. {$std} वी"), 'div' => '-', 'subject' => $sub];
            }
            $n = tachan_teacher_timetable_save($pdo, $sid, $teacherId, $cells, $medium);
            apiSuccess(['count' => $n], "माझे वेळापत्रक यशस्वीरीत्या जतन झाले ({$n} तासिका). दैनिक टाचणामध्ये हे वेळापत्रक आपोआप दिसेल!");
        }
        case 'timetable_class_import': {
            tn_require_classes($classes);
            $n = planner_import_timetable($pdo, $sid, $classes);
            apiSuccess(['count' => $n], "सर्व वर्गांचे वेळापत्रक साचे तयार झाले ({$n} तासिका).");
        }
        case 'timetable_class_save': {
            $cid = native_int($in, 'class_id');
            if (!isset($byId[$cid])) apiError('वर्ग अवैध');
            $rep = $pdo->prepare('INSERT OR REPLACE INTO timetable(school_id, class_id, day, period, subject) VALUES (?, ?, ?, ?, ?)');
            $del = $pdo->prepare('DELETE FROM timetable WHERE school_id = ? AND class_id = ? AND day = ? AND period = ?');
            $n = 0;
            foreach (native_list($in, 'cells') as $c) {
                if (!is_array($c)) continue;
                $d = (int) ($c['day'] ?? 0);
                $p = (int) ($c['period'] ?? 0);
                if ($d < 1 || $d > 6 || $p < 1 || $p > planner_period_count($d)) continue;
                $sub = trim((string) ($c['subject'] ?? ''));
                if ($sub === '') {
                    $del->execute([$sid, $cid, $d, $p]);
                    syncTenantDeleteToMySQL('timetable', ['school_id' => $sid, 'class_id' => $cid, 'day' => $d, 'period' => $p]);
                } else {
                    $rep->execute([$sid, $cid, $d, $p, $sub]);
                    syncTenantRecordToMySQL('timetable', ['school_id' => $sid, 'class_id' => $cid, 'day' => $d, 'period' => $p, 'subject' => $sub], ['school_id', 'class_id', 'day', 'period']);
                    $n++;
                }
            }
            apiSuccess(['count' => $n], "वर्ग वेळापत्रक यशस्वीरीत्या जतन झाले ({$n} तासिका).");
        }
        case 'timetable_class_template': {
            $cid = native_int($in, 'class_id');
            if (!isset($byId[$cid])) apiError('वर्ग अवैध');
            $pdo->prepare('DELETE FROM timetable WHERE school_id = ? AND class_id = ?')->execute([$sid, $cid]);
            syncTenantDeleteToMySQL('timetable', ['school_id' => $sid, 'class_id' => $cid]);
            $rep = $pdo->prepare('INSERT OR REPLACE INTO timetable(school_id, class_id, day, period, subject) VALUES (?, ?, ?, ?, ?)');
            $count = 0;
            foreach (planner_timetable_for_std(planner_class_std($byId[$cid])) as $d => $ps) foreach ($ps as $p => $sub) {
                $rep->execute([$sid, $cid, $d, $p, $sub]);
                syncTenantRecordToMySQL('timetable', ['school_id' => $sid, 'class_id' => $cid, 'day' => $d, 'period' => $p, 'subject' => $sub], ['school_id', 'class_id', 'day', 'period']);
                $count++;
            }
            apiSuccess(['count' => $count], "या वर्गाला शासकीय तयार साचा लागू झाला ({$count} तासिका).");
        }

        /* ---------------------------------------------------------------- holidays.php */
        case 'holidays': {
            planner_holidays_ensure_columns($pdo);
            $ym = preg_match('/^\d{4}-\d{2}$/', native_str($in, 'ym')) ? native_str($in, 'ym') : date('Y-m');
            [$Y, $M] = array_map('intval', explode('-', $ym));
            $hq = $pdo->prepare('SELECT id, date, name, kind FROM holidays WHERE school_id = ? AND date LIKE ? ORDER BY date');
            $hq->execute([$sid, $ym . '-%']);
            $hol = [];
            foreach ($hq->fetchAll(PDO::FETCH_ASSOC) as $r) $hol[] = ['id' => (int) $r['id'], 'date' => substr((string) $r['date'], 0, 10), 'name' => (string) $r['name'], 'kind' => (string) ($r['kind'] ?? 'govt')];
            $eq = $pdo->prepare('SELECT id, date, title, event_type FROM calendar_events WHERE school_id = ? AND date LIKE ? ORDER BY date');
            $eq->execute([$sid, $ym . '-%']);
            $events = [];
            foreach ($eq->fetchAll(PDO::FETCH_ASSOC) as $r) $events[] = ['id' => (int) $r['id'], 'date' => (string) $r['date'], 'title' => (string) $r['title'], 'event_type' => (string) ($r['event_type'] ?? '')];
            $manage = in_array($user['role'] ?? 'teacher', ['admin', 'principal', 'headmaster'], true);
            $first = mktime(0, 0, 0, $M, 1, $Y);
            apiSuccess(['ym' => $ym, 'year' => $Y, 'month' => $M, 'month_name' => $MN[$M], 'prev' => date('Y-m', mktime(0, 0, 0, $M - 1, 1, $Y)), 'next' => date('Y-m', mktime(0, 0, 0, $M + 1, 1, $Y)),
                'days_in_month' => (int) date('t', $first), 'start_dow' => (int) date('w', $first), 'day_names' => ['र', 'सो', 'मं', 'बु', 'गु', 'शु', 'श'],
                'holidays' => $hol, 'events' => $events, 'can_manage' => $manage]);
        }
        case 'holidays_save_cal': {
            $ym = native_str($in, 'ym');
            if (!preg_match('/^\d{4}-\d{2}$/', $ym)) apiError('ym अवैध');
            [$Y, $M] = array_map('intval', explode('-', $ym));
            $picked = array_values(array_filter(array_map('strval', native_list($in, 'd')), static fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && str_starts_with($d, $ym)));
            $old = $pdo->prepare("SELECT date FROM holidays WHERE school_id = ? AND kind = 'user' AND date LIKE ?");
            $old->execute([$sid, $ym . '-%']);
            $oldDates = $old->fetchAll(PDO::FETCH_COLUMN);
            $pdo->prepare("DELETE FROM holidays WHERE school_id = ? AND kind = 'user' AND date LIKE ?")->execute([$sid, $ym . '-%']);
            foreach ($oldDates as $od) syncTenantDeleteToMySQL('holidays', ['school_id' => $sid, 'date' => $od, 'kind' => 'user']);
            $govt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE school_id = ? AND date = ? AND kind <> 'user'");
            $ins = $pdo->prepare("INSERT OR REPLACE INTO holidays(school_id, date, name, kind) VALUES (?, ?, ?, 'user')");
            $n = 0;
            foreach ($picked as $d) {
                $govt->execute([$sid, $d]);
                if ((int) $govt->fetchColumn() > 0) continue;
                $ins->execute([$sid, $d, 'माझी सुट्टी']);
                syncTenantRecordToMySQL('holidays', ['school_id' => $sid, 'date' => $d, 'name' => 'माझी सुट्टी', 'kind' => 'user'], ['school_id', 'date']);
                $n++;
            }
            apiSuccess(['count' => $n], "{$MN[$M]} {$Y} : {$n} सुट्ट्या यशस्वीरीत्या जतन झाल्या.");
        }
        case 'holidays_import': {
            $n = planner_import_holidays($pdo, $sid);
            apiSuccess(['count' => $n], "शासकीय सुट्ट्या आयात झाल्या ({$n} नवीन).");
        }
        case 'upakram_import': {
            $n = planner_import_upakram($pdo, $sid);
            apiSuccess(['count' => $n], "दिनविशेष / उपक्रम आयात झाले ({$n} नवीन).");
        }
        case 'holiday_add': {
            $hDate = tn_date($in);
            $hName = native_str($in, 'name');
            if ($hName === '') apiError('सुट्टीचे नाव आवश्यक');
            $pdo->prepare("INSERT OR REPLACE INTO holidays(school_id, date, name, kind) VALUES (?, ?, ?, 'govt')")->execute([$sid, $hDate, $hName]);
            syncTenantRecordToMySQL('holidays', ['school_id' => $sid, 'date' => $hDate, 'name' => $hName, 'kind' => 'govt'], ['school_id', 'date']);
            apiSuccess([], 'शाळा सुट्टी जोडली.');
        }
        case 'holiday_delete': {
            $delId = native_int($in, 'id');
            $find = $pdo->prepare('SELECT date FROM holidays WHERE id = ? AND school_id = ?');
            $find->execute([$delId, $sid]);
            $hDate = $find->fetchColumn();
            if ($hDate === false) apiError('सुट्टी सापडली नाही', 404);
            $pdo->prepare('DELETE FROM holidays WHERE id = ? AND school_id = ?')->execute([$delId, $sid]);
            syncTenantDeleteToMySQL('holidays', ['school_id' => $sid, 'date' => $hDate]);
            apiSuccess([], 'सुट्टी वगळली.');
        }
        case 'event_add': {
            $evDate = tn_date($in);
            $title = native_str($in, 'title');
            if ($title === '') apiError('शीर्षक आवश्यक');
            $pdo->prepare('INSERT INTO calendar_events(school_id, date, title, event_type, source) VALUES (?, ?, ?, ?, ?)')->execute([$sid, $evDate, $title, 'उपक्रम', 'शाळा']);
            $lastId = (int) $pdo->lastInsertId();
            syncTenantRecordToMySQL('calendar_events', ['id' => $lastId, 'school_id' => $sid, 'date' => $evDate, 'title' => $title, 'event_type' => 'उपक्रम', 'source' => 'शाळा'], ['id']);
            apiSuccess(['id' => $lastId], 'दिनविशेष / उपक्रम जोडला.');
        }
        case 'event_delete': {
            $evId = native_int($in, 'id');
            $d = $pdo->prepare('DELETE FROM calendar_events WHERE id = ? AND school_id = ?');
            $d->execute([$evId, $sid]);
            if ($d->rowCount() < 1) apiError('दिनविशेष सापडला नाही', 404);
            syncTenantDeleteToMySQL('calendar_events', ['id' => $evId, 'school_id' => $sid]);
            apiSuccess([], 'दिनविशेष वगळला.');
        }

        /* ---------------------------------------------------------------- class_pref.php */
        case 'prefs_get': {
            tn_require_classes($classes);
            $p = tachan_prefs_get($pdo, $sid, $teacherId);
            if (!$p['class_id']) $p['class_id'] = (int) $classes[0]['id'];
            $types = [
                ['key' => 'single', 'title' => 'एक वर्ग (वर्गशिक्षक)', 'desc' => 'एकाच इयत्तेचे सर्व किंवा बहुतांश विषय तुम्ही स्वतः शिकवता.'],
                ['key' => 'jod', 'title' => 'जोडवर्ग (द्विशिक्षकी / बहुवर्ग)', 'desc' => 'दोन इयत्ता एकाच वर्गात एकत्र शिकवता (उदा. १ली + २री किंवा ६वी + ७वी).'],
                ['key' => 'periodwise', 'title' => 'तासवार (विषय शिक्षक)', 'desc' => 'वेगवेगळ्या वर्गांना तुमचे विषय तासिकांनुसार शिकवता (उदा. ६वी ला गणित, ७वी ला विज्ञान).'],
            ];
            apiSuccess(['prefs' => ['teach_type' => (string) $p['teach_type'], 'class_id' => (int) $p['class_id'], 'jod_class_id' => (int) $p['jod_class_id'], 'medium' => (string) $p['medium'],
                'day_length' => (string) $p['day_length'], 'upto_period' => (int) $p['upto_period']],
                'types' => $types, 'mediums' => $MEDIUMS,
                'lengths' => ['full' => 'पूर्ण दिवस (सर्व तासिका १–८)', 'half' => 'अर्धा दिवस (तासिका १–४)', 'upto' => 'तासिका क्रमांकापर्यंत…'],
                'periods' => tn_periods_list(1), 'classes' => array_map('tn_class', $classes)]);
        }
        case 'prefs_save': {
            tn_require_classes($classes);
            $tt = native_str($in, 'teach_type', 'single');
            if (!in_array($tt, ['single', 'jod', 'periodwise'], true)) $tt = 'single';
            $cid = native_int($in, 'class_id');
            if (!isset($byId[$cid])) $cid = (int) $classes[0]['id'];
            $jid = $tt === 'jod' ? native_int($in, 'jod_class_id') : 0;
            if ($jid && (!isset($byId[$jid]) || $jid === $cid)) $jid = 0;
            $dayLength = native_str($in, 'day_length', 'full');
            if (!in_array($dayLength, ['full', 'half', 'upto'], true)) $dayLength = 'full';
            $uptoPeriod = max(1, min(9, native_int($in, 'upto_period', 8)));
            $medium = tachan_norm_medium(native_str($in, 'medium', 'marathi'));
            tachan_prefs_save($pdo, $sid, $teacherId, ['teach_type' => $tt, 'class_id' => $cid, 'jod_class_id' => $jid, 'medium' => $medium, 'day_length' => $dayLength, 'upto_period' => $uptoPeriod]);
            if (!empty($in['auto_tt'])) {
                if ($tt === 'jod') {
                    $stdA = planner_class_std($byId[$cid]);
                    $stdB = $jid ? planner_class_std($byId[$jid]) : ($stdA === 8 ? 7 : $stdA + 1);
                    $tpls = planner_teacher_timetable_templates();
                    $tplKey = "jod_{$stdA}_{$stdB}";
                    if (!isset($tpls[$tplKey])) $tplKey = "jod_{$stdB}_{$stdA}";
                    if (!isset($tpls[$tplKey])) $tplKey = 'jod_6_7';
                    tachan_teacher_timetable_save($pdo, $sid, $teacherId, planner_get_teacher_template_grid($tplKey, $medium), $medium);
                } elseif ($tt === 'periodwise' && empty(tachan_teacher_timetable_get($pdo, $sid, $teacherId))) {
                    tachan_teacher_timetable_save($pdo, $sid, $teacherId, planner_get_teacher_template_grid((string) planner_class_std($byId[$cid]), $medium), $medium);
                }
            }
            apiSuccess(['next' => $tt === 'periodwise' ? 'timetable' : 'day'],
                $tt === 'periodwise' ? 'तासवार प्राधान्य जतन झाले. आता तुमचे तासिका वेळापत्रक तपासा व जतन करा.' : 'टाचण प्राधान्ये यशस्वीरीत्या जतन झाली.');
        }

        default:
            apiError('Unknown action: ' . $action, 404);
    }
} catch (Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
