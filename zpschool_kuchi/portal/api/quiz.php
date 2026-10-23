<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/quiz/quiz_helper.php';

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$studentId = (int)($_SESSION['student_id'] ?? $_SESSION['user_id'] ?? 0);
$studentStd = (int)($_SESSION['std'] ?? $_SESSION['standard'] ?? 0);
$studentName = $_SESSION['name'] ?? 'विद्यार्थी';
$studentNameMr = $_SESSION['name_mr'] ?? $studentName;
$division = $_SESSION['division'] ?? $_SESSION['section'] ?? 'A';

try {
    switch ($action) {
        case 'list':
            $std = (int)($_GET['std'] ?? $studentStd);
            if ($std <= 0) {
                // Default to 6 if not logged in or specified
                $std = 6;
            }
            $quizzes = getQuizzesForStudent($std, $studentId);
            echo json_encode([
                'success' => true,
                'std' => $std,
                'student_id' => $studentId,
                'quizzes' => $quizzes
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'dashboard':
            $std = (int)($_GET['std'] ?? $studentStd);
            if ($std <= 0) $std = 6;
            $quizzes = getQuizzesForStudent($std, $studentId);

            $subjects = [];
            foreach ($quizzes as $q) {
                $subName = $q['subject_name'];
                if (!isset($subjects[$subName])) {
                    $subjects[$subName] = [
                        'name' => $subName,
                        'icon' => $q['subject_icon'] ?: 'bi-book-half',
                        'color' => $q['subject_color'] ?: '#2563eb',
                        'topics' => []
                    ];
                }
                $subjects[$subName]['topics'][] = $q;
            }

            echo json_encode([
                'success' => true,
                'std' => $std,
                'student_id' => $studentId,
                'subjects' => array_values($subjects)
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'get':
            $quizId = (int)($_GET['id'] ?? 0);
            if ($quizId <= 0) {
                echo json_encode(['success' => false, 'error' => 'अवैध क्विझ आयडी.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $quiz = getQuizWithQuestions($quizId);
            if (!$quiz) {
                echo json_encode(['success' => false, 'error' => 'क्विझ सापडले नाही.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Remove correct_option and explanation before sending to client for the test
            $sanitizedQuestions = array_map(function($q) {
                return [
                    'id' => $q['id'],
                    'question_text' => $q['question_text'],
                    'option_a' => $q['option_a'],
                    'option_b' => $q['option_b'],
                    'option_c' => $q['option_c'],
                    'option_d' => $q['option_d']
                ];
            }, $quiz['questions']);

            echo json_encode([
                'success' => true,
                'quiz' => [
                    'id' => $quiz['id'],
                    'quiz_code' => $quiz['quiz_code'],
                    'std' => $quiz['std'],
                    'subject_name' => $quiz['subject_name'],
                    'title' => $quiz['title'],
                    'description' => $quiz['description'],
                    'time_limit_minutes' => $quiz['time_limit_minutes'],
                    'pass_percentage' => $quiz['pass_percentage'],
                    'total_questions' => count($sanitizedQuestions),
                    'questions' => $sanitizedQuestions
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'submit':
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;

            $quizId = (int)($data['quiz_id'] ?? 0);
            $answers = $data['answers'] ?? [];
            $timeSpent = (int)($data['time_spent'] ?? 0);

            if ($quizId <= 0 || empty($answers)) {
                echo json_encode(['success' => false, 'error' => 'उत्तरे आवश्यक आहेत.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = recordQuizAttempt(
                $quizId,
                $studentId,
                $studentName,
                $studentNameMr,
                $studentStd > 0 ? $studentStd : 6,
                $division,
                $answers,
                $timeSpent
            );

            echo json_encode([
                'success' => true,
                'result' => $result
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'अवैध क्रिया.'], JSON_UNESCAPED_UNICODE);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
