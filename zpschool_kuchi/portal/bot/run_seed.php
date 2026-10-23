<?php
/**
 * CLI-only seeder runner for 4th standard PSE questions.
 * Run: php run_seed.php
 */
declare(strict_types=1);
set_time_limit(0);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gemini.php';

$db = getDb();

/* ── Syllabus topics ── */
$syllabus = [
  'Marathi' => [
    'standard'  => '4th',
    'lang_hint' => 'Marathi',
    'topics' => [
      "वर्णमाला — स्वर व व्यंजन ओळख, अक्षर-उच्चार, जोडाक्षरे",
      "शब्दसंपत्ती — समानार्थी शब्द (synonyms) व विरुद्धार्थी शब्द (antonyms)",
      "शब्दसंपत्ती — एकवचन-अनेकवचन, लिंग (पुल्लिंग/स्त्रीलिंग)",
      "व्याकरण — नाम, सर्वनाम, विशेषण, क्रियापद ओळख",
      "व्याकरण — वर्तमानकाळ, भूतकाळ, भविष्यकाळ",
      "वाक्यप्रकार — विधानार्थी, प्रश्नार्थी, उद्गारार्थी, आज्ञार्थी",
      "म्हणी व वाक्प्रचार — अर्थ ओळखणे",
      "उतारा आकलन — छोट्या गद्यउताऱ्यावर प्रश्नोत्तरे",
    ],
  ],
  'Math' => [
    'standard'  => '4th',
    'lang_hint' => 'Marathi mixed with numbers/equations',
    'topics' => [
      "संख्या ज्ञान — 1 ते 9999 संख्या, स्थानिक किंमत, मोठी-लहान संख्या, क्रमाने मांडणे",
      "बेरीज व वजाबाकी — 4-digit numbers with carry and borrow",
      "गुणाकार — tables 2 to 10, 2-digit × 1-digit multiplication",
      "भागाकार — 2-digit ÷ 1-digit with remainder",
      "अपूर्णांक — proper fractions, half/quarter/three-quarter, equal fractions",
      "वेळ — घड्याळ वाचणे, तास-मिनिटे, AM/PM, वेळेची बेरीज-वजाबाकी",
      "पैसे — रुपये-पैसे, खरेदी-विक्री, सुट्टे पैसे",
      "मोजमाप — लांबी (मीटर/सेंटीमीटर), वजन (किलोग्राम/ग्राम), क्षमता (लिटर/मिलिलिटर)",
      "भूमिती — त्रिकोण, चतुर्भुज, वर्तुळ ओळख, परिमिती, 3D आकार",
      "आकृतिबंध (Patterns) — संख्या patterns, आकाराचे patterns पूर्ण करणे",
    ],
  ],
  'English' => [
    'standard'  => '4th',
    'lang_hint' => 'English',
    'topics' => [
      "Vocabulary — Rhyming words, Opposite words; finding small words inside bigger words",
      "Vocabulary — Parts of human body; names of birds and animals, their living places and sounds",
      "Vocabulary — Comparisons (as...as...); colours, shapes, vegetables, fruits, games",
      "Punctuation Marks — Comma, Full stop, Question mark, Exclamation mark, Apostrophe, Capitalization",
      "Numerical Information — Days of the week, Months of the year, Cardinal and Ordinal numbers, Directions",
      "Grammar — Nouns and Pronouns; Singular and Plural",
      "Grammar — Articles (a, an, the); Prepositions; Adverbs",
      "Grammar — Tenses: Simple Present, Simple Past, Simple Future",
      "Grammar — Action words (Verbs); Describing words (Adjectives)",
      "Stock Expressions — Greetings; instructions, expressions and pictures",
    ],
  ],
  'Intelligence' => [
    'standard'  => '4th',
    'lang_hint' => 'Marathi with English terms where needed',
    'topics' => [
      "संख्यामालिका (Number Series) — missing number in ascending, descending, skip-count patterns",
      "सादृश्यता (Analogy) — Word analogy: A is to B as C is to ?",
      "वर्गीकरण (Classification / Odd one out) — find the item that does not belong to the group",
      "दिशाज्ञान (Direction Sense) — simple compass directions N/S/E/W, left/right turns",
      "कोडिंग-डीकोडिंग (Coding-Decoding) — simple letter/number coding patterns",
      "दर्पण प्रतिमा (Mirror Image) — identify correct mirror reflection of letter/number/shape",
      "गणितीय तर्क (Mathematical Reasoning) — simple word-problem logic, basic age or counting problems",
      "आकृती पूर्ण करणे (Figure Completion / Pattern Matrix) — missing piece in pattern grid",
    ],
  ],
];

