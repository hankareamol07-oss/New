<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gemini.php';
require_once __DIR__ . '/syllabus.php';

/* ───────────── Standard helpers ───────────── */

/** Human-readable label for a standard, e.g. '4th' → '4थी (5वी Exam Prep)' */
function stdLabel(string $std): string {
    return match($std) {
        '4th'   => '4थी (5वी Exam Prep)',
        '5th'   => '5वी',
        '7th'   => '7वी (8वी Exam Prep)',
        '8th'   => '8वी',
        default => $std,
    };
}

/* ───────────── Session helpers ───────────── */

function getSession(string $key): array {
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM bot_sessions WHERE session_key = ?');
    $stmt->execute([$key]);
    $s = $stmt->fetch();
    if (!$s) {
        $db->prepare('INSERT INTO bot_sessions (session_key) VALUES (?)')->execute([$key]);
        $stmt->execute([$key]);
        $s = $stmt->fetch();
    }
    return $s;
}

function setSession(string $key, array $data): void {
    $db = getDb();
    $sets = []; $vals = [];
    foreach ($data as $col => $val) { $sets[] = "$col = ?"; $vals[] = $val; }
    $vals[] = $key;
    $db->prepare('UPDATE bot_sessions SET ' . implode(', ', $sets) . ', updated_at = CURRENT_TIMESTAMP WHERE session_key = ?')->execute($vals);
}

function resetSession(string $key): void {
    getDb()->prepare('DELETE FROM bot_sessions WHERE session_key = ?')->execute([$key]);
}

/* ───────────── Chip hints per state ───────────── */

function getChips(string $mode): array {
    $map = [
        'greeting'          => [],
        'await_standard'    => ['4', '7'],
        'menu'              => ['1 Quiz','2 AI Study','3 My Progress'],
        'quiz_subject'      => ['1 Math','2 Marathi','3 English','4 Intelligence'],
        'quiz_topic'        => ['0 सर्व Topics'],
        'quiz_active'       => ['A','B','C','D','submit 📊 Submit & Result','0 Menu'],
        'ai_study'          => ['1 Tutor','2 Flashcards','3 Notes','0 Menu'],
        'chat_subject'      => ['1 Math','2 Marathi','3 English','4 Intelligence','0 Menu'],
        'chat_topic'        => ['0 Menu'],
        'chat'              => ['0 Menu'],
        'flashcard_subject' => ['1 Math','2 Marathi','3 English','4 Intelligence','0 Menu'],
        'flashcard_topic'   => ['0 Menu'],
        'notes_subject'     => ['1 Math','2 Marathi','3 English','4 Intelligence','0 Menu'],
        'notes_topic'       => ['0 Menu'],
        'progress_menu'     => ['1 Report','2 Study Planner','3 Leaderboard','4 Syllabus','0 Menu'],
        'planner_menu'      => ['1 Daily','2 Weekly','3 Monthly','0 Menu'],
        'syllabus_subject'  => ['1 Math','2 Marathi','3 English','4 Intelligence','0 Menu'],
    ];
    return $map[$mode] ?? [];
}

/* ───────────── Main dispatcher ───────────── */

function processMessage(string $key, string $rawMsg): string {
    $msg = trim($rawMsg);
    $s   = getSession($key);

    if (in_array(strtolower($msg), ['reset','restart','start','नया'])) {
        resetSession($key);
        return getGreeting();
    }

    // ── Global quick-feature shortcuts (sidebar buttons send !command) ──
    $msgLow = strtolower($msg);
    $quickActions = ['!quiz','!aistudy','!progress','!report','!leaderboard'];
    if (in_array($msgLow, $quickActions)) {
        if (!$s['student_id'] && $msgLow !== '!leaderboard') {
            return "👋 पहिले नाव आणि इयत्ता सांगा — मग हे feature वापरता येईल!\n\n" . getGreeting();
        }
        if ($s['student_id'] && !$s['standard']) {
            setSession($key, ['mode' => 'await_standard']);
            return "कृपया आपली इयत्ता निवडा:\n\n*4* → 4थी\n*7* → 7वी";
        }
        if ($msgLow === '!quiz') {
            setSession($key, ['mode' => 'quiz_subject']);
            return "📖 *विषय निवडा*\n\n1️⃣ गणित (Math)\n2️⃣ मराठी (Marathi)\n3️⃣ English\n4️⃣ बुद्धिमत्ता (Intelligence)\n\nनंबर टाइप करा:";
        }
        if ($msgLow === '!aistudy') {
            setSession($key, ['mode' => 'ai_study']);
            return getAiStudyMenu();
        }
        if ($msgLow === '!report') {
            setSession($key, ['mode' => 'progress_menu']);
            $s = getSession($key);
            return getReportAndBadges($key, $s);
        }
        if ($msgLow === '!leaderboard') {
            setSession($key, ['mode' => 'progress_menu']);
            return getLeaderboard();
        }
        // !progress → progress submenu
        setSession($key, ['mode' => 'progress_menu']);
        return getProgressMenu();
    }

    switch ($s['mode']) {
        case 'greeting':          return doGreeting($key, $msg);
        case 'await_standard':    return doStandard($key, $msg, $s);
        case 'menu':              return doMenu($key, $msg, $s);
        case 'ai_study':          return doAiStudy($key, $msg, $s);
        case 'progress_menu':     return doProgressMenu($key, $msg, $s);
        case 'planner_menu':      return doPlannerMenu($key, $msg, $s);
        case 'quiz_subject':      return doQuizSubject($key, $msg, $s);
        case 'quiz_topic':        return doQuizTopic($key, $msg, $s);
        case 'quiz_active':       return doAnswer($key, $msg, $s);
        case 'chat_subject':      return doChatSubject($key, $msg, $s);
        case 'chat_topic':        return doChatTopic($key, $msg, $s);
        case 'chat':              return doChat($key, $msg, $s);
        case 'flashcard_subject': return doFlashcardSubject($key, $msg, $s);
        case 'flashcard_topic':   return doFlashcardTopic($key, $msg, $s);
        case 'notes_subject':     return doNotesSubject($key, $msg, $s);
        case 'notes_topic':       return doNotesTopic($key, $msg, $s);
        case 'syllabus_subject':  return doSyllabusSubject($key, $msg, $s);
        default:
            setSession($key, ['mode' => 'greeting']);
            return getGreeting();
    }
}

/* ───────────── Greeting ───────────── */

