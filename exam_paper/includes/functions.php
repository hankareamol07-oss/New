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
    $data = json_decode($p['paper_json'], true) ?: [];
    if (isset($data['sections'])) {           // assessment papers: {exam_type, test_no, standard, subject, medium, sections}
        $p['meta'] = $data;
        $p['sections'] = $data['sections'];
    } else {
        $p['meta'] = [];
        $p['sections'] = $data;
    }
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

/* ---------------- Textbook (Balbharati) exercise bank ---------------- */

/** URL for a textbook page image ("46/018.jpg") or a cropped figure ("46/018_2.jpg"). */
function ep_book_image_url(?string $f): ?string
{
    return $f ? EP_BASE_URL . '/book_page.php?f=' . rawurlencode($f) : null;
}

function ep_decode_book_question(array $q): array
{
    $q['page_image_url'] = ep_book_image_url($q['page_image'] ?? null);
    $q['figure_image_url'] = ep_book_image_url($q['figure_image'] ?? null);
    $q['options'] = !empty($q['options_json']) ? (json_decode($q['options_json'], true) ?: []) : [];
    $q['pairs'] = !empty($q['pairs_json']) ? ep_match_pairs(json_decode($q['pairs_json'], true) ?: []) : [];
    if (!$q['pairs'] && ($q['qtype'] ?? '') === 'match') {
        $q['pairs'] = ep_match_pairs($q['options'], true);
    }
    unset($q['options_json'], $q['pairs_json']);
    return $q;
}

/* ---------------- जोड्या लावा / match the pairs ---------------- */

/**
 * Normalise match-the-pairs data to [[left, right], ...].
 * Accepts [[l, r], ...], [{left, right}], ["l | r", ...] rows or a multi-line text with "l | r" lines.
 */
function ep_match_pairs($src, bool $dashRows = false): array
{
    if (is_string($src)) {
        $src = preg_split('/\r?\n/', $src);
    }
    if (!is_array($src)) {
        return [];
    }
    $pairs = [];
    $label = '/^\(?[A-Za-z0-9अ-ह१-९]{1,3}[\)\.:]\s*/u';
    $strip = fn($s) => preg_replace('/^[\s\-–—:]+|[\s\-–—:]+$/u', '', preg_replace($label, '', trim((string)$s)));
    $rows = [];
    foreach ($src as $row) {
        if (is_string($row) && substr_count($row, '|') > 1) {
            // legacy import: "A: l – r | B: l – r | ..." in one string
            foreach (explode('|', $row) as $seg) {
                $rows[] = trim($seg);
            }
        } else {
            $rows[] = $row;
        }
    }
    foreach ($rows as $row) {
        $l = $r = null;
        if (is_array($row)) {
            if (isset($row['left']) || isset($row['right'])) {
                $l = $row['left'] ?? null;
                $r = $row['right'] ?? null;
            } elseif (count($row) >= 2 && !is_array($row[0] ?? null)) {
                [$l, $r] = [$row[0], $row[1]];
            }
        } elseif (is_string($row) && preg_match('/^(.+?)\s*\|\s*(.+)$/u', trim($row), $m)) {
            [$l, $r] = [$m[1], $m[2]];
        } elseif ($dashRows && is_string($row) && preg_match('/^(.+?)\s+[–—-]\s+(.+)$/u', trim($row), $m)) {
            [$l, $r] = [$m[1], $m[2]];
        }
        $l = $strip($l);
        $r = $strip($r);
        if ($l !== '' && $r !== '' && $l !== $r) {
            $pairs[] = [$l, $r];
        }
    }
    return count($pairs) >= 2 ? $pairs : [];
}

/** Pairs for a paper / homework item: the editable "l | r" lines in its text win (teacher edits), else explicit `pairs`. */
function ep_item_pairs(array $item): array
{
    return ep_match_pairs($item['text'] ?? '') ?: ep_match_pairs($item['pairs'] ?? null);
}

/** The question stem of a match item = its text without the "l | r" pair lines. */
function ep_match_stem(string $text): string
{
    $lines = array_filter(preg_split('/\r?\n/', $text), fn($l) => !preg_match('/^.+?\s*\|\s*.+$/u', trim($l)));
    return trim(implode("\n", $lines));
}

/** Editable text form of a match question: stem + one "left | right" line per pair. */
function ep_match_text(string $stem, array $pairs): string
{
    $lines = array_map(fn($p) => $p[0] . ' | ' . $p[1], $pairs);
    return trim($stem . "\n" . implode("\n", $lines));
}

/**
 * Two-column layout: left in textbook order, right shuffled the same way every time (seeded on the pairs)
 * so the student sheet and the teacher key always agree. Returns [left[], right[], key[leftIdx => rightIdx]].
 */
