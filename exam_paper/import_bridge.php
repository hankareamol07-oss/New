<?php
/**
 * Textbook bridge for the classic "Create New Paper" builder.
 *
 *   php import_bridge.php
 *
 * The classic builder draws from ep_subjects / ep_chapters / ep_questions (scraped source site), which has gaps
 * (e.g. no Hindi for std 5-6, Hindi only for मराठी medium in std 7-8). This creates one extra subject per
 * textbook of the bank (ep_books) under every standard of the same class and medium that has no such
 * subject yet, copies the bank's स्वाध्याय + typed questions into ep_questions and adds a default template.
 * Re-runnable: bridge rows (subject_id >= 900000, question_id >= 6000000) are rebuilt.
 */
require_once __DIR__ . '/includes/functions.php';

if (PHP_SAPI !== 'cli') {
    die("Run from the command line: php import_bridge.php\n");
}

const BRIDGE_SUBJECT = 900000;
const BRIDGE_CHAPTER = 900000;
const BRIDGE_QUESTION = 6000000;

$db = ep_db();

$mediumMatch = function (string $bookMedium, string $stdMedium): bool {
    $m = strtolower($stdMedium);
    if ($bookMedium === 'Hindi') return true;
    if ($bookMedium === 'Marathi') return str_contains($stdMedium, 'मराठी') || str_contains($m, 'marathi');
    return str_contains($m, 'english') || str_contains($m, 'semi');
};
$subjectKeys = function (string $subject): array {
    $s = strtolower($subject);
    if (str_contains($s, 'hindi')) return ['हिंदी', 'hindi'];
    if (str_contains($s, 'marathi')) return ['मराठी', 'marathi'];
    if (str_contains($s, 'english')) return ['english', 'इंग्रजी'];
    if (str_contains($s, 'math')) return ['गणित', 'math'];
    if (str_contains($s, 'evs')) return ['परिसर', 'evs', 'environment'];
    if (str_contains($s, 'science')) return ['विज्ञान', 'science'];
    if (str_contains($s, 'geograph')) return ['भूगोल', 'geograph'];
    if (str_contains($s, 'history')) return ['इतिहास', 'history'];
    return [$s];
};
$subjectLabel = function (array $b): string {
    $t = $b['title'];
    $name = $b['subject'];
    if ($b['subject'] === 'Hindi') $name = str_contains($t, 'Sugam') ? 'हिंदी सुगमभारती' : 'हिंदी सुलभभारती';
    elseif ($b['subject'] === 'Marathi') $name = 'मराठी बालभारती';
    return $name . ' (पाठ्यपुस्तक)';
};
// bank qtype -> [classic type, question number]
$qmap = function (string $qtype): array {
    return match ($qtype) {
        'fill_blank' => ['fillinblanks', '1'],
        'mcq', 'odd_one' => ['mcq', '2'],
        'true_false', 'match' => ['descriptive', '2'],
        'one_word', 'one_sentence', 'define', 'vocabulary', 'grammar' => ['descriptive', '3'],
        'short_answer', 'reason', 'difference', 'explain', 'solve', 'draw' => ['descriptive', '4'],
        default => ['descriptive', '5'],
    };
};
$markup = function (array $q): array {
    $text = trim((string)$q['text']);
    $opts = $q['options_json'] ? json_decode($q['options_json'], true) : [];
    $pairs = $q['pairs_json'] ? json_decode($q['pairs_json'], true) : [];
    $answer = trim((string)$q['answer']);
    if ($q['qtype'] === 'mcq' || $q['qtype'] === 'odd_one') {
        if (!$opts) return ['', ''];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
        $line = [];
        foreach (array_values($opts) as $i => $o) {
            $line[] = ($letters[$i] ?? ($i + 1)) . ') ' . trim((string)$o);
        }
        $text .= "\n" . implode('  ', $line);
        if ($answer !== '' && is_numeric($answer) && isset($opts[(int)$answer])) {
            $answer = ($letters[(int)$answer] ?? '') . ') ' . trim((string)$opts[(int)$answer]);
        }
    } elseif ($q['qtype'] === 'match' && $pairs) {
        $a = array_column($pairs, 0);
        $b = array_column($pairs, 1);
        shuffle($b);
        $rows = [];
        foreach ($a as $i => $l) {
            $rows[] = ($i + 1) . ') ' . $l . '  –  ' . chr(97 + $i) . ') ' . ($b[$i] ?? '');
        }
        $text .= "\n" . implode("\n", $rows);
        if ($answer === '') {
            $answer = implode(', ', array_map(fn ($p) => $p[0] . ' – ' . $p[1], $pairs));
        }
    } elseif ($q['qtype'] === 'true_false' && !str_contains($text, 'सही') && !str_contains($text, 'True')) {
        $text .= ' (सही / गलत)';
    }
    if ($q['instruction'] && !in_array($q['qtype'], ['fill_blank', 'mcq', 'odd_one', 'true_false', 'match'], true)
        && !str_starts_with($text, trim((string)$q['instruction']))) {
        $text = trim((string)$q['instruction']) . "\n" . $text;
    }
    return [$text, $answer];
};

