<?php
/**
 * Bulk extractor: parses 880+ HTML quiz files from the uploaded dataset
 * and inserts MCQ questions into quiz_questions (standard='5th').
 *
 * Run: php run_seed_html.php [subject_filter]
 * e.g. php run_seed_html.php Math
 */
declare(strict_types=1);
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/includes/db.php';

$db = getDb();
$filterSubject = $argv[1] ?? null;

/* ── Subject mapping (file prefix → subject) ── */
$subjectMap = [
    // Math
    'berij'      => 'Math',  'vaja'       => 'Math',  'vajabaki'   => 'Math',
    'guna'       => 'Math',  'bhaga'      => 'Math',  'apurnank'   => 'Math',
    'mapan'      => 'Math',  'shekde'     => 'Math',  'nafa'       => 'Math',
    'saral'      => 'Math',  'saralvyaj'  => 'Math',  'area'       => 'Math',
    'parimiti'   => 'Math',  'trikon'     => 'Math',  'vartul'     => 'Math',
    'chouras'    => 'Math',  'trimiti'    => 'Math',  'kon'        => 'Math',
    'samantar'   => 'Math',  'numbers'    => 'Math',  'pattern'    => 'Math',
    'calendar'   => 'Math',  'Numerical'  => 'Math',  'gatsankhya' => 'Math',

    // Marathi
    'aaklan'     => 'Marathi', 'akshar'    => 'Marathi', 'alankarik' => 'Marathi',
    'bhinnarth'  => 'Marathi', 'contracted'=> 'Marathi', 'ghar'      => 'Marathi',
    'ling'       => 'Marathi', 'mhani'     => 'Marathi', 'nam'       => 'Marathi',
    'samanarthi' => 'Marathi', 'sanvad'    => 'Marathi', 'shabdhsamuh'=> 'Marathi',
    'shuddh'     => 'Marathi', 'utara'     => 'Marathi', 'vakprachar'=> 'Marathi',
    'varn'       => 'Marathi', 'virudharthi'=> 'Marathi','kavita'    => 'Marathi',
    'related'    => 'Marathi', 'sam'       => 'Marathi', 'kal'       => 'Marathi',
    'viram'      => 'Marathi', 'shabdhkodhi'=> 'Marathi','viramchinhe'=> 'Marathi',
    'gk'         => 'Marathi', 'akshare_julvun_shabdh' => 'Marathi',
    'chinha_malika' => 'Marathi',

    // English
    'opposite'   => 'English', 'punctuation'=> 'English', 'rhyming'  => 'English',
    'Homophones' => 'English', 'smallword'  => 'English', 'wformation'=> 'English',
    'wordregister'=> 'English','describing' => 'English', 'greeting' => 'English',
    'seeking'    => 'English', 'request'    => 'English', 'creative' => 'English',
    'chitralekh' => 'English', 'batmya'     => 'English', 'dictionary'=> 'English',
    'nameof'     => 'English',

    // Intelligence
    'mirror'     => 'Intelligence', 'vargi'   => 'Intelligence', 'smalika' => 'Intelligence',
    'cmalika'    => 'Intelligence', 'diagram' => 'Intelligence', 'gats'    => 'Intelligence',
    'gatw'       => 'Intelligence', 'water'   => 'Intelligence', 'bhashik' => 'Intelligence',
    'sankhya'    => 'Intelligence', 'samsabandh'=> 'Intelligence','tarksangati'=> 'Intelligence',
    'chuk'       => 'Intelligence', 'vargikaran'=> 'Intelligence',
];

/* ── Helper: extract a string value for a given JS key from an object block ── */
function extractField(string $block, string $key): string {
    // Match key: "value" or key: 'value'
    // e.g.  question_mr: "...",  or  question: '...',
    $pattern = '/' . preg_quote($key, '/') . '\s*:\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\')/' ;
    if (preg_match($pattern, $block, $m)) {
        $val = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
        return stripslashes($val);
    }
    return '';
}

/* ── Helper: extract an array of strings for a given JS key ── */
function extractArrayField(string $block, string $key): array {
    $pattern = '/' . preg_quote($key, '/') . '\s*:\s*\[([^\]]*)\]/s';
    if (!preg_match($pattern, $block, $m)) return [];
    $inner = $m[1];
    preg_match_all('/(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\')/s', $inner, $items);
    $result = [];
    foreach ($items[1] as $i => $v) {
        $result[] = stripslashes($v !== '' ? $v : ($items[2][$i] ?? ''));
    }
    return $result;
}