function getGreeting(): string {
    $countdown = examCountdown();
    return "🎓 *Scholarship Bot* मध्ये आपले स्वागत!\n"
         . "Maharashtra Scholarship Exam AI Prep\n\n"
         . "📅 परीक्षा: *{$countdown}*\n\n"
         . "आपले पूर्ण नाव सांगा:";
}

function doGreeting(string $key, string $msg): string {
    if (mb_strlen($msg) < 2) return "कृपया आपले पूर्ण नाव लिहा:";
    setSession($key, ['mode' => 'await_standard', 'temp_name' => $msg]);
    return "नमस्कार *{$msg}*! 👋\n\nआपण कोणत्या इयत्तेत आहात?\n\n*4* → 4थी इयत्ता (5वी Scholarship Exam)\n*7* → 7वी इयत्ता (8वी Scholarship Exam)";
}

function doStandard(string $key, string $msg, array $s): string {
    $std = null;
    if (in_array($msg, ['4','4th','4थी','4वी'])) $std = '4th';
    if (in_array($msg, ['7','7th','7वी']))        $std = '7th';
    if (!$std) return "कृपया *4* (4थी) किंवा *7* (7वी) टाइप करा:";

    $db   = getDb();
    $name = $s['temp_name'];
    $stmt = $db->prepare('SELECT id FROM students WHERE name = ? AND standard = ? LIMIT 1');
    $stmt->execute([$name, $std]);
    $student = $stmt->fetch();
    if (!$student) {
        $db->prepare('INSERT INTO students (name, standard) VALUES (?,?)')->execute([$name, $std]);
        $stmt->execute([$name, $std]);
        $student = $stmt->fetch();
    }
    $db->prepare('UPDATE students SET login_streak = login_streak + 1, last_active_at = CURRENT_TIMESTAMP WHERE id = ?')
       ->execute([$student['id']]);

    setSession($key, ['mode' => 'menu', 'student_id' => $student['id'], 'standard' => $std, 'temp_name' => null]);
    return "✅ *{$name}* ({$std}) — तयार!\n\n" . getMenu();
}

/* ───────────── Menu ───────────── */

function getMenu(): string {
    return "📚 *मुख्य Menu*\n\n"
         . "1️⃣  Quiz — Practice Questions 🧠\n"
         . "2️⃣  AI Study — Tutor · Flashcards · Notes 🤖\n"
         . "3️⃣  My Progress & Planner 📊\n\n"
         . "नंबर टाइप करा:";
}

function getAiStudyMenu(): string {
    return "🤖 *AI Study*\n\n"
         . "1️⃣  AI Tutor — प्रश्न विचारा\n"
         . "2️⃣  Flashcards — Quick Revision 🃏\n"
         . "3️⃣  AI Notes — Topic Summary 📝\n\n"
         . "नंबर टाइप करा: _(Menu → 0)_";
}

function getProgressMenu(): string {
    return "📊 *My Progress & Planner*\n\n"
         . "1️⃣  My Report — Score & Badges 🎖\n"
         . "2️⃣  Study Planner — Daily/Weekly/Monthly 📅\n"
         . "3️⃣  Leaderboard 🏆\n"
         . "4️⃣  Syllabus — सर्व Topics 📋\n\n"
         . "नंबर टाइप करा: _(Menu → 0)_";
}

function doMenu(string $key, string $msg, array $s): string {
    switch (strtolower(trim($msg))) {
        case '1': case 'quiz':
            setSession($key, ['mode' => 'quiz_subject']);
            return "📖 *विषय निवडा*\n\n1️⃣ गणित (Math)\n2️⃣ मराठी (Marathi)\n3️⃣ English\n4️⃣ बुद्धिमत्ता (Intelligence)\n\nनंबर टाइप करा:";
        case '2': case 'ai study': case 'aistudy': case 'ai':
            setSession($key, ['mode' => 'ai_study']);
            return getAiStudyMenu();
        case '3': case 'progress': case 'report': case 'planner':
            setSession($key, ['mode' => 'progress_menu']);
            return getProgressMenu();
        case '0': case 'menu':
            return getMenu();
        default:
            return "कृपया 1, 2 किंवा 3 टाइप करा.\n\n" . getMenu();
    }
}

/* ───────────── AI Study Submenu ───────────── */

function doAiStudy(string $key, string $msg, array $s): string {
    switch (strtolower(trim($msg))) {
        case '1': case 'tutor':
            setSession($key, ['mode' => 'chat_subject', 'subject' => null, 'quiz_topic_filter' => '']);
            return subjectPickerPrompt('🤖', 'AI Tutor — विषय निवडा');
        case '2': case 'flashcards': case 'flashcard':
            setSession($key, ['mode' => 'flashcard_subject', 'subject' => null]);
            return subjectPickerPrompt('🃏', 'Flashcards — विषय निवडा');
        case '3': case 'notes':
            setSession($key, ['mode' => 'notes_subject', 'subject' => null]);
            return subjectPickerPrompt('📝', 'AI Notes — विषय निवडा');
        case '0': case 'menu':
            setSession($key, ['mode' => 'menu']);
            return getMenu();
        default:
            return "कृपया 1, 2 किंवा 3 टाइप करा.\n\n" . getAiStudyMenu();
    }
}

/* ───────────── Progress & Planner Submenu ───────────── */

function doProgressMenu(string $key, string $msg, array $s): string {
    switch (strtolower(trim($msg))) {
        case '1': case 'report': case 'badges':
            return getReportAndBadges($key, $s);
        case '2': case 'planner': case 'study planner':
            setSession($key, ['mode' => 'planner_menu']);
            return "📅 *Study Planner*\n\n"
                 . "1️⃣  Daily Planner — आजचा अभ्यास\n"
                 . "2️⃣  Weekly Planner — या आठवड्याचा अभ्यास\n"
                 . "3️⃣  Monthly Planner — महिन्याचा अभ्यास\n\n"
                 . "_(परीक्षा: Feb 15, 2027 नुसार तयार केला जाईल)_\n\n"
                 . "नंबर टाइप करा: _(Menu → 0)_";
        case '3': case 'leaderboard':
            return getLeaderboard();
        case '4': case 'syllabus': case 'topics':
            setSession($key, ['mode' => 'syllabus_subject']);
            return "📋 *Syllabus — Topics*\n\nकोणत्या विषयाचे topics पहायचे?\n\n1️⃣ गणित (Math)\n2️⃣ मराठी (Marathi)\n3️⃣ English\n4️⃣ बुद्धिमत्ता (Intelligence)\n\nनंबर टाइप करा:";
        case '0': case 'menu':
            setSession($key, ['mode' => 'menu']);
            return getMenu();
        default:
            return "कृपया 1, 2, 3 किंवा 4 टाइप करा.\n\n" . getProgressMenu();
    }
}