/* ── Helpers ── */
function buildPrompt(string $subject, string $topic, string $lang, string $std): string {
    return <<<PROMPT
You are creating MCQ quiz questions for the Maharashtra PSE (Primary Scholarship Exam) {$std} standard.
Subject: {$subject}
Topic: {$topic}
Language: {$lang}

Generate EXACTLY 4 multiple-choice questions strictly on this topic.
Return ONLY a valid JSON array — no markdown fences, no extra text:
[
  {
    "question": "...",
    "options": ["option1","option2","option3","option4"],
    "correct_answer": "exact text matching one option",
    "explanation": "one brief explanation in Marathi or English"
  }
]
Rules:
- Age-appropriate for {$std} grade (9-10 year old students)
- Exactly 4 options per question, only one correct
- correct_answer must be the EXACT text of one option (no paraphrasing)
- Options and questions must be concise (question ≤ 20 words, option ≤ 8 words)
- For Marathi subject: write questions and options in Marathi
- For Math: use Marathi labels + numbers/equations
- For English: use English
- For Intelligence: use the specified language
PROMPT;
}

/**
 * Robustly extract the first JSON array from a Gemini response.
 * Handles markdown fences, leading prose, partial wrappers.
 */
function extractJsonArray(string $raw): ?array {
    // Strip code fences
    $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
    $raw = preg_replace('/```\s*$/m', '', $raw);
    $raw = trim($raw);

    // Find first '[' and last ']'
    $start = strpos($raw, '[');
    $end   = strrpos($raw, ']');
    if ($start === false || $end === false || $end <= $start) return null;

    $slice = substr($raw, $start, $end - $start + 1);
    $decoded = json_decode($slice, true);
    if (is_array($decoded)) return $decoded;

    // Last resort: fix common issues (trailing commas, unescaped newlines)
    $slice = preg_replace('/,\s*([\]}])/', '$1', $slice);
    $slice = preg_replace('/\n/', ' ', $slice);
    $decoded = json_decode($slice, true);
    return is_array($decoded) ? $decoded : null;
}

/** Format PHP array as Postgres array literal: {"a","b","c","d"} */
function toPgArray(array $arr): string {
    $parts = array_map(function(string $v): string {
        // Escape backslashes then double-quotes
        $v = str_replace('\\', '\\\\', $v);
        $v = str_replace('"', '\\"', $v);
        return '"' . $v . '"';
    }, array_values($arr));
    return '{' . implode(',', $parts) . '}';
}

function insertQuestion(PDO $db, string $subject, string $standard, string $question,
                        array $options, string $correct, string $explanation): string {
    $stmt = $db->prepare('SELECT id FROM quiz_questions WHERE question = ? AND standard = ? LIMIT 1');
    $stmt->execute([$question, $standard]);
    if ($stmt->fetch()) return 'skip';
    $db->prepare('INSERT INTO quiz_questions (subject,standard,question,options,correct_answer,explanation) VALUES (?,?,?,?,?,?)')
       ->execute([$subject, $standard, $question, toPgArray($options), $correct, $explanation]);
    return 'ok';
}

/* ── Main ── */
$total_ok = $total_skip = $total_err = 0;

foreach ($syllabus as $subject => $config) {
    echo "\n━━ {$subject} ({$config['standard']}) ━━\n";
    foreach ($config['topics'] as $topic) {
        $short = mb_substr($topic, 0, 70);
        echo "  ▸ {$short}\n";
        flush();

        $raw = callGemini(buildPrompt($subject, $topic, $config['lang_hint'], $config['standard']), 3500);
        if (!$raw) { echo "    ✗ Gemini empty\n"; $total_err++; usleep(500000); continue; }

        $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw = preg_replace('/```\s*$/m', '', $raw);
        $raw = trim($raw);

        // Robustly extract the first JSON array from Gemini response
        $questions = extractJsonArray($raw);
        if ($questions === null) {
            echo "    ✗ Could not parse JSON (raw: " . mb_substr($raw, 0, 80) . ")\n";
            $total_err++; usleep(500000); continue;
        }

        foreach ($questions as $q) {
            if (empty($q['question']) || empty($q['options']) || empty($q['correct_answer'])) continue;
            $status = insertQuestion($db, $subject, $config['standard'],
                                     $q['question'], $q['options'],
                                     $q['correct_answer'], $q['explanation'] ?? '');
            if ($status === 'ok')   { $total_ok++;   echo "    ✓ " . mb_substr($q['question'],0,60) . "\n"; }
            if ($status === 'skip') { $total_skip++;  echo "    ⟳ (duplicate)\n"; }
        }
        usleep(400000); // rate limit pause
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "DONE: {$total_ok} added | {$total_skip} skipped | {$total_err} errors\n";
