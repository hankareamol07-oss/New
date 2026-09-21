<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/patterns.php';

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

    default:
        http_response_code(400);
        ep_json(['status' => 'error', 'message' => 'Unknown action']);
}
