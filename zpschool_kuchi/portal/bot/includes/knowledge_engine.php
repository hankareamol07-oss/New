<?php
declare(strict_types=1);

/**
 * Local Knowledge Search & Bot Response Engine
 * Powered directly by textbook sources database (No external API needed)
 */

/**
 * Format Standard in proper Marathi grammar:
 * १ ली, २ री, ३ री, ४ थी, ५ वी, ६ वी, ७ वी, ८ वी
 */
function formatMarathiStd(int $std): string {
    return match ($std) {
        1 => '१ ली',
        2 => '२ री',
        3 => '३ री',
        4 => '४ थी',
        5 => '५ वी',
        6 => '६ वी',
        7 => '७ वी',
        8 => '८ वी',
        default => "{$std} वी",
    };
}

function getKnowledgeDb(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $path = __DIR__ . '/../data/bot_knowledge.sqlite';
    if (!file_exists($path)) {
        // Fallback to relative path
        $path = dirname(__DIR__, 2) . '/bot/data/bot_knowledge.sqlite';
    }

    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $db;
}

/**
 * Get available subjects for a standard
 */
function getSubjectsForStd(int $std): array {
    $db = getKnowledgeDb();
    $stmt = $db->prepare("SELECT DISTINCT subject FROM chapters WHERE std = ? ORDER BY subject ASC");
    $stmt->execute([$std]);
    $raw = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $list = [];
    foreach ($raw as $s) {
        $clean = str_replace('_', ' ', $s);
        $icon = '📖';
        if (str_contains($s, 'विज्ञान')) $icon = '🔬';
        elseif (str_contains($s, 'परिसर_अभ्यास_1')) $icon = '🔬';
        elseif (str_contains($s, 'परिसर_अभ्यास_2')) $icon = '🏛️';
        elseif (str_contains($s, 'परिसर_अभ्यास')) $icon = '🌿';
        elseif (str_contains($s, 'गणित') || str_contains($s, 'Math')) $icon = '📐';
        elseif (str_contains($s, 'मराठी')) $icon = '📚';
        elseif (str_contains($s, 'इंग्लिश') || str_contains($s, 'English')) $icon = '🔤';
        elseif (str_contains($s, 'हिंदी')) $icon = '🗣️';
        elseif (str_contains($s, 'इतिहास')) $icon = '🏛️';
        elseif (str_contains($s, 'भूगोल')) $icon = '🌍';
        elseif (str_contains($s, 'कला') || str_contains($s, 'कार्यानुभव')) $icon = '🎨';

        $list[] = [
            'id' => $s,
            'name' => $clean,
            'icon' => $icon,
        ];
    }

    // Add Scholarship module for Class 4 & Class 7
    if ($std === 4 || $std === 7) {
        array_unshift($list, [
            'id' => 'scholarship',
            'name' => '🏆 शिष्यवृत्ती परीक्षा (Scholarship Prep)',
            'icon' => '🏆',
            'is_scholarship' => true
        ]);
    }

    return $list;
}

/**
 * Get chapters for a subject in a standard
 */
function getChaptersForSubject(int $std, string $subject): array {
    $db = getKnowledgeDb();
    $stmt = $db->prepare("SELECT id, chapter_num, title, summary FROM chapters WHERE std = ? AND subject = ? ORDER BY chapter_num ASC");
    $stmt->execute([$std, $subject]);
    return $stmt->fetchAll();
}

/**
 * Get topics/chapters for a subject (formatted with Marathi numerals)
 */