/* ── Helper: split JS array body into individual {…} blocks ── */
function splitJsObjects(string $jsArr): array {
    $jsArr = preg_replace('/\/\/[^\n]*/', '', $jsArr);   // strip line comments
    $jsArr = preg_replace('/\/\*.*?\*\//s', '', $jsArr); // strip block comments
    // Remove template literal blocks (backtick strings) — skip SVG etc.
    $jsArr = preg_replace('/`[^`]*`/s', '""', $jsArr);

    $objects = [];
    $depth = 0;
    $start = null;
    $len   = strlen($jsArr);
    $inStr = false;
    $strChar = '';

    for ($i = 0; $i < $len; $i++) {
        $c = $jsArr[$i];
        if ($inStr) {
            if ($c === '\\') { $i++; continue; }
            if ($c === $strChar) $inStr = false;
            continue;
        }
        if ($c === '"' || $c === "'") { $inStr = true; $strChar = $c; continue; }
        if ($c === '{') {
            if ($depth === 0) $start = $i;
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0 && $start !== null) {
                $objects[] = substr($jsArr, $start, $i - $start + 1);
                $start = null;
            }
        }
    }
    return $objects;
}

/* ── Helper: extract questions array from HTML ── */
function extractQuestions(string $html): array {
    // Find questions, questions2, questions3… or riddles = [...]
    if (!preg_match('/(?:const\s+)?(?:questions\d*|riddles)\s*=\s*\[/s', $html, $m, PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $startPos = $m[0][1] + strlen($m[0][0]) - 1; // position of '['
    // Extract balanced [...] from this position
    $depth = 0;
    $len   = strlen($html);
    $arrStart = null;
    $inStr = false;
    $strChar = '';
    $jsArr = '';
    for ($i = $startPos; $i < $len; $i++) {
        $c = $html[$i];
        if ($inStr) {
            if ($c === '\\') { $i++; continue; }
            if ($c === $strChar) $inStr = false;
            continue;
        }
        if ($c === '`') {
            // skip template literal
            $i++;
            while ($i < $len && $html[$i] !== '`') {
                if ($html[$i] === '\\') $i++;
                $i++;
            }
            continue;
        }
        if ($c === '"' || $c === "'") { $inStr = true; $strChar = $c; continue; }
        if ($c === '[') {
            $depth++;
            if ($depth === 1) $arrStart = $i;
        } elseif ($c === ']') {
            $depth--;
            if ($depth === 0) {
                $jsArr = substr($html, $arrStart, $i - $arrStart + 1);
                break;
            }
        }
    }
    if (!$jsArr) return [];

    $blocks = splitJsObjects($jsArr);
    $questions = [];
    foreach ($blocks as $block) {
        // Try all known question key names in priority order
        $qKeys   = ['question_mr', 'question_en', 'question', 'riddle'];
        $optKeys = ['options_mr', 'options_en', 'options'];
        $cKeys   = ['correct', 'answer'];
        $eKeys   = ['explanation_mr', 'explanation_en', 'explanation'];

        $question = '';
        foreach ($qKeys as $k) {
            $question = extractField($block, $k);
            if ($question !== '') break;
        }
        $opts = [];
        foreach ($optKeys as $k) {
            $opts = extractArrayField($block, $k);
            if (!empty($opts)) break;
        }
        $correct = '';
        foreach ($cKeys as $k) {
            $correct = extractField($block, $k);
            if ($correct !== '') break;
        }
        $expl = '';
        foreach ($eKeys as $k) {
            $expl = extractField($block, $k);
            if ($expl !== '') break;
        }

        if ($question || $opts || $correct) {
            $questions[] = [
                'question_mr'    => $question,
                'options_mr'     => $opts,
                'correct'        => $correct,
                'explanation_mr' => $expl,
            ];
        }
    }
    return $questions;
}

/* ── Helper: normalise a question row ── */
function normaliseQuestion(array $q): ?array {
    // Skip SVG-based visual questions (no text answer possible in chat)
    if (!empty($q['svg_code']) || !empty($q['image'])) return null;

    // Question text — prefer Marathi, fall back to English
    $question = trim($q['question_mr'] ?? $q['question_en'] ?? $q['question'] ?? '');
    // Skip if question contains SVG/HTML tags
    if (preg_match('/<svg|<img|<canvas/i', $question)) return null;

    // Options — try all known key names
    $opts = $q['options_mr'] ?? $q['options_en'] ?? $q['options'] ?? [];

    // Correct answer — 'correct' or 'answer'
    $correct = trim($q['correct'] ?? $q['answer'] ?? '');

    // Explanation — prefer Marathi
    $expl = trim($q['explanation_mr'] ?? $q['explanation_en'] ?? $q['explanation'] ?? '');

    if (!$question || count($opts) < 2 || !$correct) return null;
    // Ensure exactly 4 options (pad or truncate)
    while (count($opts) < 4) $opts[] = '-';
    $opts = array_slice($opts, 0, 4);

    return [
        'question'       => $question,
        'options'        => $opts,
        'correct_answer' => $correct,
        'explanation'    => $expl,
    ];
}

/* ── Helper: convert options array to Postgres array literal ── */
function toPgArray(array $opts): string {
    $escaped = array_map(fn($o) => '"' . str_replace(['"', '\\'], ['\\"', '\\\\'], (string)$o) . '"', $opts);
    return '{' . implode(',', $escaped) . '}';
}

/* ── Main loop ── */
$htmlDir   = '/tmp/zipdata/assets/';
$standard  = '5th';
$inserted  = 0;
$skipped   = 0;
$duplic    = 0;
$errors    = 0;
$bySubject = [];

// Pre-load existing questions to skip exact duplicates
$existingStmt = $db->query("SELECT question FROM quiz_questions WHERE standard = '5th'");
$existing = array_flip($existingStmt->fetchAll(PDO::FETCH_COLUMN));

$insertStmt = $db->prepare(
    'INSERT INTO quiz_questions (subject, standard, question, options, correct_answer, explanation)
     VALUES (?, ?, ?, ?, ?, ?)'
);

// Get all numbered HTML files
$allFiles = glob($htmlDir . '*.html');
sort($allFiles);

foreach ($allFiles as $filePath) {
    $base   = basename($filePath, '.html');

    // Identify subject from prefix
    $subject = null;
    foreach ($subjectMap as $prefix => $subj) {
        if (stripos($base, $prefix) === 0 && preg_match('/\d/', $base)) {
            $subject = $subj;
            break;
        }
    }
    if (!$subject) continue;
    if ($filterSubject && $subject !== $filterSubject) continue;

    // Get topic from HTML title
    $html = file_get_contents($filePath);
    preg_match('/<title>([^<]+)<\/title>/', $html, $tm);
    $rawTitle = html_entity_decode(trim($tm[1] ?? $base), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Clean up title — remove " - संच N" suffixes and generic suffixes
    $topic = preg_replace('/\s*[-–]\s*(?:संच\s*\d+|Set\s*\d+|प्रश्नसंच\s*\d+)\s*$/u', '', $rawTitle);
    $topic = preg_replace('/\s*\([^)]+\)\s*$/u', '', $topic);
    $topic = trim($topic);
    if (mb_strlen($topic) > 120) $topic = mb_substr($topic, 0, 120);

    $questions = extractQuestions($html);
    if (empty($questions)) {
        $errors++;
        continue;
    }

    foreach ($questions as $qRaw) {
        $q = normaliseQuestion($qRaw);
        if (!$q) { $skipped++; continue; }

        // Skip exact duplicates (by question text)
        if (isset($existing[$q['question']])) { $duplic++; continue; }
        $existing[$q['question']] = true;

        try {
            $insertStmt->execute([
                $subject,
                $standard,
                $q['question'],
                toPgArray($q['options']),
                $q['correct_answer'],
                $q['explanation'],
            ]);
            $inserted++;
            $bySubject[$subject] = ($bySubject[$subject] ?? 0) + 1;
        } catch (\Exception $e) {
            $skipped++;
        }
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Inserted : {$inserted}\n";
echo "⏭  Duplicates: {$duplic}\n";
echo "⚠  Skipped  : {$skipped}\n";
echo "❌ Parse err: {$errors}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($bySubject as $s => $c) echo "  {$s}: +{$c}\n";

// Final counts
$rows = $db->query("SELECT subject, COUNT(*) as cnt FROM quiz_questions WHERE standard='5th' GROUP BY subject ORDER BY subject")->fetchAll(PDO::FETCH_ASSOC);
echo "\n📊 5th std total:\n";
foreach ($rows as $r) echo "  {$r['subject']}: {$r['cnt']}\n";
echo "  5th SUBTOTAL: " . $db->query("SELECT COUNT(*) FROM quiz_questions WHERE standard='5th'")->fetchColumn() . "\n";
$rows8 = $db->query("SELECT subject, COUNT(*) as cnt FROM quiz_questions WHERE standard='8th' GROUP BY subject ORDER BY subject")->fetchAll(PDO::FETCH_ASSOC);
echo "\n📊 8th std total:\n";
foreach ($rows8 as $r) echo "  {$r['subject']}: {$r['cnt']}\n";
echo "  8th SUBTOTAL: " . $db->query("SELECT COUNT(*) FROM quiz_questions WHERE standard='8th'")->fetchColumn() . "\n";
echo "\n  GRAND TOTAL: " . $db->query("SELECT COUNT(*) FROM quiz_questions")->fetchColumn() . "\n";
