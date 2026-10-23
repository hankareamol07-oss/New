<?php
/**
 * GET & POST /api/fee.php
 * Native Fee Management (फी व्यवस्थापन) API for Android app.
 *
 * GET:
 *   Returns fee overview (total collected, pending) and list of students with fee status
 *
 * POST:
 *   Collect fee payment from student
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$schoolId = getAuthSchoolId();
$tdb = getTenantDB($schoolId);

if (!$tdb) {
    apiError('School database not available', 500);
}

// ─── GET: Fee Overview & Student Dues ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $grade = trim($_GET['grade'] ?? '');

    try {
        $tdb->exec("CREATE TABLE IF NOT EXISTS fee_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_id INTEGER,
            student_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            paid_on TEXT NOT NULL,
            mode TEXT NOT NULL DEFAULT 'Cash',
            fee_type TEXT NOT NULL DEFAULT 'सामान्य फी',
            receipt_no TEXT NOT NULL,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $tdb->exec("CREATE TABLE IF NOT EXISTS fee_structures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_id INTEGER,
            grade TEXT NOT NULL,
            amount REAL NOT NULL DEFAULT 500.0,
            fee_name TEXT DEFAULT 'वार्षिक फी'
        )");

        // Get total collected
        $totalCollected = (float)$tdb->query("SELECT COALESCE(SUM(amount), 0) FROM fee_payments")->fetchColumn();

        // Query students with total paid
        $where = ['1=1'];
        $params = [];
        if (!empty($grade)) {
            $where[] = 's.grade = ?';
            $params[] = $grade;
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $tdb->prepare(
            "SELECT s.id, s.name, s.name_mr, s.roll_no, s.grade, s.section,
                    COALESCE(SUM(p.amount), 0) as paid_amount
             FROM students s
             LEFT JOIN fee_payments p ON p.student_id = s.id
             WHERE $whereSql
             GROUP BY s.id
             ORDER BY s.grade ASC, CAST(s.roll_no AS INTEGER) ASC"
        );
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $standardFee = 1000.0; // Default standard yearly fee
        $totalExpected = count($students) * $standardFee;
        $totalPending = max(0, $totalExpected - $totalCollected);

        foreach ($students as &$s) {
            $s['id'] = (int)$s['id'];
            $paid = (float)$s['paid_amount'];
            $s['paid_amount'] = $paid;
            $s['total_fee'] = $standardFee;
            $s['pending_amount'] = max(0, $standardFee - $paid);
            $s['status'] = ($paid >= $standardFee) ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        }

        apiSuccess([
            'summary' => [
                'total_expected' => $totalExpected,
                'total_collected' => $totalCollected,
                'total_pending' => $totalPending,
                'total_students' => count($students)
            ],
            'students' => $students
        ]);

    } catch (Exception $e) {
        apiError('फी माहिती मिळवताना त्रुटी: ' . $e->getMessage(), 500);
    }
}

// ─── POST: Collect Fee ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();

    $studentId = (int)($body['student_id'] ?? 0);
    $amount    = (float)($body['amount'] ?? 0);
    $mode      = trim($body['mode'] ?? 'रोख (Cash)');
    $feeType   = trim($body['fee_type'] ?? 'वार्षिक शैक्षणिक फी');
    $paidOn    = trim($body['paid_on'] ?? date('Y-m-d'));
    $remarks   = trim($body['remarks'] ?? '');

    if ($studentId <= 0 || $amount <= 0) {
        apiError('वैध विद्यार्थी व रक्कम निवडा');
    }

    $receiptNo = 'RCP-' . date('ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    try {
        $stmt = $tdb->prepare(
            "INSERT INTO fee_payments (
                school_id, student_id, amount, paid_on, mode, fee_type, receipt_no, remarks
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $schoolId, $studentId, $amount, $paidOn, $mode, $feeType, $receiptNo, $remarks
        ]);

        apiSuccess([
            'receipt_no' => $receiptNo,
            'amount' => $amount,
            'paid_on' => $paidOn,
            'mode' => $mode
        ], 'फी यशस्वीरित्या जमा झाली! पावती क्र: ' . $receiptNo);

    } catch (Exception $e) {
        apiError('फी जमा करताना त्रुटी: ' . $e->getMessage(), 500);
    }
}