function getTopicsForSubject(int $std, string $subject): array {
    $db = getKnowledgeDb();

    if ($subject === 'scholarship') {
        $stmt = $db->prepare("SELECT id, subject as sub_subject, topic_name as title, description FROM scholarship WHERE std = ? ORDER BY id ASC");
        $stmt->execute([$std]);
        $rows = $stmt->fetchAll();
        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id' => (int)$r['id'],
                'title' => $r['title'],
                'display_title' => "🏆 " . $r['title'],
                'sub_subject' => $r['sub_subject'],
                'is_scholarship' => true,
            ];
        }
        return $list;
    }

    $stmt = $db->prepare("SELECT id, chapter_num, title FROM chapters WHERE std = ? AND subject = ? ORDER BY chapter_num ASC");
    $stmt->execute([$std, $subject]);
    $rows = $stmt->fetchAll();

    $marathiDigits = ['0'=>'०', '1'=>'१', '2'=>'२', '3'=>'३', '4'=>'४', '5'=>'५', '6'=>'६', '7'=>'७', '8'=>'८', '9'=>'९'];

    $list = [];
    foreach ($rows as $r) {
        $numStr = (string)$r['chapter_num'];
        $marathiNum = strtr($numStr, $marathiDigits);
        $displayTitle = "{$marathiNum}. {$r['title']}";
        $list[] = [
            'id' => (int)$r['id'],
            'chapter_num' => (int)$r['chapter_num'],
            'title' => $r['title'],
            'display_title' => $displayTitle,
            'is_scholarship' => false,
        ];
    }
    return $list;
}

/**
 * Look up a specific chapter or scholarship topic by name or query
 */
function getTopicDetails(int $std, ?string $subject, string $query): ?array {
    $db = getKnowledgeDb();

    // If text inside quotes exists: e.g. 'लक्षणे सजीवांची' or "लक्षणे सजीवांची"
    if (preg_match("/['\"‘“](.*?)['\"’”]/u", $query, $m)) {
        $clean = trim($m[1]);
    } else {
        $clean = preg_replace('/(वर\s*क्विझ.*?|चा\s*सराव.*?|चा\s*स्वाध्याय.*?|क्विझ.*?|सराव.*?|प्रश्न.*?)$/u', '', $query);
        $clean = trim(preg_replace('/^[\s\x{1F300}-\x{1F9FF}🏆🎯🔬📐📚]*[०-९0-9]+[\.\s\-]+/u', '', $clean));
        $clean = trim(preg_replace('/[🎯\'"‘“’”\?]/u', '', $clean));
    }

    if (mb_strlen($clean) < 2) return null;

    // 1. Check scholarship if subject is scholarship or query mentions it
    if ($subject === 'scholarship' || str_contains($query, 'शिष्यवृत्ती')) {
        $stmt = $db->prepare("SELECT * FROM scholarship WHERE std = ? AND (topic_name LIKE ? OR subject LIKE ?) LIMIT 1");
        $stmt->execute([$std, "%$clean%", "%$clean%"]);
        $sch = $stmt->fetch();
        if ($sch) {
            return [
                'type' => 'scholarship',
                'topic' => $sch,
            ];
        }
    }

    // 2. Check chapters table with subject constraint
    $subjSql = (!empty($subject) && $subject !== 'all' && $subject !== 'scholarship') ? "AND subject = ?" : "";
    $binds = [$std, "%$clean%"];
    if (!empty($subjSql)) $binds[] = $subject;

    $stmt = $db->prepare("SELECT * FROM chapters WHERE std = ? AND title LIKE ? $subjSql LIMIT 1");
    $stmt->execute($binds);
    $ch = $stmt->fetch();

    // 3. Fallback: check chapters table without subject constraint
    if (!$ch) {
        $stmt = $db->prepare("SELECT * FROM chapters WHERE std = ? AND title LIKE ? LIMIT 1");
        $stmt->execute([$std, "%$clean%"]);
        $ch = $stmt->fetch();
    }

    if ($ch) {
        // Fetch top questions for this chapter
        $qStmt = $db->prepare("SELECT id, question, answer, q_type FROM questions WHERE std = ? AND chapter_id = ? AND answer != '' LIMIT 4");
        $qStmt->execute([$std, (int)$ch['id']]);
        $questions = $qStmt->fetchAll();

        if (empty($questions)) {
            $qStmt2 = $db->prepare("SELECT id, question, answer, q_type FROM questions WHERE std = ? AND chapter_title LIKE ? AND answer != '' LIMIT 4");
            $qStmt2->execute([$std, "%" . $ch['title'] . "%"]);
            $questions = $qStmt2->fetchAll();
        }

        return [
            'type' => 'chapter',
            'chapter' => $ch,
            'questions' => $questions,
        ];
    }

    return null;
}

