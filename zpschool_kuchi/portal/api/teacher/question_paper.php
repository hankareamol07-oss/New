<?php
/**
 * GET / POST /api/teacher/question_paper.php
 * Question Paper Generator - 61,000+ Question Bank
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$method = $_SERVER['REQUEST_METHOD'];
$in = getJsonInput();

$dbFile = DATA_DIR . '/exam_paper.sqlite';
if (!file_exists($dbFile)) {
    apiError('प्रश्नपत्रिका डेटाबेस आढळला नाही.', 500);
}

$sqlite = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if ($method === 'GET') {
    $action = $in['action'] ?? 'metadata';

    if ($action === 'metadata') {
        // Return available standards and subjects
        $standards = $sqlite->query("SELECT standard_id, name, medium FROM ep_standards ORDER BY sort_order")->fetchAll();
        $subjects = $sqlite->query("SELECT subject_id, standard_id, name FROM ep_subjects ORDER BY name")->fetchAll();
        apiSuccess(['standards' => $standards, 'subjects' => $subjects], 'मेटाडेटा लोड झाला.');
    }

    if ($action === 'search') {
        $std = (int)($in['std'] ?? 1);
        $subject = trim((string)($in['subject'] ?? ''));
        $q = trim((string)($in['q'] ?? ''));
        $limit = min(50, max(5, (int)($in['limit'] ?? 20)));

        $sql = "SELECT bq_id, standard, subject, text, instruction, qtype, options_json, answer 
                FROM ep_book_questions 
                WHERE standard = ?";
        $params = [$std];

        if (!empty($subject)) {
            $sql .= " AND subject LIKE ?";
            $params[] = "%$subject%";
        }
        if (!empty($q)) {
            $sql .= " AND text LIKE ?";
            $params[] = "%$q%";
        }

        $sql .= " ORDER BY RANDOM() LIMIT $limit";

        $stmt = $sqlite->prepare($sql);
        $stmt->execute($params);
        $questions = $stmt->fetchAll();

        foreach ($questions as &$q_item) {
            if (!empty($q_item['options_json'])) {
                $q_item['options'] = json_decode($q_item['options_json'], true);
            }
        }

        apiSuccess(['total' => count($questions), 'questions' => $questions], 'प्रश्न शोध निकाल.');
    }
}

if ($method === 'POST') {
    // Generate a structured question paper
    $title = trim((string)($in['title'] ?? 'सराव चाचणी परीक्षा'));
    $std = (int)($in['std'] ?? 1);
    $subject = trim((string)($in['subject'] ?? 'मराठी'));
    $total_marks = (int)($in['total_marks'] ?? 20);
    $count = min(30, max(5, (int)($in['question_count'] ?? 10)));

    $stmt = $sqlite->prepare("
        SELECT bq_id, standard, subject, text, instruction, qtype, options_json, answer 
        FROM ep_book_questions 
        WHERE standard = ? AND subject LIKE ?
        ORDER BY RANDOM() LIMIT ?
    ");
    $stmt->execute([$std, "%$subject%", $count]);
    $items = $stmt->fetchAll();

    foreach ($items as &$it) {
        if (!empty($it['options_json'])) {
            $it['options'] = json_decode($it['options_json'], true);
        }
    }

    apiSuccess([
        'school_name' => SCHOOL_NAME_MR,
        'title'       => $title,
        'std'         => $std,
        'subject'     => $subject,
        'total_marks' => $total_marks,
        'academic_year'=> ACADEMIC_YEAR,
        'questions'   => $items,
    ], 'प्रश्नपत्रिका यशस्वीरित्या तयार झाली!');
}
