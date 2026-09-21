<?php
/** Daily homework + topic quiz helpers. */
require_once __DIR__ . '/functions.php';

const EP_QUIZ_MIN = 10;
const EP_QUIZ_MAX = 15;

/** Option labels accepted in scraped MCQ text, in order (index 0..3). */
const EP_MCQ_LABELS = [
    ['A', 'B', 'C', 'D'], ['a', 'b', 'c', 'd'], ['अ', 'ब', 'क', 'ड'], ['1', '2', '3', '4'], ['१', '२', '३', '४'], ['i', 'ii', 'iii', 'iv'],
];

/**
 * Split a scraped MCQ (stem + inline options like "(A) x  (B) y  (C) z  (D) w") into
 * ['text' => stem, 'options' => [...], 'answer' => index] or null when it can't be parsed.
 */
function ep_parse_mcq(array $q): ?array
{
    $markup = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string)$q['markup']))));
    if ($markup === '') {
        return null;
    }
    foreach (EP_MCQ_LABELS as $labels) {
        $alt = implode('|', array_map('preg_quote', $labels));
        // an option marker: optional "(", label, then ")" or "." followed by space
        $re = '/(?:^|\s)\(?(' . $alt . ')[\)\.]\s*/u';
        if (!preg_match_all($re, $markup, $m, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        // keep only markers appearing in label order A, B, C, (D)
        $spans = [];
        $want = 0;
        foreach ($m[0] as $i => $full) {
            if ($want < 4 && $m[1][$i][0] === $labels[$want]) {
                $spans[] = [$full[1], $full[1] + strlen($full[0])];
                $want++;
            }
        }
        if (count($spans) < 3) {
            continue;
        }
        $stem = trim(substr($markup, 0, $spans[0][0]));
        $options = [];
        foreach ($spans as $i => [$s, $e]) {
            $next = $spans[$i + 1][0] ?? strlen($markup);
            $options[] = preg_replace('/^[\s\-–:]+|[\s\-–:]+$/u', '', substr($markup, $e, $next - $e));
        }
        // trailing "solution" label glued to the last option in some scraped questions
        $options[count($options) - 1] = trim(preg_replace('/\s*(उकल|उत्तर|स्पष्टीकरण|Solution|Explanation|Ans(?:wer)?)\s*[:.]?\s*$/iu', '', end($options)));
        if ($stem === '' || in_array('', $options, true)) {
            continue;
        }
        $ans = trim((string)($q['answer_markup'] ?? ''));
        $answer = null;
        if ($ans !== '') {
            if (preg_match('/^\(?(' . $alt . ')[\)\.\s]/u', $ans, $am) || preg_match('/^(' . $alt . ')$/u', $ans, $am)) {
                $answer = array_search($am[1], $labels, true);
            } else {
                foreach ($options as $i => $o) {
                    if (mb_strtolower($o) === mb_strtolower(trim($ans, " \t()"))) {
                        $answer = $i;
                    }
                }
            }
        }
        if ($answer === null || $answer === false) {
            return null;
        }
        return ['kind' => 'mcq', 'text' => $stem, 'options' => $options, 'answer' => (int)$answer];
    }
    return null;
}

/** Random auto-scorable MCQs from the scraped bank for the given ep_chapters ids. */
function ep_quiz_mcq_pool(array $chapterIds, int $count, array $excludeIds = []): array
{
    $chapterIds = array_values(array_filter(array_map('intval', $chapterIds)));
    if (!$chapterIds || $count <= 0) {
        return [];
    }
    $params = $chapterIds;
    $sql = 'SELECT * FROM ep_questions WHERE question_type = "mcq" AND answer_markup <> "" AND has_image = 0
            AND chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')';
    $excludeIds = array_values(array_filter(array_map('intval', $excludeIds)));
    if ($excludeIds) {
        $sql .= ' AND question_id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        $params = array_merge($params, $excludeIds);
    }
    $st = ep_db()->prepare($sql . ' ORDER BY RAND() LIMIT ' . (int)($count * 3));
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll() as $q) {
        $p = ep_parse_mcq($q);
        if ($p) {
            $p['question_id'] = (int)$q['question_id'];
            $out[] = $p;
            if (count($out) >= $count) {
                break;
            }
        }
    }
    return $out;
}