/**
 * Search textbook Q&A and Content using Multi-Keyword Relevance
 */
function searchKnowledge(int $std, ?string $subject, string $query, int $limit = 6): array {
    $db = getKnowledgeDb();
    $cleanQuery = trim($query);
    if (empty($cleanQuery)) return [];

    // Stop-words to ignore in Marathi and English
    $stopWords = [
        'काय', 'आहे', 'करावे', 'म्हणून', 'का', 'कसे', 'कोणता', 'कोणती', 'कोणते', 
        'आणि', 'व', 'या', 'ती', 'ते', 'हा', 'ही', 'हे', 'आहेत', 'होते', 'होती', 
        'कराल', 'सांगा', 'लिहा', 'द्या', 'आहेस', 'माहिती', 'तुम्हाला', 'आपण', 
        'what', 'is', 'the', 'how', 'why', 'who', 'which', 'and', 'or', 'in', 'on', 'of'
    ];

    // Split query into words
    $rawWords = preg_split('/[\s,\.\?!;:_\-\/]+/u', $cleanQuery, -1, PREG_SPLIT_NO_EMPTY);
    $keywords = [];
    foreach ($rawWords as $w) {
        $w = mb_strtolower(trim($w));
        if (mb_strlen($w) >= 2 && !in_array($w, $stopWords)) {
            $keywords[] = $w;
        }
    }

    $results = [];

    // 1. First attempt: Search with all keywords if keywords exist
    if (!empty($keywords)) {
        $whereClauses = [];
        $binds = [$std];

        $subjSql = "";
        if (!empty($subject) && $subject !== 'all' && $subject !== 'scholarship') {
            $subjSql = "AND subject = ?";
            $binds[] = $subject;
        }

        foreach ($keywords as $kw) {
            $whereClauses[] = "(question LIKE ? OR answer LIKE ?)";
            $binds[] = "%$kw%";
            $binds[] = "%$kw%";
        }

        $sql = "SELECT id, chapter_title, subject, q_type, question, answer 
                FROM questions 
                WHERE std = ? $subjSql AND (" . implode(' AND ', $whereClauses) . ") 
                ORDER BY (CASE WHEN answer != '' THEN 0 ELSE 1 END), length(answer) DESC 
                LIMIT ?";
        $binds[] = $limit;

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($binds);
            $qMatches = $stmt->fetchAll();

            foreach ($qMatches as $q) {
                $results[] = [
                    'type' => 'qa',
                    'chapter' => $q['chapter_title'],
                    'subject' => str_replace('_', ' ', $q['subject']),
                    'q_type' => $q['q_type'],
                    'question' => $q['question'],
                    'answer' => $q['answer'],
                ];
            }
        } catch (Throwable $e) {}
    }

    // 2. Second attempt: Search with OR (any keyword match) if not enough results
    if (count($results) < $limit && !empty($keywords)) {
        $whereOr = [];
        $binds = [$std];

        $subjSql = "";
        if (!empty($subject) && $subject !== 'all' && $subject !== 'scholarship') {
            $subjSql = "AND subject = ?";
            $binds[] = $subject;
        }

        foreach ($keywords as $kw) {
            $whereOr[] = "(question LIKE ? OR answer LIKE ?)";
            $binds[] = "%$kw%";
            $binds[] = "%$kw%";
        }

        $existingIds = array_column($results, 'id');
        $notIn = !empty($existingIds) ? "AND id NOT IN (" . implode(',', array_map('intval', $existingIds)) . ")" : "";

        $sql = "SELECT id, chapter_title, subject, q_type, question, answer 
                FROM questions 
                WHERE std = ? $subjSql $notIn AND (" . implode(' OR ', $whereOr) . ") 
                LIMIT ?";
        $binds[] = ($limit - count($results));

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($binds);
            $qMatches = $stmt->fetchAll();

            foreach ($qMatches as $q) {
                $results[] = [
                    'type' => 'qa',
                    'chapter' => $q['chapter_title'],
                    'subject' => str_replace('_', ' ', $q['subject']),
                    'q_type' => $q['q_type'],
                    'question' => $q['question'],
                    'answer' => $q['answer'],
                ];
            }
        } catch (Throwable $e) {}
    }

    // 3. Search chapters table (titles, summary, textbook text)
    if (count($results) < $limit && !empty($keywords)) {
        $cWhere = [];
        $cParams = [$std];

        $subjSql = "";
        if (!empty($subject) && $subject !== 'all' && $subject !== 'scholarship') {
            $subjSql = "AND subject = ?";
            $cParams[] = $subject;
        }

        foreach ($keywords as $kw) {
            $cWhere[] = "(title LIKE ? OR summary LIKE ? OR textbook_text LIKE ?)";
            $cParams[] = "%$kw%";
            $cParams[] = "%$kw%";
            $cParams[] = "%$kw%";
        }

        $cSql = "SELECT id, title, subject, summary 
                 FROM chapters 
                 WHERE std = ? $subjSql AND (" . implode(' OR ', $cWhere) . ") 
                 LIMIT ?";
        $cParams[] = ($limit - count($results));

        try {
            $cStmt = $db->prepare($cSql);
            $cStmt->execute($cParams);
            $cMatches = $cStmt->fetchAll();

            foreach ($cMatches as $c) {
                $results[] = [
                    'type' => 'chapter_summary',
                    'chapter' => $c['title'],
                    'subject' => str_replace('_', ' ', $c['subject']),
                    'summary' => mb_substr($c['summary'], 0, 450) . '...',
                ];
            }
        } catch (Throwable $e) {}
    }

    // 4. Search Scholarship database if Class 4 or 7
    if (($std === 4 || $std === 7) && (empty($subject) || $subject === 'scholarship' || count($results) === 0)) {
        $likePattern = '%' . $cleanQuery . '%';
        $sStmt = $db->prepare("
            SELECT subject, topic_name, description, formulas_or_tricks, sample_questions 
            FROM scholarship 
            WHERE std = ? AND (topic_name LIKE ? OR description LIKE ? OR formulas_or_tricks LIKE ?)
            LIMIT 3
        ");
        $sStmt->execute([$std, $likePattern, $likePattern, $likePattern]);
        $sMatches = $sStmt->fetchAll();
        foreach ($sMatches as $s) {
            $results[] = [
                'type' => 'scholarship',
                'subject' => $s['subject'],
                'topic' => $s['topic_name'],
                'description' => $s['description'],
                'formulas' => $s['formulas_or_tricks'],
                'samples' => json_decode($s['sample_questions'] ?? '[]', true),
            ];
        }
    }

    return $results;
}

