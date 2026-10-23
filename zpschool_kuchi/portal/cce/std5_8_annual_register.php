<?php
/**
 * इयत्ता ५ वी व ८ वी वार्षिक निकालपत्रक (Report-13: Std 5th & 8th RTE Annual Examination Register)
 * Ported from Vijeta CCE App (report-13.html)
 * Official Maharashtra RTE 35% passing mark threshold, 15 marks grace allocation, and Pass / Re-exam calculation.
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

// Restrict or default to 5 or 8, but allow viewing for any standard
$std = (int)($_GET['std'] ?? 5);
if ($std !== 5 && $std !== 8 && !isset($_GET['std'])) {
    $std = 5;
}
$section = trim((string)($_GET['section'] ?? ''));

// School details
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद प्राथमिक शाळा');
$s_dist = $school['district'] ?: '';
$s_tal  = $school['taluka'] ?: '';
$s_udise = preg_replace('/\D/', '', (string)($school['udise_code'] ?: '27350302306'));

// Teacher & HM
$teacher_name = cce_class_teacher_name($school_id, $std, $section);
if (!$teacher_name) $teacher_name = 'वर्गशिक्षक';

$hm_name = cce_headmaster_name($school_id, 2) ?: 'मुख्याध्यापक';

// Subjects & Students
$subjects = cce_subjects($school_id, $std);
$students = cce_students($school_id, $std);
if ($section !== '') {
    $students = array_filter($students, fn($s) => ($s['section'] ?? '') === $section);
}

// Split subjects into academic (tested with 35% rule) vs graded co-curricular (कला, कार्यानुभव, शा.शि.)
$academic_subjects = [];
$graded_subjects = [];
foreach ($subjects as $sub) {
    $cat = (int)($sub['category'] ?? 1);
    if ($cat === 2) {
        $graded_subjects[] = $sub;
    } else {
        $academic_subjects[] = $sub;
    }
}

// Fetch marks from BOTH Sem 1 and Sem 2 to compute annual marks (or Sem 2 annual exam)
$student_ids = array_column($students, 'id');
$annual_marks = []; // [student_id][subject_id] => total
$annual_grades = [];
$attendance_by_stu = [];

if ($student_ids) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $stmt = $db->prepare("SELECT student_id, subject_id, semester, grand_total, grade 
                          FROM cce_marks 
                          WHERE school_id = ? AND academic_year = ? AND student_id IN ($placeholders)");
    $stmt->execute(array_merge([$school_id, $year], $student_ids));
    $raw = [];
    foreach ($stmt->fetchAll() as $r) {
        $raw[(int)$r['student_id']][(int)$r['subject_id']][(int)$r['semester']] = [
            'total' => $r['grand_total'],
            'grade' => $r['grade'],
        ];
    }
    
    foreach ($student_ids as $sid) {
        foreach ($subjects as $sub) {
            $sub_id = (int)$sub['id'];
            $m1 = $raw[$sid][$sub_id][1]['total'] ?? null;
            $m2 = $raw[$sid][$sub_id][2]['total'] ?? null;
            
            // Average or combined annual mark
            if ($m1 !== null && $m2 !== null) {
                $avg = round(((float)$m1 + (float)$m2) / 2);
                $annual_marks[$sid][$sub_id] = $avg;
            } elseif ($m2 !== null) {
                $annual_marks[$sid][$sub_id] = (float)$m2;
            } elseif ($m1 !== null) {
                $annual_marks[$sid][$sub_id] = (float)$m1;
            } else {
                $annual_marks[$sid][$sub_id] = null;
            }
            
            $g1 = $raw[$sid][$sub_id][1]['grade'] ?? '';
            $g2 = $raw[$sid][$sub_id][2]['grade'] ?? '';
            $annual_grades[$sid][$sub_id] = $g2 ?: $g1;
        }
    }
    
    // Attendance
    try {
        $astmt = $db->prepare("SELECT student_id, SUM(present_days) as total_present 
                               FROM attendance 
                               WHERE student_id IN ($placeholders) AND academic_year = ? 
                               GROUP BY student_id");
        $astmt->execute(array_merge($student_ids, [$year]));
        foreach ($astmt->fetchAll() as $ar) {
            $attendance_by_stu[(int)$ar['student_id']] = (int)$ar['total_present'];
        }
    } catch (Exception $e) {}
}

// RTE Grace calculation logic (Max 15 grace marks total per student, max 3 subjects, 35% passing mark)
if (!function_exists('compute_rte_result')) {
    function compute_rte_result($student_marks, $academic_subjects) {
        $pass_min = 35; // 35 out of 100
        $max_grace_pool = 15;
        $remaining_grace = $max_grace_pool;
        
        $subject_results = [];
        $fails_before_grace = 0;
    
    foreach ($academic_subjects as $sub) {
        $sub_id = (int)$sub['id'];
        $mark = $student_marks[$sub_id] ?? null;
        if ($mark === null) {
            $subject_results[$sub_id] = ['mark' => null, 'grace' => 0, 'status' => '-'];
            continue;
        }
        
        $mark = (float)$mark;
        if ($mark >= $pass_min) {
            $subject_results[$sub_id] = ['mark' => $mark, 'grace' => 0, 'status' => 'P'];
        } else {
            $deficit = $pass_min - $mark;
            $fails_before_grace++;
            if ($deficit <= $remaining_grace && $deficit <= 10) {
                $remaining_grace -= $deficit;
                $subject_results[$sub_id] = ['mark' => $mark, 'grace' => $deficit, 'status' => 'P (सवलत)'];
            } else {
                $subject_results[$sub_id] = ['mark' => $mark, 'grace' => 0, 'status' => 'F'];
            }
        }
    }
    
    // Overall student result status
    $has_fail = false;
    $has_grace = false;
    $has_data = false;
    foreach ($subject_results as $sr) {
        if ($sr['mark'] !== null) $has_data = true;
        if ($sr['status'] === 'F') $has_fail = true;
        if ($sr['grace'] > 0) $has_grace = true;
    }
    
    if (!$has_data) {
        $final_result = '-';
    } elseif ($has_fail) {
        $final_result = 'पुनर्परीक्षा'; // RTE mandated re-exam
    } elseif ($has_grace) {
        $final_result = 'उत्तीर्ण (सवलत)';
    } else {
        $final_result = 'उत्तीर्ण';
    }
    
    return [
        'subject_results' => $subject_results,
        'final_result' => $final_result,
        'has_fail' => $has_fail,
    ];
}
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>इयत्ता <?= std_label($std) ?> वार्षिक निकालपत्रक (RTE सवलत व पुनर्परीक्षा तक्ता) - Report-13</title>
    <link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?= cce_theme_head_tags() ?>
    <style>
        * { box-sizing: border-box; font-family: 'Utsaah', 'Mukta', sans-serif; }
        body { margin: 0; background: #eef2f6; color: #111; font-size: 12.5pt; }
        
        .toolbar {
            position: sticky; top: 0; z-index: 100;
            background: #17324d; color: white;
            padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .toolbar select, .toolbar button, .toolbar a {
            font-size: 14px; padding: 6px 12px; border-radius: 6px; border: none;
        }
        .toolbar button { background: #10B981; color: white; cursor: pointer; font-weight: bold; }
        .toolbar a { background: #475569; color: white; text-decoration: none; }
        
        .page {
            width: 297mm; min-height: 210mm; margin: 16px auto; padding: 8mm 10mm;
            background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1); position: relative;
            border-radius: 4px;
        }
        
        .report-header { text-align: center; margin-bottom: 3mm; }
        .report-title { font-size: 18pt; font-weight: 800; color: #BE185D; margin-bottom: 1mm; }
        .report-subtitle { font-size: 14pt; font-weight: bold; color: #1E3A8A; }
        
        .meta-line {
            display: flex; justify-content: space-between; font-size: 12pt; font-weight: bold;
            margin-bottom: 2mm; border-bottom: 1.5px solid #1E3A8A; padding-bottom: 2mm;
        }
        
        table.rte-table {
            width: 100%; border-collapse: collapse; font-size: 10.5pt; margin-bottom: 4mm;
        }
        table.rte-table th, table.rte-table td {
            border: 1px solid #4B5563; padding: 3px 2px; text-align: center;
        }
        table.rte-table th {
            background: #E0E7FF; font-weight: 700; color: #111;
        }
        table.rte-table th.sub-th {
            background: #FEF3C7; font-size: 9.5pt;
        }
        table.rte-table td.name {
            text-align: left; padding-left: 5px; font-weight: 600; white-space: nowrap; font-size: 11pt;
        }
        
        .badge-pass { background: #D1FAE5; color: #065F46; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
        .badge-reexam { background: #FEE2E2; color: #991B1B; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
        .badge-grace { background: #FEF3C7; color: #92400E; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
        
        .footnote {
            display: flex; justify-content: space-between; font-size: 11pt; font-weight: 600;
            color: #374151; margin-top: 2mm; margin-bottom: 6mm;
        }
        
        .signatures {
            display: flex; justify-content: space-between; margin-top: 8mm; font-size: 13pt; font-weight: bold;
            padding: 0 20mm;
        }
        .sig-box { text-align: center; width: 30%; }
        .sig-line { height: 10mm; border-bottom: 1px solid #111; margin-bottom: 2px; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                margin: 0; width: 297mm; min-height: 210mm; padding: 6mm 8mm;
                box-shadow: none; border-radius: 0; page-break-after: always;
            }
            @page { size: A4 landscape; margin: 0; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <form method="GET" style="display:flex; gap:8px; align-items:center;">
        <label style="font-weight:bold;">इयत्ता:</label>
        <select name="std" onchange="this.form.submit()">
            <option value="5" <?= $std === 5 ? 'selected' : '' ?>>इयत्ता ५ वी (RTE वर्ग)</option>
            <option value="8" <?= $std === 8 ? 'selected' : '' ?>>इयत्ता ८ वी (RTE वर्ग)</option>
            <?php for ($i = 1; $i <= 8; $i++): if ($i === 5 || $i === 8) continue; ?>
                <option value="<?= $i ?>" <?= $i === $std ? 'selected' : '' ?>><?= std_label($i) ?></option>
            <?php endfor; ?>
        </select>
        <label style="font-weight:bold;">तुकडी:</label>
        <input type="text" name="section" value="<?= sanitize($section) ?>" placeholder="तुकडी" style="width:50px; padding:5px; border-radius:6px; border:none;" onchange="this.form.submit()">
    </form>
    <button type="button" onclick="window.print()"><i class="bi bi-printer-fill"></i> निकालपत्रक प्रिंट करा / PDF</button>
    <a href="<?= APP_URL ?>/cce/annual_combined_register.php?std=<?= $std ?>"><i class="bi bi-calendar-range"></i> वार्षिक निकाल</a>
    <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>"><i class="bi bi-arrow-left"></i> अहवाल यादी</a>
    <!-- Unified Theme & Font Switcher -->
    <?= cce_theme_toolbar_controls(false) ?>
</div>

<div class="page">
    <div class="report-header">
        <div class="report-title">● महाराष्ट्र प्राथमिक शिक्षण — इयत्ता ५ वी व ८ वी वार्षिक परीक्षा निकालपत्रक (Report-13) ●</div>
        <div class="report-subtitle"><?= sanitize($s_name) ?><?= $s_tal ? ', ता. ' . sanitize($s_tal) : '' ?><?= $s_dist ? ', जि. ' . sanitize($s_dist) : '' ?></div>
    </div>
    
    <div class="meta-line">
        <div>युडायस : <?= to_mr_digits($s_udise) ?></div>
        <div>इयत्ता : <?= std_label($std) ?><?= $section ? ' (तुकडी: ' . sanitize($section) . ')' : '' ?></div>
        <div>उत्तीर्ण पात्रता : किमान ३५% गुण | कमाल सवलत : १५ गुण</div>
        <div>शैक्षणिक वर्ष : सन <?= to_mr_digits($year) ?></div>
    </div>
    
    <table class="rte-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:3%;">अ.क्र.</th>
                <th rowspan="2" style="width:14%;">विद्यार्थ्याचे नाव</th>
                <th rowspan="2" style="width:4%;">हजर दिवस</th>
                <?php foreach ($academic_subjects as $sub): ?>
                    <th colspan="3"><?= sanitize($sub['name_mr'] ?: $sub['short_name']) ?></th>
                <?php endforeach; ?>
                <?php foreach ($graded_subjects as $sub): ?>
                    <th rowspan="2" style="width:4%;"><?= sanitize($sub['name_mr'] ?: $sub['short_name']) ?><br><span style="font-size:8pt;">श्रेणी</span></th>
                <?php endforeach; ?>
                <th colspan="3" style="width:14%;">अंतिम निकाल</th>
            </tr>
            <tr>
                <?php foreach ($academic_subjects as $sub): ?>
                    <th class="sub-th" style="width:4.5%;">गुण</th>
                    <th class="sub-th" style="width:3%;">ग्रेस</th>
                    <th class="sub-th" style="width:3.5%;">शेरा</th>
                <?php endforeach; ?>
                <th class="sub-th" style="width:4.5%;">एकूण</th>
                <th class="sub-th" style="width:4%;">टक्के</th>
                <th class="sub-th" style="width:5.5%;">निकाल</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="20" style="padding: 20px;">विद्यार्थी माहिती उपलब्ध नाही.</td></tr>
            <?php else: ?>
                <?php foreach ($students as $idx => $st): 
                    $sid = (int)$st['id'];
                    $att = $attendance_by_stu[$sid] ?? '-';
                    $stu_m = $annual_marks[$sid] ?? [];
                    $res = compute_rte_result($stu_m, $academic_subjects);
                    
                    $total_marks = 0;
                    $max_marks = 0;
                    $has_data = false;
                    foreach ($academic_subjects as $sub) {
                        $sub_id = (int)$sub['id'];
                        if (isset($stu_m[$sub_id]) && $stu_m[$sub_id] !== null) {
                            $total_marks += (float)$stu_m[$sub_id];
                            $max_marks += (float)($sub['max_marks'] ?? 100);
                            $has_data = true;
                        }
                    }
                    $pct = ($has_data && $max_marks > 0) ? round(($total_marks / $max_marks) * 100, 1) : 0;
                ?>
                <tr>
                    <td><?= to_mr_digits($st['roll_no'] ?: ($idx + 1)) ?></td>
                    <td class="name"><?= sanitize($st['name_mr'] ?: $st['name']) ?></td>
                    <td><?= to_mr_digits($att) ?></td>
                    
                    <?php foreach ($academic_subjects as $sub): 
                        $sub_id = (int)$sub['id'];
                        $sr = $res['subject_results'][$sub_id] ?? ['mark' => null, 'grace' => 0, 'status' => '-'];
                        $is_fail = $sr['status'] === 'F';
                        $has_gr = $sr['grace'] > 0;
                    ?>
                        <td style="<?= $is_fail ? 'color:#DC2626; font-weight:bold;' : '' ?>">
                            <?= $sr['mark'] !== null ? to_mr_digits($sr['mark']) : '-' ?>
                        </td>
                        <td style="color:#D97706; font-weight:bold;">
                            <?= $has_gr ? to_mr_digits($sr['grace']) : '-' ?>
                        </td>
                        <td>
                            <?php if ($is_fail): ?>
                                <span class="badge-reexam">अनुत्तीर्ण</span>
                            <?php elseif ($has_gr): ?>
                                <span class="badge-grace">सवलत</span>
                            <?php elseif ($sr['mark'] !== null): ?>
                                <span class="badge-pass">उत्तीर्ण</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    
                    <?php foreach ($graded_subjects as $sub): 
                        $sub_id = (int)$sub['id'];
                        $grd = $annual_grades[$sid][$sub_id] ?? '-';
                    ?>
                        <td style="font-weight:bold;"><?= sanitize($grd ?: '-') ?></td>
                    <?php endforeach; ?>
                    
                    <td style="font-weight:bold;"><?= $has_data ? to_mr_digits($total_marks) : '-' ?></td>
                    <td style="font-weight:bold;"><?= $has_data ? to_mr_digits($pct) . '%' : '-' ?></td>
                    <td>
                        <?php if ($res['final_result'] === 'पुनर्परीक्षा'): ?>
                            <span class="badge-reexam">पुनर्परीक्षा</span>
                        <?php elseif ($res['final_result'] === 'उत्तीर्ण (सवलत)'): ?>
                            <span class="badge-grace">उत्तीर्ण*</span>
                        <?php elseif ($res['final_result'] === 'उत्तीर्ण'): ?>
                            <span class="badge-pass">उत्तीर्ण</span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footnote">
        <div>
            <strong>संकेताक्षर :</strong> P = उत्तीर्ण, F = अनुत्तीर्ण, सवलत = कमाल १५ गुण सवलतीसह उत्तीर्ण, पुनर्परीक्षा = अनुत्तीर्ण विषयात पुनर्परीक्षा देणे बंधनकारक.
        </div>
        <div>
            * शासन निर्णयानुसार पुनर्परीक्षा निकालानंतर अंतिम वर्गोन्नती निश्चित केली जाईल.
        </div>
    </div>
    
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div>वर्गशिक्षक स्वाक्षरी</div>
            <div style="font-size: 11pt; color: #4B5563;">(<?= sanitize($teacher_name) ?>)</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div>तपासणी अधिकारी</div>
            <div style="font-size: 11pt; color: #4B5563;">(केंद्रप्रमुख / विस्तार अधिकारी)</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div>मुख्याध्यापक स्वाक्षरी व शिक्का</div>
            <div style="font-size: 11pt; color: #4B5563;">(<?= sanitize($hm_name) ?>)</div>
        </div>
    </div>
</div>

</body>
</html>