function doPlannerMenu(string $key, string $msg, array $s): string {
    switch (strtolower(trim($msg))) {
        case '1': case 'daily':   return doStudyPlanner('daily', $key, $s);
        case '2': case 'weekly':  return doStudyPlanner('weekly', $key, $s);
        case '3': case 'monthly': return doStudyPlanner('monthly', $key, $s);
        case '0': case 'menu':
            setSession($key, ['mode' => 'menu']);
            return getMenu();
        default:
            return "कृपया 1, 2 किंवा 3 टाइप करा.\n\n"
                 . "📅 *Study Planner*\n\n1️⃣  Daily\n2️⃣  Weekly\n3️⃣  Monthly\n\n_(Menu → 0)_";
    }
}

function doStudyPlanner(string $type, string $key, array $s): string {
    setSession($key, ['mode' => 'menu']);

    if (!$s['student_id']) return "Report साठी नोंदणी आवश्यक आहे.\n\n" . getMenu();

    $db  = getDb();
    $sid = $s['student_id'];
    $std = $s['standard'] ?? '4th';
    $stdLabel = stdLabel($std);

    $examDate = new DateTime('2027-02-15');
    $today    = new DateTime('today');
    $daysLeft = (int)$today->diff($examDate)->days;

    // Covered topics
    $stmt = $db->prepare(
        "SELECT DISTINCT qq.topic, qq.subject
         FROM performance_logs pl
         JOIN quiz_questions qq ON qq.id = pl.question_id
         WHERE pl.student_id = ? AND qq.topic != ''"
    );
    $stmt->execute([$sid]);
    $covered = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $coveredStr = empty($covered)
        ? "No topics covered yet"
        : implode(", ", array_map(fn($r) => "{$r['subject']}:{$r['topic']}", $covered));

    // Overall accuracy
    $stmt2 = $db->prepare(
        "SELECT COUNT(*) as total, SUM(is_correct::int) as correct FROM performance_logs WHERE student_id = ?"
    );
    $stmt2->execute([$sid]);
    $perf = $stmt2->fetch();
    $accuracy = $perf['total'] > 0 ? round($perf['correct'] / $perf['total'] * 100) : 0;

    $typeLabels = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
    $typeLabel  = $typeLabels[$type] ?? 'Daily';
    $emojis     = ['daily' => '📅', 'weekly' => '📆', 'monthly' => '🗓️'];
    $emoji      = $emojis[$type] ?? '📅';

    $extraInstructions = match($type) {
        'daily'   => "Create a TODAY's study schedule (6 AM to 9 PM) with hour-by-hour plan. Include short breaks. Mention exactly which topics/subtopics to cover today. End with tonight's revision task.",
        'weekly'  => "Create a 7-day plan (Day 1 to Day 7). Each day: one main subject focus + revision of previous day. Include practice quiz targets (e.g. 'do 10 Math questions'). Sunday = full revision day.",
        'monthly' => "Create a 4-week plan (Week 1, Week 2, Week 3, Week 4). Week 4 = full revision + mock tests. List subjects and key chapters for each week. Include daily question targets.",
        default   => '',
    };

    $prompt = <<<PROMPT
Create a {$typeLabel} study planner for a Maharashtra Scholarship Exam student.

STUDENT DETAILS:
- Standard: {$stdLabel}
- Days until exam (Feb 15, 2027): {$daysLeft} days
- Topics covered so far: {$coveredStr}
- Overall quiz accuracy: {$accuracy}%

INSTRUCTIONS:
{$extraInstructions}

RULES:
- Do NOT start with any greeting or introduction.
- Start IMMEDIATELY with the plan.
- Write in Marathi (use Marathi for topic names, instructions).
- Use numbered lists and clear structure.
- Be specific with time allocations and topic names.
- Include a motivating tip at the end (1 line only).
PROMPT;

    $result = callGemini($prompt, 900);
    if (!$result) return "Study Planner तयार करता आले नाही. पुन्हा प्रयत्न करा.\n\n" . getMenu();

    return "{$emoji} *{$typeLabel} Study Planner*\n"
         . "_{$stdLabel} — परीक्षा: Feb 15, 2027 ({$daysLeft} दिवस बाकी)_\n\n"
         . $result
         . "\n\n" . getMenu();
}

/* ───────────── Quiz ───────────── */

function doQuizSubject(string $key, string $msg, array $s): string {
    $map = ['1'=>'Math','2'=>'Marathi','3'=>'English','4'=>'Intelligence'];
    $subject = $map[trim($msg)] ?? null;
    if (!$subject) return "कृपया 1, 2, 3 किंवा 4 टाइप करा.";

    // Fetch available topics for this subject/standard from DB
    $db   = getDb();
    $qStd = quizStandard($s['standard'] ?? '5th');
    $rows = $db->prepare(
        "SELECT topic, COUNT(*) as cnt FROM quiz_questions
         WHERE subject = ? AND standard = ? AND topic != ''
         GROUP BY topic ORDER BY cnt DESC"
    );
    $rows->execute([$subject, $qStd]);
    $topics = $rows->fetchAll(PDO::FETCH_ASSOC);

    if (empty($topics)) {
        // No topic data — start quiz directly
        setSession($key, ['mode'=>'quiz_active','subject'=>$subject,'score'=>0,'total_asked'=>0,'quiz_topic_filter'=>'']);
        return nextQuestion($key, $subject, $s['standard'] ?? '5th');
    }

    setSession($key, ['mode'=>'quiz_topic','subject'=>$subject,'score'=>0,'total_asked'=>0,'quiz_topic_filter'=>'']);

    $topicLabels = ['Math'=>'गणित','Marathi'=>'मराठी','English'=>'English','Intelligence'=>'बुद्धिमत्ता'];
    $out = "📚 *{$topicLabels[$subject]} — Topic निवडा*\n\n";
    $out .= "*0* → 🔀 सर्व Topics (All — Random)\n";
    foreach ($topics as $i => $row) {
        $num  = $i + 1;
        $out .= "*{$num}* → {$row['topic']} _({$row['cnt']} प्रश्न)_\n";
    }
    $out .= "\nनंबर टाइप करा:";
    return $out;
}