/**
 * Generate Practice Quiz from local database
 */
function generateLocalQuiz(int $std, ?string $subject = null, ?int $chapterId = null, int $count = 5): array {
    $db = getKnowledgeDb();

    if ($subject === 'scholarship' && ($std === 4 || $std === 7)) {
        $stmt = $db->prepare("SELECT sample_questions FROM scholarship WHERE std = ? ORDER BY RANDOM() LIMIT 4");
        $stmt->execute([$std]);
        $rows = $stmt->fetchAll();
        $quiz = [];
        foreach ($rows as $r) {
            $qList = json_decode($r['sample_questions'] ?? '[]', true);
            foreach ($qList as $item) {
                if (count($quiz) >= $count) break;
                $opts = $item['opts'] ?? [];
                shuffle($opts);
                $quiz[] = [
                    'id' => rand(1000, 9999),
                    'question' => $item['q'],
                    'options' => $opts,
                    'answer' => $item['a'],
                    'explanation' => $item['exp'] ?? '',
                ];
            }
        }
        return $quiz;
    }

    $sql = "SELECT id, chapter_title, subject, q_type, question, answer FROM questions WHERE std = ? AND answer != '' ";
    $params = [$std];

    if ($chapterId && $chapterId > 0) {
        $sql .= " AND chapter_id = ? ";
        $params[] = $chapterId;
    } elseif ($subject && $subject !== 'all') {
        $sql .= " AND subject = ? ";
        $params[] = $subject;
    }

    $sql .= " ORDER BY RANDOM() LIMIT ?";
    $params[] = $count;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $quiz = [];
    foreach ($rows as $r) {
        $correct = trim($r['answer']);
        $opts = [$correct];

        // Generate plausible options from same subject if available
        $distractorStmt = $db->prepare("SELECT DISTINCT answer FROM questions WHERE std = ? AND subject = ? AND answer != ? AND length(answer) BETWEEN 1 AND 40 ORDER BY RANDOM() LIMIT 3");
        $distractorStmt->execute([$std, $r['subject'], $correct]);
        $distractors = $distractorStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($distractors as $d) {
            if (!in_array($d, $opts) && count($opts) < 4) {
                $opts[] = $d;
            }
        }

        // If true_false
        if ($r['q_type'] === 'true_false' || in_array($correct, ['बरोबर', 'चूक'])) {
            $opts = ['बरोबर', 'चूक'];
        } else {
            // Fill up to 4 if needed
            while (count($opts) < 4) {
                $dummy = ['अमरवेल', 'सूर्यप्रकाश', 'पाणी', 'हवा', 'देवदार', 'बाभूळ'][rand(0, 5)];
                if (!in_array($dummy, $opts)) $opts[] = $dummy;
                else break;
            }
            shuffle($opts);
        }

        $quiz[] = [
            'id' => (int)$r['id'],
            'chapter' => $r['chapter_title'],
            'subject' => str_replace('_', ' ', $r['subject']),
            'type' => $r['q_type'],
            'question' => $r['question'],
            'options' => $opts,
            'answer' => $correct,
        ];
    }

    return $quiz;
}

