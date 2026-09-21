<?php
require_once __DIR__ . '/../config.php';

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Convert plain-text question markup (newlines, tabs) into safe HTML. */
function ep_markup(?string $s): string
{
    $s = trim((string)$s);
    if ($s === '') {
        return '';
    }
    $s = h($s);
    $s = preg_replace('/\t+/', '&emsp;&emsp;', $s);
    $s = preg_replace('/ {3,}/', '&emsp;&emsp;', $s);
    return nl2br($s);
}

function ep_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (ep_db()->query('SELECT setting_key, setting_value FROM ep_settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache;
}

function ep_setting(string $key, string $default = ''): string
{
    return ep_settings()[$key] ?? $default;
}

function ep_save_setting(string $key, string $value): void
{
    ep_db()->prepare('INSERT INTO ep_settings (setting_key, setting_value) VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([$key, $value]);
}

function ep_standards(): array
{
    return ep_db()->query('SELECT * FROM ep_standards ORDER BY sort_order, name, medium')->fetchAll();
}

function ep_subjects(int $standardId): array
{
    $st = ep_db()->prepare('SELECT s.*, (SELECT COUNT(*) FROM ep_chapters c JOIN ep_questions q ON q.chapter_id=c.chapter_id WHERE c.subject_id=s.subject_id) AS question_count
                            FROM ep_subjects s WHERE s.standard_id = ? ORDER BY s.name');
    $st->execute([$standardId]);
    return $st->fetchAll();
}

function ep_chapters(int $subjectId): array
{
    $st = ep_db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM ep_questions q WHERE q.chapter_id=c.chapter_id) AS question_count
                            FROM ep_chapters c WHERE c.subject_id = ? ORDER BY c.sort_order, c.chapter_id');
    $st->execute([$subjectId]);
    return $st->fetchAll();
}

function ep_templates(int $subjectId): array
{
    $st = ep_db()->prepare('SELECT * FROM ep_templates WHERE subject_id = ? ORDER BY total_marks, title');
    $st->execute([$subjectId]);
    $templates = $st->fetchAll();
    $tq = ep_db()->prepare('SELECT * FROM ep_template_questions WHERE template_id = ? ORDER BY sort_order, tq_id');
    foreach ($templates as &$t) {
        $tq->execute([$t['template_id']]);
        $t['questions'] = $tq->fetchAll();
    }
    return $templates;
}

/** Map template question types (free-text on source site) to stored question_type values. */
function ep_normalize_type(string $type): string
{
    $t = strtolower(trim($type));
    if (str_starts_with($t, 'mcq')) return 'mcq';
    if (str_starts_with($t, 'fill')) return 'fillinblanks';
    return 'descriptive';
}

/** Pick $count random questions of a type/number from the given chapters, excluding ids already used. */
function ep_random_questions(array $chapterIds, string $type, string $qno, int $count, array $exclude = []): array
{
    $chapterIds = array_values(array_filter(array_map('intval', $chapterIds)));
    if (!$chapterIds || $count <= 0) {
        return [];
    }
    $params = $chapterIds;
    $sql = 'SELECT * FROM ep_questions WHERE chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')
            AND question_type = ?';
    $params[] = ep_normalize_type($type);
    if (trim($qno) !== '') {
        $sql .= ' AND question_number = ?';
        $params[] = trim($qno);
    }
    $exclude = array_values(array_filter(array_map('intval', $exclude)));
    if ($exclude) {
        $sql .= ' AND question_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
        $params = array_merge($params, $exclude);
    }
    $sql .= ' ORDER BY RAND() LIMIT ' . (int)$count;
    $st = ep_db()->prepare($sql);
    $st->execute($params);
    return array_map('ep_decode_question', $st->fetchAll());
}

function ep_questions_by_ids(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $st = ep_db()->prepare('SELECT * FROM ep_questions WHERE question_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll() as $q) {
        $byId[$q['question_id']] = ep_decode_question($q);
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}

function ep_decode_question(array $q): array
{
    $q['sub_questions'] = $q['sub_questions'] ? json_decode($q['sub_questions'], true) : [];
    foreach ($q['sub_questions'] as &$sq) {
        $sid = (int)($sq['question_id'] ?? 0);
        $sq['image_url'] = !empty($sq['has_image']) && $sid ? EP_BASE_URL . '/image.php?id=' . $sid : null;
        $sq['answer_image_url'] = !empty($sq['has_answer_image']) && $sid ? EP_BASE_URL . '/image.php?id=' . $sid . '&n=a' : null;
    }
    unset($sq);
    $q['image_url'] = $q['has_image'] ? EP_BASE_URL . '/image.php?id=' . $q['question_id'] : null;
    $q['image2_url'] = $q['has_image2'] ? EP_BASE_URL . '/image.php?id=' . $q['question_id'] . '&n=2' : null;
    $q['answer_image_url'] = $q['has_answer_image'] ? EP_BASE_URL . '/image.php?id=' . $q['question_id'] . '&n=a' : null;
    return $q;
}

function ep_paper(int $paperId): ?array
{
    $st = ep_db()->prepare('SELECT p.*, s.name AS standard_name, s.medium, sub.name AS subject_name
                            FROM ep_papers p LEFT JOIN ep_standards s ON s.standard_id=p.standard_id
                            LEFT JOIN ep_subjects sub ON sub.subject_id=p.subject_id WHERE p.paper_id = ?');
    $st->execute([$paperId]);
    $p = $st->fetch();
    if (!$p) {
        return null;
    }
    $p['sections'] = json_decode($p['paper_json'], true) ?: [];
    return $p;
}

/** All classes with their subjects and chapters (+ MCQ counts) - used by the competitive paper builder. */
function ep_source_tree(): array
{
    $rows = ep_db()->query('SELECT s.standard_id, s.name std_name, s.medium, sub.subject_id, sub.name subject_name,
                                   c.chapter_id, c.name chapter_name, COUNT(q.question_id) mcq
                            FROM ep_standards s
                            JOIN ep_subjects sub ON sub.standard_id = s.standard_id
                            JOIN ep_chapters c ON c.subject_id = sub.subject_id
                            LEFT JOIN ep_questions q ON q.chapter_id = c.chapter_id AND q.question_type = "mcq"
                            GROUP BY c.chapter_id
                            ORDER BY s.sort_order, s.name, s.medium, sub.name, c.sort_order, c.chapter_id')->fetchAll();
    $tree = [];
    foreach ($rows as $r) {
        $sid = (int)$r['standard_id'];
        $tree[$sid] ??= ['standard_id' => $sid, 'name' => $r['std_name'], 'medium' => $r['medium'], 'subjects' => []];
        $subId = (int)$r['subject_id'];
        $tree[$sid]['subjects'][$subId] ??= ['subject_id' => $subId, 'name' => $r['subject_name'], 'mcq' => 0, 'chapters' => []];
        $tree[$sid]['subjects'][$subId]['chapters'][] = ['chapter_id' => (int)$r['chapter_id'], 'name' => $r['chapter_name'], 'mcq' => (int)$r['mcq']];
        $tree[$sid]['subjects'][$subId]['mcq'] += (int)$r['mcq'];
    }
    foreach ($tree as &$std) {
        $std['subjects'] = array_values(array_filter($std['subjects'], fn($s) => $s['mcq'] > 0));
    }
    unset($std);
    return array_values(array_filter($tree, fn($s) => $s['subjects']));
}

function ep_json(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