function ep_match_layout(array $pairs): array
{
    $n = count($pairs);
    $order = range(0, $n - 1);
    mt_srand(crc32(json_encode($pairs)));
    for ($i = $n - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
    }
    if ($n > 1 && $order === range(0, $n - 1)) {
        $order[] = array_shift($order);
    }
    $right = [];
    $key = [];
    foreach ($order as $pos => $idx) {
        $right[$pos] = $pairs[$idx][1];
        $key[$idx] = $pos;
    }
    ksort($key);
    return ['left' => array_column($pairs, 0), 'right' => $right, 'key' => $key];
}

/** Label script follows the pairs themselves: Latin-only pairs get 1/2/3 + A/B/C even inside a Marathi paper. */
function ep_match_lang(array $pairs, string $lang): string
{
    return preg_match('/\p{Devanagari}/u', json_encode($pairs, JSON_UNESCAPED_UNICODE)) ? ($lang === 'en' ? 'mr' : $lang) : 'en';
}

function ep_match_labels(string $lang): array
{
    if ($lang === 'en') {
        return ['a' => 'A', 'b' => 'B', 'left' => range(1, 20), 'right' => range('a', 't')];
    }
    $right = ['अ', 'ब', 'क', 'ड', 'इ', 'ई', 'उ', 'ऊ', 'ए', 'ऐ', 'ओ', 'औ'];
    if ($lang === 'hi') {
        $right = ['क', 'ख', 'ग', 'घ', 'च', 'छ', 'ज', 'झ', 'ट', 'ठ', 'ड', 'ढ'];
    }
    $left = array_map(fn($i) => strtr((string)$i, ['0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४', '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९']), range(1, 20));
    return ['a' => $lang === 'hi' ? 'क' : 'अ', 'b' => $lang === 'hi' ? 'ख' : 'ब', 'left' => $left, 'right' => $right];
}

/** Printable two-column table for a match question; with $withKey the correct pairing is shown under it. */
function ep_match_table(array $pairs, string $lang = 'mr', bool $withKey = false): string
{
    if (!$pairs) {
        return '';
    }
    $lang = ep_match_lang($pairs, $lang);
    $lay = ep_match_layout($pairs);
    $lb = ep_match_labels($lang);
    $group = $lang === 'en' ? ['Group A', 'Group B'] : ["'{$lb['a']}' गट", "'{$lb['b']}' गट"];
    $h = '<table class="match-table"><thead><tr><th>' . h($group[0]) . '</th><th>' . h($group[1]) . '</th></tr></thead><tbody>';
    foreach ($lay['left'] as $i => $l) {
        $r = $lay['right'][$i];
        $h .= '<tr><td>(' . $lb['left'][$i] . ') ' . h($l) . '</td><td>(' . $lb['right'][$i] . ') ' . h($r) . '</td></tr>';
    }
    $h .= '</tbody></table>';
    if ($withKey) {
        $h .= '<div class="match-key">' . h(ep_match_key_text($pairs, $lang)) . '</div>';
    }
    return $h;
}

/** Teacher key line, e.g. "(१) – (ब), (२) – (अ)" followed by the pairs in words. */
function ep_match_key_text(array $pairs, string $lang = 'mr'): string
{
    $lang = ep_match_lang($pairs, $lang);
    $lay = ep_match_layout($pairs);
    $lb = ep_match_labels($lang);
    $codes = [];
    $words = [];
    foreach ($lay['key'] as $li => $ri) {
        $codes[] = '(' . $lb['left'][$li] . ') – (' . $lb['right'][$ri] . ')';
        $words[] = $pairs[$li][0] . ' – ' . $pairs[$li][1];
    }
    return implode(', ', $codes) . '  [' . implode('; ', $words) . ']';
}

/** AI topic pack (short notes + 10 MCQ quiz) for a textbook chapter, or null. */
function ep_topic_pack(int $chapterId): ?array
{
    if ($chapterId <= 0) {
        return null;
    }
    $st = ep_db()->prepare('SELECT * FROM ep_topic_packs WHERE chapter_id = ?');
    $st->execute([$chapterId]);
    $p = $st->fetch();
    if (!$p) {
        return null;
    }
    $p['notes'] = json_decode($p['notes_json'], true) ?: [];
    $p['quiz'] = json_decode($p['quiz_json'], true) ?: [];
    unset($p['notes_json'], $p['quiz_json']);
    return $p;
}