/**
 * Main AI Bot Message Processor (100% Offline / Local Data Powered)
 */
function handleBotChat(int $std, string $userMessage, ?string $subject = null, string $studentName = 'विद्यार्थी'): array {
    $msg = trim($userMessage);
    $msgLower = mb_strtolower($msg);

    $stdLabel = formatMarathiStd($std);

    // Check if user requested to switch or choose topics
    if (str_contains($msgLower, 'दुसरा घटक') || str_contains($msgLower, 'घटक निवडा') || str_contains($msgLower, 'पाठ निवडा') || str_contains($msgLower, 'घटक बदला')) {
        $subj = (!empty($subject) && $subject !== 'all') ? $subject : 'all';
        $topics = getTopicsForSubject($std, $subj);
        if (!empty($topics)) {
            $chips = array_map(fn($t) => $t['display_title'] ?? $t['title'], $topics);
            $chips[] = '🎯 सराव क्विझ';
            return [
                'reply' => "📘 **इयत्ता {$stdLabel} — खालीलपैकी ज्या घटकाचा सराव करायचा आहे तो घटक निवडा:**",
                'chips' => $chips
            ];
        }
    }

    // Check if user requested to change subject
    if (str_contains($msgLower, 'विषय बदला') || str_contains($msgLower, 'सर्व विषय')) {
        $subjs = getSubjectsForStd($std);
        $chips = array_map(fn($s) => $s['icon'] . ' ' . $s['name'], $subjs);
        return [
            'reply' => "📚 **इयत्ता {$stdLabel} — खालीलपैकी विषय निवडा:**",
            'chips' => $chips
        ];
    }

    // Check if user sent/clicked a subject name in chat
    $allSubjs = getSubjectsForStd($std);
    foreach ($allSubjs as $as) {
        $cleanSubj = trim(preg_replace('/^[\s\x{1F300}-\x{1F9FF}]*/u', '', $as['name']));
        $cleanMsg = trim(preg_replace('/^[\s\x{1F300}-\x{1F9FF}]*/u', '', $msg));
        if (mb_strlen($cleanMsg) >= 3 && (mb_stripos($cleanMsg, $cleanSubj) !== false || mb_stripos($cleanSubj, $cleanMsg) !== false)) {
            $topics = getTopicsForSubject($std, $as['id']);
            if (!empty($topics)) {
                $chips = array_map(fn($t) => $t['display_title'] ?? $t['title'], $topics);
                $chips[] = "🎯 {$as['name']} सराव क्विझ";
                return [
                    'reply' => "📘 **{$as['name']} (इयत्ता {$stdLabel}) — घटक व पाठ निवडा:**\nखालीलपैकी ज्या घटकाचा अभ्यास किंवा सराव करायचा आहे, **त्या घटकावर क्लिक करा:**",
                    'chips' => $chips
                ];
            }
        }
    }

    // 1. Check for Quiz request
    if (str_contains($msgLower, 'quiz') || str_contains($msgLower, 'क्विझ') || str_contains($msgLower, 'सराव प्रश्न') || str_contains($msgLower, 'प्रश्नमंजुषा')) {
        $matchedChId = null;
        $matchedTitle = null;
        $topicDetails = getTopicDetails($std, $subject, $msg);
        if ($topicDetails && $topicDetails['type'] === 'chapter') {
            $matchedChId = (int)$topicDetails['chapter']['id'];
            $matchedTitle = $topicDetails['chapter']['title'];
        }

        $quiz = generateLocalQuiz($std, $subject, $matchedChId, 5);
        if (!empty($quiz)) {
            $topicHeader = $matchedTitle ? " ({$matchedTitle})" : "";
            $text = "🎯 **इयत्ता {$stdLabel} सराव क्विझ{$topicHeader} तयार आहे!**\n\n";
            foreach ($quiz as $idx => $q) {
                $num = $idx + 1;
                $text .= "❓ **प्रश्न {$num}:** {$q['question']}\n";
                $letters = ['A', 'B', 'C', 'D'];
                foreach ($q['options'] as $oi => $opt) {
                    $let = $letters[$oi] ?? '-';
                    $text .= "   ({$let}) {$opt}\n";
                }
                $text .= "   💡 *उत्तर:* **{$q['answer']}**\n\n";
            }
            $text .= "👏 *उत्कृष्ट सराव! आणखी प्रश्न सोडवण्यासाठी खालील पर्याय निवडा.*";
            return [
                'reply' => $text,
                'quiz_data' => $quiz,
                'chips' => ['पुढील क्विझ सोडवा', 'या पाठाचा सारांश', 'दुसरा पाठ निवडा', 'विषय बदला']
            ];
        }
    }

    // 2. Check for specific topic or chapter request (e.g. when topic is selected from chat chips)
    $topicDetails = getTopicDetails($std, $subject, $msg);
    if ($topicDetails) {
        if ($topicDetails['type'] === 'chapter') {
            $ch = $topicDetails['chapter'];
            $cleanSubj = str_replace('_', ' ', $ch['subject']);
            $text = "📖 **इयत्ता {$stdLabel} {$cleanSubj} — पाठ {$ch['chapter_num']}: {$ch['title']}**\n\n";

            if (!empty($ch['summary'])) {
                $sum = trim(preg_replace('/^#+\s*.*?\n/u', '', $ch['summary']));
                $sumLines = array_slice(array_filter(explode("\n", $sum)), 0, 4);
                $cleanSum = implode("\n", $sumLines);
                if (mb_strlen($cleanSum) > 350) $cleanSum = mb_substr($cleanSum, 0, 350) . '...';
                $text .= "📌 **पाठ सारांश व मुख्य मुद्दे:**\n{$cleanSum}\n\n";
            }

            if (!empty($topicDetails['questions'])) {
                $text .= "❓ **महत्त्वाचे स्वाध्याय प्रश्न व उत्तरे:**\n";
                foreach ($topicDetails['questions'] as $qi => $q) {
                    $num = $qi + 1;
                    $text .= "• **प्र. {$num}:** {$q['question']}\n";
                    $text .= "  ✅ **उत्तर:** **{$q['answer']}**\n";
                }
                $text .= "\n";
            }

            $text .= "🎯 *या पाठाचा अधिक सराव करण्यासाठी खालील पर्यायांवर क्लिक करा:*";

            return [
                'reply' => $text,
                'chips' => [
                    "🎯 '{$ch['title']}' वर क्विझ सोडवा",
                    "📝 आणखी स्वाध्याय प्रश्न",
                    "📚 दुसरा घटक निवडा",
                    "📐 महत्त्वाचे नियम"
                ]
            ];
        } elseif ($topicDetails['type'] === 'scholarship') {
            $sch = $topicDetails['topic'];
            $text = "🏆 **इयत्ता {$stdLabel} शिष्यवृत्ती — {$sch['subject']}: {$sch['topic_name']}**\n\n";
            $text .= "📖 *संकल्पना:* {$sch['description']}\n\n";
            if (!empty($sch['formulas_or_tricks'])) {
                $text .= "💡 *महत्त्वाचे सूत्र / ट्रिक्स:* **{$sch['formulas_or_tricks']}**\n\n";
            }
            $samples = json_decode($sch['sample_questions'] ?? '[]', true);
            if (!empty($samples)) {
                $text .= "📝 *नमुना प्रश्न व स्पष्टीकरण:*\n";
                foreach ($samples as $s) {
                    $text .= "• **प्रश्न:** {$s['q']}\n";
                    $text .= "  ✅ **उत्तर:** {$s['a']} ({$s['exp']})\n";
                }
                $text .= "\n";
            }
            $text .= "🎯 *या घटकाचा अधिक सराव करण्यासाठी खालील पर्याय निवडा:*";

            return [
                'reply' => $text,
                'chips' => [
                    "🎯 '{$sch['topic_name']}' सराव क्विझ",
                    "💡 आणखी ट्रिक्स",
                    "🏆 इतर शिष्यवृत्ती घटक",
                    "📚 विषय बदला"
                ]
            ];
        }
    }

    // 2. Check for Scholarship request (for Std 4 & 7)
    if (str_contains($msgLower, 'scholarship') || str_contains($msgLower, 'शिष्यवृत्ती') || $subject === 'scholarship') {
        if ($std !== 4 && $std !== 7) {
            return [
                'reply' => "ℹ️ **शिष्यवृत्ती परीक्षा (Scholarship Exam) प्रामुख्याने इयत्ता ४ थी (५ वी परीक्षेची पूर्वतयारी) आणि इयत्ता ७ वी (८ वी परीक्षेची पूर्वतयारी) साठी घेतली जाते.**\n\nआपली सध्याची इयत्ता **{$stdLabel}** आहे. आपण नियमित विषयांचे प्रश्न विचारू शकता!",
                'chips' => ['विज्ञान स्वाध्याय', 'गणित सूत्रे', 'मराठी व्याकरण']
            ];
        }

        $db = getKnowledgeDb();
        $stmt = $db->prepare("SELECT topic_name, subject, description, formulas_or_tricks, sample_questions FROM scholarship WHERE std = ? ORDER BY RANDOM() LIMIT 2");
        $stmt->execute([$std]);
        $rows = $stmt->fetchAll();

        $text = "🏆 **इयत्ता {$stdLabel} शिष्यवृत्ती परीक्षा मार्गदर्शक (Scholarship Prep):**\n\n";
        foreach ($rows as $r) {
            $text .= "📌 **{$r['subject']} — {$r['topic_name']}**\n";
            $text .= "📖 *संकल्पना:* {$r['description']}\n";
            if (!empty($r['formulas_or_tricks'])) {
                $text .= "💡 *महत्त्वाचे सूत्र / ट्रिक्स:* **{$r['formulas_or_tricks']}**\n";
            }
            $samples = json_decode($r['sample_questions'] ?? '[]', true);
            if (!empty($samples)) {
                $text .= "📝 *नमुना प्रश्न:*\n";
                foreach ($samples as $s) {
                    $text .= "• **प्रश्न:** {$s['q']}\n";
                    $text .= "  ✅ **योग्य उत्तर:** {$s['a']} ({$s['exp']})\n";
                }
            }
            $text .= "\n---\n\n";
        }
        $text .= "🎯 *कोणत्या घटकाचा सराव करायचा आहे? खालील पर्यायांवर क्लिक करा किंवा थेट प्रश्न विचारा!*";

        return [
            'reply' => $text,
            'chips' => ['शिष्यवृत्ती गणित सूत्रे', 'बुद्धिमत्ता चाचणी', 'मराठी व्याकरण', 'इंग्रजी Vocabulary']
        ];
    }

    // 3. Search Knowledge Database
    $matches = searchKnowledge($std, $subject, $msg, 4);

    if (!empty($matches)) {
        $text = "नमस्कार {$studentName}! तुमच्या प्रश्नाचे पाठ्यपुस्तकातील अचूक उत्तर खालीलप्रमाणे आहे:\n\n";

        foreach ($matches as $m) {
            if ($m['type'] === 'qa') {
                $text .= "📘 **विषय:** {$m['subject']} | **धडा:** {$m['chapter']}\n";
                $text .= "❓ **प्रश्न:** {$m['question']}\n";
                $text .= "✅ **उत्तर:** **{$m['answer']}**\n\n";
            } elseif ($m['type'] === 'chapter_summary') {
                $text .= "📖 **धड्याचा सारांश — {$m['chapter']} ({$m['subject']})**\n";
                $text .= "{$m['summary']}\n\n";
            } elseif ($m['type'] === 'scholarship') {
                $text .= "🏆 **शिष्यवृत्ती घटक:** {$m['topic']} ({$m['subject']})\n";
                $text .= "💡 *नियम / सूत्र:* {$m['formulas']}\n";
                if (!empty($m['samples'])) {
                    $text .= "📝 *नमुना:* {$m['samples'][0]['q']} → **{$m['samples'][0]['a']}**\n\n";
                }
            }
        }

        $text .= "💡 *आणखी काही विचारायचे आहे का? किंवा या धड्यावर क्विझ सोडवायची आहे?*";

        return [
            'reply' => $text,
            'chips' => ['या धड्यावर क्विझ घ्या', 'पुढील धडा', 'महत्त्वाचे स्वाध्याय प्रश्न']
        ];
    }

    // 4. Fallback contextual guidance
    $subjList = getSubjectsForStd($std);
    $subjNames = array_slice(array_map(fn($s) => $s['name'], $subjList), 0, 5);

    $fallback = "👋 नमस्कार {$studentName}! मी तुमचा **AI अभ्यास मित्र** आहे.\n\n"
              . "मी **इयत्ता {$stdLabel}** च्या सर्व अधिकृत पाठ्यपुस्तकांवर आणि स्वाध्यायावर आधारित उत्तर देतो.\n\n"
              . "📚 **आपण खालील विषयांवर थेट प्रश्न विचारू शकता:**\n";
    foreach ($subjNames as $sn) {
        $fallback .= "• {$sn}\n";
    }
    if ($std === 4 || $std === 7) {
        $fallback .= "• 🏆 **शिष्यवृत्ती परीक्षा सराव (Scholarship Prep)**\n";
    }
    $fallback .= "\nउदा. 'प्रकाश संश्लेषण म्हणजे काय?', 'वाळवंटी वनस्पतींची माहिती', 'शिष्यवृत्ती गणित सूत्रे' इत्यादी टाइप करा!";

    return [
        'reply' => $fallback,
        'chips' => ['विज्ञानातील महत्त्वाचे प्रश्न', 'गणित सराव', 'शिष्यवृत्ती तयारी']
    ];
}