function doQuizTopic(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['menu', 'back'])) {
        setSession($key, ['mode'=>'quiz_subject']);
        return "📖 *विषय निवडा*\n\n1️⃣ गणित (Math)\n2️⃣ मराठी (Marathi)\n3️⃣ English\n4️⃣ बुद्धिमत्ता (Intelligence)\n\nनंबर टाइप करा:";
    }

    $choice = trim($msg);
    $subject = $s['subject'] ?? 'Math';
    $std     = $s['standard'] ?? '5th';
    $qStd    = quizStandard($std);

    if ($choice === '0') {
        // All topics
        setSession($key, ['mode'=>'quiz_active','quiz_topic_filter'=>'']);
        return nextQuestion($key, $subject, $std);
    }

    if (!ctype_digit($choice) || (int)$choice < 1) {
        return "कृपया एक नंबर टाइप करा किंवा *0* सर्व Topics साठी:";
    }

    // Fetch the chosen topic
    $db   = getDb();
    $rows = $db->prepare(
        "SELECT topic FROM quiz_questions
         WHERE subject = ? AND standard = ? AND topic != ''
         GROUP BY topic ORDER BY COUNT(*) DESC"
    );
    $rows->execute([$subject, $qStd]);
    $topicList = $rows->fetchAll(PDO::FETCH_COLUMN);

    $idx   = (int)$choice - 1;
    $topic = $topicList[$idx] ?? null;

    if (!$topic) {
        return "हा नंबर उपलब्ध नाही. पुन्हा प्रयत्न करा:";
    }

    setSession($key, ['mode'=>'quiz_active','quiz_topic_filter'=>$topic]);
    return "✅ *{$topic}* topic निवडला!\n\n" . nextQuestion($key, $subject, $std);
}

/**
 * Map student's current standard to the exam-level standard for question lookup.
 * 4th std students prepare for 5th exam; 7th for 8th exam.
 */
function quizStandard(string $std): string {
    return match($std) { '4th' => '5th', '7th' => '8th', default => $std };
}

function nextQuestion(string $key, string $subject, string $standard): string {
    $db      = getDb();
    $qStd    = quizStandard($standard);

    // Get session to check topic filter
    $s     = getSession($key);
    $topic = trim($s['quiz_topic_filter'] ?? '');

    if ($topic !== '') {
        $stmt = $db->prepare('SELECT * FROM quiz_questions WHERE subject = ? AND standard = ? AND topic = ? ORDER BY RANDOM() LIMIT 1');
        $stmt->execute([$subject, $qStd, $topic]);
        $q = $stmt->fetch();
    } else {
        $stmt = $db->prepare('SELECT * FROM quiz_questions WHERE subject = ? AND standard = ? ORDER BY RANDOM() LIMIT 1');
        $stmt->execute([$subject, $qStd]);
        $q = $stmt->fetch();
    }

    if (!$q) {
        // Try the other exam level as a last resort (only when no topic filter)
        if ($topic === '') {
            $alt = ($qStd === '5th') ? '8th' : '5th';
            $stmt2 = $db->prepare('SELECT * FROM quiz_questions WHERE subject = ? AND standard = ? ORDER BY RANDOM() LIMIT 1');
            $stmt2->execute([$subject, $alt]);
            $q = $stmt2->fetch();
        }
    }
    if (!$q) {
        setSession($key, ['mode'=>'menu']);
        return "या topic मध्ये प्रश्न उपलब्ध नाहीत.\n\n" . getMenu();
    }
    setSession($key, ['current_qid' => $q['id']]);
    $opts   = parseOptions($q['options']);
    $labels = ['A','B','C','D'];
    $optText = '';
    foreach ($opts as $i => $o) $optText .= "\n" . ($labels[$i] ?? '') . ". " . trim($o, ' "\'');

    $topicTag = ($topic !== '') ? " · _{$topic}_" : '';
    return "❓ *{$subject}*{$topicTag}\n\n{$q['question']}{$optText}\n\n_(A/B/C/D) | Menu → 0_";
}

function doAnswer(string $key, string $msg, array $s): string {
    $cmd = strtolower(trim($msg));

    if (in_array($cmd, ['0','menu'])) {
        setSession($key, ['mode'=>'menu']);
        $sc=$s['score']; $tot=$s['total_asked'];
        $pct = $tot>0 ? round($sc/$tot*100) : 0;
        return "Quiz बंद.\n📊 Score: {$sc}/{$tot} ({$pct}%)\n\n" . getMenu();
    }

    if ($cmd === 'submit') {
        return submitQuizResult($key, $s);
    }

    $qId = $s['current_qid'];
    if (!$qId) { setSession($key,['mode'=>'menu']); return getMenu(); }
    $db   = getDb();
    $stmt = $db->prepare('SELECT * FROM quiz_questions WHERE id = ?');
    $stmt->execute([$qId]);
    $q = $stmt->fetch();
    if (!$q) { setSession($key,['mode'=>'menu']); return getMenu(); }

    $opts    = parseOptions($q['options']);
    $map     = ['A'=>0,'B'=>1,'C'=>2,'D'=>3];
    $idx     = $map[strtoupper(trim($msg))] ?? -1;
    $given   = $idx >= 0 ? trim($opts[$idx] ?? '', ' "\'') : trim($msg);
    $correct = trim($q['correct_answer'], ' "\'');
    $isOk    = strtolower($given) === strtolower($correct);

    if ($s['student_id']) {
        $db->prepare('INSERT INTO performance_logs (student_id,question_id,subject,is_correct,time_to_answer_sec) VALUES (?,?,?,?,?)')
           ->execute([$s['student_id'],$qId,$s['subject'],$isOk?1:0,12]);
    }

    $newScore = ($s['score']??0) + ($isOk?1:0);
    $newTotal = ($s['total_asked']??0) + 1;
    setSession($key, ['score'=>$newScore,'total_asked'=>$newTotal]);

    if ($isOk) {
        $reply = "✅ *बरोबर! शाबास!* 🎉\nउत्तर: *{$correct}*";
    } else {
        $expl = !empty($q['explanation']) ? $q['explanation']
            : (callGemini("Explain in 2 simple Marathi sentences why '{$correct}' is the correct answer to: {$q['question']}", 120) ?: '');
        $reply = "❌ *चूक!*\nतुमचे: {$given} | बरोबर: *{$correct}*"
               . ($expl ? "\n\n💡 {$expl}" : '');
    }

    // Check for new badge earned
    $badgeMsg = '';
    if ($s['student_id']) $badgeMsg = checkNewBadge($s['student_id'], $newTotal);

    $reply .= "\n\n📊 Score: {$newScore}/{$newTotal}" . $badgeMsg;
    return $reply . "\n\n─────\n" . nextQuestion($key, $s['subject'], $s['standard'] ?? '5th');
}

