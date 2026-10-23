<?php
/**
 * GET & POST /api/attendance.php
 * Native Daily Attendance (हजेरी) API for Android app.
 *
 * GET: ?date=YYYY-MM-DD&class_id=X&grade=Y
 * Returns: list of students with attendance status (P/A/L), summary counts
 *
 * POST: { date: "YYYY-MM-DD", attendance: [ { student_id: 1, status: "P" }, ... ] }
 * Saves attendance records
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$schoolId = getAuthSchoolId();
$tdb = getTenantDB($schoolId);

if (!$tdb) {
    apiError('School database not available', 500);
}

// ─── GET: Fetch Attendance ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = trim($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $grade = trim($_GET['grade'] ?? '');
    $section = trim($_GET['section'] ?? '');

    $where = ['1=1'];
    $params = [$date];

    if (!empty($grade)) {
        $where[] = "s.grade = ?";
        $params[] = $grade;
    }
    if (!empty($section)) {
        $where[] = "s.section = ?";
        $params[] = $section;
    }

    $whereSql = implode(' AND ', $where);

    try {
        $stmt = $tdb->prepare(
            "SELECT s.id, s.name, s.name_mr, s.roll_no, s.grade, s.section, s.gender,
                    COALESCE(a.status, 'P') as status
             FROM students s
             LEFT JOIN daily_attendance a ON a.student_id = s.id AND a.date = ?
             WHERE $whereSql
             ORDER BY CAST(s.roll_no AS INTEGER) ASC, s.name ASC"
        );
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($students);
        $present = 0;
        $absent = 0;
        $leave = 0;

        foreach ($students as &$s) {
            $s['id'] = (int)$s['id'];
            if ($s['status'] === 'P') $present++;
            elseif ($s['status'] === 'A') $absent++;
            elseif ($s['status'] === 'L') $leave++;
        }

        apiSuccess([
            'date' => $date,
            'summary' => [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'leave' => $leave
            ],
            'students' => $students
        ]);
    } catch (Exception $e) {
        apiError('हजेरी मिळवण्यात त्रुटी: ' . $e->getMessage(), 500);
    }
}

// ─── POST: Save Attendance ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();
    $date = trim($body['date'] ?? date('Y-m-d'));
    $records = $body['attendance'] ?? [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        apiError('अवैध तारीख');
    }

    if (empty($records) || !is_array($records)) {
        apiError('हजेरी नोंदी पाठवा');
    }

    try {
        // Ensure daily_attendance table exists in tenant DB
        $tdb->exec("CREATE TABLE IF NOT EXISTS daily_attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_id INTEGER,
            student_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'P',
            UNIQUE(student_id, date)
        )");

        $stmt = $tdb->prepare(
            "INSERT INTO daily_attendance (school_id, student_id, date, status)
             VALUES (?, ?, ?, ?)
             ON CONFLICT(student_id, date) DO UPDATE SET status = excluded.status"
        );

        $savedCount = 0;
        foreach ($records as $item) {
            $studentId = (int)($item['student_id'] ?? 0);
            $status = strtoupper(trim($item['status'] ?? 'P'));
            if (!in_array($status, ['P', 'A', 'L'], true)) {
                $status = 'P';
            }

            if ($studentId > 0) {
                $stmt->execute([$schoolId, $studentId, $date, $status]);
                $savedCount++;
            }
        }

        // Also sync to MySQL if table exists
        try {
            $mysql = getDB();
            $mStmt = $mysql->prepare(
                "REPLACE INTO daily_attendance (school_id, student_id, date, status)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($records as $item) {
                $studentId = (int)($item['student_id'] ?? 0);
                $status = strtoupper(trim($item['status'] ?? 'P'));
                if (!in_array($status, ['P', 'A', 'L'], true)) $status = 'P';
                if ($studentId > 0) {
                    $mStmt->execute([$schoolId, $studentId, $date, $status]);
                }
            }
        } catch (Throwable $ignore) {
            // MySQL sync is optional/best-effort
        }

        apiSuccess([
            'saved_count' => $savedCount,
            'date' => $date
        ], 'हजेरी यशस्वीरित्या जतन झाली!');

    } catch (Exception $e) {
        apiError('हजेरी जतन करताना त्रुटी: ' . $e->getMessage(), 500);
    }
}
