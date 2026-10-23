<?php
/**
 * Stage-aware HPC save endpoint (इयत्ता ३–८, पूर्वतयारी + पूर्वमाध्यमिक).
 * Drop into  hpc/save_stage.php  next to save_rubric.php (which stays as-is for std 1–2).
 *
 * POST JSON: {csrf, student_id, action, ...}
 *   action=subject  : subject_key, term(1|2), fields of hpc_subject_assessments (see $ALLOW_SUBJECT)
 *                     goal_codes / comp_codes are validated against hpc_goals / hpc_competencies for the
 *                     student's stage+subject -> error 'goal:<code>' / 'comp:<code>' on unknown codes.
 *   action=summary  : subject_key, level_awareness|level_sensitivity|level_creativity, remark_mr, credit_points_earned
 *   action=parta    : intro_json, parent_json, parent_teacher_json (objects)
 *   action=status   : status (draft|completed)
 * Returns {success:true, card_id, stage_key, std} or {success:false, error}.
 */
require_once __DIR__ . '/rubric_lib.php';                 // getDB(), isLoggedIn(), hpc_rb_student(), hpc_rb_card()
require_once __DIR__ . '/../includes/hpc_curriculum.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'login']); exit; }
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($in['csrf'] ?? ''))) {
    http_response_code(403); echo json_encode(['success' => false, 'error' => 'csrf']); exit;
}

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$student = hpc_rb_student($db, $school_id, (int) ($in['student_id'] ?? 0));
if (!$student) { echo json_encode(['success' => false, 'error' => 'student']); exit; }

$std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade']);
$stage = hpc_stage_for_std($std);
if ($std < 3 || $std > 8) { echo json_encode(['success' => false, 'error' => 'stage', 'hint' => 'std 1-2 -> save_rubric.php']); exit; }

$chkAccess = can_access_hpc_class($std, $school_id);
if (!$chkAccess['allowed']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'plan_restricted', 'message' => $chkAccess['message'] ?? 'उच्च वर्गांसाठी सशुल्क योजना आवश्यक आहे.']);
    exit;
}

$card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
if (!$card) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'quota_exceeded',
        'message' => 'आपल्या योजनेची कमाल विद्यार्थी मर्यादा पूर्ण झाली आहे. पुढील विद्यार्थ्यांसाठी कृपया योजना अपग्रेड करा.'
    ]);
    exit;
}
if (empty($card['stage_key']) || (int) $card['std'] !== $std) {
    $db->prepare("UPDATE hpc_cards SET stage_key = ?, std = ?, print_format = ? WHERE id = ?")
       ->execute([$stage, $std, $stage === 'middle' ? 'scert-middle' : 'scert-prep', $card['id']]);
}

$ALLOW_SUBJECT = [
    'goal_codes' => 'json', 'comp_codes' => 'json', 'topic_keys' => 'json', 'activity_approach' => 'json',
    'activity_mr' => 'text', 'assessment_questions_mr' => 'text', 'rubric_json' => 'json',
    'level_awareness' => 'level', 'level_sensitivity' => 'level', 'level_creativity' => 'level',
    'teacher_notes_mr' => 'text', 'challenges_mr' => 'text', 'support_mr' => 'text',
    'self_reflection' => 'json', 'self_progress' => 'json', 'self_progress_count' => 'json',
    'my_learning_mr' => 'text', 'liked_most_mr' => 'text', 'need_practice_mr' => 'text', 'need_help_mr' => 'text',
    'peer_name' => 'text', 'peer_reflection' => 'json', 'peer_progress' => 'json', 'peer_progress_count' => 'json',
    'peer_need_practice_mr' => 'text', 'peer_need_help_mr' => 'text',
    'strengths' => 'json', 'hurdles' => 'json', 'teacher_help_mr' => 'text', 'teacher_observation_mr' => 'text',
    'wheel_json' => 'json',
];

function hpc_ss_clean_level($v): ?string
{
    $v = (string) $v;
    return in_array($v, ['prarambhik', 'praveen', 'pragat'], true) ? $v : null;
}