/* ───────────── Quiz Result (Submit) ───────────── */

function submitQuizResult(string $key, array $s): string {
    $sc  = (int)($s['score']        ?? 0);
    $tot = (int)($s['total_asked']  ?? 0);
    $sub = $s['subject']  ?? '';
    $topic = trim($s['quiz_topic_filter'] ?? '');

    setSession($key, ['mode' => 'menu', 'quiz_topic_filter' => '']);

    if ($tot === 0) {
        return "अजून कोणताही प्रश्न सोडवला नाही.\n\n" . getMenu();
    }

    $pct   = round($sc / $tot * 100);
    $wrong = $tot - $sc;

    // Grade & emoji
    if ($pct >= 90)      { $grade = 'A+'; $emoji = '🏆'; $msg = 'उत्कृष्ट! शाबास!'; }
    elseif ($pct >= 75)  { $grade = 'A';  $emoji = '🎯'; $msg = 'छान! आणखी सराव करा.'; }
    elseif ($pct >= 60)  { $grade = 'B';  $emoji = '👍'; $msg = 'बरे आहे. अजून मेहनत करा!'; }
    elseif ($pct >= 40)  { $grade = 'C';  $emoji = '📚'; $msg = 'अभ्यास वाढवा!'; }
    else                 { $grade = 'D';  $emoji = '💪'; $msg = 'जास्त सराव हवा. हार मानू नका!'; }

    $bar = progressBar($pct);

    // Pull per-subject stats from performance_logs if student is logged in
    $extraStats = '';
    if (!empty($s['student_id'])) {
        $db = getDb();
        $stmt = $db->prepare(
            "SELECT subject,
                    COUNT(*) as total,
                    SUM(is_correct::int) as correct
             FROM performance_logs
             WHERE student_id = ?
             GROUP BY subject ORDER BY subject"
        );
        $stmt->execute([$s['student_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $extraStats = "\n\n📈 *एकूण सर्व Quiz Performance:*\n";
            foreach ($rows as $r) {
                $sp  = $r['total'] > 0 ? round($r['correct'] / $r['total'] * 100) : 0;
                $sem = $sp >= 75 ? '🟢' : ($sp >= 50 ? '🟡' : '🔴');
                $extraStats .= "{$sem} {$r['subject']}: {$r['correct']}/{$r['total']} ({$sp}%)\n";
            }
        }
    }

    $topicLine = $topic !== '' ? "\n🏷 Topic: *{$topic}*" : '';
    $subLine   = $sub   !== '' ? "\n📖 विषय: *{$sub}*"   : '';

    $out  = "━━━━━━━━━━━━━━━━━━━━━\n";
    $out .= "{$emoji} *Quiz Result Card*\n";
    $out .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $out .= "{$subLine}{$topicLine}\n\n";
    $out .= "✅ बरोबर  : *{$sc}*\n";
    $out .= "❌ चुकीचे : *{$wrong}*\n";
    $out .= "📝 एकूण   : *{$tot}*\n\n";
    $out .= "🎯 Score  : *{$sc}/{$tot} ({$pct}%)*\n";
    $out .= "🏅 Grade  : *{$grade}*\n\n";
    $out .= "{$bar}\n\n";
    $out .= "💬 _{$msg}_";
    $out .= $extraStats;
    $out .= "\n\n" . getMenu();

    return $out;
}

/* ───────────── Syllabus / Topics ───────────── */

function doSyllabusSubject(string $key, string $msg, array $s): string {
    global $SYLLABUS;

    if (in_array(strtolower(trim($msg)), ['0','menu'])) {
        setSession($key, ['mode' => 'menu']);
        return getMenu();
    }

    $map = ['1'=>'Math','2'=>'Marathi','3'=>'English','4'=>'Intelligence'];
    $subject = $map[trim($msg)] ?? null;
    if (!$subject) return "कृपया 1, 2, 3 किंवा 4 टाइप करा.\n_(Menu → 0)_";

    $std    = quizStandard($s['standard'] ?? '5th');
    $topics = $SYLLABUS[$std][$subject] ?? [];

    setSession($key, ['mode' => 'menu']);

    if (empty($topics)) {
        return "या विषयाचे topics सापडले नाहीत.\n\n" . getMenu();
    }

    $stdLabel = ($std === '5th') ? 'PSE (5वी)' : 'UPS (8वी)';
    $emojis   = ['📌','📌','📌','📌','📌','📌','📌','📌','📌','📌',
                  '📌','📌','📌','📌','📌','📌','📌','📌','📌','📌',
                  '📌','📌','📌','📌','📌','📌','📌','📌','📌','📌',
                  '📌','📌','📌','📌','📌','📌','📌','📌'];

    $out = "📋 *{$subject} — Syllabus Topics ({$stdLabel})*\n";
    $out .= "एकूण: " . count($topics) . " topics\n\n";

    foreach ($topics as $i => $topic) {
        $num  = str_pad((string)($i + 1), 2, ' ', STR_PAD_LEFT);
        $out .= "{$num}. {$topic}\n";
    }

    $out .= "\n💡 _Quiz मध्ये हे सर्व topics येतात!_\n\n";
    $out .= "_(Flashcards किंवा Notes साठी topic नाव टाइप करा)_\n\n";
    $out .= getMenu();

    return $out;
}

/* ───────────── AI Feature Helpers ───────────── */

function subjectPickerPrompt(string $icon, string $title): string {
    return "{$icon} *{$title}*\n\nविषय निवडा:\n\n"
         . "1️⃣  गणित (Math)\n"
         . "2️⃣  मराठी (Marathi)\n"
         . "3️⃣  English\n"
         . "4️⃣  बुद्धिमत्ता (Intelligence)\n\n"
         . "नंबर टाइप करा: _(Menu → 0)_";
}

function resolveSubject(string $msg): ?string {
    $map = [
        '1' => 'Math',        'math' => 'Math',        'गणित' => 'Math',
        '2' => 'Marathi',     'marathi' => 'Marathi',  'मराठी' => 'Marathi',
        '3' => 'English',     'english' => 'English',  'इंग्रजी' => 'English',
        '4' => 'Intelligence','intelligence' => 'Intelligence','बुद्धिमत्ता' => 'Intelligence',
    ];
    return $map[strtolower(trim($msg))] ?? null;
}

function topicPickerData(string $subject, string $standard): array {
    $db   = getDb();
    $qStd = quizStandard($standard);
    $stmt = $db->prepare(
        "SELECT DISTINCT topic FROM quiz_questions
         WHERE subject = ? AND standard = ? AND topic != ''
         ORDER BY topic"
    );
    $stmt->execute([$subject, $qStd]);
    $topics = array_column($stmt->fetchAll(), 'topic');

    $icons = ['Math' => '🔢', 'Marathi' => '📖', 'English' => '🔤', 'Intelligence' => '🧠'];
    $names = ['Math' => 'गणित', 'Marathi' => 'मराठी', 'English' => 'English', 'Intelligence' => 'बुद्धिमत्ता'];
    $stdLabel = stdLabel($standard);

    $msg  = "{$icons[$subject]} *{$names[$subject]} — {$stdLabel} Topics*\n\n";
    foreach ($topics as $i => $t) {
        $msg .= "*" . ($i + 1) . "* → {$t}\n";
    }
    $msg .= "\nनंबर टाइप करा किंवा topic नाव लिहा:\n_(Menu → 0)_";

    return ['msg' => $msg, 'topics' => $topics];
}

function resolveTopic(string $msg, array $topics): string {
    $trimmed = trim($msg);
    if (ctype_digit($trimmed)) {
        $idx = (int)$trimmed - 1;
        if ($idx >= 0 && isset($topics[$idx])) return $topics[$idx];
    }
    return $trimmed;
}

/* ───────────── AI Tutor ───────────── */

function doChatSubject(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['0', 'menu'])) { setSession($key, ['mode' => 'menu']); return getMenu(); }
    $subject = resolveSubject($msg);
    if (!$subject) return "कृपया 1, 2, 3 किंवा 4 टाइप करा.\n\n" . subjectPickerPrompt('🤖', 'AI Tutor — विषय निवडा');
    setSession($key, ['mode' => 'chat_topic', 'subject' => $subject]);
    $s = getSession($key);
    $data = topicPickerData($subject, $s['standard'] ?? '5th');
    return "🤖 *AI Tutor*\n\n" . $data['msg'];
}