function ep_quiz_code(): string
{
    do {
        $code = substr(strtoupper(strtr(base64_encode(random_bytes(6)), '+/', 'XY')), 0, 8);
        $st = ep_db()->prepare('SELECT 1 FROM ep_quizzes WHERE code = ?');
        $st->execute([$code]);
    } while ($st->fetchColumn());
    return $code;
}

function ep_quiz(int $quizId): ?array
{
    $st = ep_db()->prepare('SELECT * FROM ep_quizzes WHERE quiz_id = ?');
    $st->execute([$quizId]);
    return ep_decode_quiz($st->fetch() ?: null);
}

function ep_quiz_by_code(string $code): ?array
{
    if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
        return null;
    }
    $st = ep_db()->prepare('SELECT * FROM ep_quizzes WHERE code = ?');
    $st->execute([$code]);
    return ep_decode_quiz($st->fetch() ?: null);
}

function ep_decode_quiz(?array $q): ?array
{
    if (!$q) {
        return null;
    }
    $q['questions'] = json_decode($q['questions_json'], true) ?: [];
    unset($q['questions_json']);
    return $q;
}

/** Validate/clean quiz questions coming from the builder. */
function ep_clean_quiz_questions(array $raw): array
{
    $clean = [];
    foreach ($raw as $it) {
        $text = trim((string)($it['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $kind = ($it['kind'] ?? 'mcq') === 'text' ? 'text' : 'mcq';
        $item = ['kind' => $kind, 'text' => $text, 'question_id' => (int)($it['question_id'] ?? 0) ?: null, 'bq_id' => (int)($it['bq_id'] ?? 0) ?: null];
        if ($kind === 'mcq') {
            $opts = array_values(array_filter(array_map(fn($o) => trim((string)$o), $it['options'] ?? []), fn($o) => $o !== ''));
            $ans = (int)($it['answer'] ?? -1);
            if (count($opts) < 2 || !isset($opts[$ans])) {
                continue;
            }
            $item['options'] = $opts;
            $item['answer'] = $ans;
        } else {
            $item['answer'] = trim((string)($it['answer'] ?? ''));
        }
        if (!empty($it['image_url']) && preg_match('~^[\w/.\-]*image\.php\?id=\d+(&n=\w+)?$~', (string)$it['image_url'])) {
            $item['image_url'] = $it['image_url'];
        }
        $clean[] = $item;
    }
    return $clean;
}

/** Score submitted answers against a quiz; returns [score, total, per-question result]. */
function ep_quiz_score(array $quiz, array $answers): array
{
    $score = 0;
    $total = 0;
    $detail = [];
    $norm = fn($s) => mb_strtolower(preg_replace('/[\s\p{P}]+/u', '', (string)$s));
    foreach ($quiz['questions'] as $i => $q) {
        $total++;
        $given = $answers[$i] ?? null;
        if ($q['kind'] === 'mcq') {
            $ok = $given !== null && $given !== '' && (int)$given === (int)$q['answer'];
        } else {
            $ok = $q['answer'] !== '' && $given !== null && $norm($given) === $norm($q['answer']);
        }
        $score += $ok ? 1 : 0;
        $detail[] = ['given' => $given, 'correct' => $ok];
    }
    return [$score, $total, $detail];
}

/** Absolute public URL for a quiz (students' phones). */
function ep_public_base(): string
{
    $base = rtrim(ep_setting('public_base_url'), '/');
    if ($base !== '') {
        return $base;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . EP_BASE_URL;
}

function ep_quiz_url(array $quiz): string
{
    return ep_public_base() . '/quiz.php?c=' . $quiz['code'];
}

function ep_homework(int $hwId): ?array
{
    $st = ep_db()->prepare('SELECT h.*, c.title chapter_title, c.chapter_no FROM ep_homework h LEFT JOIN ep_book_chapters c ON c.chapter_id = h.chapter_id WHERE h.hw_id = ?');
    $st->execute([$hwId]);
    $hw = $st->fetch();
    if (!$hw) {
        return null;
    }
    $hw['items'] = json_decode($hw['items_json'], true) ?: [];
    unset($hw['items_json']);
    foreach ($hw['items'] as &$it) {
        $it['page_image_url'] = !empty($it['page_image']) ? EP_BASE_URL . '/book_page.php?f=' . rawurlencode($it['page_image']) : null;
    }
    unset($it);
    $hw['quiz'] = $hw['quiz_id'] ? ep_quiz((int)$hw['quiz_id']) : null;
    return $hw;
}

/**
 * Suggest scraped-bank (downloadpapers) chapters for a textbook class/subject/chapter so the quiz
 * can be auto-filled: returns [{standard_id, std_name, subject_id, subject_name, chapters:[{chapter_id, name, mcq, score}]}].
 */
function ep_quiz_sources(int $standard, string $subject, string $medium, string $chapterTitle): array
{
    $aliases = [
        'Marathi' => ['मराठी', 'marathi'], 'Hindi' => ['हिंदी', 'hindi'], 'English' => ['english'], 'Maths' => ['गणित', 'math'],
        'Science' => ['विज्ञान', 'science'], 'EVS' => ['परिसर', 'evs'], 'EVS Part 1' => ['परिसर अभ्यास भाग - १', 'evs - 1'], 'EVS Part 2' => ['परिसर अभ्यास भाग - २', 'evs - 2'],
        'Geography' => ['भूगोल', 'geography'], 'History & Civics' => ['इतिहास', 'history'],
    ];
    $keys = array_map('mb_strtolower', $aliases[$subject] ?? [$subject]);
    $out = [];
    foreach (ep_source_tree() as $std) {
        if (!preg_match('/^' . $standard . '(\D|$)/u', $std['name'])) {
            continue;
        }
        $stdMarathi = ($std['medium'] ?? '') === 'मराठी';
        foreach ($std['subjects'] as $sub) {
            $sname = mb_strtolower($sub['name']);
            $hit = false;
            foreach ($keys as $k) {
                if (str_contains($sname, $k)) {
                    $hit = true;
                }
            }
            if (!$hit || str_contains($sname, 'without space')) {
                continue;
            }
            $chapters = [];
            foreach ($sub['chapters'] as $c) {
                $c['score'] = ep_title_similarity($chapterTitle, $c['name']);
                $chapters[] = $c;
            }
            usort($chapters, fn($a, $b) => $b['score'] <=> $a['score']);
            // prefer same-medium bank first
            $out[] = ['standard_id' => $std['standard_id'], 'std_name' => $std['name'], 'medium' => $std['medium'], 'subject_id' => $sub['subject_id'],
                'subject_name' => $sub['name'], 'mcq' => $sub['mcq'], 'chapters' => $chapters, 'same_medium' => ($medium === 'Marathi') === $stdMarathi];
        }
    }
    usort($out, fn($a, $b) => [$b['same_medium'], $b['mcq']] <=> [$a['same_medium'], $a['mcq']]);
    return $out;
}

/** Token overlap similarity (0..1) between two chapter titles. */
function ep_title_similarity(string $a, string $b): float
{
    $tok = function (string $s): array {
        $s = mb_strtolower(preg_replace('/^\s*[\d०-९]+[\.\)]?\s*/u', '', $s));
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $s, $m);
        return array_unique($m[0]);
    };
    $ta = $tok($a);
    $tb = $tok($b);
    if (!$ta || !$tb) {
        return 0.0;
    }
    $common = 0;
    foreach ($ta as $t) {
        foreach ($tb as $u) {
            if ($t === $u || (mb_strlen($t) > 3 && mb_strlen($u) > 3 && (str_starts_with($t, $u) || str_starts_with($u, $t)))) {
                $common++;
                break;
            }
        }
    }
    return $common / max(count($ta), count($tb));
}
