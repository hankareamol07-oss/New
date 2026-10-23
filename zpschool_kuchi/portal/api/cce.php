<?php
/**
 * GET & POST /api/cce.php
 * Native CCE & HPC Evaluation (सातत्यपूर्ण सर्वंकष मूल्यमापन) API for Android app.
 *
 * GET: ?grade=4&subject=मराठी&term=1&tool=आकारिक_१
 * Returns: list of students with entered marks & grades
 *
 * POST: Save marks
 * Body: {
 *   grade: "4",
 *   subject: "मराठी",
 *   term: 1,
 *   tool: "आकारिक_१",
 *   max_marks: 20,
 *   marks: [ { student_id: 1, marks: 18 }, ... ]
 * }
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$schoolId = getAuthSchoolId();
$tdb = getTenantDB($schoolId);

if (!$tdb) {
    apiError('School database not available', 500);
}

// ─── GET: Fetch Marks ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $grade   = trim($_GET['grade'] ?? '1');
    $subject = trim($_GET['subject'] ?? 'मराठी');
    $term    = (int)($_GET['term'] ?? 1);
    $tool    = trim($_GET['tool'] ?? 'आकारिक_१');

    try {
        $tdb->exec("CREATE TABLE IF NOT EXISTS cce_marks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_id INTEGER,
            student_id INTEGER NOT NULL,
            grade TEXT NOT NULL,
            subject TEXT NOT NULL,
            term INTEGER DEFAULT 1,
            tool TEXT NOT NULL,
            marks REAL DEFAULT 0,
            max_marks REAL DEFAULT 20,
            grade_point TEXT DEFAULT '',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(student_id, subject, term, tool)
        )");

        $stmt = $tdb->prepare(
            "SELECT s.id, s.name, s.name_mr, s.roll_no, s.grade, s.section,
                    COALESCE(m.marks, 0) as marks,
                    COALESCE(m.max_marks, 20) as max_marks,
                    COALESCE(m.grade_point, '') as grade_point
             FROM students s
             LEFT JOIN cce_marks m ON m.student_id = s.id
                AND m.subject = ? AND m.term = ? AND m.tool = ?
             WHERE s.grade = ?
             ORDER BY CAST(s.roll_no AS INTEGER) ASC, s.name ASC"
        );
        $stmt->execute([$subject, $term, $tool, $grade]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as &$s) {
            $s['id'] = (int)$s['id'];
            $s['marks'] = (float)$s['marks'];
            $s['max_marks'] = (float)$s['max_marks'];
            if (empty($s['grade_point']) && $s['marks'] > 0) {
                $pct = ($s['marks'] / max(1, $s['max_marks'])) * 100;
                $s['grade_point'] = calculateGrade($pct);
            }
        }

        $subjects = ['मराठी', 'गणित', 'इंग्रजी', 'परिसर अभ्यास', 'हिंदी', 'कला', 'कार्यान्वय', 'शारीरिक शिक्षण'];
        $tools = [
            'आकारिक_१ (दैनंदिन निरीक्षण)',
            'आकारिक_२ (तोंडी काम)',
            'आकारिक_३ (प्रात्यक्षिक / प्रयोग)',
            'आकारिक_४ (उपक्रम / प्रकल्प)',
            'आकारिक_५ (चाचणी)',
            'संकलित (सत्र परीक्षा)'
        ];

        apiSuccess([
            'grade' => $grade,
            'subject' => $subject,
            'term' => $term,
            'tool' => $tool,
            'subjects' => $subjects,
            'tools' => $tools,
            'students' => $students
        ]);

    } catch (Exception $e) {
        apiError('मूल्यमापन माहिती मिळवताना त्रुटी: ' . $e->getMessage(), 500);
    }
}

// ─── POST: Save Marks ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();

    $grade    = trim($body['grade'] ?? '1');
    $subject  = trim($body['subject'] ?? 'मराठी');
    $term     = (int)($body['term'] ?? 1);
    $tool     = trim($body['tool'] ?? 'आकारिक_१');
    $maxMarks = (float)($body['max_marks'] ?? 20);
    $marksList = $body['marks'] ?? [];

    if (empty($marksList) || !is_array($marksList)) {
        apiError('विद्यार्थ्यांचे गुण पाठवा');
    }

    try {
        $stmt = $tdb->prepare(
            "INSERT INTO cce_marks (
                school_id, student_id, grade, subject, term, tool, marks, max_marks, grade_point
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(student_id, subject, term, tool) DO UPDATE SET
                marks = excluded.marks,
                max_marks = excluded.max_marks,
                grade_point = excluded.grade_point,
                updated_at = CURRENT_TIMESTAMP"
        );

        $savedCount = 0;
        foreach ($marksList as $item) {
            $studentId = (int)($item['student_id'] ?? 0);
            $marks = (float)($item['marks'] ?? 0);
            $pct = ($marks / max(1, $maxMarks)) * 100;
            $gradePoint = calculateGrade($pct);

            if ($studentId > 0) {
                $stmt->execute([
                    $schoolId, $studentId, $grade, $subject, $term, $tool,
                    $marks, $maxMarks, $gradePoint
                ]);
                $savedCount++;
            }
        }

        apiSuccess([
            'saved_count' => $savedCount
        ], 'मूल्यमापन गुण यशस्वीरित्या जतन झाले!');

    } catch (Exception $e) {
        apiError('गुण जतन करताना त्रुटी: ' . $e->getMessage(), 500);
    }
}

function calculateGrade($pct) {
    if ($pct >= 91) return 'A1';
    if ($pct >= 81) return 'A2';
    if ($pct >= 71) return 'B1';
    if ($pct >= 61) return 'B2';
    if ($pct >= 51) return 'C1';
    if ($pct >= 41) return 'C2';
    if ($pct >= 33) return 'D';
    return 'E';
}
