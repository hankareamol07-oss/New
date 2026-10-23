<?php
/**
 * CLI seeder for 7th std TL (Third Language / English) and IQ (Intelligence)
 * from official MSCEPUPPSS 7th standard Marathi Medium PDFs.
 * Stored as standard='8th' (quizStandard('7th') → '8th').
 * Run: php run_seed_tl_iq.php [English|Intelligence]
 */
declare(strict_types=1);
set_time_limit(0);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gemini.php';

$db = getDb();

$syllabus = [
  'English' => [
    'standard'  => '8th',
    'lang_hint' => 'English',
    'topics' => [
      // Vocabulary
      "Vocabulary — Writing words using clues and contextual meanings",
      "Vocabulary — Singular and Plural; Opposite and Similar meaning words",
      "Vocabulary — Word Formation (prefixes, suffixes, compound words)",
      "Vocabulary — Phrases and their meanings",
      "Vocabulary — One word substitution for given clues",
      "Vocabulary — Figures of speech: Simile, Metaphor, Personification, Hyperbole",
      "Vocabulary — Homophones (words that sound alike but differ in meaning/spelling)",
      // Word Puzzles
      "Word Puzzles — Crossword puzzles and Riddles",
      "Word Puzzles — Word Grid (finding hidden words in a letter grid)",
      // Language Study
      "Language Study — Sentence Formation and Types of Sentences",
      "Language Study — Parts of Speech (noun, pronoun, verb, adjective, adverb, preposition, conjunction, interjection)",
      "Language Study — Modal Auxiliaries (can, could, may, might, shall, will, should, would, must)",
      "Language Study — Tenses: Simple Present, Present Progressive, Present Perfect",
      "Language Study — Tenses: Simple Past, Past Progressive, Past Perfect",
      "Language Study — Tenses: Simple Future, Future Progressive, Future Perfect",
      // Grammar
      "Grammar — Punctuation marks and their correct use",
      "Grammar — Direct and Indirect Speech (Statements only)",
      "Grammar — Degree of Comparison (Positive, Comparative, Superlative in statements)",
      // Creative Writing
      "Creative Writing — Short Note writing",
      "Creative Writing — Dialogue writing",
      "Creative Writing — Slogans and Advertisements",
      "Creative Writing — News writing; E-mail and Website language",
      "Creative Writing — Stock Expressions (greetings, requests, apologies, formal phrases)",
      // Reading Skills
      "Reading Skills — Prose comprehension (60–70 words): factual and inferential questions",
      // Miscellaneous
      "Miscellaneous — Games vocabulary and rules description",
      "Miscellaneous — Clock: reading time, calculating elapsed time, AM/PM",
    ],
  ],
  'Intelligence' => [
    'standard'  => '8th',
    'lang_hint' => 'Marathi with English terms where needed',
    'topics' => [
      // आकलन
      "आकलन — सूचनापालन: वर्णनावर आधारित प्रश्न; मजकूर व संख्यांमधून माहिती काढणे",
      "आकलन — इंग्रजी अक्षरमाला क्रम: अक्षरांची स्थाने, श्रेणी, A-Z ओळख",
      // वर्गीकरण
      "वर्गीकरण — शब्दसंग्रह: गटात न बसणारा शब्द ओळखा (Odd One Out - words)",
      "वर्गीकरण — आकृत्या: गटात न बसणारी आकृती ओळखा (Odd One Out - figures)",
      "वर्गीकरण — संख्या: गटात न बसणारी संख्या ओळखा (Odd One Out - numbers)",
      "वर्गीकरण — अक्षरमाला: गटात न बसणारे अक्षरगट ओळखा (Odd One Out - letters)",
      // समसंबंध
      "समसंबंध (Analogy) — शब्दसंग्रह: शब्दांमधील संबंध ओळखून रिकामी जागा भरा",
      "समसंबंध (Analogy) — आकृत्या: आकृत्यांमधील संबंध ओळखणे",
      "समसंबंध (Analogy) — संख्या: संख्यांमधील नियम ओळखून रिकामी जागा भरा",
      "समसंबंध (Analogy) — अक्षरमाला: अक्षरांमधील संबंध ओळखणे",
      // क्रम
      "क्रम (Series) — संख्यामालिका: चुकीचे पद ओळखणे व रिकामी जागा भरणे",
      "क्रम (Series) — आकृती मालिका: पुढील आकृती ओळखणे",
      "क्रम (Series) — अक्षरमाला मालिका: इंग्रजी अक्षरांचा क्रम पूर्ण करणे",
      // गटाशी जुळणारे पद
      "गटाशी जुळणारे पद — संख्या: दिलेल्या गटाशी जुळणारी संख्या निवडणे",
      "गटाशी जुळणारे पद — शब्द: दिलेल्या गटाशी जुळणारा शब्द निवडणे",
      "गटाशी जुळणारे पद — आकृती: दिलेल्या गटाशी जुळणारी आकृती निवडणे",
      // लपवब्द मांडणी
      "लपवब्द मांडणी (Coding) — अंक सांकेतिक भाषा: संख्यांचे कोड ओळखणे",
      "लपवब्द मांडणी (Coding) — अक्षर सांकेतिक भाषा: अक्षरांचे कोड ओळखणे",
      "लपवब्द मांडणी (Coding) — चिन्ह सांकेतिक भाषा: चिन्हांचे कोड ओळखणे",
      // जलप्रतिबिंब व आरशातील प्रतिमा
      "जलप्रतिबिंब (Water Image) — अंक, अक्षरे व आकृत्यांचे जलप्रतिबिंब ओळखणे",
      "आरशातील प्रतिमा (Mirror Image) — अंक, अक्षरे व आकृत्यांची आरशातील प्रतिमा ओळखणे",
      // तर्क व अनुमान
      "तर्क व अनुमान (भाषिक) — वय, वेळ व घड्याळ, नाते यावरील प्रश्न",
      "तर्क व अनुमान (भाषिक) — माहितीवरून अनुमान काढणे (logical deduction)",
      "तर्क व अनुमान (अभाषिक) — घनाकृती ठोकळे मोजणे; त्रिकोण व चौकोन मोजणे",
      // कूटप्रश्न
      "कूटप्रश्न — रांगेतील स्थान (Ranking): रांगेत स्थान शोधणे",
      "कूटप्रश्न — दिशांवरील प्रश्न (Direction Sense): दिशा व अंतर",
      "कूटप्रश्न — दिनदर्शिका (Calendar): वार शोधणे, लीप वर्ष",
      "कूटप्रश्न — वेन आकृती (Venn Diagram): संच व त्यांचे छेद",
      "कूटप्रश्न — गणिती कोडे; संख्यांच्या मांडणीतील सूत्र ओळखणे",
      // सांकेतिक भाषा
      "सांकेतिक भाषा (Symbol/Coding) — आकृत्या, अंक व अक्षरे यांचे कोडिंग-डीकोडिंग",
      // मनोरे
      "मनोरे/रचना (Towers/Stacking) — अंक, अक्षरे व चिन्हे यांची रचना व नियम ओळखणे",
      // आकृतीचे पृथक्करण
      "आकृतीचे पृथक्करण — अपूर्ण आकृती पूर्ण करणे; तंतोतंत आकृती ओळखणे",
      "आकृतीचे पृथक्करण — घडीच्या आकृत्या; लपलेली आकृती शोधणे",
    ],
  ],
];

