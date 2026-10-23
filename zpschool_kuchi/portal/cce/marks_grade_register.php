<?php
/**
 * गुण-श्रेणीयुक्त निकालपत्रक (Report-08: Single-Semester Subject Total Marks & Grade Register)
 * Ported from Vijeta CCE App (report-08.html)
 * Official Maharashtra primary CCE landscape tabulation register showing total marks & grades per subject,
 * attendance, overall percentage, and bottom grade distribution matrix.
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();
$standards = cce_school_standards($school_id);
if (empty($standards)) {
    for ($i = 1; $i <= 8; $i++) $standards[$i] = std_label($i);
}

$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_GET['semester'] ?? 1) === 2 ? 2 : 1;
$section = trim((string)($_GET['section'] ?? ''));

// School details
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद प्राथमिक शाळा');
$s_dist = $school['district'] ?: '';
$s_tal  = $school['taluka'] ?: '';
$s_udise = preg_replace('/\D/', '', (string)($school['udise_code'] ?: '27350302306'));

// Teacher & HM
$teacher_name = cce_class_teacher_name($school_id, $std, $section);
if (!$teacher_name) $teacher_name = 'वर्गशिक्षक';

$hm_name = cce_headmaster_name($school_id, $semester) ?: 'मुख्याध्यापक';

// Subjects & Students
$subjects = cce_subjects($school_id, $std);
$students = cce_students($school_id, $std);
if ($section !== '') {
    $students = array_filter($students, fn($s) => ($s['section'] ?? '') === $section);
}

// Student marks & attendance
$student_ids = array_column($students, 'id');
$marks_by_stu = [];
$attendance_by_stu = [];

if ($student_ids) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $stmt = $db->prepare("SELECT student_id, subject_id, grand_total, grade 
                          FROM cce_marks 
                          WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($placeholders)");
    $stmt->execute(array_merge([$school_id, $year, $semester], $student_ids));
    foreach ($stmt->fetchAll() as $r) {
        $marks_by_stu[(int)$r['student_id']][(int)$r['subject_id']] = [
            'total' => $r['grand_total'],
            'grade' => $r['grade'],
        ];
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

$all_grades = ['अ-१', 'अ-२', 'ब-१', 'ब-२', 'क-१', 'क-२', 'ड', 'इ-१', 'इ-२'];
$grade_counts = [];
foreach ($subjects as $sub) {
    $sub_id = (int)$sub['id'];
    foreach ($all_grades as $g) {
        $grade_counts[$sub_id][$g] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>गुण-श्रेणीयुक्त निकालपत्रक (सत्र <?= $semester ?>) - इयत्ता <?= std_label($std) ?></title>
    <link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?= cce_theme_head_tags() ?>
    <style>
        * { box-sizing: border-box; font-family: 'Utsaah', 'Mukta', sans-serif; }
        body { margin: 0; background: #eef2f6; color: #111; font-size: 13pt; }
        
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
            display: flex; justify-content: space-between; font-size: 12.5pt; font-weight: bold;
            margin-bottom: 2mm; border-bottom: 1.5px solid #1E3A8A; padding-bottom: 2mm;
        }
        
        table.marks-table {
            width: 100%; border-collapse: collapse; font-size: 11pt; margin-bottom: 4mm;
        }
        table.marks-table th, table.marks-table td {
            border: 1px solid #4B5563; padding: 3px 2px; text-align: center;
        }
        table.marks-table th {
            background: #E0E7FF; font-weight: 700; color: #111;
        }
        table.marks-table th.sub-th {
            background: #FEF3C7; font-size: 10pt;
        }
        table.marks-table td.name {
            text-align: left; padding-left: 5px; font-weight: 600; white-space: nowrap; font-size: 11.5pt;
        }
        table.marks-table tr.total-row {
            background: #F3F4F6; font-weight: bold;
        }
        
        table.summary-table {
            width: 100%; border-collapse: collapse; font-size: 10.5pt; margin-top: 2mm; margin-bottom: 4mm;
        }
        table.summary-table th, table.summary-table td {
            border: 1px solid #4B5563; padding: 2px 4px; text-align: center;
        }
        table.summary-table th { background: #E2E8F0; font-weight: bold; }
        
        .signatures {
            display: flex; justify-content: space-between; margin-top: 6mm; font-size: 13pt; font-weight: bold;
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
            <?php foreach ($standards as $n => $l): ?>
                <option value="<?= $n ?>" <?= $n === $std ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <label style="font-weight:bold;">सत्र:</label>
        <select name="semester" onchange="this.form.submit()">
            <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>प्रथम सत्र</option>
            <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>द्वितीय सत्र</option>
        </select>
        <label style="font-weight:bold;">तुकडी:</label>
        <input type="text" name="section" value="<?= sanitize($section) ?>" placeholder="तुकडी" style="width:50px; padding:5px; border-radius:6px; border:none;" onchange="this.form.submit()">
    </form>
    <button type="button" onclick="window.print()"><i class="bi bi-printer-fill"></i> निकालपत्रक प्रिंट करा / PDF</button>
    <a href="<?= APP_URL ?>/cce/annual_combined_register.php?std=<?= $std ?>"><i class="bi bi-calendar-range"></i> दोन्ही सत्र एकत्र</a>
    <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>"><i class="bi bi-arrow-left"></i> अहवाल यादी</a>
    <!-- Unified Theme & Font Switcher -->
    <?= cce_theme_toolbar_controls(false) ?>
</div>

<div class="page">
    <div class="report-header">
        <div class="report-title">● सातत्यपूर्ण सर्वंकष मूल्यमापन — गुण व श्रेणी निकालपत्रक (Report-08) ●</div>
        <div class="report-subtitle"><?= sanitize($s_name) ?><?= $s_tal ? ', ता. ' . sanitize($s_tal) : '' ?><?= $s_dist ? ', जि. ' . sanitize($s_dist) : '' ?></div>
    </div>
    
    <div class="meta-line">
        <div>युडायस : <?= to_mr_digits($s_udise) ?></div>
        <div>इयत्ता : <?= std_label($std) ?><?= $section ? ' (तुकडी: ' . sanitize($section) . ')' : '' ?></div>
        <div>सत्र : <?= $semester === 1 ? 'प्रथम सत्र' : 'द्वितीय सत्र' ?></div>
        <div>शैक्षणिक वर्ष : सन <?= to_mr_digits($year) ?></div>
    </div>
    
    <table class="marks-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:3%;">अ.क्र.</th>
                <th rowspan="2" style="width:16%;">विद्यार्थ्याचे नाव</th>
                <th rowspan="2" style="width:3%;">लिंग</th>
                <th rowspan="2" style="width:4%;">प्रवर्ग</th>
                <th rowspan="2" style="width:4%;">हजर दिवस</th>
                <?php foreach ($subjects as $sub): ?>
                    <th colspan="2" style="width: <?= round(50 / max(1, count($subjects)), 1) ?>%;">
                        <?= sanitize($sub['name_mr'] ?: $sub['short_name']) ?>
                    </th>
                <?php endforeach; ?>
                <th colspan="3" style="width:14%;">एकूण निकाल</th>
            </tr>
            <tr>
                <?php foreach ($subjects as $sub): ?>
                    <th class="sub-th" style="width:<?= round(25 / max(1, count($subjects)), 1) ?>%;">गुण</th>
                    <th class="sub-th" style="width:<?= round(25 / max(1, count($subjects)), 1) ?>%;">श्रेणी</th>
                <?php endforeach; ?>
                <th class="sub-th" style="width:5%;">एकूण</th>
                <th class="sub-th" style="width:4%;">टक्के</th>
                <th class="sub-th" style="width:5%;">श्रेणी</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="<?= 8 + (count($subjects) * 2) ?>" style="padding: 20px;">विद्यार्थी माहिती उपलब्ध नाही.</td></tr>
            <?php else: ?>
                <?php foreach ($students as $idx => $st): 
                    $sid = (int)$st['id'];
                    $is_girl = cce_is_female_student($st);
                    $att = $attendance_by_stu[$sid] ?? '-';
                    
                    $student_tot = 0;
                    $student_max = 0;
                    $has_marks = false;
                ?>
                <tr>
                    <td><?= to_mr_digits($st['roll_no'] ?: ($idx + 1)) ?></td>
                    <td class="name"><?= sanitize($st['name_mr'] ?: $st['name']) ?></td>
                    <td><?= $is_girl ? 'स्त्री' : 'पु' ?></td>
                    <td><?= sanitize(($st['category'] ?? '') ?: (($st['caste_category'] ?? '') ?: (($st['caste'] ?? '') ?: '-'))) ?></td>
                    <td><?= to_mr_digits($att) ?></td>
                    
                    <?php foreach ($subjects as $sub): 
                        $sub_id = (int)$sub['id'];
                        $m = $marks_by_stu[$sid][$sub_id] ?? null;
                        $tot = ($m && $m['total'] !== null && $m['total'] !== '') ? (float)$m['total'] : null;
                        $grd = $m['grade'] ?? '';
                        
                        if ($tot !== null) {
                            $student_tot += $tot;
                            $student_max += (float)($sub['max_marks'] ?? 100);
                            $has_marks = true;
                        }
                        
                        // Count grades for bottom matrix
                        $norm_grade = cce_normalize_grade($grd);
                        if ($norm_grade && isset($grade_counts[$sub_id][$norm_grade])) {
                            $grade_counts[$sub_id][$norm_grade]++;
                        }
                    ?>
                        <td><?= $tot !== null ? to_mr_digits($tot) : '-' ?></td>
                        <td style="font-weight:bold; color: <?= in_array($norm_grade, ['अ-१','अ-२']) ? '#065F46' : '#1F2937' ?>;">
                            <?= sanitize($grd ?: '-') ?>
                        </td>
                    <?php endforeach; ?>
                    
                    <?php 
                    $pct = ($has_marks && $student_max > 0) ? round(($student_tot / $student_max) * 100, 1) : 0;
                    $final_grade = $has_marks ? cce_calc_grade($pct) : '-';
                    ?>
                    <td style="font-weight:bold;"><?= $has_marks ? to_mr_digits($student_tot) : '-' ?></td>
                    <td style="font-weight:bold;"><?= $has_marks ? to_mr_digits($pct) . '%' : '-' ?></td>
                    <td style="font-weight:bold; color:#BE185D;"><?= sanitize($final_grade) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- BOTTOM GRADE MATRIX (एकवट सांख्यिकी तक्ता) -->
    <div style="font-weight:bold; font-size:11.5pt; color:#1E3A8A; margin-bottom:1mm;">
        ● विषयनिहाय श्रेणी एकवट तक्ता :
    </div>
    <table class="summary-table">
        <thead>
            <tr>
                <th style="width:18%;">विषय</th>
                <?php foreach ($all_grades as $g): ?>
                    <th><?= $g ?></th>
                <?php endforeach; ?>
                <th style="font-weight:bold;">एकूण</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subjects as $sub): 
                $sub_id = (int)$sub['id'];
                $sub_total = array_sum($grade_counts[$sub_id] ?? []);
            ?>
            <tr>
                <td style="text-align:left; font-weight:600; padding-left:6px;"><?= sanitize($sub['name_mr'] ?: $sub['short_name']) ?></td>
                <?php foreach ($all_grades as $g): 
                    $cnt = $grade_counts[$sub_id][$g] ?? 0;
                ?>
                    <td style="<?= $cnt > 0 ? 'font-weight:bold;' : 'color:#9CA3AF;' ?>"><?= to_mr_digits($cnt) ?></td>
                <?php endforeach; ?>
                <td style="font-weight:bold;"><?= to_mr_digits($sub_total) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
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
