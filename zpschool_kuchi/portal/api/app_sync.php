<?php
/**
 * /api/app_sync.php — offline-first sync for the Flutter app.
 *
 * GET  ?action=meta                         table list, row counts, last change
 * GET  ?action=pull&tables=a,b&since=TS      rows changed since TS (+ all ids, to detect deletes)
 * POST {action:"push", changes:[{table, op:"upsert"|"delete", id, client_id, base_updated_at, force, row:{}}]}
 *
 * Works only on the school's existing tenant SQLite tables; no schema changes.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

const SYNC_PULL_TABLES = [
    'school_info', 'classes', 'teachers', 'students', 'attendance', 'holidays', 'calendar_events',
    'timetable', 'teacher_timetable', 'lesson_plans', 'annual_plans', 'tachan_prefs',
    'cce_subjects', 'cce_subject_teachers', 'cce_marks', 'cce_student_notes', 'cce_student_outcomes', 'cce_student_extra',
    'mdm_students', 'mdm_daily', 'mdm_entries', 'mdm_stock', 'mdm_bmi', 'mdm_menus', 'mdm_menu_items', 'mdm_holidays',
];
const SYNC_PUSH_TABLES = [
    'teachers', 'students', 'attendance', 'lesson_plans', 'cce_marks', 'cce_student_notes', 'cce_student_outcomes', 'cce_student_extra',
    'mdm_daily', 'mdm_entries', 'mdm_bmi',
];
const SYNC_DELETE_TABLES = ['attendance', 'lesson_plans', 'mdm_entries'];
const SYNC_PAGE = 5000;

$schoolId = getAuthSchoolId();
$tdb = getSchoolTenantDB($schoolId);
if (!$tdb) {
    apiError('शाळेचा डेटाबेस उपलब्ध नाही', 500);
}
$tdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function sync_tables(PDO $db): array
{
    return $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
}

function sync_columns(PDO $db, string $table): array
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cols = [];
        foreach ($db->query('PRAGMA table_info("' . $table . '")') as $r) {
            if (!preg_match('/pass|token|secret|otp/i', $r['name'])) {
                $cols[] = $r['name'];
            }
        }
        $cache[$table] = $cols;
    }
    return $cache[$table];
}

/** Find an existing row that matches one of the table's UNIQUE indexes. */
function sync_find_unique(PDO $db, string $table, array $row): int
{
    foreach ($db->query('PRAGMA index_list("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC) as $ix) {
        if ((int) $ix['unique'] !== 1) continue;
        $cols = array_column($db->query('PRAGMA index_info("' . $ix['name'] . '")')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!$cols || array_diff($cols, array_keys($row))) continue;
        $where = implode(' AND ', array_map(fn($c) => "\"$c\" = ?", $cols));
        $st = $db->prepare("SELECT id FROM \"$table\" WHERE $where LIMIT 1");
        $st->execute(array_map(fn($c) => $row[$c], $cols));
        $id = (int) $st->fetchColumn();
        if ($id > 0) return $id;
    }
    return 0;
}

function sync_stamp_col(array $cols): ?string
{
    foreach (['updated_at', 'modified_at', 'created_at'] as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

$in = array_merge($_GET, getRequestBody() ?: []);
$action = (string) ($in['action'] ?? 'meta');
$existing = sync_tables($tdb);
$now = date('Y-m-d H:i:s');

// Auto-hydrate classes & students from MySQL into tenant SQLite if empty
try {
    $hasStudents = in_array('students', $existing, true) ? (int) $tdb->query('SELECT COUNT(*) FROM "students"')->fetchColumn() : 0;
    $hasClasses  = in_array('classes', $existing, true) ? (int) $tdb->query('SELECT COUNT(*) FROM "classes"')->fetchColumn() : 0;
    if ($hasStudents === 0 || $hasClasses === 0) {
        require_once __DIR__ . '/../includes/cce_functions.php';
        if ($hasClasses === 0 && function_exists('sync_classes_to_sqlite')) {
            sync_classes_to_sqlite($schoolId);
        }
        if ($hasStudents === 0 && function_exists('sync_students_to_sqlite')) {
            sync_students_to_sqlite($schoolId);
        }
    }
} catch (Throwable $ignore) {}

if ($action === 'meta') {
    $out = [];
    foreach (SYNC_PULL_TABLES as $t) {
        if (!in_array($t, $existing, true)) continue;
        $cols = sync_columns($tdb, $t);
        $stamp = sync_stamp_col($cols);
        $out[] = [
            'table' => $t,
            'columns' => $cols,
            'rows' => (int) $tdb->query('SELECT COUNT(*) FROM "' . $t . '"')->fetchColumn(),
            'last_change' => $stamp ? $tdb->query('SELECT MAX("' . $stamp . '") FROM "' . $t . '"')->fetchColumn() : null,
            'writable' => in_array($t, SYNC_PUSH_TABLES, true),
            'deletable' => in_array($t, SYNC_DELETE_TABLES, true),
        ];
    }
    apiSuccess(['server_time' => $now, 'tables' => $out]);
}

if ($action === 'pull') {
    $want = $in['tables'] ?? '';
    $want = is_array($want) ? $want : array_filter(explode(',', (string) $want));
    $want = $want ? array_values(array_intersect(SYNC_PULL_TABLES, $want)) : SYNC_PULL_TABLES;
    $since = trim((string) ($in['since'] ?? ''));
    $offset = max(0, (int) ($in['offset'] ?? 0));
    $data = [];
    foreach ($want as $t) {
        if (!in_array($t, $existing, true)) continue;
        $cols = sync_columns($tdb, $t);
        $stamp = sync_stamp_col($cols);
        $select = implode(',', array_map(fn($c) => '"' . $c . '"', $cols));
        $sql = "SELECT $select FROM \"$t\"";
        $params = [];
        if ($since !== '' && $stamp) {
            $sql .= " WHERE \"$stamp\" > ? OR \"$stamp\" IS NULL";
            $params[] = $since;
        }
        $sql .= in_array('id', $cols, true) ? ' ORDER BY id' : '';
        $sql .= ' LIMIT ' . (SYNC_PAGE + 1) . ' OFFSET ' . $offset;
        $st = $tdb->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $more = count($rows) > SYNC_PAGE;
        if ($more) array_pop($rows);
        $entry = ['rows' => $rows, 'has_more' => $more, 'full' => $since === '' || !$stamp];
        if ($since !== '' && in_array('id', $cols, true)) {
            $entry['all_ids'] = array_map('intval', $tdb->query("SELECT id FROM \"$t\"")->fetchAll(PDO::FETCH_COLUMN));
        }
        $data[$t] = $entry;
    }
    apiSuccess(['server_time' => $now, 'data' => $data]);
}

if ($action === 'push') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiError('Method not allowed', 405);
    }
    $changes = $in['changes'] ?? [];
    if (!is_array($changes) || count($changes) > 2000) {
        apiError('अवैध बदल यादी');
    }
    $results = [];
    foreach ($changes as $c) {
        $t = (string) ($c['table'] ?? '');
        $op = (string) ($c['op'] ?? 'upsert');
        $cid = $c['client_id'] ?? null;
        $id = (int) ($c['id'] ?? 0);
        try {
            if (!in_array($t, SYNC_PUSH_TABLES, true) || !in_array($t, $existing, true)) {
                throw new RuntimeException('table not writable');
            }
            $cols = sync_columns($tdb, $t);
            $stamp = in_array('updated_at', $cols, true) ? 'updated_at' : null;

            if ($op === 'delete') {
                if (!in_array($t, SYNC_DELETE_TABLES, true) || $id <= 0) {
                    throw new RuntimeException('delete not allowed');
                }
                $tdb->prepare("DELETE FROM \"$t\" WHERE id = ?")->execute([$id]);
                if (function_exists('syncTenantDeleteToMySQL')) {
                    syncTenantDeleteToMySQL($t, ['id' => $id, 'school_id' => $schoolId]);
                }
                $results[] = ['client_id' => $cid, 'ok' => true, 'id' => $id, 'deleted' => true];
                continue;
            }

            $row = is_array($c['row'] ?? null) ? $c['row'] : [];
            $row = array_intersect_key($row, array_flip($cols));
            unset($row['id']);
            if (in_array('school_id', $cols, true)) {
                $row['school_id'] = $schoolId;
            }
            if ($stamp) {
                $row[$stamp] = $now;
            }
            if (!$row) {
                throw new RuntimeException('empty row');
            }

            $current = null;
            if ($id <= 0) {
                $id = sync_find_unique($tdb, $t, $row);
            }
            if ($id > 0) {
                $st = $tdb->prepare("SELECT * FROM \"$t\" WHERE id = ?");
                $st->execute([$id]);
                $current = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($current) {
                $base = (string) ($c['base_updated_at'] ?? '');
                if ($stamp && $base !== '' && empty($c['force']) && (string) $current[$stamp] > $base) {
                    $results[] = ['client_id' => $cid, 'ok' => false, 'id' => $id, 'conflict' => true,
                        'server_row' => array_intersect_key($current, array_flip($cols))];
                    continue;
                }
                $set = implode(',', array_map(fn($k) => "\"$k\" = ?", array_keys($row)));
                $tdb->prepare("UPDATE \"$t\" SET $set WHERE id = ?")->execute([...array_values($row), $id]);
            } else {
                if (in_array('created_at', $cols, true) && empty($row['created_at'])) {
                    $row['created_at'] = $now;
                }
                $names = implode(',', array_map(fn($k) => "\"$k\"", array_keys($row)));
                $marks = implode(',', array_fill(0, count($row), '?'));
                $tdb->prepare("INSERT INTO \"$t\" ($names) VALUES ($marks)")->execute(array_values($row));
                $id = (int) $tdb->lastInsertId();
            }
            if (function_exists('syncTenantRecordToMySQL')) {
                syncTenantRecordToMySQL($t, array_merge(['id' => $id], $row), ['id']);
            }
            $results[] = ['client_id' => $cid, 'ok' => true, 'id' => $id, 'updated_at' => $stamp ? $now : null];
        } catch (Throwable $e) {
            error_log('app_sync push: ' . $e->getMessage());
            $msg = $e instanceof PDOException ? 'save failed' : $e->getMessage();
            $results[] = ['client_id' => $cid, 'ok' => false, 'id' => $id, 'error' => $msg];
        }
    }
    apiSuccess(['server_time' => $now, 'results' => $results]);
}

apiError('Unknown action', 404);
