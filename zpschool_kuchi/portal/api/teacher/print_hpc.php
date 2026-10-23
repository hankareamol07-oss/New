<?php
/**
 * GET /api/teacher/print_hpc.php
 * 1-Click A4 Colour PDF Print Router for 19-Page HPC
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin', 'student']);
$db = getDB();

$student_id = (int)($_GET['student_id'] ?? 0);
$card_id = (int)($_GET['id'] ?? 0);

if ($card_id > 0 && $student_id <= 0) {
    $st = $db->prepare("SELECT student_id FROM hpc_cards WHERE id = ? AND school_id = ?");
    $st->execute([$card_id, SCHOOL_ID]);
    $student_id = (int)$st->fetchColumn();
}

if ($student_id <= 0) {
    apiError('विद्यार्थी आयडी किंवा कार्ड आयडी आवश्यक आहे.', 400);
}

// Student role can only print their OWN card
if ($user['role'] === 'student' && (int)$user['linked_id'] !== $student_id) {
    apiError('प्रवेश नाकारला: आपण केवळ स्वतःचे प्रगती पत्रक पाहू शकता.', 403);
}

// Teacher role: verify access to this student
if ($user['role'] === 'teacher') {
    assertTeacherStudentAccess($user, $student_id);
}

// Check or create HPC card
$st = $db->prepare("SELECT * FROM hpc_cards WHERE student_id = ? AND school_id = ? AND academic_year = ? ORDER BY id DESC LIMIT 1");
$st->execute([$student_id, SCHOOL_ID, ACADEMIC_YEAR]);
$card = $st->fetch();

if (!$card) {
    $db->prepare("INSERT INTO hpc_cards (student_id, school_id, academic_year, status) VALUES (?, ?, ?, 'draft')")
       ->execute([$student_id, SCHOOL_ID, ACADEMIC_YEAR]);
    $st->execute([$student_id, SCHOOL_ID, ACADEMIC_YEAR]);
    $card = $st->fetch();
}

$cid = (int)$card['id'];

// Get student's standard
$std_st = $db->prepare("SELECT std FROM students WHERE id = ?");
$std_st->execute([$student_id]);
$std = (int)$std_st->fetchColumn();

// Route to official 19-page booklet or stage format
$format = ($std <= 2) ? 'official19' : 'stage';
$url = APP_URL . "/cards/print_hpc_" . ($std <= 2 ? "19.php" : "stage.php") . "?id=$cid&format=$format";

if (isset($_GET['json'])) {
    apiSuccess([
        'card_id'    => $cid,
        'student_id' => $student_id,
        'std'        => $std,
        'format'     => $format,
        'print_url'  => $url,
    ], 'छपाई लिंक तयार झाली.');
}

// Direct redirection for web browser 1-click print
header("Location: $url");
exit();
