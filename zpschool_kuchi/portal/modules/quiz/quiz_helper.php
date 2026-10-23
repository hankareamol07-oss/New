<?php
declare(strict_types=1);

/**
 * SQLite Quiz Database Helper
 * Connects to portal/data/quiz.sqlite
 */

function getQuizDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dbPath = __DIR__ . '/../../data/quiz.sqlite';
        if (!file_exists($dbPath)) {
            // Also check relative to portal root
            $dbPath = __DIR__ . '/../data/quiz.sqlite';
        }
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

/**
 * Fetch quizzes for a specific standard with student's best score
 */
function getQuizzesForStudent(int $std, int $studentId, string $medium = 'Marathi'): array {
    $db = getQuizDB();
    $stmt = $db->prepare("
        SELECT q.*, s.icon as subject_icon, s.name_mr as subject_name_mr, s.color as subject_color,
               (SELECT MAX(score) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.student_id = ?) as best_score,
               (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.student_id = ?) as attempt_count
        FROM quizzes q
        LEFT JOIN subjects s ON q.subject_id = s.id
        WHERE q.std = ? 
          AND (
              q.medium = 'Marathi' 
              OR q.medium = 'Hindi' 
              OR (q.medium = 'English' AND (q.subject_name LIKE '%English%' OR s.name_mr LIKE '%इंग्रजी%'))
          )
          AND q.is_active = 1
        ORDER BY q.id ASC
    ");
    $stmt->execute([$studentId, $studentId, $std]);
    return $stmt->fetchAll();
}

/**
 * Get quiz details with questions
 */
function getQuizWithQuestions(int $quizId): ?array {
    $db = getQuizDB();
    $stmt = $db->prepare("SELECT * FROM quizzes WHERE id = ? AND is_active = 1");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    if (!$quiz) return null;

    $qStmt = $db->prepare("
        SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation
        FROM quiz_questions
        WHERE quiz_id = ?
        ORDER BY id ASC
    ");
    $qStmt->execute([$quizId]);
    $quiz['questions'] = $qStmt->fetchAll();
    return $quiz;
}

/**
 * Record a student quiz attempt
 */
function recordQuizAttempt(int $quizId, int $studentId, string $studentName, string $studentNameMr, int $std, string $division, array $answers, int $timeSpent = 0): array {
    $quiz = getQuizWithQuestions($quizId);
    if (!$quiz) {
        throw new InvalidArgumentException("Quiz not found");
    }

    $questions = $quiz['questions'];
    $totalQuestions = count($questions);
    $score = 0;
    $review = [];

    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        $userAns = strtoupper(trim((string)($answers[$qid] ?? '')));
        $correctAns = strtoupper(trim((string)$q['correct_option']));
        $isCorrect = ($userAns !== '' && $userAns === $correctAns);

        if ($isCorrect) {
            $score++;
        }

        $review[] = [
            'question_id' => $qid,
            'question_text' => $q['question_text'],
            'option_a' => $q['option_a'],
            'option_b' => $q['option_b'],
            'option_c' => $q['option_c'],
            'option_d' => $q['option_d'],
            'user_answer' => $userAns,
            'correct_answer' => $correctAns,
            'is_correct' => $isCorrect,
            'explanation' => $q['explanation'],
        ];
    }

    $percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 1) : 0;
    $status = $percentage >= $quiz['pass_percentage'] ? 'passed' : 'needs_practice';

    $db = getQuizDB();
    $ins = $db->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, student_name, student_name_mr, std, division, score, total_marks, percentage, status, time_spent_seconds, answers_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([
        $quizId,
        $studentId,
        $studentName,
        $studentNameMr,
        $std,
        $division,
        $score,
        $totalQuestions,
        $percentage,
        $status,
        $timeSpent,
        json_encode($review, JSON_UNESCAPED_UNICODE)
    ]);
    $attemptId = (int)$db->lastInsertId();

    return [
        'attempt_id' => $attemptId,
        'quiz_id' => $quizId,
        'title' => $quiz['title'],
        'score' => $score,
        'total' => $totalQuestions,
        'percentage' => $percentage,
        'status' => $status,
        'review' => $review
    ];
}
