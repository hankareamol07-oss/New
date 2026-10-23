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

/** Random auto-scorable MCQs from the question bank for the given ep_chapters ids. */
function ep_quiz_mcq_pool(array $chapterIds, int $count, array $excludeIds = []): array
{
    $chapterIds = array_values(array_filter(array_map('intval', $chapterIds)));
    if (!$chapterIds || $count <= 0) {
        return [];
    }
    $params = $chapterIds;
    // Allow questions with images as well
    $sql = 'SELECT * FROM ep_questions WHERE question_type = "mcq" AND answer_markup <> ""
            AND chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')';
    $excludeIds = array_values(array_filter(array_map('intval', $excludeIds)));
    if ($excludeIds) {
        $sql .= ' AND question_id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
        $params = array_merge($params, $excludeIds);
    }
    $st = ep_db()->prepare($sql . ' ORDER BY RAND() LIMIT ' . (int)($count * 4));
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll() as $q) {
        $p = ep_parse_mcq($q);
        if ($p) {
            $p['question_id'] = (int)$q['question_id'];
            if (!empty($q['has_image'])) {
                $p['image_url'] = EP_BASE_URL . '/image.php?id=' . $q['question_id'];
            } elseif (preg_match('/src=["\']([^"\']+)["\']/i', $q['markup'] . ' ' . ($q['passage'] ?? ''), $im)) {
                $p['image_url'] = str_starts_with($im[1], 'http') ? $im[1] : EP_BASE_URL . '/' . ltrim($im[1], '/');
            }
            $out[] = $p;
            if (count($out) >= $count) {
                break;
            }
        }
    }

    // If still short of the requested count, supplement from ep_topic_packs
    if (count($out) < $count) {
        $tpSt = ep_db()->prepare('SELECT quiz_json FROM ep_topic_packs WHERE chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')');
        $tpSt->execute($chapterIds);
        foreach ($tpSt->fetchAll() as $tpRow) {
            $packQuiz = json_decode($tpRow['quiz_json'] ?? '[]', true) ?: [];
            foreach ($packQuiz as $pq) {
                if (count($out) >= $count) break 2;
                if (!empty($pq['question']) && !empty($pq['options']) && isset($pq['answer'])) {
                    $out[] = [
                        'kind' => 'mcq',
                        'text' => trim($pq['question']),
                        'options' => $pq['options'],
                        'answer' => (int)$pq['answer'],
                        'question_id' => null
                    ];
                }
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
        if (!empty($it['image_url'])) {
            $item['image_url'] = trim((string)$it['image_url']);
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

/** Automatically create or retrieve an online interactive student quiz from any generated paper. */
function ep_create_quiz_from_paper(int $paperId): array
{
    $paper = ep_paper($paperId);
    if (!$paper) {
        return ['status' => 'error', 'message' => 'पेपर आढळला नाही'];
    }

    $allIds = [];
    foreach ($paper['sections'] as $s) {
        if (!empty($s['question_ids'])) {
            $allIds = array_merge($allIds, $s['question_ids']);
        } elseif (!empty($s['items'])) {
            foreach ($s['items'] as $it) {
                if (!empty($it['bq_id'])) $allIds[] = (int)$it['bq_id'];
            }
        }
    }

    $isAssessment = in_array(($paper['paper_type'] ?? ''), ['assessment', 'daily_study'], true);
    $questions = [];

    if ($isAssessment) {
        $loaded = [];
        if ($allIds) {
            foreach (ep_book_questions_by_ids($allIds) as $q) {
                $loaded[$q['bq_id']] = $q;
            }
        }
        foreach ($paper['sections'] as $s) {
            foreach ($s['items'] ?? [] as $it) {
                $qid = !empty($it['bq_id']) ? (int)$it['bq_id'] : null;
                $bq = $qid && isset($loaded[$qid]) ? $loaded[$qid] : null;
                $opts = $bq ? ($bq['options'] ?: []) : [];
                $qtype = $it['qtype'] ?? ($bq['qtype'] ?? '');
                $rawText = trim($it['text'] ?? ($bq['text'] ?? ''));

                if (!$opts && preg_match_all('/(?:\([1-4A-D]\)|\[[1-4A-D]\]|[1-4A-D]\))\s*([^(\n\r]+)/u', $rawText, $m)) {
                    $opts = array_map('trim', $m[1]);
                }

                if (($qtype === 'mcq' || count($opts) >= 2) && count($opts) >= 2) {
                    $ansVal = trim($it['answer'] ?? ($bq['answer'] ?? ''));
                    $ansIdx = 0;
                    if ($ansVal !== '') {
                        if (preg_match('/^\(?([1-4A-D])\)?/i', $ansVal, $am)) {
                            $char = strtoupper($am[1]);
                            $ansIdx = is_numeric($char) ? (int)$char - 1 : ord($char) - ord('A');
                        } else {
                            foreach ($opts as $oi => $ov) {
                                if (trim($ov) === $ansVal) { $ansIdx = $oi; break; }
                            }
                        }
                    }
                    $ansIdx = max(0, min(count($opts) - 1, $ansIdx));
                    $imgUrl = null;
                    if (!empty($bq['figure_image_url'])) {
                        $imgUrl = $bq['figure_image_url'];
                    } elseif (!empty($bq['page_image_url'])) {
                        $imgUrl = $bq['page_image_url'];
                    } elseif (!empty($it['page_image'])) {
                        $imgUrl = EP_BASE_URL . '/book_page.php?f=' . rawurlencode($it['page_image']);
                    }

                    $mainText = $rawText;
                    if (str_contains($mainText, "\n(")) {
                        $parts = explode("\n(", $mainText, 2);
                        $mainText = trim($parts[0]);
                    }

                    $questions[] = [
                        'kind' => 'mcq',
                        'text' => $mainText,
                        'options' => $opts,
                        'answer' => $ansIdx,
                        'bq_id' => $qid,
                        'image_url' => $imgUrl
                    ];
                }
            }
        }
    } else {
        $loaded = [];
        foreach (ep_questions_by_ids($allIds) as $q) {
            $loaded[$q['question_id']] = $q;
        }
        foreach ($allIds as $qid) {
            if (!isset($loaded[$qid])) continue;
            $q = $loaded[$qid];
            $p = ep_parse_mcq($q);
            if ($p) {
                $p['question_id'] = (int)$q['question_id'];
                if (!empty($q['has_image'])) {
                    $p['image_url'] = EP_BASE_URL . '/image.php?id=' . $q['question_id'];
                } elseif (preg_match('/src=["\']([^"\']+)["\']/i', $q['markup'] . ' ' . ($q['passage'] ?? ''), $im)) {
                    $p['image_url'] = str_starts_with($im[1], 'http') ? $im[1] : EP_BASE_URL . '/' . ltrim($im[1], '/');
                }
                $questions[] = $p;
            } else {
                $rawMarkup = $q['markup2'] ?: $q['markup'];
                $opts = [];
                if (preg_match_all('/(?:\([A-D1-4]\)|\[[A-D1-4]\]|[A-D1-4]\))\s*([^(\n\r]+)/u', $rawMarkup, $m)) {
                    $opts = array_map('trim', $m[1]);
                }
                if (count($opts) >= 2) {
                    $ansOpt = ep_parse_omr_option($q['answer_markup'] ?: $q['answer_markup2']) ?? 1;
                    $ansIdx = max(0, min(count($opts) - 1, $ansOpt - 1));
                    $imgUrl = null;
                    if (!empty($q['has_image'])) {
                        $imgUrl = EP_BASE_URL . '/image.php?id=' . $q['question_id'];
                    } elseif (preg_match('/src=["\']([^"\']+)["\']/i', $q['markup'] . ' ' . ($q['passage'] ?? ''), $im)) {
                        $imgUrl = str_starts_with($im[1], 'http') ? $im[1] : EP_BASE_URL . '/' . ltrim($im[1], '/');
                    }
                    $questions[] = [
                        'kind' => 'mcq',
                        'text' => trim($q['markup']),
                        'options' => $opts,
                        'answer' => $ansIdx,
                        'question_id' => (int)$q['question_id'],
                        'image_url' => $imgUrl
                    ];
                }
            }
        }
    }

    if (empty($questions)) {
        return ['status' => 'error', 'message' => 'या पेपरमधील प्रश्न बहुपर्यायी (MCQ) स्वरूपात सापडले नाहीत.'];
    }

    $cleanQs = ep_clean_quiz_questions($questions);
    $code = ep_quiz_code();
    $std = (int)($paper['standard_id'] ?? 0) ?: 5;
    $sub = $paper['subject_name'] ?: ($paper['exam_name'] ?: 'Competitive Exam');
    $med = $paper['medium'] ?: 'Marathi';
    $topic = $paper['title'];
    $duration = (int)($paper['duration'] ?? 20) ?: 20;

    $st = ep_db()->prepare('INSERT INTO ep_quizzes (code, title, standard, subject, medium, topic, time_limit, show_answers, questions_json)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $st->execute([
        $code,
        $paper['title'],
        $std,
        $sub,
        $med,
        $topic,
        $duration,
        json_encode($cleanQs, JSON_UNESCAPED_UNICODE)
    ]);
    $quizId = (int)ep_db()->lastInsertId();

    return [
        'status' => 'success',
        'quiz_id' => $quizId,
        'code' => $code,
        'quiz_url' => EP_BASE_URL . '/quiz.php?c=' . $code
    ];
}
