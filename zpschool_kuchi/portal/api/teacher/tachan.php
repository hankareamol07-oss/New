<?php
/**
 * GET / POST /api/teacher/tachan.php
 * Daily Lesson Notes (दैनिक टाचण) & AWS Bedrock AI Assistant
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$scope = getTeacherClassScope($user);
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$in = getJsonInput();

$teacherId = (int)$user['linked_id'];

if ($method === 'GET') {
    $action = $in['action'] ?? 'list';

    if ($action === 'bank_search') {
        // Search in master tachan_bank (11,851 records)
        $std = isset($in['std']) ? (int)$in['std'] : ($scope['stds'][0] ?? 1);
        $subject = trim((string)($in['subject'] ?? ''));
        $q = trim((string)($in['q'] ?? ''));

        $sql = "SELECT id, std, subject, dn, month_no, day, topic, tl_activity, eval_tool, materials, learning_outcome, homework 
                FROM tachan_bank WHERE std = ?";
        $params = [$std];

        if (!empty($subject)) {
            $sql .= " AND subject LIKE ?";
            $params[] = "%$subject%";
        }
        if (!empty($q)) {
            $sql .= " AND (topic LIKE ? OR learning_outcome LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        $sql .= " ORDER BY dn ASC, seq ASC LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        apiSuccess(['items' => $items, 'total' => count($items)], 'टाचण बँक निकाल.');
    }

    // Default: Return teacher's saved lesson plans
    $date = trim((string)($in['date'] ?? date('Y-m-d')));
    $st = $db->prepare("
        SELECT lp.*, c.name as class_name 
        FROM lesson_plans lp
        LEFT JOIN classes c ON lp.class_id = c.id
        WHERE lp.school_id = ? AND lp.teacher_id = ? AND (lp.date = ? OR ? = '')
        ORDER BY lp.period ASC
    ");
    $st->execute([SCHOOL_ID, $teacherId, $date, $date]);
    $plans = $st->fetchAll(PDO::FETCH_ASSOC);

    apiSuccess(['date' => $date, 'plans' => $plans], 'शिक्षकांचे दैनिक टाचण लोड झाले.');
}

if ($method === 'POST') {
    $action = $in['action'] ?? 'save';

    if ($action === 'ai_generate') {
        // AWS Bedrock AI Generation
        $topic = trim((string)($in['topic'] ?? ''));
        $subject = trim((string)($in['subject'] ?? 'मराठी'));
        $std = (int)($in['std'] ?? 1);

        if (empty($topic)) {
            apiError('घटक / पाठाचे नाव (topic) आवश्यक आहे.', 400);
        }

        $prompt = "इयत्ता {$std} वी, विषय: {$subject}, घटक: '{$topic}'. या घटकासाठी NEP 2020 नुसार अधिकृत ५ स्तंभी दैनिक टाचण (Daily Lesson Plan) मराठीत तयार करा. 
यामध्ये खालील मुद्दे स्पष्ट द्या:
1. अध्ययन निष्पत्ती (Learning Outcome)
2. शैक्षणिक साहित्य (Teaching Materials)
3. अध्यापन पद्धती व अध्ययन-अध्यापन कृती (T-L Activities)
4. मूल्यमापन साधने (Evaluation Tools)
5. स्वाध्याय / गृहपाठ (Homework)";

        $generated = generateBedrockContent($prompt, "तुम्ही महाराष्ट्र राज्य प्राथमिक शिक्षणातील तज्ज्ञ आहात. स्वच्छ मराठीत मुद्देसूद टाचण लिहा.");

        if ($generated) {
            apiSuccess([
                'topic'     => $topic,
                'subject'   => $subject,
                'std'       => $std,
                'ai_content'=> $generated,
                'provider'  => 'AWS Bedrock (Claude 3.5 Haiku)'
            ], 'AI द्वारे दैनिक टाचण यशस्वीरित्या तयार झाले!');
        } else {
            apiError('AI सेवा सध्या उपलब्ध नाही किंवा की कॉन्फिगर केलेली नाही.', 503);
        }
    }

    // Normal save to lesson_plans
    $date = trim((string)($in['date'] ?? date('Y-m-d')));
    $period = (int)($in['period'] ?? 1);
    $class_id = (int)($in['class_id'] ?? ($scope['class_ids'][0] ?? 0));
    $subject = trim((string)($in['subject'] ?? ''));
    $topic = trim((string)($in['topic'] ?? ''));
    $learning_outcome = trim((string)($in['learning_outcome'] ?? ''));
    $materials = trim((string)($in['materials'] ?? ''));
    $activities = trim((string)($in['activities'] ?? ''));
    $eval_tool = trim((string)($in['eval_tool'] ?? ''));
    $homework = trim((string)($in['homework'] ?? ''));
    $notes = trim((string)($in['notes'] ?? ''));

    if (empty($topic)) {
        apiError('घटकाचे नाव आवश्यक आहे.', 400);
    }

    $st_ins = $db->prepare("
        INSERT INTO lesson_plans (
            school_id, teacher_id, date, period, class_id, subject, topic, 
            learning_outcome, materials, activities, eval_tool, homework, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st_ins->execute([
        SCHOOL_ID, $teacherId, $date, $period, $class_id, $subject, $topic,
        $learning_outcome, $materials, $activities, $eval_tool, $homework, $notes
    ]);

    apiSuccess(['id' => (int)$db->lastInsertId()], 'दैनिक टाचण यशस्वीरित्या जतन झाले!');
}
