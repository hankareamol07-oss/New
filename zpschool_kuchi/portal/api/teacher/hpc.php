<?php
/**
 * GET / POST /api/teacher/hpc.php
 * Holistic Progress Card (HPC) - 3-Level Rubrics (निर्झर, पर्वत, आकाश)
 * Strictly constrained to teacher's assigned class students.
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';
require_once __DIR__ . '/../../includes/hpc_fs_data.php';

$user = requireRole(['teacher', 'admin']);
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Input
$in = getJsonInput();
$student_id = (int)($in['student_id'] ?? 0);
if ($student_id <= 0) {
    apiError('विद्यार्थी आयडी (student_id) आवश्यक आहे.', 400);
}

// Strict Access Assertion: Teacher MUST have access to this student!
$student = assertTeacherStudentAccess($user, $student_id);
$std = (int)$student['std'];
$academic_year = ACADEMIC_YEAR;

if ($method === 'GET') {
    // 1. Get or create HPC Card
    $st = $db->prepare("SELECT * FROM hpc_cards WHERE student_id = ? AND school_id = ? AND academic_year = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$student_id, SCHOOL_ID, $academic_year]);
    $card = $st->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        $ins = $db->prepare("INSERT INTO hpc_cards (student_id, school_id, academic_year, status) VALUES (?, ?, ?, 'draft')");
        $ins->execute([$student_id, SCHOOL_ID, $academic_year]);
        $st->execute([$student_id, SCHOOL_ID, $academic_year]);
        $card = $st->fetch(PDO::FETCH_ASSOC);
    }

    $card_id = (int)$card['id'];

    // 2. Fetch Domain Assessments
    $ast = $db->prepare("SELECT * FROM hpc_domain_assessments WHERE hpc_card_id = ? ORDER BY domain_id ASC");
    $ast->execute([$card_id]);
    $assessments = [];
    foreach ($ast->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $assessments[(int)$a['domain_id']] = $a;
    }

    // 3. Fetch Kamgiri (Strengths & Improvements)
    $kst = $db->prepare("SELECT * FROM hpc_kamgiri WHERE hpc_card_id = ?");
    $kst->execute([$card_id]);
    $kamgiri = [];
    foreach ($kst->fetchAll(PDO::FETCH_ASSOC) as $k) {
        $kamgiri[(int)$k['domain_id']] = $k;
    }

    // 4. Domains Master Data
    $domains = hpc_fs_domains();

    apiSuccess([
        'student'     => $student,
        'card'        => $card,
        'assessments' => $assessments,
        'kamgiri'     => $kamgiri,
        'domains'     => $domains,
        'rubric_levels' => [
            ['level' => 1, 'name' => 'निर्झर', 'desc' => 'सुरुवातीचा स्तर'],
            ['level' => 2, 'name' => 'पर्वत',  'desc' => 'मध्यम / प्रगतीपथावर स्तर'],
            ['level' => 3, 'name' => 'आकाश',  'desc' => 'प्रवीण / उत्कृष्ट स्तर'],
        ]
    ], 'HPC कार्ड तपशील लोड झाले.');
}

if ($method === 'POST') {
    // Save Rubrics, Feedback, or Kamgiri
    $st = $db->prepare("SELECT id FROM hpc_cards WHERE student_id = ? AND school_id = ? AND academic_year = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$student_id, SCHOOL_ID, $academic_year]);
    $card = $st->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        $db->prepare("INSERT INTO hpc_cards (student_id, school_id, academic_year, status) VALUES (?, ?, ?, 'draft')")
           ->execute([$student_id, SCHOOL_ID, $academic_year]);
        $st->execute([$student_id, SCHOOL_ID, $academic_year]);
        $card = $st->fetch(PDO::FETCH_ASSOC);
    }

    $card_id = (int)$card['id'];
    $domain_id = (int)($in['domain_id'] ?? 1);
    $domain_name = trim((string)($in['domain_name'] ?? 'शारीरिक विकास'));
    $domain_name_mr = trim((string)($in['domain_name_mr'] ?? $domain_name));

    // Rubric levels (1 = निर्झर, 2 = पर्वत, 3 = आकाश)
    $awareness = trim((string)($in['awareness_level'] ?? ''));
    $sensitivity = trim((string)($in['sensitivity_level'] ?? ''));
    $creativity = trim((string)($in['creativity_level'] ?? ''));
    $teacher_feedback = trim((string)($in['teacher_feedback'] ?? $in['teacher_feedback_mr'] ?? ''));

    // Self & Peer emojis / notes
    $self_assessment = trim((string)($in['self_assessment'] ?? ''));
    $peer_assessment = trim((string)($in['peer_assessment'] ?? ''));

    // Upsert hpc_domain_assessments
    $chk = $db->prepare("SELECT id FROM hpc_domain_assessments WHERE hpc_card_id = ? AND domain_id = ? LIMIT 1");
    $chk->execute([$card_id, $domain_id]);
    $dom_row = $chk->fetch(PDO::FETCH_ASSOC);

    if ($dom_row) {
        $upd = $db->prepare("UPDATE hpc_domain_assessments SET 
            awareness_level = ?, sensitivity_level = ?, creativity_level = ?, 
            teacher_feedback_mr = ?, self_assessment = ?, peer_assessment = ?
            WHERE id = ?");
        $upd->execute([$awareness, $sensitivity, $creativity, $teacher_feedback, $self_assessment, $peer_assessment, $dom_row['id']]);
    } else {
        $ins = $db->prepare("INSERT INTO hpc_domain_assessments (
            hpc_card_id, domain_id, domain_name, domain_name_mr, 
            awareness_level, sensitivity_level, creativity_level, 
            teacher_feedback_mr, self_assessment, peer_assessment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$card_id, $domain_id, $domain_name, $domain_name_mr, $awareness, $sensitivity, $creativity, $teacher_feedback, $self_assessment, $peer_assessment]);
    }

    // Save Kamgiri (Strengths & Improvements)
    if (isset($in['strengths']) || isset($in['improvements'])) {
        $strengths = is_array($in['strengths'] ?? null) ? json_encode($in['strengths'], JSON_UNESCAPED_UNICODE) : (string)($in['strengths'] ?? '');
        $improvements = is_array($in['improvements'] ?? null) ? json_encode($in['improvements'], JSON_UNESCAPED_UNICODE) : (string)($in['improvements'] ?? '');

        $kchk = $db->prepare("SELECT id FROM hpc_kamgiri WHERE hpc_card_id = ? AND domain_id = ? LIMIT 1");
        $kchk->execute([$card_id, $domain_id]);
        $k_row = $kchk->fetch(PDO::FETCH_ASSOC);

        if ($k_row) {
            $db->prepare("UPDATE hpc_kamgiri SET strengths = ?, improvements = ? WHERE id = ?")
               ->execute([$strengths, $improvements, $k_row['id']]);
        } else {
            $db->prepare("INSERT INTO hpc_kamgiri (hpc_card_id, domain_id, strengths, improvements) VALUES (?, ?, ?, ?)")
               ->execute([$card_id, $domain_id, $strengths, $improvements]);
        }
    }

    // Update Card Status
    $db->prepare("UPDATE hpc_cards SET updated_at = NOW() WHERE id = ?")->execute([$card_id]);

    apiSuccess([
        'card_id'   => $card_id,
        'domain_id' => $domain_id,
    ], 'HPC रुब्रिक मूल्यांकन यशस्वीरित्या सेव्ह झाले!');
}