try {
    $action = (string) ($in['action'] ?? 'subject');

    if ($action === 'status') {
        $status = ($in['status'] ?? '') === 'completed' ? 'completed' : 'draft';
        $db->prepare("UPDATE hpc_cards SET status = ? WHERE id = ?")->execute([$status, $card['id']]);
        echo json_encode(['success' => true, 'card_id' => (int) $card['id'], 'stage_key' => $stage, 'std' => $std]); exit;
    }

    if ($action === 'parta') {
        $cols = ['intro_json', 'parent_json', 'parent_teacher_json'];
        $vals = [];
        foreach ($cols as $c) $vals[] = isset($in[$c]) ? json_encode($in[$c], JSON_UNESCAPED_UNICODE) : null;
        $db->prepare("INSERT INTO hpc_card_parta (hpc_card_id, intro_json, parent_json, parent_teacher_json) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE intro_json = COALESCE(VALUES(intro_json), intro_json),
                                              parent_json = COALESCE(VALUES(parent_json), parent_json),
                                              parent_teacher_json = COALESCE(VALUES(parent_teacher_json), parent_teacher_json)")
           ->execute(array_merge([(int) $card['id']], $vals));
        echo json_encode(['success' => true, 'card_id' => (int) $card['id']]); exit;
    }

    $subject = hpc_subject_by_key($stage, (string) ($in['subject_key'] ?? ''));
    if (!$subject) { echo json_encode(['success' => false, 'error' => 'subject']); exit; }
    $sid = (int) $subject['id'];

    if ($action === 'summary') {
        $db->prepare("INSERT INTO hpc_subject_summary (hpc_card_id, subject_id, level_awareness, level_sensitivity, level_creativity, remark_mr, credit_points_earned)
                      VALUES (?,?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE level_awareness = VALUES(level_awareness), level_sensitivity = VALUES(level_sensitivity),
                          level_creativity = VALUES(level_creativity), remark_mr = VALUES(remark_mr), credit_points_earned = VALUES(credit_points_earned)")
           ->execute([(int) $card['id'], $sid,
               hpc_ss_clean_level($in['level_awareness'] ?? ''), hpc_ss_clean_level($in['level_sensitivity'] ?? ''),
               hpc_ss_clean_level($in['level_creativity'] ?? ''), trim((string) ($in['remark_mr'] ?? '')),
               isset($in['credit_points_earned']) && $in['credit_points_earned'] !== '' ? (float) $in['credit_points_earned'] : null]);
        echo json_encode(['success' => true, 'card_id' => (int) $card['id']]); exit;
    }

    // action=subject -----------------------------------------------------------------------------------------------
    $term = ((int) ($in['term'] ?? 1)) === 2 ? 2 : 1;

    // explicit goal / competency validation for this stage + subject
    $validGoals = array_column(hpc_goals($subject), 'goal_code');
    $validComps = array_column(hpc_competencies($subject), 'comp_code');
    foreach ((array) ($in['goal_codes'] ?? []) as $g) {
        if (!in_array((string) $g, $validGoals, true)) { echo json_encode(['success' => false, 'error' => 'goal:' . $g]); exit; }
    }
    foreach ((array) ($in['comp_codes'] ?? []) as $c) {
        if (!in_array((string) $c, $validComps, true)) { echo json_encode(['success' => false, 'error' => 'comp:' . $c]); exit; }
    }

    $set = []; $vals = [];
    foreach ($ALLOW_SUBJECT as $col => $type) {
        if (!array_key_exists($col, $in)) continue;
        $v = $in[$col];
        if ($type === 'json') $v = $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE);
        elseif ($type === 'level') $v = hpc_ss_clean_level($v);
        else $v = trim((string) $v);
        $set[] = "$col = ?"; $vals[] = $v;
    }
    // denormalise levels out of rubric_json when the caller only sent rubric_json
    if (isset($in['rubric_json']) && is_array($in['rubric_json'])) {
        foreach (['awareness', 'sensitivity', 'creativity'] as $ab) {
            if (!array_key_exists('level_' . $ab, $in) && isset($in['rubric_json'][$ab]['level'])) {
                $set[] = "level_$ab = ?"; $vals[] = hpc_ss_clean_level($in['rubric_json'][$ab]['level']);
            }
        }
    }

    $db->prepare("INSERT IGNORE INTO hpc_subject_assessments (hpc_card_id, subject_id, term) VALUES (?,?,?)")
       ->execute([(int) $card['id'], $sid, $term]);
    if ($set) {
        $vals[] = (int) $card['id']; $vals[] = $sid; $vals[] = $term;
        $db->prepare("UPDATE hpc_subject_assessments SET " . implode(', ', $set) . " WHERE hpc_card_id = ? AND subject_id = ? AND term = ?")
           ->execute($vals);
    }
    echo json_encode(['success' => true, 'card_id' => (int) $card['id'], 'stage_key' => $stage, 'std' => $std, 'subject_id' => $sid, 'term' => $term]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db', 'detail' => $e->getMessage()]);
}
