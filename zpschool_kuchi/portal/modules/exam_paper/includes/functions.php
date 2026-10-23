<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/answer_space.php';

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Converts text blanks (e.g. _______ or .......) into a generous handwriting blank underline for exam papers.
 */
function ep_format_blanks(string $s): string
{
    // Match 3+ consecutive underscores or 4+ dots or space-separated underscores/dots
    $pattern = '/\s*(?:_{3,}|(?:\s*_\s*){3,}|(?:\.\s*){3,}\.|\.{4,})/u';
    return preg_replace($pattern, ' <span class="ans-inline-blank">&nbsp;</span>', $s);
}

/** Convert plain-text question markup (newlines, tabs) or safe HTML (MathML, images, tables) into rendered HTML. */
function ep_markup(?string $s): string
{
    $s = trim((string)$s);
    if ($s === '') {
        return '';
    }
    // Normalize relative image src paths so they always load correctly regardless of route
    $s = preg_replace('/src=["\']image\.php\?/i', 'src="' . EP_BASE_URL . '/image.php?', $s);
    $s = preg_replace('/src=["\']book_page\.php\?/i', 'src="' . EP_BASE_URL . '/book_page.php?', $s);
    if (preg_match('/<(?:math|mrow|mn|mo|mfrac|msup|msub|msqrt|mroot|div|span|p|b|i|u|strong|em|img|table|thead|tbody|tr|td|th|br|ul|ol|li)\b/i', $s)) {
        $allowed = '<math><mrow><mn><mo><mfrac><msup><msub><msqrt><mroot><mstyle><mtext><mtable><mtr><mtd><div><span><p><b><i><u><strong><em><img><table><thead><tbody><tr><td><th><br><hr><ul><ol><li>';
        $s = strip_tags($s, $allowed);
        $s = preg_replace('/\t+/', '&emsp;&emsp;', $s);
        $s = preg_replace('/ {3,}/', '&emsp;&emsp;', $s);
        $s = ep_format_blanks($s);
        return nl2br($s);
    }
    $s = h($s);
    $s = preg_replace('/\t+/', '&emsp;&emsp;', $s);
    $s = preg_replace('/ {3,}/', '&emsp;&emsp;', $s);
    $s = ep_format_blanks($s);
    return nl2br($s);
}

function ep_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (ep_db()->query('SELECT setting_key, setting_value FROM ep_settings') as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Throwable $e) {}
    }
    return $cache;
}

function ep_get_hub_school(): ?array
{
    static $hubCache = [];
    $school_id = (int)($_SESSION['school_id'] ?? 0);
    if (isset($hubCache[$school_id])) {
        return $hubCache[$school_id];
    }

    $data = [];

    // 1. Try MySQL getSchool() first if logged in
    if ($school_id > 0) {
        if (function_exists('getSchool')) {
            $sch = getSchool();
            if ($sch && (int)($sch['id'] ?? 0) === $school_id) {
                $data = $sch;
            }
        }
        if (empty($data) && function_exists('getDB')) {
            try {
                $st = getDB()->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
                $st->execute([$school_id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $data = $row;
                }
            } catch (Throwable $e) {}
        }
    }

    // 2. Try reading from school tenant SQLite
    if ($school_id > 0 && function_exists('getSchoolUdise')) {
        $udise = getSchoolUdise($school_id);
        if ($udise) {
            $sqlitePath = dirname(__DIR__, 2) . "/data/schools/{$udise}.sqlite";
            if (file_exists($sqlitePath)) {
                try {
                    $sqlite = new PDO("sqlite:$sqlitePath");
                    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    $row = $sqlite->query("SELECT * FROM school_info LIMIT 1")->fetch();
                    if ($row) {
                        foreach ($row as $k => $v) {
                            if (empty($data[$k]) && !empty($v)) {
                                $data[$k] = $v;
                            }
                        }
                    }
                } catch (Throwable $e) {}
            }
        }
    }

    if (!empty($data)) {
        // Format full address
        $addrParts = array_filter([
            $data['address_line1'] ?? '',
            $data['village'] ?? '',
            !empty($data['taluka']) ? 'ता. ' . $data['taluka'] : '',
            !empty($data['district']) ? 'जि. ' . $data['district'] : '',
            !empty($data['pin_code']) ? 'पिन: ' . $data['pin_code'] : '',
            !empty($data['udise_code']) ? 'UDISE: ' . $data['udise_code'] : ''
        ]);
        $data['formatted_address'] = implode(', ', $addrParts);
        $hubCache[$school_id] = $data;
        return $hubCache[$school_id];
    }

    return null;
}

function ep_logo_url(): string
{
    $logo = ep_setting('school_logo');
    if (!$logo) return '';
    if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
        return $logo;
    }
    if (str_starts_with($logo, 'uploads/')) {
        return APP_URL . '/' . $logo;
    }
    if (file_exists(__DIR__ . '/../uploads/' . $logo)) {
        return EP_BASE_URL . '/uploads/' . $logo;
    }
    if (file_exists(dirname(__DIR__, 2) . '/uploads/' . $logo)) {
        return APP_URL . '/uploads/' . $logo;
    }
    if (file_exists(dirname(__DIR__, 2) . '/' . $logo)) {
        return APP_URL . '/' . $logo;
    }
    return EP_BASE_URL . '/uploads/' . $logo;
}

function ep_setting(string $key, string $default = ''): string
{
    $school_id = (int)($_SESSION['school_id'] ?? 0);
    $sch = ep_get_hub_school();
    
    // 1. If school is logged in, check school-specific saved settings in ep_school_settings
    if ($school_id > 0) {
        static $schoolSettingsCache = [];
        if (!isset($schoolSettingsCache[$school_id])) {
            $schoolSettingsCache[$school_id] = [];
            try {
                $st = ep_db()->prepare('SELECT setting_key, setting_value FROM ep_school_settings WHERE school_id = ?');
                $st->execute([$school_id]);
                foreach ($st->fetchAll() as $r) {
                    $schoolSettingsCache[$school_id][$r['setting_key']] = $r['setting_value'];
                }
            } catch (Throwable $e) {}
        }
        
        $val = $schoolSettingsCache[$school_id][$key] ?? null;
        if ($val !== null && $val !== '') {
            return (string)$val;
        }
        
        // 2. If no custom override saved for this school, automatically use the current school's hub profile!
        if ($sch) {
            if ($key === 'school_name') {
                $name = trim((string)($sch['name_mr'] ?: $sch['name'] ?: ''));
                if ($name !== '') return $name;
            }
            if ($key === 'school_address') {
                $addr = trim((string)($sch['formatted_address'] ?? ''));
                if ($addr !== '') return $addr;
            }
            if ($key === 'school_logo') {
                if (!empty($sch['logo'])) return (string)$sch['logo'];
            }
            if ($key === 'udise_code') {
                if (!empty($sch['udise_code'])) return (string)$sch['udise_code'];
            }
            if ($key === 'watermark_text') {
                $wm = trim((string)($sch['name_mr'] ?: $sch['name'] ?: ''));
                if ($wm !== '') return $wm;
            }
        }
    }
    
    // 3. Fallback to global ep_settings for system-wide defaults (default_paper_format, paper_footer, etc.)
    $globalVal = ep_settings()[$key] ?? '';
    if ($globalVal !== '') {
        return $globalVal;
    }
    
    return $default;
}

