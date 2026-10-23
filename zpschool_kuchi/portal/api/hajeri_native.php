<?php
/**
 * Native हजेरी API: monthly student register + staff (teacher) daily attendance.
 * GET  ?action=month&month=YYYY-MM&grade=&section=
 * GET  ?action=staff&date=YYYY-MM-DD
 * GET  ?action=staff_month&month=YYYY-MM
 * POST {action:"staff_save", date, rows:[{teacher_id, status}]}
 */
require_once __DIR__ . '/native_bootstrap.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$ctx = native_boot();
$tdb = getTenantDB($ctx['school_id']);
if (!$tdb) {
    apiError('School database not available', 500);
}
$tdb->exec("CREATE TABLE IF NOT EXISTS daily_attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER, student_id INTEGER NOT NULL, date TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'P', UNIQUE(student_id, date))");
$tdb->exec("CREATE TABLE IF NOT EXISTS staff_daily_attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, teacher_id INTEGER NOT NULL, date TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'P', updated_at TEXT, UNIQUE(teacher_id, date))");

$in = native_input();
$action = native_action($in);

function hn_month(string $m): string
{
    return preg_match('/^\d{4}-\d{2}$/', $m) ? $m : date('Y-m');
}

function hn_date(string $d): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : date('Y-m-d');
}

function hn_status(string $s): string
{
    $s = strtoupper(trim($s));
    return in_array($s, ['P', 'A', 'L', 'H'], true) ? $s : 'P';
}

if ($action === 'month') {
    $month = hn_month(native_str($in, 'month'));
    $grade = native_str($in, 'grade');
    $section = native_str($in, 'section');
    $where = ['COALESCE(s.is_active,1) = 1'];
    $params = [];
    if ($grade !== '') { $where[] = 's.grade = ?'; $params[] = $grade; }
    if ($section !== '') { $where[] = 's.section = ?'; $params[] = $section; }
    $st = $tdb->prepare('SELECT s.id, s.name, s.name_mr, s.roll_no, s.gr_no, s.grade, s.section, s.gender FROM students s WHERE ' . implode(' AND ', $where) . ' ORDER BY CAST(s.roll_no AS INTEGER), s.name');
    $st->execute($params);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(fn($r) => (int) $r['id'], $students);
    $marks = [];
    $dates = [];
    if ($ids) {
        $idList = implode(',', $ids);
        $q = $tdb->prepare("SELECT student_id, date, status FROM daily_attendance WHERE substr(date,1,7) = ? AND student_id IN ($idList)");
        $q->execute([$month]);
        foreach ($q as $r) {
            $day = (int) substr($r['date'], 8, 2);
            $marks[(int) $r['student_id']][$day] = $r['status'];
            $dates[$day] = true;
        }
    }
    foreach ($students as &$s) {
        $m = $marks[(int) $s['id']] ?? [];
        $s['id'] = (int) $s['id'];
        $s['days'] = (object) $m;
        $s['present'] = count(array_filter($m, fn($v) => $v === 'P'));
        $s['absent'] = count(array_filter($m, fn($v) => $v === 'A'));
        $s['leave'] = count(array_filter($m, fn($v) => $v === 'L'));
    }
    unset($s);
    $holidays = [];
    try {
        $h = $tdb->prepare('SELECT * FROM holidays');
        $h->execute();
        foreach ($h as $r) {
            $d = (string) ($r['date'] ?? $r['holiday_date'] ?? $r['start_date'] ?? '');
            if (substr($d, 0, 7) === $month) {
                $holidays[(int) substr($d, 8, 2)] = (string) ($r['title'] ?? $r['name'] ?? 'सुट्टी');
            }
        }
    } catch (Throwable $e) {
    }
    ksort($dates);
    apiSuccess([
        'month' => $month,
        'days_in_month' => (int) date('t', strtotime($month . '-01')),
        'working_days' => count($dates),
        'holidays' => (object) $holidays,
        'students' => $students,
    ]);
}

if ($action === 'staff' || $action === 'staff_month') {
    $teachers = $tdb->query('SELECT id, name, name_mr, role, class_assigned, section, phone FROM teachers WHERE COALESCE(is_active,1) = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if ($action === 'staff') {
        $date = hn_date(native_str($in, 'date'));
        $q = $tdb->prepare('SELECT teacher_id, status FROM staff_daily_attendance WHERE date = ?');
        $q->execute([$date]);
        $map = [];
        foreach ($q as $r) $map[(int) $r['teacher_id']] = $r['status'];
        foreach ($teachers as &$t) {
            $t['id'] = (int) $t['id'];
            $t['status'] = $map[$t['id']] ?? 'P';
            $t['marked'] = isset($map[$t['id']]);
        }
        unset($t);
        apiSuccess(['date' => $date, 'teachers' => $teachers]);
    }
    $month = hn_month(native_str($in, 'month'));
    $q = $tdb->prepare('SELECT teacher_id, date, status FROM staff_daily_attendance WHERE substr(date,1,7) = ?');
    $q->execute([$month]);
    $map = [];
    foreach ($q as $r) $map[(int) $r['teacher_id']][(int) substr($r['date'], 8, 2)] = $r['status'];
    foreach ($teachers as &$t) {
        $m = $map[(int) $t['id']] ?? [];
        $t['id'] = (int) $t['id'];
        $t['days'] = (object) $m;
        $t['present'] = count(array_filter($m, fn($v) => $v === 'P'));
        $t['absent'] = count(array_filter($m, fn($v) => $v === 'A'));
        $t['leave'] = count(array_filter($m, fn($v) => $v === 'L'));
    }
    unset($t);
    apiSuccess(['month' => $month, 'days_in_month' => (int) date('t', strtotime($month . '-01')), 'teachers' => $teachers]);
}

if ($action === 'staff_save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiError('Method not allowed', 405);
    }
    $date = hn_date(native_str($in, 'date'));
    $rows = native_list($in, 'rows');
    $st = $tdb->prepare('INSERT INTO staff_daily_attendance (teacher_id, date, status, updated_at) VALUES (?,?,?,?) ON CONFLICT(teacher_id, date) DO UPDATE SET status = excluded.status, updated_at = excluded.updated_at');
    $n = 0;
    $tdb->beginTransaction();
    foreach ($rows as $r) {
        $tid = (int) ($r['teacher_id'] ?? 0);
        if ($tid <= 0) continue;
        $st->execute([$tid, $date, hn_status((string) ($r['status'] ?? 'P')), date('Y-m-d H:i:s')]);
        $n++;
    }
    $tdb->commit();
    apiSuccess(['saved' => $n, 'date' => $date], 'कर्मचारी हजेरी जतन झाली');
}

apiError('Unknown action', 400);
