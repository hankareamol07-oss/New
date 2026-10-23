<?php
/**
 * रुब्रिक wizard — AJAX जतन (JSON).
 * body: {csrf, student_id, action: code|abhipray|kamgiri, domain_id, semester, capacity_code | ids | strengths, improvements}
 */
require_once __DIR__ . '/rubric_lib.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'login']); exit; }

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;

if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($in['csrf'] ?? ''))) {
    http_response_code(403); echo json_encode(['success' => false, 'error' => 'csrf']); exit;
}

$db = getDB();
hpc_rb_ensure_schema($db);
$school_id = (int) $_SESSION['school_id'];
$student = hpc_rb_student($db, $school_id, (int) ($in['student_id'] ?? 0));
if (!$student) { echo json_encode(['success' => false, 'error' => 'student']); exit; }

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
$domain_id = (int) ($in['domain_id'] ?? 0);
$sem = ((int) ($in['semester'] ?? 1)) === 2 ? 2 : 1;
$girl = hpc_rb_is_girl($student);
if (!isset(hpc_fs_domains()[$domain_id])) { echo json_encode(['success' => false, 'error' => 'domain']); exit; }

try {
    switch ((string) ($in['action'] ?? 'code')) {
        case 'code':
            $ok = hpc_rb_save_code($db, (int) $card['id'], $domain_id, $sem, trim((string) ($in['capacity_code'] ?? '')));
            echo json_encode(['success' => $ok]);
            break;
        case 'abhipray':
            $n = hpc_rb_save_abhipray($db, (int) $card['id'], $domain_id, $sem, (array) ($in['ids'] ?? []), $girl);
            echo json_encode(['success' => true, 'count' => $n]);
            break;
        case 'kamgiri':
            [$s, $i] = hpc_rb_save_kamgiri($db, (int) $card['id'], $domain_id, (array) ($in['strengths'] ?? []), (array) ($in['improvements'] ?? []), $girl);
            echo json_encode(['success' => true, 'strengths' => $s, 'improvements' => $i]);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db']);
}