function ep_save_setting(string $key, string $value): void
{
    $school_id = (int)($_SESSION['school_id'] ?? 0);
    if ($school_id > 0) {
        ep_db()->prepare('INSERT INTO ep_school_settings (school_id, setting_key, setting_value) VALUES (?, ?, ?)
                          ON CONFLICT(school_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value')
               ->execute([$school_id, $key, $value]);
    } else {
        ep_db()->prepare('INSERT INTO ep_settings (setting_key, setting_value) VALUES (?, ?)
                          ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value')
               ->execute([$key, $value]);
    }
}

function ep_sync_from_hub(): bool
{
    $sch = ep_get_hub_school();
    if (!$sch) return false;
    $school_id = (int)($_SESSION['school_id'] ?? 0);
    if (!empty($sch['name_mr'] ?: $sch['name'])) {
        ep_save_setting('school_name', (string)($sch['name_mr'] ?: $sch['name']));
    }
    if (!empty($sch['formatted_address'])) {
        ep_save_setting('school_address', (string)$sch['formatted_address']);
    }
    if (!empty($sch['logo'])) {
        ep_save_setting('school_logo', (string)$sch['logo']);
    }
    if (!empty($sch['udise_code'])) {
        ep_save_setting('udise_code', (string)$sch['udise_code']);
    }
    return true;
}

function ep_check_access(): array
{
    if (function_exists('check_module_access')) {
        return check_module_access('que');
    }
    return ['allowed' => true, 'type' => 'free', 'badge_text' => 'मोफत', 'days_left' => null];
}

function ep_can_create_paper(): array
{
    $acc = ep_check_access();
    if (!$acc['allowed']) {
        return ['allowed' => false, 'message' => $acc['message'] ?? 'प्रश्नपत्रिका निर्मितीसाठी ॲक्सेस नाही.'];
    }

    $school_id = (int)($_SESSION['school_id'] ?? 0);
    $type = $acc['type'] ?? 'trial';

    // If on Free Plan or Trial, max 5 papers allowed!
    $is_limited = in_array($type, ['trial', 'free', 'free_plan'], true);

    $st = ep_db()->prepare("SELECT COUNT(*) FROM ep_papers WHERE school_id = ? OR (school_id IS NULL AND ? = 0)");
    $st->execute([$school_id, $school_id]);
    $count = (int)$st->fetchColumn();

    $max_free = 5;
    if ($is_limited && $count >= $max_free) {
        return [
            'allowed' => false,
            'is_limited' => true,
            'count' => $count,
            'max' => $max_free,
            'message' => "मोफत चाचणीमध्ये कमाल {$max_free} प्रश्नपत्रिका तयार करण्याची मर्यादा आहे (आपण {$count}/{$max_free} प्रश्नपत्रिका तयार केल्या आहेत). अमर्यादित प्रश्नपत्रिकांसाठी कृपया ऑल-इन-वन (१ वर्ष) किंवा प्रश्नपत्रिका योजना निवडा."
        ];
    }

    return [
        'allowed' => true,
        'is_limited' => $is_limited,
        'count' => $count,
        'max' => $is_limited ? $max_free : 9999
    ];
}

function ep_standards(bool $onlyWithContent = true): array
{
    $where = $onlyWithContent 
        ? 'WHERE (SELECT COUNT(*) FROM ep_subjects sub WHERE sub.standard_id = ep_standards.standard_id) > 0' 
        : '';
    $all = ep_db()->query("SELECT * FROM ep_standards {$where} 
                           ORDER BY sort_order, 
                                    CASE WHEN medium LIKE '%मराठी%' AND medium NOT LIKE '%Semi%' THEN 1
                                         WHEN medium LIKE '%Semi%' THEN 2
                                         ELSE 3 END, 
                                    name")->fetchAll();

    // Strict Teacher Scope: show only teacher's assigned classes
    if (function_exists('get_current_teacher_scope')) {
        $tScope = get_current_teacher_scope();
        if ($tScope && !empty($tScope['stds'])) {
            $allowedStds = array_map('strval', $tScope['stds']);
            $filtered = [];
            foreach ($all as $s) {
                $rawName = trim((string)$s['name']);
                if (in_array($rawName, $allowedStds, true)) {
                    $filtered[] = $s;
                    continue;
                }
                foreach ($allowedStds as $astd) {
                    if (str_starts_with($rawName, $astd . 'th') || 
                        str_starts_with($rawName, $astd . ' ') || 
                        str_starts_with($rawName, $astd . 'थी') ||
                        str_starts_with($rawName, $astd . 'वी') ||
                        str_starts_with($rawName, $astd . 'री') ||
                        str_starts_with($rawName, $astd . 'ली')) {
                        $filtered[] = $s;
                        break;
                    }
                }
            }
            if (!empty($filtered)) {
                return $filtered;
            }
        }
    }

    return $all;
}

function ep_is_competitive_standard(array|string $std): bool
{
    $name = is_array($std) ? ($std['name'] ?? '') : (string)$std;
    return (bool)preg_match('/(Scholarship|शिष्यवृत्ती|NMMS|Navodaya|नवोदय|मंथन|Olympiad|FOUNDATION)/iu', $name);
}

function ep_format_standard_name(array|string $name, ?string $medium = null): string
{
    $rawName = is_array($name) ? ($name['name'] ?? '') : (string)$name;
    $med = is_array($name) ? ($name['medium'] ?? '') : ($medium ?? '');
    
    // Regular School Standard (1..10)
    if (is_numeric(trim($rawName))) {
        $num = (int)$rawName;
        $ordMap = [
            1 => '१ ली', 2 => '२ री', 3 => '३ री', 4 => '४ थी', 5 => '५ वी',
            6 => '६ वी', 7 => '७ वी', 8 => '८ वी', 9 => '९ वी', 10 => '१० वी'
        ];
        $ord = $ordMap[$num] ?? ($num . ' वी');
        if ($med === 'मराठी') {
            return "इयत्ता {$ord} (मराठी माध्यम)";
        }
        if (stripos($med, 'Semi') !== false) {
            return "इयत्ता {$ord} (सेमी-इंग्रजी / Semi-English)";
        }
        if ($med === 'English') {
            $enOrd = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th', 6 => '6th', 7 => '7th', 8 => '8th', 9 => '9th', 10 => '10th'];
            $suf = $enOrd[$num] ?? ($num . 'th');
            return "Std {$suf} (English Medium)";
        }
        return "इयत्ता {$ord} ({$med})";
    }

    // Competitive Exam Standards
    if (preg_match('/8\)\s*NMMS/i', $rawName)) {
        return "८ वी NMMS परीक्षा (Mental Ability & SAT)";
    }
    if (preg_match('/4th\s*शिष्यवृत्ती/i', $rawName)) {
        return "४ थी / ५ वी शिष्यवृत्ती परीक्षा (Scholarship)";
    }
    if (preg_match('/7th\s*शिष्यवृत्ती/i', $rawName)) {
        return "७ वी / ८ वी शिष्यवृत्ती परीक्षा (Scholarship)";
    }
    if (preg_match('/Navodaya/i', $rawName)) {
        return "५ वी नवोदय परीक्षा (JNVST)";
    }

    return $rawName . ($med ? " ({$med})" : '');
}

function ep_subjects(int $standardId): array
{
    $st = ep_db()->prepare('SELECT s.*, (SELECT COUNT(*) FROM ep_chapters c JOIN ep_questions q ON q.chapter_id=c.chapter_id WHERE c.subject_id=s.subject_id) AS question_count
                            FROM ep_subjects s WHERE s.standard_id = ? ORDER BY s.name');
    $st->execute([$standardId]);
    return $st->fetchAll();
}

function ep_chapters(int $subjectId): array
{
    $st = ep_db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM ep_questions q WHERE q.chapter_id=c.chapter_id) AS question_count
                            FROM ep_chapters c WHERE c.subject_id = ? ORDER BY c.sort_order, c.chapter_id');
    $st->execute([$subjectId]);
    return $st->fetchAll();
}

function ep_templates(int $subjectId): array
{
    $st = ep_db()->prepare('SELECT * FROM ep_templates WHERE subject_id = ? ORDER BY total_marks, title');
    $st->execute([$subjectId]);
    $templates = $st->fetchAll();
    $tq = ep_db()->prepare('SELECT * FROM ep_template_questions WHERE template_id = ? ORDER BY sort_order, tq_id');
    foreach ($templates as &$t) {
        $tq->execute([$t['template_id']]);
        $t['questions'] = $tq->fetchAll();
    }
    unset($t);

    // Prepend standard topicwise unit test templates (घटक चाचणी / Topic Test Patterns)
    $topicTemplates = [
        [
            'template_id' => -110,
            'subject_id'  => $subjectId,
            'title'       => "📝 घटक चाचणी (Topicwise Unit Test - 20 गुण / 20 Marks)",
            'total_marks' => 20,
            'description' => "घटक चाचणी: रिकाम्या जागा, जोड्या/एका शब्दात, थोडक्यात उत्तरे व उदाहरणे/कारणे",
            'questions'   => [
                ['sort_order' => 1, 'question_title' => 'Q.1) Fill in the blanks with proper words / रिकाम्या जागा भरा', 'marks' => 4, 'question_type' => 'fill_blank', 'question_number' => '', 'no_of_questions' => 4],
                ['sort_order' => 2, 'question_title' => 'Q.2) Match the following / एका शब्दात उत्तरे लिहा (Objective)', 'marks' => 4, 'question_type' => 'match', 'question_number' => '', 'no_of_questions' => 4],
                ['sort_order' => 3, 'question_title' => 'Q.3) Answer the following questions / खालील प्रश्नांची उत्तरे लिहा', 'marks' => 6, 'question_type' => 'descriptive', 'question_number' => '', 'no_of_questions' => 3],
                ['sort_order' => 4, 'question_title' => 'Q.4) Solve / Concepts / उदाहरणे व संकल्पना स्पष्ट करा', 'marks' => 6, 'question_type' => 'solve', 'question_number' => '', 'no_of_questions' => 3],
            ]
        ],
        [
            'template_id' => -111,
            'subject_id'  => $subjectId,
            'title'       => "📝 घटक चाचणी (Topicwise Unit Test - 15 गुण / 15 Marks)",
            'total_marks' => 15,
            'description' => "घटक चाचणी: रिकाम्या जागा, एका वाक्यात, थोडक्यात उत्तरे व कारणे",
            'questions'   => [
                ['sort_order' => 1, 'question_title' => 'Q.1) रिकाम्या जागा भरा (Fill in the blanks)', 'marks' => 3, 'question_type' => 'fill_blank', 'question_number' => '', 'no_of_questions' => 3],
                ['sort_order' => 2, 'question_title' => 'Q.2) एका वाक्यात उत्तरे लिहा / जोड्या जुळवा (One sentence / Match)', 'marks' => 3, 'question_type' => 'one_sentence', 'question_number' => '', 'no_of_questions' => 3],
                ['sort_order' => 3, 'question_title' => 'Q.3) थोडक्यात उत्तरे लिहा (Answer in short)', 'marks' => 6, 'question_type' => 'short_answer', 'question_number' => '', 'no_of_questions' => 3],
                ['sort_order' => 4, 'question_title' => 'Q.4) कारणे द्या / स्पष्ट करा (Give reasons / Explain)', 'marks' => 3, 'question_type' => 'reason', 'question_number' => '', 'no_of_questions' => 1],
            ]
        ],
        [
            'template_id' => -112,
            'subject_id'  => $subjectId,
            'title'       => "📝 घटक चाचणी (Topicwise Unit Test - 10 गुण / 10 Marks)",
            'total_marks' => 10,
            'description' => "लहान घटक चाचणी: रिकाम्या जागा, एका वाक्यात व उत्तरे लिहा",
            'questions'   => [
                ['sort_order' => 1, 'question_title' => 'Q.1) रिकाम्या जागा भरा (Fill in the blanks)', 'marks' => 2, 'question_type' => 'fill_blank', 'question_number' => '', 'no_of_questions' => 2],
                ['sort_order' => 2, 'question_title' => 'Q.2) एका वाक्यात उत्तरे लिहा (Answer in one sentence)', 'marks' => 2, 'question_type' => 'one_sentence', 'question_number' => '', 'no_of_questions' => 2],
                ['sort_order' => 3, 'question_title' => 'Q.3) थोडक्यात उत्तरे लिहा (Answer the following)', 'marks' => 6, 'question_type' => 'descriptive', 'question_number' => '', 'no_of_questions' => 3],
            ]
        ],
        [
            'template_id' => -113,
            'subject_id'  => $subjectId,
            'title'       => "📝 घटक चाचणी (Topicwise Unit Test - 25 गुण / 25 Marks)",
            'total_marks' => 25,
            'description' => "घटक चाचणी: वस्तुनिष्ठ प्रश्न, थोडक्यात, कारणे व सविस्तर उत्तरे",
            'questions'   => [
                ['sort_order' => 1, 'question_title' => 'Q.1) अ) रिकाम्या जागा भरा (Fill in the blanks)', 'marks' => 4, 'question_type' => 'fill_blank', 'question_number' => '', 'no_of_questions' => 4],
                ['sort_order' => 2, 'question_title' => 'Q.1) ब) चूक की बरोबर / जोड्या जुळवा (True/False or Match)', 'marks' => 4, 'question_type' => 'true_false', 'question_number' => '', 'no_of_questions' => 4],
                ['sort_order' => 3, 'question_title' => 'Q.2) थोडक्यात उत्तरे लिहा (Answer in short)', 'marks' => 6, 'question_type' => 'short_answer', 'question_number' => '', 'no_of_questions' => 3],
                ['sort_order' => 4, 'question_title' => 'Q.3) शास्त्रीय कारणे द्या / स्पष्ट करा (Reasons / Explain)', 'marks' => 6, 'question_type' => 'reason', 'question_number' => '', 'no_of_questions' => 3],
                ['sort_order' => 5, 'question_title' => 'Q.4) सविस्तर उत्तरे लिहा / उदाहरणे सोडवा (Detailed answers / Solve)', 'marks' => 5, 'question_type' => 'descriptive', 'question_number' => '', 'no_of_questions' => 2],
            ]
        ],
    ];

    $hasQuiz = false;
    foreach ($templates as $t) {
        if (stripos($t['title'], 'quiz') !== false || stripos($t['title'], 'स्पर्धा') !== false) {
            $hasQuiz = true;
            break;
        }
    }
    if (!$hasQuiz) {
        $templates[] = [
            'template_id' => -102,
            'subject_id'  => $subjectId,
            'title'       => "🏆 स्पर्धा परीक्षा वस्तुनिष्ठ क्विझ (MCQ Quiz - 20 प्रश्न / 40 गुण)",
            'total_marks' => 40,
            'questions'   => [
                ['sort_order' => 1, 'question_title' => 'विभाग १: बहुपर्यायी वस्तुनिष्ठ प्रश्न (MCQ चाचणी)', 'marks' => 2, 'question_type' => 'mcq', 'question_number' => '1', 'no_of_questions' => 10],
                ['sort_order' => 2, 'question_title' => 'विभाग २: बहुपर्यायी वस्तुनिष्ठ प्रश्न (MCQ चाचणी)', 'marks' => 2, 'question_type' => 'mcq', 'question_number' => '2', 'no_of_questions' => 10],
            ]
        ];
    }

    return array_merge($topicTemplates, $templates);
}

/** Map template question types (free-text on source site) to stored question_type values. */
function ep_normalize_type(string $type): string
{
    $t = strtolower(trim($type));
    if (str_starts_with($t, 'mcq')) return 'mcq';
    if (str_starts_with($t, 'fill')) return 'fill_blank';
    $known = ['match', 'true_false', 'one_sentence', 'short_answer', 'descriptive', 'solve', 'draw', 'one_word', 'reason', 'difference', 'define', 'explain', 'vocabulary', 'grammar', 'activity', 'odd_one'];
    foreach ($known as $k) {
        if ($t === $k || str_starts_with($t, $k)) return $k;
    }
    return $t !== '' ? $t : 'descriptive';
}

/** Return compatible question types for flexible querying and browsing. */
function ep_compatible_types(string $type): array
{
    $norm = ep_normalize_type($type);
    if (in_array($norm, ['fill_blank', 'fillinblanks'], true)) {
        return ['fill_blank', 'fillinblanks', 'one_word', 'vocabulary'];
    }
    if ($norm === 'mcq') {
        return ['mcq', 'odd_one'];
    }
    if ($norm === 'match') {
        return ['match', 'one_word', 'vocabulary', 'one_sentence'];
    }
    if ($norm === 'true_false') {
        return ['true_false', 'odd_one'];
    }
    if ($norm === 'one_sentence') {
        return ['one_sentence', 'one_word', 'short_answer', 'define'];
    }
    if ($norm === 'short_answer') {
        return ['short_answer', 'one_sentence', 'explain', 'reason', 'define', 'descriptive'];
    }
    if ($norm === 'reason') {
        return ['reason', 'explain', 'descriptive', 'short_answer'];
    }
    if ($norm === 'explain') {
        return ['explain', 'reason', 'descriptive', 'short_answer', 'difference'];
    }
    if ($norm === 'difference') {
        return ['difference', 'explain', 'descriptive', 'short_answer'];
    }
    if ($norm === 'solve') {
        return ['solve', 'descriptive', 'activity', 'explain', 'short_answer'];
    }
    if ($norm === 'one_word') {
        return ['one_word', 'one_sentence', 'vocabulary', 'short_answer'];
    }
    if ($norm === 'vocabulary' || $norm === 'grammar') {
        return [$norm, 'one_word', 'one_sentence'];
    }
    // Default descriptive
    return ['descriptive', 'explain', 'reason', 'difference', 'solve', 'define', 'short_answer', 'one_sentence', 'activity', 'observe', 'draw'];
}

/** Pick $count random questions of a type/number from the given chapters, excluding ids already used. */
function ep_random_questions(array $chapterIds, string $type, string $qno, int $count, array $exclude = []): array
{
    $chapterIds = array_values(array_filter(array_map('intval', $chapterIds)));
    if (!$chapterIds || $count <= 0) {
        return [];
    }
    
    $currentExclude = array_values(array_filter(array_map('intval', $exclude)));
    $rawType = strtolower(trim($type));
    $normType = ep_normalize_type($rawType);

    if (in_array($normType, ['fill_blank', 'fillinblanks'], true)) {
        $primaryTypes = ['fill_blank', 'fillinblanks'];
        $fallbackTypes = ['one_word', 'vocabulary'];
    } elseif ($normType === 'mcq') {
        $primaryTypes = ['mcq'];
        $fallbackTypes = ['odd_one'];
    } elseif ($normType === 'match') {
        $primaryTypes = ['match'];
        $fallbackTypes = ['one_word', 'vocabulary', 'one_sentence'];
    } elseif ($normType === 'true_false') {
        $primaryTypes = ['true_false'];
        $fallbackTypes = ['odd_one'];
    } elseif ($normType === 'one_sentence') {
        $primaryTypes = ['one_sentence', 'one_word', 'short_answer'];
        $fallbackTypes = ['define', 'reason', 'explain'];
    } elseif ($normType === 'short_answer') {
        $primaryTypes = ['short_answer', 'one_sentence', 'explain', 'reason', 'define'];
        $fallbackTypes = ['descriptive', 'difference', 'solve'];
    } elseif ($normType === 'reason') {
        $primaryTypes = ['reason', 'explain', 'descriptive', 'short_answer'];
        $fallbackTypes = ['difference', 'define'];
    } elseif ($normType === 'explain') {
        $primaryTypes = ['explain', 'reason', 'descriptive', 'short_answer'];
        $fallbackTypes = ['difference', 'solve'];
    } elseif ($normType === 'difference') {
        $primaryTypes = ['difference', 'explain', 'descriptive', 'short_answer'];
        $fallbackTypes = ['reason'];
    } elseif ($normType === 'solve') {
        $primaryTypes = ['solve', 'descriptive', 'activity'];
        $fallbackTypes = ['explain', 'short_answer'];
    } elseif ($normType === 'one_word') {
        $primaryTypes = ['one_word', 'one_sentence', 'vocabulary'];
        $fallbackTypes = ['short_answer'];
    } elseif ($normType === 'vocabulary' || $normType === 'grammar') {
        $primaryTypes = [$normType, 'one_word', 'one_sentence'];
        $fallbackTypes = [];
    } else {
        $primaryTypes = ['descriptive', 'explain', 'reason', 'difference', 'solve', 'define', 'short_answer', 'one_sentence', 'activity', 'observe', 'draw'];
        $fallbackTypes = ['vocabulary', 'grammar', 'one_word'];
    }

    $fetchQuestions = function(array $types, ?string $targetQno, int $limit) use ($chapterIds, &$currentExclude) {
        if ($limit <= 0 || empty($types)) return [];
        $where = 'chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')';
        $params = $chapterIds;
        $where .= ' AND question_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
        $params = array_merge($params, $types);
        if ($targetQno !== null && trim($targetQno) !== '') {
            $where .= ' AND question_number = ?';
            $params[] = trim($targetQno);
        }
        if (!empty($currentExclude)) {
            $where .= ' AND question_id NOT IN (' . implode(',', array_fill(0, count($currentExclude), '?')) . ')';
            $params = array_merge($params, $currentExclude);
        }
        $sql = "SELECT * FROM ep_questions WHERE $where ORDER BY RAND() LIMIT " . (int)$limit;
        $st = ep_db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $currentExclude[] = (int)$r['question_id'];
        }
        return $rows;
    };

    $selected = [];
    $targetQno = trim((string)$qno);
    if ($targetQno !== '') {
        $selected = array_merge($selected, $fetchQuestions($primaryTypes, $targetQno, $count));
    }
    if (count($selected) < $count) {
        $needed = $count - count($selected);
        $selected = array_merge($selected, $fetchQuestions($primaryTypes, null, $needed));
    }
    if (count($selected) < $count && !empty($fallbackTypes)) {
        $needed = $count - count($selected);
        $selected = array_merge($selected, $fetchQuestions($fallbackTypes, null, $needed));
    }
    if (count($selected) < $count && $normType !== 'mcq') {
        $needed = $count - count($selected);
        $where = 'chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')';
        $params = $chapterIds;
        if (!empty($currentExclude)) {
            $where .= ' AND question_id NOT IN (' . implode(',', array_fill(0, count($currentExclude), '?')) . ')';
            $params = array_merge($params, $currentExclude);
        }
        $sql = "SELECT * FROM ep_questions WHERE $where ORDER BY RAND() LIMIT " . (int)$needed;
        $st = ep_db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $selected = array_merge($selected, $rows);
    }

    return array_map('ep_decode_question', $selected);
}

function ep_questions_by_ids(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $st = ep_db()->prepare('SELECT * FROM ep_questions WHERE question_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll() as $q) {
        $byId[$q['question_id']] = ep_decode_question($q);
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}

function ep_decode_question(array $q): array
{
    $q['sub_questions'] = $q['sub_questions'] ? json_decode($q['sub_questions'], true) : [];
    foreach ($q['sub_questions'] as &$sq) {
        $sid = (int)($sq['question_id'] ?? 0);
        $sq['image_url'] = !empty($sq['has_image']) && $sid ? EP_BASE_URL . '/image.php?id=' . $sid : null;
        $sq['answer_image_url'] = !empty($sq['has_answer_image']) && $sid ? EP_BASE_URL . '/image.php?id=' . $sid . '&n=a' : null;
    }
    unset($sq);
    $q['image_url'] = $q['has_image'] ? EP_BASE_URL . '/image.php?id=' . $q['question_id'] : null;
    $q['image2_url'] = $q['has_image2'] ? EP_BASE_URL . '/image.php?id=' . $q['question_id'] . '&n=2' : null;
    $q['answer_image_url'] = $q['has_answer_image'] ? EP_BASE_URL . '/image.php?id=' . $q['question_id'] . '&n=a' : null;
    return $q;
}

function ep_paper(int $paperId): ?array
{
    $st = ep_db()->prepare('SELECT p.*, s.name AS standard_name, s.medium, sub.name AS subject_name
                            FROM ep_papers p LEFT JOIN ep_standards s ON s.standard_id=p.standard_id
                            LEFT JOIN ep_subjects sub ON sub.subject_id=p.subject_id WHERE p.paper_id = ?');
    $st->execute([$paperId]);
    $p = $st->fetch();
    if (!$p) {
        return null;
    }
    $data = json_decode($p['paper_json'], true) ?: [];
    if (isset($data['sections'])) {           // assessment papers: {exam_type, test_no, standard, subject, medium, sections}
        $p['meta'] = $data;
        $p['sections'] = $data['sections'];
    } else {
        $p['meta'] = [];
        $p['sections'] = $data;
    }
    return $p;
}

/** Extracts MCQ option number (1, 2, 3, or 4) from answer markup. */
function ep_parse_omr_option(?string $markup): ?int
{
    if (!$markup) return null;
    $text = trim(strip_tags((string)$markup));
    if (preg_match('/^\s*[\(\[\{]?([A-Da-d1-4१-४])[\)\]\}\.\-]?/u', $text, $m)) {
        $val = mb_strtoupper($m[1]);
        $dev = ['१' => 1, '२' => 2, '३' => 3, '४' => 4];
        if (isset($dev[$val])) return $dev[$val];
        if (is_numeric($val)) return (int)$val;
        $map = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
        return $map[$val] ?? null;
    }
    if (preg_match('/(?:पर्याय|Option|Ans|Answer|उत्तर)\s*(?:क्रमांक|क्र\.?|no\.?)?\s*[:\-\s]*[\(\[\{]?([A-Da-d1-4१-४])[\)\]\}]?/iu', $text, $m)) {
        $val = mb_strtoupper($m[1]);
        $dev = ['१' => 1, '२' => 2, '३' => 3, '४' => 4];
        if (isset($dev[$val])) return $dev[$val];
        if (is_numeric($val)) return (int)$val;
        $map = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
        return $map[$val] ?? null;
    }
    return null;
}

/** All classes with their subjects and chapters (+ MCQ counts) - used by the competitive paper builder. */
function ep_source_tree(bool $competitiveOnly = false): array
{
    $sql = 'SELECT s.standard_id, s.name std_name, s.medium, sub.subject_id, sub.name subject_name,
                   c.chapter_id, c.name chapter_name,
                   (SELECT COUNT(*) FROM ep_questions q WHERE q.chapter_id = c.chapter_id AND q.question_type = "mcq") AS mcq
            FROM ep_standards s
            JOIN ep_subjects sub ON sub.standard_id = s.standard_id
            JOIN ep_chapters c ON c.subject_id = sub.subject_id ';
    if ($competitiveOnly) {
        $sql .= " WHERE (s.name LIKE '%शिष्यवृत्ती%' OR s.name LIKE '%Scholarship%' OR s.name LIKE '%NMMS%' OR s.name LIKE '%Navodaya%' OR s.name LIKE '%नवोदय%' OR s.name LIKE '%मंथन%' OR s.standard_id IN (72, 71, 46)) ";
    }
    $sql .= ' ORDER BY s.sort_order, s.name, s.medium, sub.name, c.sort_order, c.chapter_id';
    $rows = ep_db()->query($sql)->fetchAll();
    $tree = [];
    foreach ($rows as $r) {
        $sid = (int)$r['standard_id'];
        $tree[$sid] ??= ['standard_id' => $sid, 'name' => $r['std_name'], 'medium' => $r['medium'], 'subjects' => []];
        $subId = (int)$r['subject_id'];
        $tree[$sid]['subjects'][$subId] ??= ['subject_id' => $subId, 'name' => $r['subject_name'], 'mcq' => 0, 'chapters' => []];
        $tree[$sid]['subjects'][$subId]['chapters'][] = ['chapter_id' => (int)$r['chapter_id'], 'name' => $r['chapter_name'], 'mcq' => (int)$r['mcq']];
        $tree[$sid]['subjects'][$subId]['mcq'] += (int)$r['mcq'];
    }
    foreach ($tree as &$std) {
        $std['subjects'] = array_values(array_filter($std['subjects'], fn($s) => $s['mcq'] > 0));
    }
    unset($std);
    return array_values(array_filter($tree, fn($s) => $s['subjects']));
}

function ep_competitive_tree(): array
{
    return ep_source_tree(true);
}

/* ---------------- Textbook (Balbharati) exercise bank ---------------- */

/** URL for a textbook page image ("46/018.jpg") or a cropped figure ("46/018_2.jpg"). */
function ep_book_image_url(?string $f): ?string
{
    return $f ? EP_BASE_URL . '/book_page.php?f=' . rawurlencode($f) : null;
}

function ep_book_question(int $id): ?array
{
    $st = ep_db()->prepare('SELECT * FROM ep_book_questions WHERE bq_id = ?');
    $st->execute([$id]);
    $q = $st->fetch(PDO::FETCH_ASSOC);
    return $q ? ep_decode_book_question($q) : null;
}

function ep_decode_book_question(array $q): array
{
    $q['page_image_url'] = ep_book_image_url($q['page_image'] ?? null);
    $q['figure_image_url'] = ep_book_image_url($q['figure_image'] ?? null);
    $q['options'] = !empty($q['options_json']) ? (json_decode($q['options_json'], true) ?: []) : [];
    $q['pairs'] = !empty($q['pairs_json']) ? ep_match_pairs(json_decode($q['pairs_json'], true) ?: []) : [];
    if (!$q['pairs'] && ($q['qtype'] ?? '') === 'match') {
        $q['pairs'] = ep_match_pairs($q['options'], true);
        if (!$q['pairs'] && !empty($q['answer'])) {
            $q['pairs'] = ep_match_pairs_from_answer((string)$q['answer']);
        }
    }
    unset($q['options_json'], $q['pairs_json']);
    return $q;
}

/* ---------------- जोड्या लावा / match the pairs ---------------- */

/**
 * Normalise match-the-pairs data to [[left, right], ...].
 * Accepts [[l, r], ...], [{left, right}], ["l | r", ...] rows or a multi-line text with "l | r" lines.
 */
function ep_match_pairs($src, bool $dashRows = false): array
{
    if (is_string($src)) {
        $src = preg_split('/\r?\n/', $src);
    }
    if (!is_array($src)) {
        return [];
    }
    $pairs = [];
    $label = '/^\(?[A-Za-z0-9अ-ह१-९]{1,3}[\)\.:]\s*/u';
    $strip = fn($s) => preg_replace('/^[\s\-–—:]+|[\s\-–—:]+$/u', '', preg_replace($label, '', trim((string)$s)));
    $rows = [];
    foreach ($src as $row) {
        if (is_string($row) && substr_count($row, '|') > 1) {
            // legacy import: "A: l – r | B: l – r | ..." in one string
            foreach (explode('|', $row) as $seg) {
                $rows[] = trim($seg);
            }
        } else {
            $rows[] = $row;
        }
    }
    foreach ($rows as $row) {
        $l = $r = null;
        if (is_array($row)) {
            if (isset($row['left']) || isset($row['right'])) {
                $l = $row['left'] ?? null;
                $r = $row['right'] ?? null;
            } elseif (count($row) >= 2 && !is_array($row[0] ?? null)) {
                [$l, $r] = [$row[0], $row[1]];
            }
        } elseif (is_string($row) && preg_match('/^(.+?)\s*\|\s*(.+)$/u', trim($row), $m)) {
            [$l, $r] = [$m[1], $m[2]];
        } elseif ($dashRows && is_string($row) && preg_match('/^(.+?)\s+[–—-]\s+(.+)$/u', trim($row), $m)) {
            [$l, $r] = [$m[1], $m[2]];
        }
        $l = $strip($l);
        $r = $strip($r);
        if ($l !== '' && $r !== '' && $l !== $r) {
            $pairs[] = [$l, $r];
        }
    }
    return count($pairs) >= 2 ? $pairs : [];
}

/** Parse match pairs from text in answer or options column. */
function ep_match_pairs_from_answer(string $ans): array
{
    $ans = trim($ans);
    if ($ans === '') return [];

    if (preg_match('/^\[\s*\[/u', $ans)) {
        $jsonStr = strtr($ans, ["'" => '"', '“' => '"', '”' => '"']);
        $decoded = json_decode($jsonStr, true);
        if (is_array($decoded) && count($decoded) >= 2) {
            $pairs = [];
            foreach ($decoded as $row) {
                if (is_array($row) && count($row) >= 2) {
                    $pairs[] = [trim((string)$row[0]), trim((string)$row[1])];
                }
            }
            if (count($pairs) >= 2) return $pairs;
        }
    }

    if (strpos($ans, "\n") !== false) {
        $candidates = preg_split('/\r?\n/', $ans);
    } elseif (strpos($ans, ';') !== false) {
        $candidates = explode(';', $ans);
    } else {
        $candidates = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*[^\"]*$)/u', $ans);
    }

    $pairs = [];
    $labelRegex = '/^\s*(?:\([A-Za-z0-9\x{0905}-\x{0939}\x{0966}-\x{096F}]{1,3}\)|[A-Za-z0-9\x{0905}-\x{0939}\x{0966}-\x{096F}]{1,3}[\)\.:])\s*/u';
    $delimRegex = '/^(.+?)\s*(?:[\x{2013}\x{2014}]|\||\s+-\s+|(?<=[^\s\-])-(?=[^\s\-])|\s*=\s*|\s+[Xx\x{00D7}]\s+|\s+matches with\s+)\s*(.+)$/u';

    foreach ($candidates as $cand) {
        $cand = trim($cand);
        if ($cand === '') continue;
        $clean = preg_replace($labelRegex, '', $cand);
        if (preg_match($delimRegex, $clean, $m)) {
            $l = trim($m[1]);
            $r = trim($m[2]);
            $l = preg_replace('/^[\s\-–—:]+|[\s\-–—:\.]+$|^[0-9]+[\.\)]\s*/u', '', $l);
            $r = preg_replace('/^[\s\-–—:]+|[\s\-–—:\.]+$|^[0-9]+[\.\)]\s*/u', '', $r);
            if ($l !== '' && $r !== '' && $l !== $r) {
                $pairs[] = [$l, $r];
            }
        }
    }
    return count($pairs) >= 2 ? $pairs : [];
}

/** Pairs for a paper / homework item: the editable "l | r" lines in its text win (teacher edits), else explicit `pairs`, else fetch from book question by bq_id. */
function ep_item_pairs(array $item): array
{
    $pairs = ep_match_pairs($item['text'] ?? '') ?: ep_match_pairs($item['pairs'] ?? null);
    if (!$pairs && !empty($item['bq_id'])) {
        static $bqCache = [];
        $bid = (int)$item['bq_id'];
        if (!array_key_exists($bid, $bqCache)) {
            $bq = ep_book_question($bid);
            $bqCache[$bid] = ($bq && !empty($bq['pairs'])) ? $bq['pairs'] : [];
        }
        if (!empty($bqCache[$bid])) {
            $pairs = $bqCache[$bid];
        }
    }
    return $pairs;
}

/** The question stem of a match item = its text without the "l | r" pair lines. */
function ep_match_stem(string $text): string
{
    $lines = array_filter(preg_split('/\r?\n/', $text), fn($l) => !preg_match('/^.+?\s*\|\s*.+$/u', trim($l)));
    return trim(implode("\n", $lines));
}

/** Editable text form of a match question: stem + one "left | right" line per pair. */
function ep_match_text(string $stem, array $pairs): string
{
    $lines = array_map(fn($p) => $p[0] . ' | ' . $p[1], $pairs);
    return trim($stem . "\n" . implode("\n", $lines));
}

/**
 * Two-column layout: left in textbook order, right shuffled the same way every time (seeded on the pairs)
 * so the student sheet and the teacher key always agree. Returns [left[], right[], key[leftIdx => rightIdx]].
 */
function ep_match_layout(array $pairs): array
{
    $n = count($pairs);
    $order = range(0, $n - 1);
    mt_srand(crc32(json_encode($pairs)));
    for ($i = $n - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
    }
    if ($n > 1 && $order === range(0, $n - 1)) {
        $order[] = array_shift($order);
    }
    $right = [];
    $key = [];
    foreach ($order as $pos => $idx) {
        $right[$pos] = $pairs[$idx][1];
        $key[$idx] = $pos;
    }
    ksort($key);
    return ['left' => array_column($pairs, 0), 'right' => $right, 'key' => $key];
}

/** Label script follows the pairs themselves: Latin-only pairs get 1/2/3 + A/B/C even inside a Marathi paper. */
function ep_match_lang(array $pairs, string $lang): string
{
    return preg_match('/\p{Devanagari}/u', json_encode($pairs, JSON_UNESCAPED_UNICODE)) ? ($lang === 'en' ? 'mr' : $lang) : 'en';
}

function ep_match_labels(string $lang): array
{
    if ($lang === 'en') {
        return ['a' => 'A', 'b' => 'B', 'left' => range(1, 20), 'right' => range('a', 't')];
    }
    $right = ['अ', 'ब', 'क', 'ड', 'इ', 'ई', 'उ', 'ऊ', 'ए', 'ऐ', 'ओ', 'औ'];
    if ($lang === 'hi') {
        $right = ['क', 'ख', 'ग', 'घ', 'च', 'छ', 'ज', 'झ', 'ट', 'ठ', 'ड', 'ढ'];
    }
    $left = array_map(fn($i) => strtr((string)$i, ['0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४', '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९']), range(1, 20));
    return ['a' => $lang === 'hi' ? 'क' : 'अ', 'b' => $lang === 'hi' ? 'ख' : 'ब', 'left' => $left, 'right' => $right];
}

/** Printable two-column table for a match question; with $withKey the correct pairing is shown under it. */
function ep_match_table(array $pairs, string $lang = 'mr', bool $withKey = false): string
{
    if (!$pairs) {
        return '';
    }
    $lang = ep_match_lang($pairs, $lang);
    $lay = ep_match_layout($pairs);
    $lb = ep_match_labels($lang);
    $group = $lang === 'en' ? ['Group A', 'Group B'] : ["'{$lb['a']}' गट", "'{$lb['b']}' गट"];
    $h = '<table class="match-table"><thead><tr><th>' . h($group[0]) . '</th><th>' . h($group[1]) . '</th></tr></thead><tbody>';
    foreach ($lay['left'] as $i => $l) {
        $r = $lay['right'][$i];
        $h .= '<tr><td>(' . $lb['left'][$i] . ') ' . h($l) . '</td><td>(' . $lb['right'][$i] . ') ' . h($r) . '</td></tr>';
    }
    $h .= '</tbody></table>';
    if ($withKey) {
        $h .= '<div class="match-key">' . h(ep_match_key_text($pairs, $lang)) . '</div>';
    }
    return $h;
}

/** Teacher key line, e.g. "(१) – (ब), (२) – (अ)" followed by the pairs in words. */
function ep_match_key_text(array $pairs, string $lang = 'mr'): string
{
    $lang = ep_match_lang($pairs, $lang);
    $lay = ep_match_layout($pairs);
    $lb = ep_match_labels($lang);
    $codes = [];
    $words = [];
    foreach ($lay['key'] as $li => $ri) {
        $codes[] = '(' . $lb['left'][$li] . ') – (' . $lb['right'][$ri] . ')';
        $words[] = $pairs[$li][0] . ' – ' . $pairs[$li][1];
    }
    return implode(', ', $codes) . '  [' . implode('; ', $words) . ']';
}

/** AI topic pack (short notes + 10 MCQ quiz) for a textbook chapter, or null. */
function ep_topic_pack(int $chapterId): ?array
{
    if ($chapterId <= 0) {
        return null;
    }
    $st = ep_db()->prepare('SELECT * FROM ep_topic_packs WHERE chapter_id = ?');
    $st->execute([$chapterId]);
    $p = $st->fetch();
    if (!$p) {
        return null;
    }
    $p['notes'] = json_decode($p['notes_json'], true) ?: [];
    $p['quiz'] = json_decode($p['quiz_json'], true) ?: [];
    unset($p['notes_json'], $p['quiz_json']);
    return $p;
}

/** Classes -> subjects -> books -> chapters (+ question counts) for the textbook bank. */
function ep_book_tree(): array
{
    $rows = ep_db()->query('SELECT b.book_id, b.standard, b.subject, b.medium, b.title, c.chapter_id, c.chapter_no, c.title chapter_title,
                                   (SELECT COUNT(*) FROM ep_book_questions q WHERE q.chapter_id = c.chapter_id) n
                            FROM ep_books b LEFT JOIN ep_book_chapters c ON c.book_id = b.book_id
                            ORDER BY b.standard, b.subject, b.medium, b.book_id, c.chapter_no')->fetchAll();
    $tree = [];
    foreach ($rows as $r) {
        $std = (int)$r['standard'];
        $tree[$std] ??= ['standard' => $std, 'subjects' => []];
        $key = $r['subject'] . ' (' . $r['medium'] . ')';
        $tree[$std]['subjects'][$key] ??= ['name' => $key, 'subject' => $r['subject'], 'medium' => $r['medium'], 'books' => [], 'n' => 0];
        $bid = (int)$r['book_id'];
        $tree[$std]['subjects'][$key]['books'][$bid] ??= ['book_id' => $bid, 'title' => $r['title'], 'chapters' => [], 'n' => 0];
        if ($r['chapter_id']) {
            $tree[$std]['subjects'][$key]['books'][$bid]['chapters'][] = ['chapter_id' => (int)$r['chapter_id'], 'no' => (int)$r['chapter_no'], 'title' => $r['chapter_title'], 'n' => (int)$r['n']];
            $tree[$std]['subjects'][$key]['books'][$bid]['n'] += (int)$r['n'];
            $tree[$std]['subjects'][$key]['n'] += (int)$r['n'];
        }
    }
    foreach ($tree as &$std) {
        foreach ($std['subjects'] as &$s) {
            $s['books'] = array_values(array_filter($s['books'], fn($b) => $b['n'] > 0));
        }
        unset($s);
        $std['subjects'] = array_values(array_filter($std['subjects'], fn($s) => $s['n'] > 0));
    }
    unset($std);
    return array_values(array_filter($tree, fn($s) => $s['subjects']));
}

function ep_book_questions_by_ids(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $st = ep_db()->prepare('SELECT q.*, c.title chapter_title, c.chapter_no FROM ep_book_questions q LEFT JOIN ep_book_chapters c ON c.chapter_id = q.chapter_id
                            WHERE q.bq_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll() as $q) {
        $byId[$q['bq_id']] = ep_decode_book_question($q);
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}

/** Question types that can stand in for a requested type when the exact one is scarce. */
function ep_book_qtype_group(string $qtype): array
{
    $groups = [
        ['fill_blank', 'fillinblanks'],
        ['true_false'],
        ['match'],
        ['mcq'],
        ['odd_one'],
        ['one_word', 'one_sentence'],
        ['short_answer', 'reason', 'define', 'explain'],
        ['descriptive', 'difference'],
        ['solve'],
        ['draw'],
        ['vocabulary', 'grammar'],
        ['activity'],
    ];
    foreach ($groups as $g) {
        if (in_array($qtype, $g, true)) {
            return $g;
        }
    }
    return [$qtype];
}

/** Random textbook questions from the given chapters; exact qtype first, then related types. */
function ep_book_random(array $chapterIds, string $qtype, int $count, array $exclude = []): array
{
    $chapterIds = array_values(array_filter(array_map('intval', $chapterIds)));
    if (!$chapterIds || $count <= 0) {
        return [];
    }
    $exclude = array_values(array_filter(array_map('intval', $exclude)));
    $pick = function (array $types, int $n) use ($chapterIds, &$exclude): array {
        if ($n <= 0) return [];
        $params = $chapterIds;
        $sql = 'SELECT * FROM ep_book_questions WHERE chapter_id IN (' . implode(',', array_fill(0, count($chapterIds), '?')) . ')'
             . " AND LENGTH(text) >= 8 AND text NOT LIKE '%|%' AND text NOT LIKE '%epee%'";
        if ($types) {
            $sql .= ' AND qtype IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            $params = array_merge($params, $types);
        }
        if ($exclude) {
            $sql .= ' AND bq_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
            $params = array_merge($params, $exclude);
        }
        $st = ep_db()->prepare($sql . ' ORDER BY RAND() LIMIT ' . (int)$n);
        $st->execute($params);
        $rows = array_map('ep_decode_book_question', $st->fetchAll());
        foreach ($rows as $r) {
            $exclude[] = (int)$r['bq_id'];
        }
        return $rows;
    };
    
    $out = [];
    if ($qtype !== '') {
        $exactTypes = ($qtype === 'fill_blank' || $qtype === 'fillinblanks') ? ['fill_blank', 'fillinblanks'] : [$qtype];
        $out = $pick($exactTypes, $count);
        if (count($out) < $count) {
            $compat = ep_book_qtype_group($qtype);
            $diff = array_diff($compat, $exactTypes);
            if (!empty($diff)) {
                $out = array_merge($out, $pick($diff, $count - count($out)));
            }
        }
    } else {
        $out = $pick([], $count);
    }
    
    return $out;
}

if (!function_exists('ep_num')) {
    function ep_num($v): int
    {
        return (int)strtr((string)$v, ['०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9']);
    }
}

/** Strip OCR noise from a scanned-paper instruction line. */
function ep_clean_model_text(string $s): string
{
    $s = preg_replace('/\s*\|.*$/u', '', $s);
    $s = preg_replace('/\bepee\b.*$/u', '', $s);
    $s = preg_replace('/\s*\(\s*(?:marks|गुण)\s*[0-9०-९]+\s*\).*$/iu', '', $s);
    $s = preg_replace('/\s+/u', ' ', (string)$s);
    return trim((string)$s, " \t.-:।");
}

function ep_sub_rank(string $sub): int
{
    if ($sub === '') return 0;
    if (preg_match('/^[A-Za-z]$/', $sub)) return ord(strtoupper($sub)) - 64;
    foreach ([['अ', 'ब', 'क', 'ड', 'इ', 'फ'], ['अ', 'आ', 'इ', 'ई', 'उ', 'ऊ']] as $seq) {
        $i = array_search($sub, $seq, true);
        if ($i !== false) return $i + 1;
    }
    return 0;
}

function ep_next_sub(string $sub): string
{
    if ($sub === '') return '';
    if (preg_match('/^[A-Za-z]$/', $sub)) return chr(ord(strtoupper($sub)) + 1);
    $seq = in_array($sub, ['आ', 'ई', 'उ'], true) ? ['अ', 'आ', 'इ', 'ई', 'उ', 'ऊ', 'ए'] : ['अ', 'ब', 'क', 'ड', 'इ', 'फ', 'ग'];
    $i = array_search($sub, $seq, true);
    return $i === false ? '' : ($seq[$i + 1] ?? '');
}

/**
 * Real scanned papers are used only as a STRUCTURE template: question numbers, instructions, marks and
 * how many questions to ask. The OCR question text is dropped — questions are filled from the textbook
 * bank. Section headers that OCR merged into question lines ("Q. 4 (A) Write difference… (marks 6)")
 * become their own sections, and numbering is kept in order.
 */
function ep_clean_model_sections(array $sections): array
{
    $hdr = '/(?:Q\s*\.?|प्रश्न|प्र\s*\.)\s*([0-9०-९]+)\s*[.)]?\s*(?:\(\s*([A-Za-zअ-ह])\s*\))?\s*(.+?)\s*\(\s*(?:marks|गुण)\s*([0-9०-९]+)\s*\)(?:\s*\(\s*(?:any|कोणतेही)\s*([0-9०-९]+)\s*\))?/iu';
    $inline = '/^(.{3,60}?)\s*\(\s*(?:marks|गुण)\s*([0-9०-९]+)\s*\)/iu';
    $raw = [];
    foreach ($sections as $s) {
        $raw[] = ['q_no' => (int)($s['q_no'] ?? 0), 'sub' => trim((string)($s['sub'] ?? '')), 'instruction' => (string)($s['instruction'] ?? ''), 'marks' => (float)($s['marks'] ?? 0), 'any' => 0];
        foreach ($s['items'] ?? [] as $it) {
            $it = (string)$it;
            if (preg_match($hdr, $it, $m)) {
                $raw[] = ['q_no' => ep_num($m[1]), 'sub' => $m[2] ?? '', 'instruction' => $m[3], 'marks' => ep_num($m[4]), 'any' => isset($m[5]) ? ep_num($m[5]) : 0];
            } elseif (preg_match($inline, $it, $m) && ep_answer_space_guess_qtype($m[1]) !== '') {
                $raw[] = ['q_no' => null, 'sub' => null, 'instruction' => $m[1], 'marks' => ep_num($m[2]), 'any' => 0];
            }
        }
    }
    $one = ['fill_blank', 'true_false', 'mcq', 'one_word', 'one_sentence', 'odd_one', 'match', 'vocabulary', 'grammar'];
    $out = [];
    $pq = 0;
    $ps = '';
    foreach ($raw as $r) {
        $instr = ep_clean_model_text($r['instruction']);
        if (preg_match('/^\(\s*([A-Za-zअ-ह])\s*\)\s*(.+)$/u', $instr, $m)) {
            if ((string)$r['sub'] === '') $r['sub'] = $m[1];
            $instr = trim($m[2]);
        }
        if (mb_strlen($instr) < 3 || $r['marks'] <= 0) continue;
        if ($r['q_no'] === null) {
            $q = $pq;
            $sub = ep_next_sub($ps);
        } else {
            $q = (int)$r['q_no'];
            $sub = (string)$r['sub'];
            if ($q < $pq || ($q === $pq && $sub !== '' && $ps !== '' && ep_sub_rank($sub) <= ep_sub_rank($ps))) {
                $q = $pq;
                $sub = ep_next_sub($ps);
            }
        }
        $qtype = ep_answer_space_guess_qtype($instr);
        if ($qtype === '') {
            foreach ([['explain', '/what will happen|काय होईल|explain|स्पष्ट/iu'], ['difference', '/difference|फरक/iu'],
                      ['short_answer', '/answer|उत्तरे|brief|थोडक्यात|लिहा|write/iu']] as [$t, $re]) {
                if (preg_match($re, $instr)) { $qtype = $t; break; }
            }
        }
        $per = in_array($qtype, $one, true) ? 1 : 2;
        $count = max(1, (int)round($r['marks'] / $per));
        if ($qtype === 'match') $count = 1;
        if ($r['any'] > 0) {
            $count = $r['any'] + 1;
            $instr .= preg_match('/[A-Za-z]/', $instr) ? " (any {$r['any']})" : " (कोणतेही {$r['any']})";
        }
        $out[] = ['q_no' => $q, 'sub' => $sub, 'instruction' => $instr, 'marks' => $r['marks'], 'count' => min($count, 12), 'qtype' => $qtype, 'items' => []];
        $pq = $q;
        $ps = $sub;
    }
    return $out;
}

/** Reference संकलित / आकारिक paper structures (from real papers) for a class + subject. */
function ep_paper_models(string $examType, ?int $standard, string $subject = ''): array
{
    $sql = 'SELECT * FROM ep_paper_models WHERE exam_type = ?';
    $params = [$examType];
    if ($standard) {
        $sql .= ' AND standard = ?';
        $params[] = $standard;
    }
    if ($subject !== '') {
        $sql .= ' AND subject = ?';
        $params[] = $subject;
    }
    $st = ep_db()->prepare($sql . ' ORDER BY standard, subject, test_no, model_id');
    $st->execute($params);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['sections'] = ep_clean_model_sections(json_decode($r['sections_json'], true) ?: []);
        unset($r['sections_json']);
        foreach ($r['sections'] as &$s) {      // suggested question type + answer space for the उत्तर-लेखन format
            $s['qtype'] = ($s['qtype'] ?? '') !== '' ? $s['qtype'] : ep_answer_space_guess_qtype((string)($s['instruction'] ?? ''));
            $s['oral'] = (bool)preg_match('/तोंडी|\boral\b/iu', (string)($s['instruction'] ?? ''));
            $sp = ep_answer_space($s, (int)($r['standard'] ?? 0));
            $s['answer_space'] = $sp['mode'] . (isset($sp['lines']) ? ':' . $sp['lines'] : (isset($sp['mm']) ? ':' . $sp['mm'] : ''));
        }
        unset($s);
    }
    return $rows;
}

function ep_json(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Generates HMAC-SHA256 digital authenticity signature for a paper.
 */
function ep_paper_signature(array $paper): string
{
    $secret = 'HPC_EXAM_SIG_SECRET_2026';
    $payload = implode('|', [
        $paper['paper_id'] ?? 0,
        $paper['school_id'] ?? 0,
        $paper['title'] ?? '',
        $paper['total_marks'] ?? 0,
        $paper['created_at'] ?? '',
    ]);
    $raw = hash_hmac('sha256', $payload, $secret);
    $sub = strtoupper(substr($raw, 0, 12));
    return 'EV-' . substr($sub, 0, 4) . '-' . substr($sub, 4, 4) . '-' . substr($sub, 8, 4);
}

function ep_verify_paper_signature(array $paper, string $sig): bool
{
    return hash_equals(ep_paper_signature($paper), trim($sig));
}

/**
 * Deterministically scrambles a question paper for Multi-Set Scrambler Studio (Sets A, B, C, D).
 * Set A is always original. Sets B, C, D have reproducible permutations based on seed.
 */
function ep_scramble_paper(array $paper, string $set = 'A'): array
{
    $set = strtoupper(trim($set));
    if (!in_array($set, ['A', 'B', 'C', 'D'], true)) {
        $set = 'A';
    }
    $paper['current_set'] = $set;
    if ($set === 'A') {
        return $paper;
    }

    $paperId = (int)($paper['paper_id'] ?? 1);
    // Use deterministic seed for this paper + set
    $seed = crc32($paperId . '_EXAM_SET_' . $set);
    mt_srand($seed);

    if (!empty($paper['sections'])) {
        foreach ($paper['sections'] as &$sec) {
            // Assessment paper with items
            if (!empty($sec['items']) && is_array($sec['items'])) {
                $items = $sec['items'];
                $count = count($items);
                if ($count > 1) {
                    for ($i = $count - 1; $i > 0; $i--) {
                        $j = mt_rand(0, $i);
                        $tmp = $items[$i];
                        $items[$i] = $items[$j];
                        $items[$j] = $tmp;
                    }
                }
                $sec['items'] = $items;
            }
            // Regular/competitive paper with question_ids
            if (!empty($sec['question_ids']) && is_array($sec['question_ids'])) {
                $qids = $sec['question_ids'];
                $count = count($qids);
                if ($count > 1) {
                    for ($i = $count - 1; $i > 0; $i--) {
                        $j = mt_rand(0, $i);
                        $tmp = $qids[$i];
                        $qids[$i] = $qids[$j];
                        $qids[$j] = $tmp;
                    }
                }
                $sec['question_ids'] = $qids;
            }
        }
        unset($sec);
    }

    // Reset random generator seed to arbitrary random
    mt_srand();
    return $paper;
}

/**
 * Get active school header branding style ('board', 'center', 'modern', 'compact').
 */
function ep_header_style(?string $override = null): string
{
    $valid = ['board', 'center', 'modern', 'compact'];
    if ($override && in_array($override, $valid, true)) {
        return $override;
    }
    if (!empty($_GET['header_style']) && in_array($_GET['header_style'], $valid, true)) {
        return $_GET['header_style'];
    }
    $saved = ep_setting('header_style', 'board');
    return in_array($saved, $valid, true) ? $saved : 'board';
}
