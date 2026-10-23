<?php
/**
 * 4th Standard PSE Quiz Question Seeder
 * Seeds questions for Marathi, Math, English, Intelligence (IQ)
 * Based on official MSCEPUPPSS PSE 4th standard syllabus.
 *
 * Access: /seed_4th.php?secret=<ADMIN_SEED_SECRET env or default 'seed2026'>
 * Progress is streamed live. Safe to re-run (duplicate questions skipped).
 */

$secret = getenv('ADMIN_SEED_SECRET') ?: 'seed2026';
if (($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    echo '<h2>403 Forbidden</h2><p>Pass ?secret= query param.</p>'; exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gemini.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>4th Std Seeder</title>
<style>body{font-family:monospace;background:#0f172a;color:#94a3b8;padding:24px}
h1{color:#f97316}span.ok{color:#22c55e}span.skip{color:#64748b}span.err{color:#ef4444}
span.sub{color:#60a5fa;font-weight:700}p{margin:2px 0}</style></head><body>
<h1>🎓 4th Std PSE Question Seeder</h1>';
flush_output();

$db = getDb();

/* ── Syllabus topics ──────────────────────────────────────────────────────── */
$syllabus = [

  'Marathi' => [
    'standard'   => '4th',
    'lang_hint'  => 'Marathi',
    'topics' => [
      "वर्णमाला — स्वर व व्यंजन ओळख, अक्षर-उच्चार, जोडाक्षरे",
      "शब्दसंपत्ती — समानार्थी शब्द (synonyms), विरुद्धार्थी शब्द (antonyms)",
      "शब्दसंपत्ती — एकवचन-अनेकवचन, लिंग (पुल्लिंग/स्त्रीलिंग)",
      "व्याकरण — नाम, सर्वनाम, विशेषण, क्रियापद",
      "व्याकरण — वर्तमानकाळ, भूतकाळ, भविष्यकाळ",
      "वाक्यप्रकार — विधानार्थी, प्रश्नार्थी, उद्गारार्थी, आज्ञार्थी",
      "म्हणी व वाक्प्रचार — अर्थ ओळखणे",
      "उताऱ्यावर आधारित प्रश्न — छोट्या गद्यउताऱ्यावर प्रश्नोत्तरे",
    ],
  ],

  'Math' => [
    'standard'   => '4th',
    'lang_hint'  => 'Marathi (mix with numbers/equations as needed)',
    'topics' => [
      "संख्या ज्ञान — 1 ते 9999 संख्या, स्थानिक किंमत (units, tens, hundreds, thousands), मोठी-लहान संख्या",
      "बेरीज व वजाबाकी — 4-digit numbers, carry/borrow operations",
      "गुणाकार — tables 2 ते 10, 2-digit × 1-digit multiplication",
      "भागाकार — simple division with remainder, 2-digit ÷ 1-digit",
      "अपूर्णांक — proper fractions, equal fractions, half/quarter/three-quarter",
      "वेळ — घड्याळ वाचणे, तास-मिनिटे, वेळेची बेरीज-वजाबाकी",
      "पैसे — रुपये-पैसे, खरेदी-विक्री, सुट्टे पैसे",
      "मोजमाप — लांबी (मीटर/सेंटीमीटर), वजन (किलोग्राम/ग्राम), क्षमता (लिटर/मिलिलिटर)",
      "भूमिती — त्रिकोण, चतुर्भुज, वर्तुळ, घन, घनगोल, दंडगोल ओळख; परिमिती",
      "आकृतिबंध (Patterns) — संख्या-रचना patterns, आकाराचे patterns",
    ],
  ],

  'English' => [
    'standard'   => '4th',
    'lang_hint'  => 'English',
    'topics' => [
      "Vocabulary — Rhyming words, Opposite words, Word register; finding small words in big words",
      "Vocabulary — Parts of human body; names of birds and animals, their living places and sounds",
      "Vocabulary — Comparisons (as...as...); colours, shapes, vegetables, fruits, games",
      "Punctuation Marks — Comma, Full stop, Question mark, Exclamation mark, Apostrophe, Capitalization",
      "Numerical Information — Days of the week, Months of the year, Cardinal and Ordinal numbers",
      "Grammar — Nouns and Pronouns; Singular and Plural",
      "Grammar — Articles (a, an, the); Prepositions; Adverbs",
      "Grammar — Tenses: Simple Present, Simple Past, Simple Future",
      "Grammar — Verbs: action words; Adjectives: describing words",
      "Stock Expressions — Greetings; instructions and expressions",
    ],
  ],

  'Intelligence' => [
    'standard'   => '4th',
    'lang_hint'  => 'Marathi (mix English where needed for clarity)',
    'topics' => [
      "संख्यामालिका (Number Series) — missing number in ascending/descending/skip-count patterns",
      "सादृश्यता (Analogy) — Word analogy: A is to B as C is to ?",
      "वर्गीकरण (Classification / Odd one out) — find the one that does not belong",
      "दिशाज्ञान (Direction Sense) — simple compass directions N/S/E/W, left/right turns",
      "कोडिंग-डीकोडिंग (Coding-Decoding) — letter/number coding patterns",
      "दर्पण प्रतिमा (Mirror Image) — identify correct mirror reflection of a shape or letter",
      "गणितीय तर्क (Mathematical Reasoning) — simple word-problem logic, age problems",
      "आकृती पूर्ण करणे (Figure Completion) — pattern matrices, missing piece in 3×3 grid",
    ],
  ],
];

/* ── Gemini prompt template ───────────────────────────────────────────────── */
function buildPrompt(string $subject, string $topic, string $lang, string $std): string {
    return <<<PROMPT
You are creating MCQ quiz questions for the Maharashtra PSE (Primary Scholarship Exam) {$std} standard exam.
Subject: {$subject}
Topic: {$topic}
Language: {$lang}

Generate EXACTLY 4 multiple-choice questions on this topic.
Return ONLY a valid JSON array with this exact structure (no markdown, no extra text):
[
  {
    "question": "...",
    "options": ["option1", "option2", "option3", "option4"],
    "correct_answer": "exact text of correct option",
    "explanation": "one sentence explanation in Marathi"
  }
]

Rules:
- Questions must be appropriate for {$std} grade level students
- All 4 options must be plausible but only one correct
- correct_answer must match exactly one of the options
- Keep questions concise (under 20 words)
- Keep options concise (under 8 words each)
- For Marathi subject: use Marathi language
- For Math: mix Marathi labels with numbers
- For English: use English
- For Intelligence: use the specified language
PROMPT;
}

/* ── Helpers ──────────────────────────────────────────────────────────────── */
function flush_output(): void { if (ob_get_level()) ob_flush(); flush(); }

function insertQuestion(PDO $db, string $subject, string $standard, string $question,
                        array $options, string $correct, string $explanation): string
{
    $stmt = $db->prepare('SELECT id FROM quiz_questions WHERE question = ? AND standard = ? LIMIT 1');
    $stmt->execute([$question, $standard]);
    if ($stmt->fetch()) return 'skip';

    $db->prepare('INSERT INTO quiz_questions (subject, standard, question, options, correct_answer, explanation)
                  VALUES (?,?,?,?,?,?)')
       ->execute([$subject, $standard, $question, json_encode($options, JSON_UNESCAPED_UNICODE), $correct, $explanation]);
    return 'ok';
}

/* ── Main loop ────────────────────────────────────────────────────────────── */
$total_ok = 0; $total_skip = 0; $total_err = 0;

foreach ($syllabus as $subject => $config) {
    echo "<p><br><span class='sub'>━━ {$subject} ({$config['standard']}) ━━</span></p>";
    flush_output();

    foreach ($config['topics'] as $topic) {
        $shortTopic = mb_substr($topic, 0, 60);
        echo "<p>  ▸ {$shortTopic}…</p>";
        flush_output();

        $prompt   = buildPrompt($subject, $topic, $config['lang_hint'], $config['standard']);
        $raw      = callGemini($prompt, 800);

        if (!$raw) {
            echo "<p>    <span class='err'>✗ Gemini returned empty</span></p>";
            $total_err++;
            flush_output();
            continue;
        }

        // Strip markdown code fences if present
        $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw = preg_replace('/```\s*$/m', '', $raw);
        $raw = trim($raw);

        // Extract JSON array
        if (!preg_match('/\[.*\]/s', $raw, $m)) {
            echo "<p>    <span class='err'>✗ No JSON array found</span></p>";
            $total_err++;
            flush_output();
            continue;
        }

        $questions = json_decode($m[0], true);
        if (!is_array($questions)) {
            echo "<p>    <span class='err'>✗ JSON parse error</span></p>";
            $total_err++;
            flush_output();
            continue;
        }

        foreach ($questions as $q) {
            if (empty($q['question']) || empty($q['options']) || empty($q['correct_answer'])) continue;
            $status = insertQuestion(
                $db, $subject, $config['standard'],
                $q['question'], $q['options'],
                $q['correct_answer'], $q['explanation'] ?? ''
            );
            if ($status === 'ok')   { $total_ok++;   echo "<p>    <span class='ok'>  ✓ " . htmlspecialchars(mb_substr($q['question'],0,60)) . "</span></p>"; }
            if ($status === 'skip') { $total_skip++; echo "<p>    <span class='skip'>  ⟳ (already exists)</span></p>"; }
            flush_output();
        }

        // Small delay to avoid rate limiting
        usleep(300000);
    }
}

echo "<p><br><span class='ok'>━━ DONE: {$total_ok} added, {$total_skip} skipped, {$total_err} errors ━━</span></p>";
echo "<p><a href='/admin.php' style='color:#f97316'>← Back to Admin</a></p>";
echo '</body></html>';