/** Classes -> subjects -> books -> chapters (+ question counts) for the textbook bank. */
function ep_book_tree(): array
{
    $rows = ep_db()->query('SELECT b.book_id, b.standard, b.subject, b.medium, b.title, c.chapter_id, c.chapter_no, c.title chapter_title,
                                   (SELECT COUNT(*) FROM ep_book_questions q WHERE q.chapter_id = c.chapter_id) n
                            FROM ep_books b LEFT JOIN ep_book_chapters c ON c.book_id = b.book_id
                            ORDER BY b.standard, b.subject, b.medium, b.book_id, c.chapter_no')->fetchAll();
    $tree = [];
    foreach ($rows as $r) {
        $std = (int)$r['standard'];
        $tree[$std] ??= ['standard' => $std, 'subjects' => []];
        $key = $r['subject'] . ' (' . $r['medium'] . ')';
        $tree[$std]['subjects'][$key] ??= ['name' => $key, 'subject' => $r['subject'], 'medium' => $r['medium'], 'books' => [], 'n' => 0];
        $bid = (int)$r['book_id'];
        $tree[$std]['subjects'][$key]['books'][$bid] ??= ['book_id' => $bid, 'title' => $r['title'], 'chapters' => [], 'n' => 0];
        if ($r['chapter_id']) {
            $tree[$std]['subjects'][$key]['books'][$bid]['chapters'][] = ['chapter_id' => (int)$r['chapter_id'], 'no' => (int)$r['chapter_no'], 'title' => $r['chapter_title'], 'n' => (int)$r['n']];
            $tree[$std]['subjects'][$key]['books'][$bid]['n'] += (int)$r['n'];
            $tree[$std]['subjects'][$key]['n'] += (int)$r['n'];
        }
    }
    foreach ($tree as &$std) {
        foreach ($std['subjects'] as &$s) {
            $s['books'] = array_values(array_filter($s['books'], fn($b) => $b['n'] > 0));
        }
        unset($s);
        $std['subjects'] = array_values(array_filter($std['subjects'], fn($s) => $s['n'] > 0));
    }
    unset($std);
    return array_values(array_filter($tree, fn($s) => $s['subjects']));
}

function ep_book_questions_by_ids(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $st = ep_db()->prepare('SELECT q.*, c.title chapter_title, c.chapter_no FROM ep_book_questions q LEFT JOIN ep_book_chapters c ON c.chapter_id = q.chapter_id
                            WHERE q.bq_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll() as $q) {
        $byId[$q['bq_id']] = ep_decode_book_question($q);
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}

/** Question types that can stand in for a requested type when the exact one is scarce. */
function ep_book_qtype_group(string $qtype): array
{
    $groups = [
        ['fill_blank'], ['true_false'], ['match'], ['mcq', 'odd_one'],
        ['one_word', 'one_sentence', 'short_answer', 'descriptive', 'reason', 'difference', 'define', 'explain'],
        ['solve', 'draw'], ['vocabulary', 'grammar'], ['activity'],
    ];
    foreach ($groups as $g) {
        if (in_array($qtype, $g, true)) {
            return $g;
        }
    }
    return [$qtype];
}

/** Random textbook questions from the given chapters; exact qtype first, then related types. */
function ep_book_random(array $chapterIds, string $qtype, int $count, array $exclude = []): array
{
    $chapterIds = array_values(array_filter(array_map('intval', $chapterIds)));
    if (!$chapterIds || $count <= 0) {
        return [];
    }
    $exclude = array_values(array_filter(array_map('intval', $exclude)));
    $pick = function (array $types, int $n) use ($chapterIds, &$exclude): array {
        $params = $chapterIds;
        $sql = 'SELECT * FROM ep_book_questions WHERE chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')';
        if ($types) {
            $sql .= ' AND qtype IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            $params = array_merge($params, $types);
        }
        if ($exclude) {
            $sql .= ' AND bq_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
            $params = array_merge($params, $exclude);
        }
        $st = ep_db()->prepare($sql . ' ORDER BY RAND() LIMIT ' . (int)$n);
        $st->execute($params);
        $rows = array_map('ep_decode_book_question', $st->fetchAll());
        foreach ($rows as $r) {
            $exclude[] = (int)$r['bq_id'];
        }
        return $rows;
    };
    $out = $qtype !== '' ? $pick([$qtype], $count) : [];
    if (count($out) < $count && $qtype !== '') {
        $out = array_merge($out, $pick(ep_book_qtype_group($qtype), $count - count($out)));
    }
    if (count($out) < $count) {
        $out = array_merge($out, $pick([], $count - count($out)));
    }
    return $out;
}

/** Reference संकलित / आकारिक paper structures (from real papers) for a class + subject. */
function ep_paper_models(string $examType, ?int $standard, string $subject = ''): array
{
    $sql = 'SELECT * FROM ep_paper_models WHERE exam_type = ?';
    $params = [$examType];
    if ($standard) {
        $sql .= ' AND standard = ?';
        $params[] = $standard;
    }
    if ($subject !== '') {
        $sql .= ' AND subject = ?';
        $params[] = $subject;
    }
    $st = ep_db()->prepare($sql . ' ORDER BY standard, subject, test_no, model_id');
    $st->execute($params);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['sections'] = json_decode($r['sections_json'], true) ?: [];
        unset($r['sections_json']);
    }
    return $rows;
}

function ep_json(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