/* ── Helpers ── */
function buildPrompt(string $subject, string $topic, string $lang, string $std): string {
    $grade = '7th/8th';
    return <<<PROMPT
You are creating MCQ quiz questions for the Maharashtra PSE (Upper Primary Scholarship Exam) {$grade} standard.
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
- Age-appropriate for 12-13 year old students
- Exactly 4 options per question, only one correct
- correct_answer must be the EXACT text of one option (no paraphrasing)
- Options and questions must be concise (question ≤ 25 words, option ≤ 10 words)
- For Intelligence (Marathi): write questions and options in Marathi
- For English (TL): write questions and options in English
PROMPT;
}

function extractJsonArray(string $raw): ?array {
    $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
    $raw = preg_replace('/```\s*$/m', '', $raw);
    $raw = trim($raw);
    $start = strpos($raw, '[');
    $end   = strrpos($raw, ']');
    if ($start === false || $end === false || $end <= $start) return null;
    $slice = substr($raw, $start, $end - $start + 1);
    $decoded = json_decode($slice, true);
    if (is_array($decoded)) return $decoded;
    $slice = preg_replace('/,\s*([\]}])/', '$1', $slice);
    $slice = preg_replace('/\n/', ' ', $slice);
    $decoded = json_decode($slice, true);
    return is_array($decoded) ? $decoded : null;
}

function toPgArray(array $arr): string {
    $parts = array_map(function(string $v): string {
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
$filterSubject = $argv[1] ?? null;

foreach ($syllabus as $subject => $config) {
    if ($filterSubject && strcasecmp($subject, $filterSubject) !== 0) continue;
    echo "\n━━ {$subject} ({$config['standard']}) ━━\n";
    foreach ($config['topics'] as $topic) {
        $short = mb_substr($topic, 0, 70);
        echo "  ▸ {$short}\n";
        flush();

        $raw = callGemini(buildPrompt($subject, $topic, $config['lang_hint'], $config['standard']), 3500);
        if (!$raw) { echo "    ✗ Gemini empty\n"; $total_err++; usleep(500000); continue; }

        $questions = extractJsonArray($raw);
        if ($questions === null) {
            echo "    ✗ JSON parse failed (raw: " . mb_substr($raw, 0, 80) . ")\n";
            $total_err++; usleep(500000); continue;
        }

        foreach ($questions as $q) {
            if (empty($q['question']) || empty($q['options']) || empty($q['correct_answer'])) continue;
            $status = insertQuestion($db, $subject, $config['standard'],
                                     $q['question'], $q['options'],
                                     $q['correct_answer'], $q['explanation'] ?? '');
            if ($status === 'ok')   { $total_ok++;  echo "    ✓ " . mb_substr($q['question'],0,60) . "\n"; }
            if ($status === 'skip') { $total_skip++; echo "    ⟳ (duplicate)\n"; }
        }
        usleep(400000);
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "DONE: {$total_ok} added | {$total_skip} skipped | {$total_err} errors\n";