function doChatTopic(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['0', 'menu'])) { setSession($key, ['mode' => 'menu']); return getMenu(); }
    $subject  = $s['subject']  ?? 'Math';
    $standard = $s['standard'] ?? '5th';
    $data  = topicPickerData($subject, $standard);
    $topic = resolveTopic($msg, $data['topics']);
    if (mb_strlen($topic) < 1) return "Topic निवडा:\n\n" . $data['msg'];
    setSession($key, ['mode' => 'chat', 'quiz_topic_filter' => $topic]);
    $names = ['Math' => 'गणित', 'Marathi' => 'मराठी', 'English' => 'English', 'Intelligence' => 'बुद्धिमत्ता'];
    $stdLabel = stdLabel($standard);
    return "🤖 *AI Tutor — {$names[$subject]}: {$topic}*\n\n"
         . "या topic बद्दल काहीही विचारा! ({$stdLabel} स्तरावर समजावून सांगतो)\n\n"
         . "_(Menu → 0)_";
}

function doChat(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['0', 'menu'])) { setSession($key, ['mode' => 'menu']); return getMenu(); }
    $std      = $s['standard'] ?? '5th';
    $stdLabel = stdLabel($std);
    $topic    = $s['quiz_topic_filter'] ?? '';
    $topicCtx = $topic ? " You are focused on the topic \"{$topic}\"." : '';
    $prompt   = <<<PROMPT
You are TechEdu.in AI tutor for Maharashtra scholarship exams ({$stdLabel} standard).{$topicCtx}
STRICT RULES:
- Do NOT start with any greeting, namaste, or introduction.
- Answer the student's question DIRECTLY and immediately.
- Use simple Marathi. Give concrete examples, formulas, or steps where useful.
- Keep answer to 4-6 sentences. End with one encouraging line.
- If the question has a formula or calculation, show it clearly.

Student's question: {$msg}
PROMPT;
    $ans = callGemini($prompt, 350);
    return "🤖 " . ($ans ?: "माफ करा, पुन्हा प्रयत्न करा.") . "\n\n_(Menu → 0)_";
}

/* ───────────── Flashcards ───────────── */

function doFlashcardSubject(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['0', 'menu'])) { setSession($key, ['mode' => 'menu']); return getMenu(); }
    $subject = resolveSubject($msg);
    if (!$subject) return "कृपया 1, 2, 3 किंवा 4 टाइप करा.\n\n" . subjectPickerPrompt('🃏', 'Flashcards — विषय निवडा');
    setSession($key, ['mode' => 'flashcard_topic', 'subject' => $subject]);
    $s = getSession($key);
    $data  = topicPickerData($subject, $s['standard'] ?? '5th');
    $names = ['Math' => 'गणित', 'Marathi' => 'मराठी', 'English' => 'English', 'Intelligence' => 'बुद्धिमत्ता'];
    return "🃏 *Flashcards — {$names[$subject]}*\n\n" . $data['msg'];
}

function doFlashcardTopic(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['0', 'menu'])) { setSession($key, ['mode' => 'menu']); return getMenu(); }
    $subject  = $s['subject']  ?? '';
    $standard = $s['standard'] ?? '5th';
    $topic    = $msg;
    if ($subject) {
        $data  = topicPickerData($subject, $standard);
        $topic = resolveTopic($msg, $data['topics']);
        if (mb_strlen(trim($topic)) < 2) return "Topic निवडा:\n\n" . $data['msg'];
    }
    if (mb_strlen(trim($topic)) < 2) return "Topic नाव सांगा:";
    setSession($key, ['mode' => 'menu']);
    $stdLabel = stdLabel($standard);
    $prompt = <<<PROMPT
Create exactly 5 flashcards for Maharashtra scholarship exam ({$stdLabel} standard) on topic: "{$topic}".

OUTPUT FORMAT — follow exactly, no deviation:
🃏 Q1: [exam-style question in Marathi]
💡 A1: [answer, max 12 words, in Marathi]

🃏 Q2: [question]
💡 A2: [answer]

🃏 Q3: [question]
💡 A3: [answer]

