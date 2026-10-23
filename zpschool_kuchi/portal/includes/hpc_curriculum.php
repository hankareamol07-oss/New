<?php
/**
 * HPC curriculum access layer (stage-aware, std 1–8).
 *
 * Reads the normalised tables created by sql/01_hpc_curriculum_schema.sql.
 * If the tables are missing (or HPC_CURRICULUM_JSON is defined) it falls back
 * to data/hpc_curriculum.json so the print template can run without a DB.
 *
 * Drop this file in  includes/  of the live portal.
 * Foundational stage (std 1–2) keeps using includes/hpc_fs_data.php; the
 * helpers below wrap it so callers only need ONE API.
 */

if (!defined('HPC_CURRICULUM_JSON')) {
    define('HPC_CURRICULUM_JSON', __DIR__ . '/../data/hpc_curriculum.json');
}
if (!defined('HPC_RUBRIC_BANK_JSON')) {
    define('HPC_RUBRIC_BANK_JSON', __DIR__ . '/../data/hpc_rubric_bank.json');
}
if (!defined('HPC_PHRASE_BANK_JSON')) {
    define('HPC_PHRASE_BANK_JSON', __DIR__ . '/../data/hpc_phrase_bank.json');
}
if (!defined('HPC_ACTIVITY_BANK_JSON')) {
    define('HPC_ACTIVITY_BANK_JSON', __DIR__ . '/../data/hpc_activity_bank.json');
}
if (!defined('HPC_TOPIC_MAP_JSON')) {
    define('HPC_TOPIC_MAP_JSON', __DIR__ . '/../data/hpc_topic_map.json');
}

/* ---------------------------------------------------------------- stages */

function hpc_stage_for_std(int $std): string
{
    if ($std >= 1 && $std <= 2) return 'foundational';
    if ($std >= 3 && $std <= 5) return 'preparatory';
    if ($std >= 6 && $std <= 8) return 'middle';
    return 'foundational';
}

function hpc_stage_name_mr(string $stage): string
{
    return ['foundational' => 'पायाभूत स्तर', 'preparatory' => 'पूर्वतयारी स्तर', 'middle' => 'पूर्वमाध्यमिक स्तर'][$stage] ?? $stage;
}

function hpc_stage_std_range(string $stage): string
{
    return ['foundational' => 'इयत्ता पहिली व दुसरी', 'preparatory' => 'इयत्ता तिसरी ते पाचवी', 'middle' => 'इयत्ता सहावी ते आठवी'][$stage] ?? '';
}

/** students.grade ('इयत्ता ३', 'तिसरी', '3', '३') -> 1..8 (0 = unknown) */
function hpc_std_from_grade($grade): int
{
    if (function_exists('std_from_grade')) return (int) std_from_grade($grade);
    $g = strtr((string) $grade, ['०'=>'0','१'=>'1','२'=>'2','३'=>'3','४'=>'4','५'=>'5','६'=>'6','७'=>'7','८'=>'8','९'=>'9']);
    if (preg_match('/(\d+)/', $g, $m) && $m[1] >= 1 && $m[1] <= 8) return (int) $m[1];
    $w = ['पहिली'=>1,'दुसरी'=>2,'तिसरी'=>3,'चौथी'=>4,'पाचवी'=>5,'सहावी'=>6,'सातवी'=>7,'आठवी'=>8];
    foreach ($w as $k => $n) if (mb_strpos((string) $grade, $k) !== false) return $n;
    return 0;
}

/* ------------------------------------------------------------ data source */

function hpc_cur_json(): array
{
    static $J = null;
    if ($J === null) {
        $J = is_file(HPC_CURRICULUM_JSON) ? (json_decode(file_get_contents(HPC_CURRICULUM_JSON), true) ?: []) : [];
    }
    return $J;
}

/** true when the normalised tables exist in the connected DB */
function hpc_cur_db_ready(?PDO $db): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!$db) return $ok = false;
    try {
        $db->query("SELECT 1 FROM hpc_subjects LIMIT 1");
        return $ok = true;
    } catch (Throwable $e) {
        return $ok = false;
    }
}

function hpc_cur_db(): ?PDO
{
    if (defined('HPC_FORCE_JSON') && HPC_FORCE_JSON) return null;
    return function_exists('getDB') ? getDB() : null;
}

