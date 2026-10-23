<?php
declare(strict_types=1);

/**
 * AI Study Bot API Endpoint
 * Zero External API Dependency — Powered 100% by local syllabus & scholarship knowledge engine
 */

require_once __DIR__ . '/../includes/knowledge_engine.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawInput = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? $rawInput['action'] ?? 'chat';

try {
    switch ($action) {
        case 'get_subjects':
            $std = (int)($_GET['std'] ?? $_POST['std'] ?? $rawInput['std'] ?? 6);
            if ($std < 1 || $std > 8) $std = 6;
            $subjects = getSubjectsForStd($std);
            echo json_encode(['success' => true, 'std' => $std, 'subjects' => $subjects], JSON_UNESCAPED_UNICODE);
            exit;

        case 'get_topics':
        case 'get_chapters':
            $std = (int)($_GET['std'] ?? $_POST['std'] ?? $rawInput['std'] ?? 6);
            if ($std < 1 || $std > 8) $std = 6;
            $subject = trim((string)($_GET['subject'] ?? $_POST['subject'] ?? $rawInput['subject'] ?? ''));
            $topics = getTopicsForSubject($std, $subject);
            echo json_encode([
                'success' => true,
                'std' => $std,
                'std_label' => formatMarathiStd($std),
                'subject' => $subject,
                'topics' => $topics
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'get_quiz':
            $std = (int)($_GET['std'] ?? $_POST['std'] ?? $rawInput['std'] ?? 6);
            $subject = trim((string)($_GET['subject'] ?? $_POST['subject'] ?? $rawInput['subject'] ?? ''));
            $chapterId = (int)($_GET['chapter_id'] ?? $_POST['chapter_id'] ?? $rawInput['chapter_id'] ?? 0);
            $count = (int)($_GET['count'] ?? $_POST['count'] ?? $rawInput['count'] ?? 5);
            $quiz = generateLocalQuiz($std, $subject, $chapterId, $count);
            echo json_encode(['success' => true, 'quiz' => $quiz], JSON_UNESCAPED_UNICODE);
            exit;

        case 'chat':
        default:
            $msg = trim((string)($_POST['msg'] ?? $_POST['message'] ?? $rawInput['msg'] ?? $rawInput['message'] ?? ''));
            $std = (int)($_POST['std'] ?? $rawInput['std'] ?? $_GET['std'] ?? 6);
            $subject = trim((string)($_POST['subject'] ?? $rawInput['subject'] ?? $_GET['subject'] ?? ''));
            $studentName = trim((string)($_POST['name'] ?? $rawInput['name'] ?? $_GET['name'] ?? 'विद्यार्थी'));

            if (empty($msg)) {
                echo json_encode(['success' => false, 'error' => 'कृपया प्रश्न टाइप करा.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($std < 1 || $std > 8) $std = 6;

            $result = handleBotChat($std, $msg, $subject, $studentName);
            echo json_encode([
                'success' => true,
                'reply' => $result['reply'],
                'chips' => $result['chips'] ?? [],
                'quiz_data' => $result['quiz_data'] ?? null
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
