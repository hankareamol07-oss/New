<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/patterns.php';
require_once __DIR__ . '/includes/assessment_patterns.php';

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
        // sections: [{q_no, sub, instruction, marks, qtype, chapter_ids[], items:[{bq_id, text, page_image}]}]
        $sections = $body['sections'] ?? [];
        if (!$sections) ep_json(['status' => 'error', 'message' => 'Add at least one question section']);
        $clean = [];
        $totalMarks = 0;
        foreach ($sections as $s) {
            $items = [];
            foreach ($s['items'] ?? [] as $it) {
                $text = trim((string)($it['text'] ?? ''));
                if ($text === '') continue;
                $img = preg_match('~^\d+/\d{3}\.jpg$~', (string)($it['page_image'] ?? '')) ? $it['page_image'] : null;
                $items[] = ['bq_id' => (int)($it['bq_id'] ?? 0) ?: null, 'text' => $text, 'page_image' => $img, 'show_image' => $img && !empty($it['show_image'])];
            }
            $instruction = trim((string)($s['instruction'] ?? ''));
            if (!$items && $instruction === '') continue;
            $clean[] = [
                'q_no' => (int)($s['q_no'] ?? 0), 'sub' => trim((string)($s['sub'] ?? '')), 'instruction' => $instruction,
                'marks' => (float)($s['marks'] ?? 0), 'qtype' => trim((string)($s['qtype'] ?? '')),
                'chapter_ids' => array_values(array_filter(array_map('intval', $s['chapter_ids'] ?? []))), 'items' => $items,
            ];
            $totalMarks += (float)($s['marks'] ?? 0);
        }
        if (!$clean) ep_json(['status' => 'error', 'message' => 'Every section needs an instruction or at least one question']);
        $examType = ($body['exam_type'] ?? '') === 'aakarik' ? 'aakarik' : 'sankalit';
        $meta = [
            'exam_type' => $examType, 'test_no' => (int)($body['test_no'] ?? 1) ?: 1, 'standard' => (int)($body['standard'] ?? 0),
            'subject' => trim((string)($body['subject'] ?? '')), 'medium' => trim((string)($body['medium'] ?? '')),
            'student_fields' => !empty($body['student_fields']), 'sections' => $clean,
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

    default:
        http_response_code(400);
        ep_json(['status' => 'error', 'message' => 'Unknown action']);
}
