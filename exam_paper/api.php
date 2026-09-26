<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/patterns.php';
require_once __DIR__ . '/includes/assessment_patterns.php';
require_once __DIR__ . '/includes/quiz.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {
    case 'subjects':
        ep_json(ep_subjects((int)($_GET['standard_id'] ?? 0)));

    case 'chapters':
        ep_json(ep_chapters((int)($_GET['subject_id'] ?? 0)));

    case 'templates':
        ep_json(ep_templates((int)($_GET['subject_id'] ?? 0)));

    case 'question_types':
        // Distinct (type, question number, marks) combos available in the chosen chapters - used for custom templates
        $ids = array_values(array_filter(array_map('intval', $_GET['chapter_ids'] ?? [])));
        if (!$ids) ep_json([]);
        $st = ep_db()->prepare('SELECT question_type, question_number, MAX(marks) marks, COUNT(*) total FROM ep_questions
            WHERE chapter_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
            GROUP BY question_type, question_number ORDER BY question_number, question_type');
        $st->execute($ids);
        ep_json($st->fetchAll());

    case 'random':
        ep_json(ep_random_questions($body['chapter_ids'] ?? [], $body['type'] ?? '', $body['qno'] ?? '',
            (int)($body['count'] ?? 1), $body['exclude'] ?? []));

    case 'browse':
        $ids = array_values(array_filter(array_map('intval', $body['chapter_ids'] ?? [])));
        if (!$ids) ep_json(['total' => 0, 'items' => []]);
        $params = $ids;
        $where = 'chapter_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        if (!empty($body['type'])) { $where .= ' AND question_type = ?'; $params[] = ep_normalize_type($body['type']); }
        if (!empty($body['qno'])) { $where .= ' AND question_number = ?'; $params[] = trim($body['qno']); }
        if (!empty($body['search'])) { $where .= ' AND (markup LIKE ? OR passage LIKE ?)'; $params[] = '%' . $body['search'] . '%'; $params[] = '%' . $body['search'] . '%'; }
        $page = max(0, (int)($body['page'] ?? 0));
        $size = 20;
        $total = ep_db()->prepare("SELECT COUNT(*) FROM ep_questions WHERE $where");
        $total->execute($params);
        $st = ep_db()->prepare("SELECT * FROM ep_questions WHERE $where ORDER BY question_id LIMIT $size OFFSET " . ($page * $size));
        $st->execute($params);
        ep_json(['total' => (int)$total->fetchColumn(), 'page' => $page, 'size' => $size, 'items' => array_map('ep_decode_question', $st->fetchAll())]);

    case 'save_paper':
        $sections = $body['sections'] ?? [];
        if (empty($body['standard_id']) || empty($body['subject_id']) || !$sections) {
            ep_json(['status' => 'error', 'message' => 'Class, subject and at least one section are required']);
        }
        $clean = [];
        $totalMarks = 0;
        foreach ($sections as $s) {
            $qids = array_values(array_filter(array_map('intval', $s['question_ids'] ?? [])));
            if (!$qids) continue;
            $clean[] = [
                'title' => trim($s['title'] ?? ''),
                'marks' => (int)($s['marks'] ?? 0),
                'type' => ep_normalize_type($s['type'] ?? ''),
                'qno' => trim($s['qno'] ?? ''),
                'question_ids' => $qids,
            ];
            $totalMarks += (int)($s['marks'] ?? 0);
        }
        if (!$clean) ep_json(['status' => 'error', 'message' => 'Every section needs at least one question']);
        $params = [
            trim($body['title'] ?? '') ?: 'Question Paper', (int)$body['standard_id'], (int)$body['subject_id'],
            !empty($body['template_id']) ? (int)$body['template_id'] : null,
            !empty($body['exam_date']) ? $body['exam_date'] : null, trim($body['duration'] ?? ''),
            (int)($body['total_marks'] ?? 0) ?: $totalMarks, trim($body['instructions'] ?? ''),
            json_encode($clean, JSON_UNESCAPED_UNICODE),
        ];
        if (!empty($body['paper_id'])) {
            $params[] = (int)$body['paper_id'];
            ep_db()->prepare('UPDATE ep_papers SET title=?, standard_id=?, subject_id=?, template_id=?, exam_date=?, duration=?, total_marks=?, instructions=?, paper_json=? WHERE paper_id=?')->execute($params);
            ep_json(['status' => 'success', 'paper_id' => (int)$body['paper_id']]);
        }
        ep_db()->prepare('INSERT INTO ep_papers (title, standard_id, subject_id, template_id, exam_date, duration, total_marks, instructions, paper_json) VALUES (?,?,?,?,?,?,?,?,?)')->execute($params);
        ep_json(['status' => 'success', 'paper_id' => (int)ep_db()->lastInsertId()]);

    case 'save_competitive':
        // sections: [{title, subtitle, count, marks_per_q, chapter_ids[], question_ids[]}]
        $sections = $body['sections'] ?? [];
        if (!$sections) ep_json(['status' => 'error', 'message' => 'Add at least one section']);
        $clean = [];
        $totalMarks = 0.0;
        foreach ($sections as $s) {
            $qids = array_values(array_filter(array_map('intval', $s['question_ids'] ?? [])));
            if (!$qids) continue;
            $perQ = round((float)($s['marks_per_q'] ?? 1), 2);
            $clean[] = [
                'title' => trim($s['title'] ?? ''),
                'subtitle' => trim($s['subtitle'] ?? ''),
                'type' => 'mcq',
                'marks_per_q' => $perQ,
                'marks' => round($perQ * count($qids), 2),
                'chapter_ids' => array_values(array_filter(array_map('intval', $s['chapter_ids'] ?? []))),
                'question_ids' => $qids,
            ];
            $totalMarks += $perQ * count($qids);
        }
        if (!$clean) ep_json(['status' => 'error', 'message' => 'Every section needs at least one question']);
        $params = [
            trim($body['title'] ?? '') ?: 'Practice Paper', trim($body['exam_name'] ?? ''), trim($body['std_label'] ?? ''),
            !empty($body['exam_date']) ? $body['exam_date'] : null, trim($body['duration'] ?? ''),
            (float)($body['total_marks'] ?? 0) ?: round($totalMarks, 2), trim($body['instructions'] ?? ''),
            json_encode($clean, JSON_UNESCAPED_UNICODE),
        ];
        if (!empty($body['paper_id'])) {
            $params[] = (int)$body['paper_id'];
            ep_db()->prepare("UPDATE ep_papers SET title=?, exam_name=?, std_label=?, exam_date=?, duration=?, total_marks=?, instructions=?, paper_json=?, paper_type='competitive', standard_id=NULL, subject_id=NULL, template_id=NULL WHERE paper_id=?")->execute($params);
            ep_json(['status' => 'success', 'paper_id' => (int)$body['paper_id']]);
        }
        ep_db()->prepare("INSERT INTO ep_papers (title, exam_name, std_label, exam_date, duration, total_marks, instructions, paper_json, paper_type) VALUES (?,?,?,?,?,?,?,?,'competitive')")->execute($params);
        ep_json(['status' => 'success', 'paper_id' => (int)ep_db()->lastInsertId()]);

    case 'exam_patterns':
        ep_json(ep_exam_patterns());

    case 'source_tree':
        ep_json(ep_source_tree());

    /* ---------- textbook bank + संकलित / आकारिक papers ---------- */
    case 'book_tree':
        ep_json(ep_book_tree());

    case 'book_random':
        ep_json(ep_book_random($body['chapter_ids'] ?? [], trim($body['qtype'] ?? ''), (int)($body['count'] ?? 1), $body['exclude'] ?? []));

    case 'book_browse':
        $ids = array_values(array_filter(array_map('intval', $body['chapter_ids'] ?? [])));
        $params = [];
        $where = '1=1';
        if ($ids) { $where .= ' AND q.chapter_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $params = $ids; }
        if (!empty($body['standard'])) { $where .= ' AND q.standard = ?'; $params[] = (int)$body['standard']; }
        if (!empty($body['qtype'])) { $where .= ' AND q.qtype = ?'; $params[] = trim($body['qtype']); }
        if (!empty($body['search'])) { $where .= ' AND (q.text LIKE ? OR q.instruction LIKE ?)'; $params[] = '%' . $body['search'] . '%'; $params[] = '%' . $body['search'] . '%'; }
        if (!$ids && empty($body['search'])) ep_json(['total' => 0, 'items' => []]);
        $page = max(0, (int)($body['page'] ?? 0));
        $size = 20;
        $total = ep_db()->prepare("SELECT COUNT(*) FROM ep_book_questions q WHERE $where");
        $total->execute($params);
        $st = ep_db()->prepare("SELECT q.*, c.title chapter_title, c.chapter_no FROM ep_book_questions q LEFT JOIN ep_book_chapters c ON c.chapter_id = q.chapter_id
                                WHERE $where ORDER BY q.page, q.bq_id LIMIT $size OFFSET " . ($page * $size));
        $st->execute($params);
        ep_json(['total' => (int)$total->fetchColumn(), 'page' => $page, 'size' => $size, 'items' => array_map('ep_decode_book_question', $st->fetchAll())]);

    case 'assessment_layout':
        ep_json(ep_assessment_layout(($_GET['exam_type'] ?? '') === 'aakarik' ? 'aakarik' : 'sankalit', trim($_GET['subject'] ?? ''), trim($_GET['medium'] ?? 'Marathi')));

    case 'paper_models':
        ep_json(ep_paper_models(($_GET['exam_type'] ?? 'sankalit') === 'aakarik' ? 'aakarik' : 'sankalit',
            (int)($_GET['standard'] ?? 0) ?: null, trim($_GET['subject'] ?? '')));

    case 'save_assessment':
        // sections: [{q_no, sub, instruction, marks, qtype, answer_space, oral, chapter_ids[], items:[{bq_id, text, page_image}]}]
        $sections = $body['sections'] ?? [];
        if (!$sections) ep_json(['status' => 'error', 'message' => 'Add at least one question section']);
        $clean = [];
        $totalMarks = 0;
        foreach ($sections as $s) {
            $items = [];
            foreach ($s['items'] ?? [] as $it) {
                $text = trim((string)($it['text'] ?? ''));
                if ($text === '') continue;
                $img = preg_match('~^\d+/\d{3}(_\d+)?\.jpg$~', (string)($it['page_image'] ?? '')) ? $it['page_image'] : null;
                $item = ['bq_id' => (int)($it['bq_id'] ?? 0) ?: null, 'text' => $text, 'page_image' => $img, 'show_image' => $img && !empty($it['show_image']),
                    'qtype' => preg_match('/^[a-z_]+$/', (string)($it['qtype'] ?? '')) ? $it['qtype'] : ''];
                if ($pairs = ep_item_pairs(['pairs' => $it['pairs'] ?? null, 'text' => $text])) {
                    $item['pairs'] = $pairs;
                }
                $items[] = $item;
            }
            $instruction = trim((string)($s['instruction'] ?? ''));
            if (!$items && $instruction === '') continue;
            $clean[] = [
                'q_no' => (int)($s['q_no'] ?? 0), 'sub' => trim((string)($s['sub'] ?? '')), 'instruction' => $instruction,
                'marks' => (float)($s['marks'] ?? 0), 'qtype' => trim((string)($s['qtype'] ?? '')),
                'answer_space' => preg_match('/^[a-z0-9]+(:\d+)?$/', (string)($s['answer_space'] ?? '')) ? $s['answer_space'] : '',
                'oral' => !empty($s['oral']),
                'chapter_ids' => array_values(array_filter(array_map('intval', $s['chapter_ids'] ?? []))), 'items' => $items,
            ];
            $totalMarks += (float)($s['marks'] ?? 0);
        }
        if (!$clean) ep_json(['status' => 'error', 'message' => 'Every section needs an instruction or at least one question']);
        $examType = ($body['exam_type'] ?? '') === 'aakarik' ? 'aakarik' : 'sankalit';
        $meta = [
            'exam_type' => $examType, 'test_no' => (int)($body['test_no'] ?? 1) ?: 1, 'standard' => (int)($body['standard'] ?? 0),
            'subject' => trim((string)($body['subject'] ?? '')), 'medium' => trim((string)($body['medium'] ?? '')),
            'student_fields' => !empty($body['student_fields']),
            'format' => ($body['format'] ?? '') === 'standard' ? 'standard' : 'lines',
            'oral_marks' => (float)($body['oral_marks'] ?? 0),
            'sections' => $clean,
        ];
        $params = [
            trim($body['title'] ?? '') ?: ($examType === 'aakarik' ? 'आकारिक मूल्यमापन चाचणी' : 'संकलित मूल्यमापन चाचणी'),
            trim($body['exam_name'] ?? ''), trim($body['std_label'] ?? ''),
            !empty($body['exam_date']) ? $body['exam_date'] : null, trim($body['duration'] ?? ''),
            (float)($body['total_marks'] ?? 0) ?: $totalMarks, trim($body['instructions'] ?? ''),
            json_encode($meta, JSON_UNESCAPED_UNICODE),
        ];
        if (!empty($body['paper_id'])) {
            $params[] = (int)$body['paper_id'];
            ep_db()->prepare("UPDATE ep_papers SET title=?, exam_name=?, std_label=?, exam_date=?, duration=?, total_marks=?, instructions=?, paper_json=?, paper_type='assessment', standard_id=NULL, subject_id=NULL, template_id=NULL WHERE paper_id=?")->execute($params);
            ep_json(['status' => 'success', 'paper_id' => (int)$body['paper_id']]);
        }
        ep_db()->prepare("INSERT INTO ep_papers (title, exam_name, std_label, exam_date, duration, total_marks, instructions, paper_json, paper_type) VALUES (?,?,?,?,?,?,?,?,'assessment')")->execute($params);
        ep_json(['status' => 'success', 'paper_id' => (int)ep_db()->lastInsertId()]);

    /* ---------------- daily homework + topic quiz ---------------- */

    case 'quiz_sources':
        // scraped-bank chapters that can feed auto MCQs for a textbook class/subject/chapter
        ep_json(ep_quiz_sources((int)($_GET['standard'] ?? 0), trim($_GET['subject'] ?? ''), trim($_GET['medium'] ?? ''), trim($_GET['chapter'] ?? '')));

    case 'quiz_mcq':
        ep_json(ep_quiz_mcq_pool($body['chapter_ids'] ?? [], max(1, min(30, (int)($body['count'] ?? EP_QUIZ_MIN))), $body['exclude'] ?? []));

    case 'topic_pack':
        // AI short notes + 10-question quiz for a textbook chapter (null when not generated yet)
        ep_json(ep_topic_pack((int)($_GET['chapter_id'] ?? 0)));

    case 'save_homework':
        $items = [];
        foreach ($body['items'] ?? [] as $it) {
            $text = trim((string)($it['text'] ?? ''));
            if ($text === '') continue;
            $img = preg_match('~^\d+/\d{3}(_\d+)?\.jpg$~', (string)($it['page_image'] ?? '')) ? $it['page_image'] : null;
            $item = ['bq_id' => (int)($it['bq_id'] ?? 0) ?: null, 'text' => $text, 'answer' => trim((string)($it['answer'] ?? '')), 'page_image' => $img, 'show_image' => $img && !empty($it['show_image']),
                    'qtype' => preg_match('/^[a-z_]+$/', (string)($it['qtype'] ?? '')) ? $it['qtype'] : ''];
            if ($pairs = ep_item_pairs(['pairs' => $it['pairs'] ?? null, 'text' => $text])) {
                $item['pairs'] = $pairs;
            }
            $items[] = $item;
        }
        $standard = (int)($body['standard'] ?? 0);
        $subject = trim((string)($body['subject'] ?? ''));
        if (!$standard || $subject === '' || (!$items && trim((string)($body['note'] ?? '')) === '')) {
            ep_json(['status' => 'error', 'message' => 'Class, subject and at least one homework item (or a note) are required']);
        }
        $quizQ = ep_clean_quiz_questions($body['quiz']['questions'] ?? []);
        $quizId = !empty($body['quiz_id']) ? (int)$body['quiz_id'] : null;
        $topic = trim((string)($body['topic'] ?? ''));
        $medium = trim((string)($body['medium'] ?? ''));
        if ($quizQ) {
            $qp = [trim((string)($body['quiz']['title'] ?? '')) ?: ('Quiz - ' . $topic), $standard, $subject, $medium, $topic,
                max(0, (int)($body['quiz']['time_limit'] ?? 0)), !empty($body['quiz']['show_answers']) ? 1 : 0, json_encode($quizQ, JSON_UNESCAPED_UNICODE)];
            if ($quizId && ep_quiz($quizId)) {
                $qp[] = $quizId;
                ep_db()->prepare('UPDATE ep_quizzes SET title=?, standard=?, subject=?, medium=?, topic=?, time_limit=?, show_answers=?, questions_json=? WHERE quiz_id=?')->execute($qp);
            } else {
                ep_db()->prepare('INSERT INTO ep_quizzes (code, title, standard, subject, medium, topic, time_limit, show_answers, questions_json) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute(array_merge([ep_quiz_code()], $qp));
                $quizId = (int)ep_db()->lastInsertId();
            }
        } else {
            $quizId = null;
        }
        $notes = array_values(array_filter(array_map(fn($n) => trim((string)$n), (array)($body['notes'] ?? [])), fn($n) => $n !== ''));
        $hp = [
            !empty($body['hw_date']) ? $body['hw_date'] : date('Y-m-d'), $standard, trim((string)($body['division'] ?? '')), trim((string)($body['std_label'] ?? '')),
            $subject, $medium, (int)($body['chapter_id'] ?? 0) ?: null, $topic, trim((string)($body['teacher'] ?? '')),
            trim((string)($body['title'] ?? '')) ?: 'गृहपाठ', trim((string)($body['note'] ?? '')), json_encode($items, JSON_UNESCAPED_UNICODE), $quizId,
            $notes ? json_encode($notes, JSON_UNESCAPED_UNICODE) : null,
        ];
        if (!empty($body['hw_id'])) {
            $hp[] = (int)$body['hw_id'];
            ep_db()->prepare('UPDATE ep_homework SET hw_date=?, standard=?, division=?, std_label=?, subject=?, medium=?, chapter_id=?, topic=?, teacher=?, title=?, note=?, items_json=?, quiz_id=?, notes_json=? WHERE hw_id=?')->execute($hp);
            ep_json(['status' => 'success', 'hw_id' => (int)$body['hw_id'], 'quiz_id' => $quizId]);
        }
        ep_db()->prepare('INSERT INTO ep_homework (hw_date, standard, division, std_label, subject, medium, chapter_id, topic, teacher, title, note, items_json, quiz_id, notes_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($hp);
        ep_json(['status' => 'success', 'hw_id' => (int)ep_db()->lastInsertId(), 'quiz_id' => $quizId]);

    case 'delete_homework':
        $hw = ep_homework((int)($body['hw_id'] ?? 0));
        if ($hw) {
            ep_db()->prepare('DELETE FROM ep_homework WHERE hw_id = ?')->execute([(int)$hw['hw_id']]);
            if ($hw['quiz_id']) ep_db()->prepare('DELETE FROM ep_quizzes WHERE quiz_id = ?')->execute([(int)$hw['quiz_id']]);
        }
        ep_json(['status' => 'success']);

    case 'quiz_submit':
        // public: student submits answers -> stored + scored
        $quiz = ep_quiz_by_code(trim((string)($body['code'] ?? '')));
        $name = trim((string)($body['student_name'] ?? ''));
        if (!$quiz || $name === '') {
            ep_json(['status' => 'error', 'message' => 'Quiz not found or name missing']);
        }
        $answers = [];
        foreach ($quiz['questions'] as $i => $q) {
            $a = $body['answers'][$i] ?? null;
            $answers[$i] = $a === null ? null : ($q['kind'] === 'mcq' ? (int)$a : mb_substr(trim((string)$a), 0, 200));
        }
        [$score, $total, $detail] = ep_quiz_score($quiz, $answers);
        ep_db()->prepare('INSERT INTO ep_quiz_attempts (quiz_id, student_name, roll_no, division, answers_json, score, total) VALUES (?,?,?,?,?,?,?)')
            ->execute([(int)$quiz['quiz_id'], mb_substr($name, 0, 120), mb_substr(trim((string)($body['roll_no'] ?? '')), 0, 20), mb_substr(trim((string)($body['division'] ?? '')), 0, 20),
                json_encode($answers, JSON_UNESCAPED_UNICODE), $score, $total]);
        $resp = ['status' => 'success', 'score' => $score, 'total' => $total, 'detail' => $detail];
        if ($quiz['show_answers']) {
            $resp['answers'] = array_map(fn($q) => $q['answer'], $quiz['questions']);
        }
        ep_json($resp);

    default:
        http_response_code(400);
        ep_json(['status' => 'error', 'message' => 'Unknown action']);
}