🃏 Q4: [question]
💡 A4: [answer]

🃏 Q5: [question]
💡 A5: [answer]

RULES:
- Do NOT write any greeting, introduction, or conclusion.
- Start the output IMMEDIATELY with 🃏 Q1:
- Include formulas, definitions, or key facts relevant to the topic.
- Make questions exam-worthy — definitions, calculations, fill-in-the-blank style.
PROMPT;
    $result = callGemini($prompt, 600);
    if (!$result) return "Flashcards तयार करता आले नाही. पुन्हा प्रयत्न करा.\n\n" . getMenu();
    return "🃏 *Flashcards: {$topic}*\n\n" . $result . "\n\n" . getMenu();
}

/* ───────────── AI Notes ───────────── */

function doNotesSubject(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['0', 'menu'])) { setSession($key, ['mode' => 'menu']); return getMenu(); }
    $subject = resolveSubject($msg);
    if (!$subject) return "कृपया 1, 2, 3 किंवा 4 टाइप करा.\n\n" . subjectPickerPrompt('📝', 'AI Notes — विषय निवडा');
    setSession($key, ['mode' => 'notes_topic', 'subject' => $subject]);
    $s = getSession($key);
    $data  = topicPickerData($subject, $s['standard'] ?? '5th');
    $names = ['Math' => 'गणित', 'Marathi' => 'मराठी', 'English' => 'English', 'Intelligence' => 'बुद्धिमत्ता'];
    return "📝 *AI Notes — {$names[$subject]}*\n\n" . $data['msg'];
}

function doNotesTopic(string $key, string $msg, array $s): string {
    if (in_array(strtolower(trim($msg)), ['0', 'menu'])) { setSession($key, ['mode' => 'menu']); return getMenu(); }
    $subject  = $s['subject']  ?? '';
    $standard = $s['standard'] ?? '5th';
    $topic    = $msg;
    if ($subject) {
        $data  = topicPickerData($subject, $standard);
        $topic = resolveTopic($msg, $data['topics']);
        if (mb_strlen(trim($topic)) < 2) return "Topic निवडा:\n\n" . $data['msg'];
    }
    if (mb_strlen(trim($topic)) < 2) return "Topic नाव सांगा:";
    setSession($key, ['mode' => 'menu']);
    $stdLabel = stdLabel($standard);
    $prompt = <<<PROMPT
Write study notes for Maharashtra scholarship exam ({$stdLabel} standard) on topic: "{$topic}".

OUTPUT FORMAT — follow exactly:
📌 व्याख्या: [one-line definition in Marathi]

🔑 मुख्य मुद्दे:
• [key point 1 — include formula/rule if applicable]
• [key point 2]
• [key point 3]
• [key point 4]
• [key point 5]
• [key point 6]

⭐ लक्षात ठेवा:
• [important fact or trick 1]
• [important fact or trick 2]

📝 उदाहरण: [1 solved example with working, if topic has calculation/formula]

RULES:
- Do NOT write any greeting, introduction, or closing line.
- Start the output IMMEDIATELY with 📌 व्याख्या:
- Write in simple Marathi. Include formulas, units, and examples where relevant.
- Each bullet point must be clear and exam-focused.
PROMPT;
    $result = callGemini($prompt, 600);
    if (!$result) return "Notes तयार करता आले नाही. पुन्हा प्रयत्न करा.\n\n" . getMenu();
    return "📝 *AI Notes: {$topic}*\n\n" . $result . "\n\n" . getMenu();
}

/* ───────────── Leaderboard ───────────── */

