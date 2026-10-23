<?php
/**
 * GET /api/student/hpc.php
 * Student HPC Card (Strictly Read-Only - No Edit/Save Permitted)
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';
require_once __DIR__ . '/../../includes/hpc_fs_data.php';

$user = requireRole('student');
$studentId = (int)$user['linked_id'];
$db = getDB();

$st = $db->prepare("SELECT * FROM hpc_cards WHERE student_id = ? AND school_id = ? AND academic_year = ? ORDER BY id DESC LIMIT 1");
$st->execute([$studentId, SCHOOL_ID, ACADEMIC_YEAR]);
$card = $st->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    apiSuccess(['has_card' => false], 'चालू शैक्षणिक वर्षाचे HPC कार्ड अद्याप तयार केलेले नाही.');
}

$card_id = (int)$card['id'];

// Fetch assessments
$ast = $db->prepare("SELECT * FROM hpc_domain_assessments WHERE hpc_card_id = ? ORDER BY domain_id ASC");
$ast->execute([$card_id]);
$assessments = [];
foreach ($ast->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $assessments[(int)$a['domain_id']] = $a;
}

// Fetch kamgiri
$kst = $db->prepare("SELECT * FROM hpc_kamgiri WHERE hpc_card_id = ?");
$kst->execute([$card_id]);
$kamgiri = [];
foreach ($kst->fetchAll(PDO::FETCH_ASSOC) as $k) {
    $kamgiri[(int)$k['domain_id']] = $k;
}

$domains = hpc_fs_domains();

apiSuccess([
    'has_card'    => true,
    'card'        => $card,
    'assessments' => $assessments,
    'kamgiri'     => $kamgiri,
    'domains'     => $domains,
    'rubric_levels' => [
        ['level' => 1, 'name' => 'निर्झर', 'desc' => 'सुरुवातीचा स्तर'],
        ['level' => 2, 'name' => 'पर्वत',  'desc' => 'मध्यम / प्रगतीपथावर स्तर'],
        ['level' => 3, 'name' => 'आकाश',  'desc' => 'प्रवीण / उत्कृष्ट स्तर'],
    ],
    'is_editable' => false // Explicit flag for frontends to disable write operations
], 'विद्यार्थ्याचे HPC कार्ड यशस्वीरित्या लोड झाले.');
