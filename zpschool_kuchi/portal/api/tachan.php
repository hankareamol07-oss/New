<?php
/**
 * GET & POST /api/tachan.php
 * Native Lesson Planning (दैनिक टाचण) API for Android app.
 *
 * GET: ?date=YYYY-MM-DD&grade=X
 * Returns: list of lesson plans for that day, subject list, suvichar
 *
 * POST: Add new lesson plan
 * Body: {
 *   date: "YYYY-MM-DD",
 *   grade: "4",
 *   period: 1,
 *   subject: "मराठी",
 *   topic: "झाड",
 *   learning_outcome: "...",
 *   materials: "चित्र, तक्ता",
 *   eval_tool: "प्रश्नोत्तरे",
 *   homework: "..."
 * }
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$schoolId = getAuthSchoolId();
$tdb = getTenantDB($schoolId);

if (!$tdb) {
    apiError('School database not available', 500);
}

// ─── GET: Fetch Lesson Plans ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = trim($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $grade = trim($_GET['grade'] ?? '');

    try {
        $tdb->exec("CREATE TABLE IF NOT EXISTS lesson_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_id INTEGER,
            teacher_id INTEGER DEFAULT 0,
            date TEXT NOT NULL,
            period INTEGER DEFAULT 1,
            class_id INTEGER DEFAULT 0,
            grade TEXT DEFAULT '',
            subject TEXT NOT NULL,
            topic TEXT NOT NULL,
            objectives TEXT,
            activities TEXT,
            eval_tool TEXT,
            materials TEXT,
            learning_outcome TEXT,
            homework TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $where = ["date = ?"];
        $params = [$date];

        if (!empty($grade)) {
            $where[] = "grade = ?";
            $params[] = $grade;
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $tdb->prepare(
            "SELECT * FROM lesson_plans
             WHERE $whereSql
             ORDER BY period ASC, id ASC"
        );
        $stmt->execute($params);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($plans as &$p) {
            $p['id'] = (int)$p['id'];
            $p['period'] = (int)($p['period'] ?? 1);
        }

        // Standard subjects
        $subjects = ['मराठी', 'गणित', 'इंग्रजी', 'परिसर अभ्यास', 'हिंदी', 'कला', 'कार्यान्वय', 'शारीरिक शिक्षण'];

        apiSuccess([
            'date' => $date,
            'plans' => $plans,
            'subjects' => $subjects
        ]);
    } catch (Exception $e) {
        apiError('टाचण माहिती मिळवताना त्रुटी: ' . $e->getMessage(), 500);
    }
}

// ─── POST: Add New Lesson Plan ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();

    $date            = trim($body['date'] ?? date('Y-m-d'));
    $grade           = trim($body['grade'] ?? '1');
    $period          = (int)($body['period'] ?? 1);
    $subject         = trim($body['subject'] ?? '');
    $topic           = trim($body['topic'] ?? '');
    $learningOutcome = trim($body['learning_outcome'] ?? '');
    $materials       = trim($body['materials'] ?? '');
    $evalTool        = trim($body['eval_tool'] ?? '');
    $homework        = trim($body['homework'] ?? '');
    $activities      = trim($body['activities'] ?? '');

    if (empty($subject) || empty($topic)) {
        apiError('विषय आणि घटक/उपघटक आवश्यक आहेत');
    }

    try {
        $stmt = $tdb->prepare(
            "INSERT INTO lesson_plans (
                school_id, date, grade, period, subject, topic,
                learning_outcome, materials, eval_tool, homework, activities
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $schoolId, $date, $grade, $period, $subject, $topic,
            $learningOutcome, $materials, $evalTool, $homework, $activities
        ]);

        $id = (int)$tdb->lastInsertId();

        apiSuccess([
            'id' => $id,
            'date' => $date,
            'grade' => $grade,
            'subject' => $subject,
            'topic' => $topic
        ], 'दैनिक टाचण यशस्वीरित्या जतन झाले!');
    } catch (Exception $e) {
        apiError('टाचण जतन करताना त्रुटी: ' . $e->getMessage(), 500);
    }
}