function getLeaderboard(): string {
    $db   = getDb();
    $rows = $db->query("
        SELECT s.name, s.standard,
               COUNT(p.id)                  AS attempts,
               SUM(p.is_correct::int)       AS correct,
               ROUND(AVG(p.is_correct::int)*100) AS accuracy
        FROM students s
        JOIN performance_logs p ON p.student_id = s.id
        GROUP BY s.id, s.name, s.standard
        HAVING COUNT(p.id) >= 5
        ORDER BY accuracy DESC, correct DESC
        LIMIT 10
    ")->fetchAll();

    if (empty($rows)) {
        return "🏆 *Leaderboard*\n\nअजून पुरेसे data नाही.\nQuiz खेळा आणि leaderboard वर या!\n\n" . getMenu();
    }

    $medals = ['🥇','🥈','🥉','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟'];
    $out = "🏆 *Top Students Leaderboard*\n\n";
    foreach ($rows as $i => $r) {
        $out .= "{$medals[$i]} *{$r['name']}* ({$r['standard']}) — {$r['accuracy']}% ({$r['attempts']} प्रश्न)\n";
    }
    return $out . "\n" . getMenu();
}

/* ───────────── Report & Badges ───────────── */

function getReportAndBadges(string $key, array $s): string {
    if (!($s['student_id'] ?? null)) return "Report साठी प्रथम नोंदणी करा.\n\n" . getMenu();

    $db   = getDb();
    $sid  = $s['student_id'];
    $std  = $s['standard'] ?? '4th';

    $stmt = $db->prepare('SELECT * FROM performance_logs WHERE student_id = ?');
    $stmt->execute([$sid]);
    $logs = $stmt->fetchAll();

    if (empty($logs)) {
        return "📊 *Report*\n\nअजून Quiz दिला नाही.\nQuiz खेळा आणि progress बघा! 💪\n\n" . getProgressMenu();
    }

    $tot = count($logs);
    $cor = count(array_filter($logs, fn($l) => (int)$l['is_correct']));
    $pct = $tot > 0 ? round($cor / $tot * 100) : 0;

    // Bot usage: each question attempt ≈ 2 min; estimate total minutes
    $usageMins = max(1, (int)round($tot * 2));
    $usageStr  = $usageMins >= 60
        ? floor($usageMins / 60) . ' तास ' . ($usageMins % 60) . ' मिनिट'
        : $usageMins . ' मिनिट';

    // Days to exam
    $examDate = new DateTime('2027-02-15');
    $today    = new DateTime('today');
    $daysLeft = (int)$today->diff($examDate)->days;

    // Topics covered (from performance_logs joined to quiz_questions)
    $covStmt = $db->prepare(
        "SELECT DISTINCT qq.subject, qq.topic
         FROM performance_logs pl
         JOIN quiz_questions qq ON qq.id = pl.question_id
         WHERE pl.student_id = ? AND qq.topic != ''"
    );
    $covStmt->execute([$sid]);
    $coveredRows = $covStmt->fetchAll(PDO::FETCH_ASSOC);
    $coveredBySubject = [];
    foreach ($coveredRows as $r) {
        $coveredBySubject[$r['subject']][] = $r['topic'];
    }

    // Total topics available per subject from DB
    $qStd      = quizStandard($std);
    $allTopics = $db->prepare(
        "SELECT subject, COUNT(DISTINCT topic) as cnt FROM quiz_questions
         WHERE standard = ? AND topic != '' GROUP BY subject"
    );
    $allTopics->execute([$qStd]);
    $totalBySubject = [];
    foreach ($allTopics->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $totalBySubject[$r['subject']] = (int)$r['cnt'];
    }

    $out  = "━━━━━━━━━━━━━━━━━━━━━\n";
    $out .= "📊 *Overall Report Card*\n";
    $out .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

    // Summary box
    $out .= "⏱ *Bot वापर:* {$usageStr}\n";
    $out .= "🧠 *एकूण Quiz Score:* {$cor}/{$tot} ({$pct}%)\n";
    $out .= progressBar($pct) . "\n";
    $out .= "📅 *परीक्षा:* Feb 15, 2027 _{$daysLeft} दिवस बाकी_\n";

    // Today's target
    $todayStmt = $db->prepare(
        "SELECT COUNT(*) FROM performance_logs WHERE student_id = ? AND DATE(created_at) = CURRENT_DATE"
    );
    $todayStmt->execute([$sid]);
    $todayCount = (int)$todayStmt->fetchColumn();
    $goal = 10;
    $goalIcon = $todayCount >= $goal ? '✅' : '🎯';
    $out .= "📌 *आजचे target:* {$todayCount}/{$goal} प्रश्न {$goalIcon}\n\n";

    // Per-subject stats + topics remaining
    $subjects     = ['Math','Marathi','English','Intelligence'];
    $subjectNames = ['Math'=>'गणित','Marathi'=>'मराठी','English'=>'English','Intelligence'=>'बुद्धिमत्ता'];
    $out .= "─ *विषयनिहाय Progress* ─\n";

    foreach ($subjects as $sub) {
        $sl  = array_values(array_filter($logs, fn($l) => $l['subject'] === $sub));
        $tot2 = count($sl);
        if (!$tot2) continue;
        $cor2  = count(array_filter($sl, fn($l) => (int)$l['is_correct']));
        $pct2  = round($cor2 / $tot2 * 100);
        $em    = $pct2 >= 75 ? '🟢' : ($pct2 >= 50 ? '🟡' : '🔴');
        $covCount  = count($coveredBySubject[$sub] ?? []);
        $totalTop  = $totalBySubject[$sub]  ?? 0;
        $remaining = max(0, $totalTop - $covCount);
        $topicLine = $totalTop > 0
            ? " | 📚 Topics: {$covCount}/{$totalTop} _(बाकी: {$remaining})_"
            : '';
        $out .= "{$em} *{$subjectNames[$sub]}*: {$cor2}/{$tot2} ({$pct2}%){$topicLine}\n";
    }

    // Badges
    $badges = computeBadges($logs, $s);
    if (!empty($badges)) {
        $out .= "\n🎖 *तुमचे Badges:*\n";
        foreach ($badges as $b) $out .= "  {$b}\n";
    }

    // Streak
    $stmtStreak = $db->prepare('SELECT login_streak FROM students WHERE id = ?');
    $stmtStreak->execute([$sid]);
    $streak = (int)$stmtStreak->fetchColumn();
    $out .= "\n🔥 *Login Streak:* {$streak} दिवस";

    return $out . "\n\n" . getProgressMenu();
}

function computeBadges(array $logs, array $s): array {
    $badges = [];
    $total  = count($logs);
    $correct= count(array_filter($logs, fn($l) => (int)$l['is_correct']));
    $pct    = $total > 0 ? round($correct/$total*100) : 0;
    $avgSpd = $total > 0 ? array_sum(array_column($logs,'time_to_answer_sec'))/$total : 999;
    $subjects = array_unique(array_column($logs,'subject'));
    $streak = (int)($s['login_streak'] ?? 0);

    if ($total >= 1)           $badges[] = '🌱 Beginner — पहिला Quiz!';
    if ($total >= 10)          $badges[] = '🎮 Quiz Player — 10 प्रश्न पूर्ण';
    if ($total >= 50)          $badges[] = '📚 Quiz Champ — 50 प्रश्न पूर्ण';
    if ($total >= 100)         $badges[] = '🏅 Century Club — 100 प्रश्न!';
    if ($pct >= 70 && $total >= 10) $badges[] = '🎯 Accurate — 70%+ अचूकता';
    if ($pct >= 90 && $total >= 20) $badges[] = '🏆 Scholar — 90%+ महारथी!';
    if ($avgSpd <= 8 && $total >= 10) $badges[] = '⚡ Speed King — सर्वात जलद!';
    if (count($subjects) >= 4) $badges[] = '🌍 All-Rounder — सर्व विषय';
    if ($streak >= 3)          $badges[] = "🔥 Streak Hero — {$streak} दिवस";
    if ($streak >= 7)          $badges[] = '🌟 Super Streak — 7 दिवस!';

    return $badges;
}

function checkNewBadge(int $studentId, int $totalAnswered): string {
    // Only check milestone badges
    $milestones = [10 => '🎮 Quiz Player', 50 => '📚 Quiz Champ', 100 => '🏅 Century Club!'];
    if (isset($milestones[$totalAnswered])) {
        return "\n\n🎖 *नवीन Badge मिळाला!* " . $milestones[$totalAnswered];
    }
    return '';
}

/* ───────────── Utilities ───────────── */

function examCountdown(): string {
    $examDate = new DateTime('2027-02-15');
    $today    = new DateTime('today');
    $diff     = $today->diff($examDate);
    $days     = (int)$diff->format('%R%a');
    if ($days <= 0) return 'परीक्षा आली! All the best! 🎉';
    return "15 Feb 2027 — {$days} दिवस बाकी";
}

function progressBar(int $pct): string {
    $filled = (int)round($pct / 10);
    $empty  = 10 - $filled;
    return str_repeat('█', $filled) . str_repeat('░', $empty) . " {$pct}%";
}

function parseOptions($raw): array {
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;
    $raw = trim($raw, '{}');
    return array_map(fn($o) => trim($o, ' "\''), explode(',', $raw));
}
