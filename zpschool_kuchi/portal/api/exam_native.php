<?php
/**
 * Native Android API — प्रश्नपत्रिका.
 * Runs the existing exam_paper/api.php for the bearer-token school, plus a few app-only read actions.
 *   GET  ?action=standards | papers | subjects&standard_id= | chapters&subject_id= | templates&subject_id=
 *   POST {action:"generate", standard_id, subject_id, template_id, chapter_ids[], title, exam_date, duration}
 */
require_once __DIR__ . '/native_bootstrap.php';
$ctx = native_boot();
$in = native_input();
$action = native_action($in);
$_SERVER['SCRIPT_NAME'] = dirname($_SERVER['SCRIPT_NAME'], 2) . '/modules/exam_paper/api.php';
require_once __DIR__ . '/../modules/exam_paper/includes/functions.php';

if ($action === 'standards') {
    ep_json(['success' => true, 'standards' => ep_standards(true)]);
}
if ($action === 'papers') {
    $st = ep_db()->prepare('SELECT p.paper_id, p.title, p.total_marks, p.exam_date, p.created_at, s.name AS subject_name, st.name AS standard_name, st.medium
        FROM ep_papers p LEFT JOIN ep_subjects s ON s.subject_id = p.subject_id LEFT JOIN ep_standards st ON st.standard_id = p.standard_id
        WHERE p.school_id = ? ORDER BY p.paper_id DESC LIMIT 100');
    $st->execute([$ctx['school_id']]);
    ep_json(['success' => true, 'papers' => $st->fetchAll()]);
}
if ($action === 'generate') {
    $tid = native_int($in, 'template_id');
    $chapters = array_values(array_filter(array_map('intval', native_list($in, 'chapter_ids'))));
    $subId = native_int($in, 'subject_id');
    if (!$tid || !$chapters) {
        apiError('नमुना आणि किमान एक धडा निवडा');
    }
    $can = ep_can_create_paper();
    if (!$can['allowed']) {
        apiError($can['message'] ?? 'प्रश्नपत्रिका तयार करता येत नाही', 402);
    }
    $tqRows = [];
    $tplRow = [];
    if ($tid > 0) {
        $tq = ep_db()->prepare('SELECT * FROM ep_template_questions WHERE template_id = ? ORDER BY sort_order, tq_id');
        $tq->execute([$tid]);
        $tqRows = $tq->fetchAll();
        $tpl = ep_db()->prepare('SELECT title, total_marks FROM ep_templates WHERE template_id = ?');
        $tpl->execute([$tid]);
        $tplRow = $tpl->fetch() ?: [];
    }
    if (empty($tqRows) && $subId > 0) {
        $allTpls = ep_templates($subId);
        foreach ($allTpls as $tpl) {
            if ((int)$tpl['template_id'] === $tid) {
                $tqRows = $tpl['questions'] ?? [];
                $tplRow = ['title' => $tpl['title'], 'total_marks' => $tpl['total_marks']];
                break;
            }
        }
    }
    if (empty($tqRows)) {
        $tqRows = [
            ['sort_order' => 1, 'question_title' => 'प्र. १) रिकाम्या जागा भरा / वस्तुनिष्ठ प्रश्न', 'marks' => 5, 'question_type' => 'fill_blank', 'question_number' => '', 'no_of_questions' => 5],
            ['sort_order' => 2, 'question_title' => 'प्र. २) एका वाक्यात उत्तरे लिहा / जोड्या जुळवा', 'marks' => 5, 'question_type' => 'one_sentence', 'question_number' => '', 'no_of_questions' => 5],
            ['sort_order' => 3, 'question_title' => 'प्र. ३) थोडक्यात उत्तरे लिहा', 'marks' => 6, 'question_type' => 'short_answer', 'question_number' => '', 'no_of_questions' => 3],
            ['sort_order' => 4, 'question_title' => 'प्र. ४) सविस्तर उत्तरे लिहा / कारणे स्पष्ट करा', 'marks' => 4, 'question_type' => 'descriptive', 'question_number' => '', 'no_of_questions' => 2],
        ];
    }
    $sections = [];
    $used = [];
    $total = 0;
    foreach ($tqRows as $t) {
        $n = max(1, (int) ($t['no_of_questions'] ?? 1));
        $qtype = (string) ($t['question_type'] ?? '');
        $qno = (string) ($t['question_number'] ?? '');
        $qs = ep_random_questions($chapters, $qtype, $qno, $n, $used);
        if (!$qs && $qno !== '') {
            $qs = ep_random_questions($chapters, $qtype, '', $n, $used);
        }
        if (!$qs) {
            $compat = ep_compatible_types($qtype);
            foreach ($compat as $ct) {
                $qs = ep_random_questions($chapters, $ct, '', $n, $used);
                if ($qs) break;
            }
        }
        if (!$qs) {
            $where = 'chapter_id IN (' . implode(',', $chapters) . ')';
            if ($used) $where .= ' AND question_id NOT IN (' . implode(',', $used) . ')';
            $st = ep_db()->query("SELECT * FROM ep_questions WHERE $where ORDER BY RANDOM() LIMIT $n");
            $qs = array_map('ep_decode_question', $st->fetchAll());
        }
        if (!$qs && $subId > 0) {
            $allChs = array_map(fn($c) => (int)$c['chapter_id'], ep_chapters($subId));
            if ($allChs) {
                $where = 'chapter_id IN (' . implode(',', $allChs) . ')';
                if ($used) $where .= ' AND question_id NOT IN (' . implode(',', $used) . ')';
                $st = ep_db()->query("SELECT * FROM ep_questions WHERE $where ORDER BY RANDOM() LIMIT $n");
                $qs = array_map('ep_decode_question', $st->fetchAll());
            }
        }
        if (!$qs) continue;
        $ids = array_map(fn($q) => (int) $q['question_id'], $qs);
        $used = array_merge($used, $ids);
        $secMarks = (int) ($t['marks'] ?? (count($ids) * 2));
        $sections[] = [
            'title' => $t['question_title'],
            'marks' => $secMarks,
            'type' => $t['question_type'] ?? 'descriptive',
            'qno' => $t['question_number'] ?? '',
            'question_ids' => $ids
        ];
        $total += $secMarks;
    }
    if (!$sections) {
        apiError('निवडलेल्या विषयामध्ये प्रश्न उपलब्ध नाहीत. कृपया दुसरा विषय किंवा सर्व धडे निवडा.');
    }
    $tplTitle = $tplRow['title'] ?? 'प्रश्नपत्रिका';
    $targetMarks = (int) ($tplRow['total_marks'] ?? 0) ?: $total;
    $paperData = ['format' => 'standard', 'sections' => $sections];
    ep_db()->prepare('INSERT INTO ep_papers (title, standard_id, subject_id, template_id, exam_date, duration, total_marks, instructions, paper_json, school_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            native_str($in, 'title') ?: $tplTitle, native_int($in, 'standard_id'), $subId, $tid,
            native_str($in, 'exam_date') ?: null, native_str($in, 'duration'), $targetMarks,
            native_str($in, 'instructions'), json_encode($paperData, JSON_UNESCAPED_UNICODE), $ctx['school_id'],
        ]);
    ep_json(['success' => true, 'paper_id' => (int) ep_db()->lastInsertId(), 'sections' => count($sections), 'total_marks' => $total]);
}
if (in_array($action, ['subjects', 'chapters', 'templates'], true)) {
    $fn = 'ep_' . $action;
    $arg = $action === 'subjects' ? native_int($in, 'standard_id') : native_int($in, 'subject_id');
    ep_json(['success' => true, 'items' => $fn($arg)]);
}
if ($action === 'homework_list') {
    require_once __DIR__ . '/../modules/exam_paper/includes/quiz.php';
    $where = '1=1';
    $params = [];
    $date = native_str($in, 'date');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $where .= ' AND h.hw_date = ?'; $params[] = $date; }
    if ($std = native_int($in, 'standard')) { $where .= ' AND h.standard = ?'; $params[] = $std; }
    $st = ep_db()->prepare("SELECT h.hw_id, h.hw_date, h.standard, h.division, h.std_label, h.subject, h.medium, h.chapter_id, h.topic, h.teacher, h.title, h.note,
        h.items_json, h.notes_json, h.quiz_id, q.code quiz_code, q.title quiz_title, q.time_limit, q.show_answers, q.questions_json, (SELECT COUNT(*) FROM ep_quiz_attempts a WHERE a.quiz_id = h.quiz_id) attempts,
        (SELECT ROUND(AVG(a.score / NULLIF(a.total,0) * 100)) FROM ep_quiz_attempts a WHERE a.quiz_id = h.quiz_id) avg_pct
        FROM ep_homework h LEFT JOIN ep_quizzes q ON q.quiz_id = h.quiz_id WHERE $where ORDER BY h.hw_date DESC, h.hw_id DESC LIMIT 300");
    $st->execute($params);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $r['items'] = json_decode((string) ($r['items_json'] ?? ''), true) ?: [];
        $r['notes'] = json_decode((string) ($r['notes_json'] ?? ''), true) ?: [];
        $r['quiz_questions'] = json_decode((string) ($r['questions_json'] ?? ''), true) ?: [];
        unset($r['items_json'], $r['notes_json'], $r['questions_json']);
        $rows[] = $r;
    }
    ep_json(['success' => true, 'items' => $rows]);
}
if ($action === 'competitive_list') {
    $st = ep_db()->prepare("SELECT paper_id, title, exam_name, std_label, exam_date, total_marks, created_at FROM ep_papers
        WHERE paper_type = 'competitive' AND (school_id = ? OR school_id IS NULL) ORDER BY paper_id DESC LIMIT 100");
    $st->execute([$ctx['school_id']]);
    ep_json(['success' => true, 'items' => $st->fetchAll()]);
}
$bridge = ['assessment_layout', 'book_browse', 'book_random', 'book_tree', 'browse', 'create_quiz_from_paper', 'delete_homework', 'exam_patterns',
    'generate_daily_study', 'paper_models', 'question_types', 'quiz_mcq', 'quiz_sources', 'quiz_submit', 'random', 'save_assessment', 'save_competitive',
    'save_homework', 'save_paper', 'source_tree', 'topic_pack'];
if (in_array($action, $bridge, true)) {
    foreach ($in as $k => $v) {
        if (!isset($_GET[$k])) $_GET[$k] = $v;
    }
    $_GET['action'] = $action;
    $_REQUEST = array_merge($_REQUEST, $_GET);
    ob_start(function (string $out): string {
        $j = json_decode($out, true);
        if (!is_array($j)) return $out;
        if (array_is_list($j)) return json_encode(['success' => true, 'items' => $j], JSON_UNESCAPED_UNICODE);
        if (!isset($j['success'])) $j['success'] = ($j['status'] ?? '') !== 'error';
        return json_encode($j, JSON_UNESCAPED_UNICODE);
    });
    require __DIR__ . '/../modules/exam_paper/api.php';
    exit;
}
apiError('Unknown action', 404);