$books = $db->query('SELECT * FROM ep_books ORDER BY standard, subject, medium, book_id')->fetchAll();
$standards = $db->query('SELECT * FROM ep_standards')->fetchAll();
$subjectsByStd = [];
// a legacy subject only blocks the bridge when it has a usable bank (>= 300 questions)
foreach ($db->query('SELECT s.standard_id, s.name FROM ep_subjects s WHERE s.subject_id < ' . BRIDGE_SUBJECT . '
                     AND (SELECT COUNT(*) FROM ep_chapters c JOIN ep_questions q ON q.chapter_id = c.chapter_id WHERE c.subject_id = s.subject_id) >= 300') as $s) {
    $subjectsByStd[$s['standard_id']][] = $s['name'];
}

$db->beginTransaction();
$db->exec('DELETE FROM ep_questions WHERE question_id >= ' . BRIDGE_QUESTION);
$db->exec('DELETE FROM ep_subjects WHERE subject_id >= ' . BRIDGE_SUBJECT); // cascades chapters / templates

$insSub = $db->prepare('INSERT INTO ep_subjects (subject_id, standard_id, name) VALUES (?,?,?)');
$insCh = $db->prepare('INSERT INTO ep_chapters (chapter_id, subject_id, name, sort_order) VALUES (?,?,?,?)');
$insTpl = $db->prepare('INSERT INTO ep_templates (template_id, subject_id, title, total_marks, description) VALUES (?,?,?,?,?)');
$insTq = $db->prepare('INSERT INTO ep_template_questions (tq_id, template_id, question_title, marks, question_type, question_number, no_of_questions, sort_order) VALUES (?,?,?,?,?,?,?,?)');
$insQ = $db->prepare('INSERT INTO ep_questions (question_id, chapter_id, question_type, question_number, marks, difficulty, source, passage, markup, markup2, markup3, answer_markup, answer_markup2, has_image, has_image2, has_answer_image, sub_questions)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)');
$selCh = $db->prepare('SELECT chapter_id, chapter_no, title FROM ep_book_chapters WHERE book_id = ? ORDER BY chapter_no');
$selQ = $db->prepare('SELECT * FROM ep_book_questions WHERE chapter_id = ? AND text <> ""');

$templateDef = [ // title, marks per question, type, qno, count
    ['रिक्त स्थानों की पूर्ति करो', 1, 'fillinblanks', '1', 5],
    ['सही विकल्प चुनो', 1, 'mcq', '2', 5],
    ['एक शब्द / एक वाक्य में उत्तर लिखो', 2, 'descriptive', '3', 5],
    ['संक्षेप में उत्तर लिखो', 2, 'descriptive', '4', 5],
    ['विस्तार से उत्तर लिखो', 5, 'descriptive', '5', 2],
];

$counts = ['subjects' => 0, 'chapters' => 0, 'questions' => 0];
$slot = 0;
foreach ($books as $b) {
    $keys = $subjectKeys($b['subject']);
    $chapters = ($selCh->execute([$b['book_id']]) ? $selCh->fetchAll() : []);
    if (!$chapters) continue;
    $questionsByChapter = [];
    foreach ($chapters as $c) {
        $selQ->execute([$c['chapter_id']]);
        $questionsByChapter[$c['chapter_id']] = $selQ->fetchAll();
    }
    if (!array_sum(array_map('count', $questionsByChapter))) continue;
    foreach ($standards as $st) {
        $stdNo = (int)preg_replace('/\D.*/', '', trim($st['name']));
        if ($stdNo !== (int)$b['standard'] || trim($st['name']) !== (string)$stdNo) continue; // plain class rows only
        if (!$mediumMatch($b['medium'], (string)$st['medium'])) continue;
        $has = false;
        foreach ($subjectsByStd[$st['standard_id']] ?? [] as $name) {
            foreach ($keys as $k) {
                if (str_contains(mb_strtolower($name), $k)) $has = true;
            }
        }
        if ($has) continue;
        $slot++;
        $seq = 0;
        $sid = BRIDGE_SUBJECT + $slot;
        $insSub->execute([$sid, $st['standard_id'], $subjectLabel($b)]);
        $counts['subjects']++;
        $tid = $sid;
        $insTpl->execute([$tid, $sid, $subjectLabel($b) . ' ४० गुण', 40, 'पाठ्यपुस्तक स्वाध्याय + सराव प्रश्न']);
        foreach ($templateDef as $i => [$title, $marks, $type, $qno, $n]) {
            $insTq->execute([$tid * 10 + $i, $tid, $title, $marks, $type, $qno, $n, $i]);
        }
        foreach ($chapters as $c) {
            $cid = BRIDGE_CHAPTER + $slot * 100 + (int)$c['chapter_no'];
            $insCh->execute([$cid, $sid, $c['chapter_no'] . '. ' . $c['title'], (int)$c['chapter_no']]);
            $counts['chapters']++;
            foreach ($questionsByChapter[$c['chapter_id']] as $q) {
                [$type, $qno] = $qmap((string)$q['qtype']);
                [$text, $answer] = $markup($q);
                if ($text === '') continue;
                $qid = BRIDGE_QUESTION + $slot * 200000 + (++$seq);
                $insQ->execute([$qid, $cid, $type, $qno, (int)($q['marks'] ?: ($type === 'descriptive' ? 2 : 1)), 2,
                    'textbook:' . $q['source'], '', $text, '', '', $answer, '', 0, 0, 0]);
                $counts['questions']++;
            }
        }
    }
}
$db->commit();
echo json_encode($counts) . "\n";
