<?php
/**
 * GET / POST /api/student/mcqs.php
 * Interactive MCQ Quiz Module for Students
 * Inspired by LPK Quiz Engine & Powered by 61,000+ Question Master Bank
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['student', 'teacher', 'admin']);
$db = getDB();

$studentId = ($user['role'] === 'student') ? (int)$user['linked_id'] : (int)($_GET['student_id'] ?? 0);
$st = $db->prepare("SELECT id, name, name_mr, roll_no, grade, std, section FROM students WHERE id = ? AND school_id = ?");
$st->execute([$studentId, SCHOOL_ID]);
$student = $st->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    apiError('विद्यार्थी सापडला नाही.', 404);
}

$std = (int)$student['std'];
$dbFile = DATA_DIR . '/exam_paper.sqlite';
if (!file_exists($dbFile)) {
    apiError('प्रश्नसंच उपलब्ध नाही.', 500);
}

$sqlite = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$method = $_SERVER['REQUEST_METHOD'];
$in = getJsonInput();
$action = $in['action'] ?? 'topics';

if ($method === 'GET' && $action === 'topics') {
    // Return subjects and topics available for this student's standard
    $stmt = $sqlite->prepare("
        SELECT subject, COUNT(*) as question_count 
        FROM ep_book_questions 
        WHERE standard = ? 
        GROUP BY subject 
        ORDER BY question_count DESC
    ");
    $stmt->execute([$std]);
    $subjects = $stmt->fetchAll();

    // If zero questions in ep_book_questions for this std, fallback to nearby standard or all
    if (empty($subjects)) {
        $stmt2 = $sqlite->prepare("SELECT DISTINCT subject, COUNT(*) as question_count FROM ep_book_questions GROUP BY subject");
        $stmt2->execute();
        $subjects = $stmt2->fetchAll();
    }

    apiSuccess([
        'student'  => [
            'name'  => $student['name'],
            'std'   => $std,
            'grade' => $student['grade'],
        ],
        'subjects' => $subjects,
    ], 'विषय व सराव संच लोड झाले.');
}

if ($method === 'GET' && $action === 'quiz') {
    // Generate an interactive quiz for this student's standard & subject
    $subject = trim((string)($in['subject'] ?? ''));
    $limit = min(25, max(5, (int)($in['limit'] ?? 10)));

    $sql = "SELECT bq_id, standard, subject, text as question, instruction, options_json, answer 
            FROM ep_book_questions 
            WHERE standard = ?";
    $params = [$std];

    if (!empty($subject)) {
        $sql .= " AND subject LIKE ?";
        $params[] = "%$subject%";
    }
    $sql .= " AND options_json IS NOT NULL AND options_json != '' AND options_json != '[]' 
              ORDER BY RANDOM() LIMIT $limit";

    $stmt = $sqlite->prepare($sql);
    $stmt->execute($params);
    $raw_questions = $stmt->fetchAll();

    if (empty($raw_questions)) {
        // Fallback: search questions without options_json constraint
        $fallback_sql = "SELECT bq_id, standard, subject, text as question, instruction, options_json, answer 
                         FROM ep_book_questions WHERE standard = ? ORDER BY RANDOM() LIMIT $limit";
        $fb_stmt = $sqlite->prepare($fallback_sql);
        $fb_stmt->execute([$std]);
        $raw_questions = $fb_stmt->fetchAll();
    }

    $quiz_questions = [];
    foreach ($raw_questions as $idx => $q) {
        $options = [];
        if (!empty($q['options_json'])) {
            $parsed = json_decode($q['options_json'], true);
            if (is_array($parsed)) {
                $options = array_values($parsed);
            }
        }

        // If no options stored in JSON, create standard MCQ choices
        if (empty($options)) {
            $correct = trim((string)$q['answer']) ?: 'पर्याय अ';
            $options = [$correct, 'पर्याय ब', 'पर्याय क', 'पर्याय ड'];
            shuffle($options);
        }

        // Hash token for answer verification
        $token = hash_hmac('sha256', $q['bq_id'] . ':' . trim((string)$q['answer']), AUTH_SECRET);

        $quiz_questions[] = [
            'q_num'       => $idx + 1,
            'id'          => (int)$q['bq_id'],
            'question'    => strip_tags((string)$q['question']),
            'instruction' => (string)($q['instruction'] ?? ''),
            'subject'     => $q['subject'],
            'options'     => $options,
            'token'       => $token,
        ];
    }

    apiSuccess([
        'quiz_title' => "इयत्ता {$std} वी सराव प्रश्नमंजुषा (MCQ)",
        'subject'    => $subject ?: 'सर्व विषय',
        'std'        => $std,
        'total'      => count($quiz_questions),
        'questions'  => $quiz_questions,
    ], 'प्रश्नमंजुषा लोड झाली! सुरू करा.');
}

if ($method === 'POST' && $action === 'submit') {
    // User submits: { "answers": { "123": "Option A", "124": "Option B" } }
    $answers = $in['answers'] ?? [];
    if (!is_array($answers) || empty($answers)) {
        apiError('उत्तरे पाठवणे आवश्यक आहे.', 400);
    }

    $score = 0;
    $total = count($answers);
    $review = [];

    $q_ids = array_map('intval', array_keys($answers));
    if (!empty($q_ids)) {
        $inClause = implode(',', array_fill(0, count($q_ids), '?'));
        $st = $sqlite->prepare("SELECT bq_id, text as question, answer, options_json FROM ep_book_questions WHERE bq_id IN ($inClause)");
        $st->execute($q_ids);
        $fetched = [];
        foreach ($st->fetchAll() as $row) {
            $fetched[(int)$row['bq_id']] = $row;
        }

        foreach ($answers as $qid => $user_ans) {
            $qid = (int)$qid;
            $q_row = $fetched[$qid] ?? null;
            if (!$q_row) continue;

            $correct_ans = trim((string)$q_row['answer']);
            $user_ans_trimmed = trim((string)$user_ans);

            $is_correct = (strcasecmp($user_ans_trimmed, $correct_ans) === 0);
            if ($is_correct) {
                $score++;
            }

            $review[] = [
                'question_id' => $qid,
                'question'    => strip_tags((string)$q_row['question']),
                'user_answer' => $user_ans_trimmed,
                'correct_ans' => $correct_ans,
                'is_correct'  => $is_correct,
            ];
        }
    }

    $percent = $total > 0 ? round(($score / $total) * 100, 1) : 0;
    $badge = ($percent >= 80) ? '🏆 सुवर्ण पदक (Excellent)' : (($percent >= 60) ? '🥈 रौप्य पदक (Good)' : '🥉 कांस्य पदक (Keep Trying)');

    apiSuccess([
        'score'      => $score,
        'total'      => $total,
        'percentage' => $percent,
        'badge'      => $badge,
        'review'     => $review,
    ], "अभिनंदन! आपले गुण: {$score}/{$total} ({$percent}%)");
}