/* --------------------------------------------------------------- subjects */

/**
 * Subjects (or FS domains) shown on the card for a std.
 * Returns list of ['id','stage_key','subject_key','code_prefix','name_mr','name_en','is_domain','legacy_domain_id','sort_order']
 */
function hpc_subjects_for_std(int $std): array
{
    $stage = hpc_stage_for_std($std);
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db)) {
        $q = $db->prepare("SELECT s.* FROM hpc_subjects s JOIN hpc_subject_std x ON x.subject_id = s.id
                           WHERE s.stage_key = ? AND x.std = ? ORDER BY s.sort_order");
        $q->execute([$stage, $std]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    $out = [];
    foreach (hpc_cur_json()['subjects'] ?? [] as $i => $s) {
        $stds = $s['std_list'] ?? ($s['stds'] ?? []);
        if ($s['stage_key'] === $stage && in_array($std, $stds, true)) {
            $s['id'] = $s['id'] ?? ($i + 1);
            $out[] = $s;
        }
    }
    usort($out, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
    return $out;
}

function hpc_subject_by_key(string $stage, string $subject_key): ?array
{
    foreach (hpc_cur_json()['subjects'] ?? [] as $i => $s) {
        if ($s['stage_key'] === $stage && $s['subject_key'] === $subject_key) { $s['id'] = $s['id'] ?? ($i + 1); return $s; }
    }
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db)) {
        $q = $db->prepare("SELECT * FROM hpc_subjects WHERE stage_key = ? AND subject_key = ?");
        $q->execute([$stage, $subject_key]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    return null;
}

/** ['L1CG1' => ['goal_code','text_mr','scf_code','provenance',…], …] */
function hpc_goals(array $subject): array
{
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db) && !empty($subject['id']) && empty($subject['goals'])) {
        $q = $db->prepare("SELECT * FROM hpc_goals WHERE subject_id = ? ORDER BY sort_order, id");
        $q->execute([(int) $subject['id']]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = $subject['goals'] ?? (hpc_subject_by_key($subject['stage_key'], $subject['subject_key'])['goals'] ?? []);
    }
    $out = [];
    foreach ($rows as $r) $out[$r['goal_code']] = $r;
    return $out;
}

/** ['L1C1.1' => ['comp_code','goal_code','text_mr',…], …] */
function hpc_competencies(array $subject): array
{
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db) && !empty($subject['id']) && empty($subject['competencies'])) {
        $q = $db->prepare("SELECT c.*, g.goal_code FROM hpc_competencies c LEFT JOIN hpc_goals g ON g.id = c.goal_id
                           WHERE c.subject_id = ? ORDER BY c.sort_order, c.id");
        $q->execute([(int) $subject['id']]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = $subject['competencies'] ?? (hpc_subject_by_key($subject['stage_key'], $subject['subject_key'])['competencies'] ?? []);
    }
    $out = [];
    foreach ($rows as $r) $out[$r['comp_code']] = $r;
    return $out;
}

/** goal code for a competency code, e.g. L1C3.2 -> L1CG3, MC2.1 -> MCG2, C-1.1 -> CG-1 */
function hpc_goal_code_of(string $comp_code): string
{
    if (preg_match('/^([A-Z]+)C(\d+)\.\d+$/', $comp_code, $m)) return $m[1] . 'CG' . $m[2];
    if (preg_match('/^C-(\d+)\.\d+$/', $comp_code, $m)) return 'CG-' . $m[1];
    return '';
}

/* ----------------------------------------------------------------- rubric */

function hpc_rubric_abilities(string $stage): array
{
    $J = hpc_cur_json()['rubric'][$stage]['abilities'] ?? null;
    if ($J) return $J;
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db)) {
        $q = $db->prepare("SELECT * FROM hpc_rubric_abilities WHERE stage_key = ? ORDER BY sort_order");
        $q->execute([$stage]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    return [
        ['ability_key' => 'awareness',   'name_mr' => 'जाणीवजागृती',  'name_en' => 'Awareness',   'color_hex' => '#f4b6c8'],
        ['ability_key' => 'sensitivity', 'name_mr' => 'संवेदनशीलता', 'name_en' => 'Sensitivity', 'color_hex' => '#fdf3b0'],
        ['ability_key' => 'creativity',  'name_mr' => 'सर्जनशीलता',  'name_en' => 'Creativity',  'color_hex' => '#b7dcf3'],
    ];
}

function hpc_rubric_levels(string $stage): array
{
    $J = hpc_cur_json()['rubric'][$stage]['levels'] ?? null;
    if ($J) return $J;
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db)) {
        $q = $db->prepare("SELECT * FROM hpc_rubric_levels WHERE stage_key = ? ORDER BY sort_order");
        $q->execute([$stage]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($stage === 'foundational') {
        return [
            ['level_key' => 'nirjhar', 'name_mr' => 'निर्झर', 'name_en' => 'Stream'],
            ['level_key' => 'parvat',  'name_mr' => 'पर्वत',  'name_en' => 'Mountain'],
            ['level_key' => 'akash',   'name_mr' => 'आकाश',   'name_en' => 'Sky'],
        ];
    }
    return [
        ['level_key' => 'prarambhik', 'name_mr' => 'प्रारंभिक', 'name_en' => 'Beginner',   'score_min' => 0, 'score_max' => 2],
        ['level_key' => 'praveen',    'name_mr' => 'प्रवीण',    'name_en' => 'Proficient', 'score_min' => 3, 'score_max' => 4],
        ['level_key' => 'pragat',     'name_mr' => 'प्रगत',     'name_en' => 'Advanced',   'score_min' => 5, 'score_max' => 6],
    ];
}

/**
 * AI-generated std-wise rubric suggestions (table hpc_rubric_bank / data/hpc_rubric_bank.json).
 * Returns [comp_code => [ability_key => [level_key => text_mr]]] for one std + subject.
 * Suggestion text only (provenance ai_generated) – the teacher edits/confirms it in the form.
 */
function hpc_rubric_bank(int $std, string $subjectKey): array
{
    static $J = null;
    $out = [];
    $db = hpc_cur_db();
    if ($db && hpc_cur_db_ready($db)) {
        try {
            $q = $db->prepare("SELECT comp_code, ability_key, level_key, text_mr FROM hpc_rubric_bank WHERE std = ? AND subject_key = ? ORDER BY comp_code, ability_key, level_key");
            $q->execute([$std, $subjectKey]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[$r['comp_code']][$r['ability_key']][$r['level_key']] = $r['text_mr'];
            }
            if ($out) return $out;
        } catch (PDOException $e) { /* table not installed yet -> JSON */ }
    }
    if ($J === null) {
        $J = is_file(HPC_RUBRIC_BANK_JSON) ? (json_decode(file_get_contents(HPC_RUBRIC_BANK_JSON), true)['rows'] ?? []) : [];
    }
    foreach ($J as $r) {
        if ((int) $r['std'] === $std && $r['subject_key'] === $subjectKey) {
            $out[$r['comp_code']][$r['ability_key']][$r['level_key']] = $r['text_mr'];
        }
    }
    return $out;
}

/**
 * Dropdown phrases for the free-text fields (table hpc_phrase_bank / data/hpc_phrase_bank.json).
 * Returns [field => [text_mr, ...]] for one std + subject; std-independent rows (std = 0) are appended.
 */
function hpc_phrase_bank(int $std, string $subjectKey): array
{
    static $J = null;
    $rows = [];
    $db = hpc_cur_db();
    if ($db && hpc_cur_db_ready($db)) {
        try {
            $q = $db->prepare("SELECT std, field, text_mr FROM hpc_phrase_bank WHERE std IN (0, ?) AND subject_key IN ('*', ?) ORDER BY std DESC, id");
            $q->execute([$std, $subjectKey]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { /* table not installed yet -> JSON */ }
    }
    if (!$rows) {
        if ($J === null) {
            $J = is_file(HPC_PHRASE_BANK_JSON) ? (json_decode(file_get_contents(HPC_PHRASE_BANK_JSON), true)['rows'] ?? []) : [];
        }
        foreach ($J as $r) {
            if (in_array((int) $r['std'], [0, $std], true) && in_array($r['subject_key'], ['*', $subjectKey], true)) $rows[] = $r;
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $t = trim($r['text_mr']);
        if ($t !== '' && !in_array($t, $out[$r['field']] ?? [], true)) $out[$r['field']][] = $t;
    }
    return $out;
}

/**
 * Activity suggestions per competency (table hpc_activity_bank / data/hpc_activity_bank.json).
 * Returns [comp_code => ['approach','title','activity_mr','learning_outcome_mr','cwsn_note_mr','assessment_questions'=>[],'provenance']].
 */
function hpc_activity_bank(int $std, string $subjectKey): array
{
    static $J = null;
    $out = [];
    $db = hpc_cur_db();
    if ($db && hpc_cur_db_ready($db)) {
        try {
            $q = $db->prepare("SELECT comp_code, approach, title, activity_mr, learning_outcome_mr, cwsn_note_mr, assessment_questions_json, provenance FROM hpc_activity_bank WHERE std = ? AND subject_key = ? ORDER BY comp_code, provenance");
            $q->execute([$std, $subjectKey]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $r['assessment_questions'] = json_decode($r['assessment_questions_json'] ?? '[]', true) ?: [];
                unset($r['assessment_questions_json']);
                $out[$r['comp_code']] = $r;
            }
            if ($out) return $out;
        } catch (PDOException $e) { /* table not installed yet -> JSON */ }
    }
    if ($J === null) {
        $J = is_file(HPC_ACTIVITY_BANK_JSON) ? (json_decode(file_get_contents(HPC_ACTIVITY_BANK_JSON), true)['rows'] ?? []) : [];
    }
    foreach ($J as $r) {
        if ((int) $r['std'] === $std && $r['subject_key'] === $subjectKey) {
            $out[$r['comp_code']] = ['comp_code' => $r['comp_code'], 'approach' => $r['approach'], 'title' => $r['title'], 'activity_mr' => $r['activity_mr'],
                'learning_outcome_mr' => $r['learning_outcome_mr'], 'cwsn_note_mr' => $r['cwsn_note_mr'], 'assessment_questions' => $r['assessment_questions'],
                'provenance' => $r['provenance'] ?? 'ai_generated'];
        }
    }
    return $out;
}

/**
 * 2026-27 तचन (annual plan) topics of a std mapped to an HPC subject (table hpc_topic_map / data/hpc_topic_map.json).
 * Returns rows ordered by tachan subject + seq:
 *   ['key' => 'std|tachan_subject|seq', 'tachan_subject', 'seq', 'topic', 'learning_outcome', 'comp_codes' => [], 'ep_book_id', 'ep_chapter_no']
 * The same std/subject/seq identity is used by exam_paper (ep_book_chapters.tachan_seq) and the YouTube projects.
 */
function hpc_topics(int $std, string $subjectKey): array
{
    static $J = null;
    $rows = [];
    $db = hpc_cur_db();
    if ($db && hpc_cur_db_ready($db)) {
        try {
            $q = $db->prepare("SELECT std, tachan_subject, tachan_seq, topic, learning_outcome, comp_codes_json, ep_book_id, ep_chapter_no, ep_chapter_title
                               FROM hpc_topic_map WHERE std = ? AND hpc_subject_key = ? ORDER BY tachan_subject, tachan_seq");
            $q->execute([$std, $subjectKey]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $r['comp_codes'] = json_decode($r['comp_codes_json'] ?? '[]', true) ?: [];
                unset($r['comp_codes_json']);
                $rows[] = $r;
            }
        } catch (PDOException $e) { /* table not installed yet -> JSON */ }
    }
    if (!$rows) {
        if ($J === null) {
            $J = is_file(HPC_TOPIC_MAP_JSON) ? (json_decode(file_get_contents(HPC_TOPIC_MAP_JSON), true)['rows'] ?? []) : [];
        }
        foreach ($J as $r) {
            if ((int) $r['std'] === $std && ($r['hpc_subject_key'] ?? '') === $subjectKey) $rows[] = $r;
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['key' => $std . '|' . $r['tachan_subject'] . '|' . (int) $r['tachan_seq'], 'tachan_subject' => $r['tachan_subject'],
            'seq' => (int) $r['tachan_seq'], 'topic' => $r['topic'], 'learning_outcome' => $r['learning_outcome'] ?? '',
            'comp_codes' => array_values((array) ($r['comp_codes'] ?? [])), 'ep_book_id' => $r['ep_book_id'] ?? null, 'ep_chapter_no' => $r['ep_chapter_no'] ?? null,
            'ep_chapter_title' => $r['ep_chapter_title'] ?? null];
    }
    return $out;
}

/**
 * Gets assessment questions for specific competencies or stage/subject.
 * Fallbacks from hpc_activity_bank, hpc_assessment_questions, and default Marathi questions.
 */
function hpc_get_assessment_questions(int $std, string $subjectKey, array $compCodes = []): array
{
    $questions = [];
    $actBank = hpc_activity_bank($std, $subjectKey);
    foreach ($compCodes as $cc) {
        if (!empty($actBank[$cc]['assessment_questions'])) {
            foreach ($actBank[$cc]['assessment_questions'] as $q) {
                $q = trim((string)$q);
                if ($q !== '' && !in_array($q, $questions, true)) $questions[] = $q;
            }
        }
    }
    if (empty($questions) && $actBank) {
        foreach ($actBank as $ab) {
            if (!empty($ab['assessment_questions'])) {
                foreach ($ab['assessment_questions'] as $q) {
                    $q = trim((string)$q);
                    if ($q !== '' && !in_array($q, $questions, true)) $questions[] = $q;
                    if (count($questions) >= 5) break 2;
                }
            }
        }
    }
    // Also try hpc_assessment_questions table
    if (empty($questions)) {
        $db = hpc_cur_db();
        if ($db) {
            try {
                if ($compCodes) {
                    $inQ = implode(',', array_fill(0, count($compCodes), '?'));
                    $st = $db->prepare("SELECT question_mr FROM hpc_assessment_questions WHERE competency_code IN ($inQ) AND question_mr != '' LIMIT 5");
                    $st->execute($compCodes);
                    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
                    if ($rows) $questions = array_filter(array_map('trim', $rows));
                }
                if (empty($questions)) {
                    $st = $db->prepare("SELECT question_mr FROM hpc_assessment_questions WHERE grade LIKE ? AND question_mr != '' LIMIT 5");
                    $st->execute(['%' . $std . '%']);
                    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
                    if ($rows) $questions = array_filter(array_map('trim', $rows));
                }
            } catch (Throwable $e) {}
        }
    }
    return $questions;
}

/** middle-stage गुणांकन योजना: circled-statement count -> level_key */
function hpc_level_from_count(string $stage, int $n): string
{
    foreach (hpc_rubric_levels($stage) as $l) {
        if (isset($l['score_min']) && $n >= (int) $l['score_min'] && $n <= (int) $l['score_max']) return $l['level_key'];
    }
    return '';
}

/* ------------------------------------------------------------- statements */

/** fixed card statements: kind = self_reflection | progress_chart | peer_reflection | peer_progress | strength | hurdle | activity_approach | self_assessment */
function hpc_statements(string $stage, string $kind): array
{
    $J = hpc_cur_json()['statements'][$stage][$kind] ?? null;
    if ($J !== null) return $J;
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db)) {
        $q = $db->prepare("SELECT * FROM hpc_card_statements WHERE stage_key = ? AND kind = ? ORDER BY sort_order, id");
        $q->execute([$stage, $kind]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

/* ---------------------------------------------------------------- credits */

function hpc_credit_std(int $std): ?array
{
    $J = hpc_cur_json()['credits'][(string) $std] ?? null;
    if ($J) return $J;
    $db = hpc_cur_db();
    if (hpc_cur_db_ready($db)) {
        $q = $db->prepare("SELECT * FROM hpc_credit_std WHERE std = ?");
        $q->execute([$std]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $q = $db->prepare("SELECT c.*, s.subject_key, s.name_mr FROM hpc_credit_subject c JOIN hpc_subjects s ON s.id = c.subject_id WHERE c.std = ? ORDER BY s.sort_order");
        $q->execute([$std]);
        $row['subjects'] = $q->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }
    return null;
}

/**
 * Check if the school can access HPC for a given standard/grade.
 * Rule: Standard 1 and 2 (Foundational Stage) are 100% Free.
 * Higher classes (Standard 3 upwards) require a Paid Plan or HPC Higher Class subscription.
 */
if (!function_exists('can_access_hpc_class')) {
    function can_access_hpc_class(int $std, ?int $school_id = null): array
    {
        return [
            'allowed'    => true,
            'is_higher'  => ($std >= 3),
            'reason'     => 'unlimited_single_school',
            'message'    => 'सर्व वर्ग १ ते ७ सक्रिय'
        ];
    }
}

