<?php
/**
 * GET /api/student/wheel.php
 * 360° Progress Wheel (Multidimensional Assessment: Self, Peer, Teacher)
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['student', 'teacher', 'admin']);
$db = getDB();

$studentId = ($user['role'] === 'student') ? (int)$user['linked_id'] : (int)($_GET['student_id'] ?? 0);

if ($studentId <= 0) {
    apiError('विद्यार्थी आयडी आवश्यक आहे.', 400);
}

if ($user['role'] === 'teacher') {
    assertTeacherStudentAccess($user, $studentId);
}

// Fetch Card & Assessments
$st = $db->prepare("SELECT id FROM hpc_cards WHERE student_id = ? AND school_id = ? AND academic_year = ? ORDER BY id DESC LIMIT 1");
$st->execute([$studentId, SCHOOL_ID, ACADEMIC_YEAR]);
$cardId = (int)$st->fetchColumn();

// 6 Holistic Domains
$domains = [
    1 => ['id' => 1, 'name' => 'शारीरिक व हालचाल विकास', 'code' => 'PHY', 'color' => '#f59e0b'],
    2 => ['id' => 2, 'name' => 'सामाजिक-भावनिक विकास',  'code' => 'SOC', 'color' => '#10b981'],
    3 => ['id' => 3, 'name' => 'भाषा व साक्षरता विकास',    'code' => 'LANG', 'color' => '#3b82f6'],
    4 => ['id' => 4, 'name' => 'संज्ञानात्मक व गणित विकास', 'code' => 'COG', 'color' => '#8b5cf6'],
    5 => ['id' => 5, 'name' => 'सौंदर्यशास्त्र व कला विकास', 'code' => 'ART', 'color' => '#ec4899'],
    6 => ['id' => 6, 'name' => 'सकारात्मक सवयी व मूल्ये',  'code' => 'VAL', 'color' => '#06b6d4'],
];

$wheel_data = [];

if ($cardId > 0) {
    $ast = $db->prepare("SELECT * FROM hpc_domain_assessments WHERE hpc_card_id = ?");
    $ast->execute([$cardId]);
    $rows = [];
    foreach ($ast->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[(int)$r['domain_id']] = $r;
    }

    $level_to_score = [
        'निर्झर' => 1, '1' => 1, 'level_1' => 1,
        'पर्वत'  => 2, '2' => 2, 'level_2' => 2,
        'आकाश'  => 3, '3' => 3, 'level_3' => 3,
    ];

    foreach ($domains as $did => $d) {
        $r = $rows[$did] ?? [];
        $t_level = $r['awareness_level'] ?? 'पर्वत';
        $t_score = $level_to_score[$t_level] ?? 2;

        // Self & peer scores (1 to 3)
        $self_score = !empty($r['self_assessment']) ? 3 : 2;
        $peer_score = !empty($r['peer_assessment']) ? 3 : 2;

        $overall = round(($t_score * 0.5) + ($self_score * 0.25) + ($peer_score * 0.25), 1);

        $wheel_data[] = [
            'domain_id'   => $did,
            'name'        => $d['name'],
            'code'        => $d['code'],
            'color'       => $d['color'],
            'teacher'     => $t_score,
            'self'        => $self_score,
            'peer'        => $peer_score,
            'overall'     => $overall,
            'max_score'   => 3,
            'level_label' => ($overall >= 2.5 ? 'आकाश' : ($overall >= 1.8 ? 'पर्वत' : 'निर्झर')),
        ];
    }
} else {
    // Default baseline wheel
    foreach ($domains as $did => $d) {
        $wheel_data[] = [
            'domain_id'   => $did,
            'name'        => $d['name'],
            'code'        => $d['code'],
            'color'       => $d['color'],
            'teacher'     => 2,
            'self'        => 2,
            'peer'        => 2,
            'overall'     => 2.0,
            'max_score'   => 3,
            'level_label' => 'पर्वत',
        ];
    }
}

apiSuccess([
    'student_id'   => $studentId,
    'wheel_data'   => $wheel_data,
    'total_domains'=> 6,
    'max_score'    => 3,
], '३६०° प्रोग्रेस व्हील डेटा लोड झाला.');
