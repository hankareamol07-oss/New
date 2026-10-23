<?php
/**
 * CLI seeder for 7th standard PSE questions (stored as standard='8th' in DB,
 * because quizStandard('7th') returns '8th').
 * Run: php run_seed_7th.php
 */
declare(strict_types=1);
set_time_limit(0);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gemini.php';

$db = getDb();

/* ── Syllabus topics (from official MSCEPUPPSS 7th std Marathi Medium PDFs) ── */
$syllabus = [
  'Marathi' => [
    'standard'  => '8th',
    'lang_hint' => 'Marathi',
    'topics' => [
      "उतारा व त्यावर आधारित प्रश्न — गद्यउतारा वाचून प्रश्नांची उत्तरे",
      "कविता व त्यावर आधारित प्रश्न — कवितेचा आशय, भावार्थ",
      "सुसंगत वाक्यांचा परिच्छेद — शब्दांचा योग्य क्रम, सुसंगत परिच्छेद",
      "संवाद व त्यावर आधारित प्रश्न — संवाद आकलन व प्रश्नोत्तरे",
      "समानार्थी शब्द — word pairs with same meaning",
      "विरुद्धार्थी शब्द — antonyms in Marathi",
      "शुद्ध - अशुद्ध शब्द — spelling correction, identify correctly spelled word",
      "आलंकारिक शब्द — उपमा, रूपक, अनुप्रास अलंकार ओळखणे",
      "शब्दसमूहाबद्दल एक शब्द — one word substitution in Marathi",
      "वाक्प्रचार — idioms and their correct meanings",
      "म्हणी — proverbs and their correct meanings",
      "पारिभाषिक शब्द — technical/subject-specific Marathi terminology",
      "वर्णविचार — स्वर, व्यंजन, जोडाक्षरे, उच्चारस्थान",
      "संधी - स्वरसंधी — vowel sandhi rules and examples",
      "संधी - व्यंजनसंधी — consonant sandhi rules and examples",
      "संधी - विसर्ग संधी — visarga sandhi rules",
      "संधी - पूर्वरूप संधी — purvarupa sandhi",
      "संधी - पररूप संधी — pararupa sandhi",
      "शब्दांच्या जाती - विकारी व अविकारी — changeable vs unchangeable parts of speech",
      "लिंग — पुल्लिंग, स्त्रीलिंग, नपुंसकलिंग ओळखणे व रूपे",
      "वचन — एकवचन, अनेकवचन रूपांतर",
      "विभक्ती — eight cases (विभक्ती प्रत्यय) and their uses",
      "कारकार्थ व शब्दांचे सामान्यरूप — karak meanings, samanyrupa of nouns",
      "प्रयोग - सकर्मक व अकर्मक कर्तरी प्रयोग — active voice transitive/intransitive",
      "प्रयोग - कर्मणी प्रयोग — passive voice (karmani prayog)",
      "प्रयोग - संकर्मक व अकर्मक भावे प्रयोग — bhave prayog forms",
      "वाक्यांचे प्रकार - केवल वाक्य — simple sentence identification",
      "वाक्यांचे प्रकार - मिश्र वाक्य — complex/mixed sentence",
      "वाक्यांचे प्रकार - संयुक्त वाक्य — compound sentence",
      "वाक्यांचे प्रकार - होकारार्थी व नकारार्थी — affirmative and negative sentences",
      "विरामचिन्हे — punctuation marks: पूर्णविराम, स्वल्पविराम, प्रश्नचिन्ह, उद्गारचिन्ह",
      "काळ — वर्तमानकाळ, भूतकाळ, भविष्यकाळ ओळख",
      "काळांचे प्रकार व क्रियापदाचे अर्थ — tense sub-types and verb meanings",
      "लेखक - कवी यांची टोपण नावे — pen names of famous Marathi authors/poets",
      "लेखक - कवी यांची उपाधी — honorary titles of Marathi writers",
      "साहित्य व साहित्यिक — literary genres and notable Marathi authors",
      "साहित्य क्षेत्रातील पुरस्कार — literary awards (Sahitya Akademi, Dnyanpith etc.)",
    ],
  ],
  'Math' => [
    'standard'  => '8th',
    'lang_hint' => 'Marathi mixed with numbers and equations',
    'topics' => [
      "सम, विषम, मूळ, संयुक्त, सह-अविभाज्य, जुळ्या मूळ संख्या, त्रिकोणी संख्या, रोमन अंक",
      "नैसर्गिक संख्या, पूर्ण संख्या, पूर्णांक, परिमेय संख्या",
      "परिमेय संख्यांवरील क्रिया — बेरीज, वजाबाकी, गुणाकार, भागाकार",
      "संख्यारेषा — संख्यांची मांडणी व तुलना",
      "विभाज्यता — भाजक, विभाज्यतेच्या कसोट्या",
      "वर्ग व वर्गमूळ, घन व घनमूळ",
      "लसावि आणि मसावि — LCM आणि HCF काढणे",
      "अपूर्णांक — दशांश अपूर्णांक व साधे अपूर्णांक",
      "घातांक (Indices) — नियम व उदाहरणे",
      "गुणोत्तर आणि प्रमाण, भागीदारी",
      "विचरण (Variation) — प्रत्यक्ष व व्यस्त प्रमाण",
      "वेळ, काम, वेग — time, work, speed problems",
      "सरासरी (Average) — arithmetic mean calculation",
      "भूमिती — बिंदू, रेषा, कोन, समतल, विरुद्धकोन, आसन्नकोन, पूरककोन, संपूरककोन",
      "समांतर रेषांचे गुणधर्म — संगत कोन, एकांतर कोन, अंत:कोन",
      "वर्तुळ — चाप, केंद्रकोन, चापाचे माप",
      "त्रिकोणाचे गुणधर्म — properties and angle sum",
      "पायथागोरसचे प्रमेय — Pythagorean theorem problems",
      "चतुर्भुजांचे गुणधर्म — चौरस, आयत, समभुज चौकोन, समलंब",
      "भौमितिक आकार — प्रिझम, पिरॅमिड, गोल, फलक-शिरोबिंदू-कोर",
      "मोजमाप — लांबी, वस्तुमान, वेळ, नाणी व नोटा",
      "परिमिती — त्रिकोण, चतुर्भुज, बहुभुज, वर्तुळ",
      "क्षेत्रफळ — त्रिकोण, चौरस, आयत, वर्तुळ",
      "घनफळ व पृष्ठफळ — घनाकृती व चौरसाकृती",
      "मध्यमान, वारंवारिता तक्ता — mean and frequency table",
      "स्तंभालेख, दुहेरी स्तंभालेख — bar graph and double bar graph",
      "टक्केवारी (Percentage) — conversion and word problems",
      "बँक व साधे व्याज — simple interest problems",
      "नफा - तोटा, सवलत, दलाली — profit/loss, discount, commission",
      "बीजगणित — अक्षरांसाठी संख्या, बीजगणितीय राशी व त्यांवरील क्रिया",
      "सूत्रे — बीजगणितीय सूत्रे व विस्तार",
      "एका चलातील रेषीय समीकरण व त्यावर आधारित शब्दसमस्या",
    ],
  ],
  'English' => [
    'standard'  => '8th',
    'lang_hint' => 'English',
    'topics' => [
      "Reading Comprehension — unseen passage, factual and inferential questions",
      "Vocabulary — synonyms, antonyms, one-word substitution",
      "Vocabulary — idioms and phrases, proverbs",
      "Grammar — Parts of Speech: Nouns, Pronouns, Adjectives, Adverbs, Verbs",
      "Grammar — Tenses: all 12 tenses identification and use",
      "Grammar — Active and Passive Voice transformation",
      "Grammar — Direct and Indirect Speech (Narration)",
      "Grammar — Prepositions and Conjunctions",
      "Grammar — Articles (a/an/the) and Determiners",
      "Grammar — Subject-Verb Agreement",
      "Sentence Structure — simple, compound, complex sentences",
      "Punctuation and Spelling — common errors and corrections",
    ],
  ],
  'Intelligence' => [
    'standard'  => '8th',
    'lang_hint' => 'Marathi with English terms where needed',
    'topics' => [
      "संख्यामालिका (Number Series) — arithmetic and geometric progressions, missing terms",
      "सादृश्यता (Analogy) — word analogy, number analogy, figure analogy",
      "वर्गीकरण (Odd One Out) — classify by category, number property, or pattern",
      "कोडिंग-डीकोडिंग — letter shift coding, number coding, symbol coding",
      "दिशाज्ञान (Direction Sense) — multi-step direction and distance problems",
      "रक्तसंबंध (Blood Relations) — family tree reasoning",
      "क्रम व श्रेणी (Ranking and Order) — position in a row/class",
      "वेन आकृती (Venn Diagram) — sets and intersections, logical Venn diagrams",
      "घड्याळ व दिनदर्शिका (Clock & Calendar) — angles between hands, day of the week",
      "दर्पण व जल प्रतिमा (Mirror and Water Image) — reflections of figures and letters",
      "आकृती मालिका (Figure Series / Matrix) — next figure in series, matrix completion",
      "गणितीय तर्क (Mathematical Reasoning) — age problems, logical deductions",
    ],
  ],
];

/* ── Helpers (same as run_seed.php) ── */
function buildPrompt(string $subject, string $topic, string $lang, string $std): string {
    $grade = ($std === '8th') ? '7th/8th' : $std;
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
- Age-appropriate for {$grade} grade students (12-13 year old)
- Exactly 4 options per question, only one correct
- correct_answer must be the EXACT text of one option (no paraphrasing)
- Options and questions must be concise (question ≤ 25 words, option ≤ 10 words)
- For Marathi subject: write questions and options in Marathi
- For Math: use Marathi labels with numbers/equations
- For English: use English
- For Intelligence: use language specified
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

// Optional: filter to one subject via CLI arg, e.g. php run_seed_7th.php Marathi
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
        usleep(400000);
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "DONE: {$total_ok} added | {$total_skip} skipped | {$total_err} errors\n";
