<?php
declare(strict_types=1);

/**
 * दोन्ही सत्र एकत्र वार्षिक निकालपत्रक (Combined Annual Result Master Register)
 * Ported and enhanced from Vijeta CCE App report-15.html
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
$label = $standards[$std] ?? std_label($std);
$subjects = cce_subjects($school_id, $std);
$students = cce_students($school_id, $std);

// Fetch Marks for both Semester 1 and Semester 2
$marks_s1 = [];
$marks_s2 = [];
$att_map = [];

if ($students) {
    $ids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    
    // Sem 1 marks
    $stmt1 = $db->prepare("SELECT student_id, subject_id, grand_total, grade FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = 1 AND student_id IN ($in)");
    $stmt1->execute(array_merge([$school_id, $year], $ids));
    foreach ($stmt1->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $marks_s1[(int)$r['student_id']][(int)$r['subject_id']] = $r;
    }

    // Sem 2 marks
    $stmt2 = $db->prepare("SELECT student_id, subject_id, grand_total, grade FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = 2 AND student_id IN ($in)");
    $stmt2->execute(array_merge([$school_id, $year], $ids));
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $marks_s2[(int)$r['student_id']][(int)$r['subject_id']] = $r;
    }

    // Attendance
    try {
        $stt = $db->prepare("SELECT student_id, SUM(present_days) as tot_p FROM attendance WHERE academic_year = ? AND student_id IN ($in) GROUP BY student_id");
        $stt->execute(array_merge([$year], $ids));
        foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $ar) {
            $att_map[(int)$ar['student_id']] = (int)$ar['tot_p'];
        }
    } catch (Exception $e) {}
}

$total_max_per_sem = 0;
foreach ($subjects as $sub) {
    $total_max_per_sem += (float)$sub['fe_max'] + (float)$sub['se_max'];
}
$annual_max = $total_max_per_sem * 2;

$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद शाळा');
$s_udise = preg_replace('/\D/', '', (string)($school['udise_code'] ?: ''));
$hm_name = cce_headmaster_name($school_id, 2);
$teacher_name = cce_class_teacher_name($school_id, $std);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>दोन्ही सत्र एकत्र वार्षिक निकालपत्रक - इयत्ता <?= sanitize($label) ?> - <?= sanitize($s_name) ?></title>
<link href="<?= APP_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
* { box-sizing: border-box; }
body {
    font-family: 'Utsaah', 'Mukta', 'Nirmala UI', sans-serif;
    margin: 0;
    padding: 0;
    background: #f4f6f9;
    color: #1a1a1a;
    font-size: 13px;
}
.no-print-bar {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #1e3a5f;
    color: #fff;
    padding: 10px 16px;
    display: flex;
    gap: 12px;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.no-print-bar a, .no-print-bar button {
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
}
.sheet-container {
    width: 297mm;
    margin: 15px auto;
    background: #fff;
    padding: 10mm 12mm;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-radius: 4px;
}
.sheet-header {
    text-align: center;
    border-bottom: 2px solid #00838f;
    padding-bottom: 6px;
    margin-bottom: 10px;
}
.sheet-header h1 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: #1e3a5f;
}
.sheet-header h2 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 4px 0;
    color: #00838f;
}
.sheet-meta {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 600;
    color: #333;
}
table.annual-register {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}
table.annual-register th, table.annual-register td {
    border: 1px solid #4a779d;
    text-align: center;
    padding: 3px 2px;
    font-size: 11.5px;
    vertical-align: middle;
}
table.annual-register thead tr.main-head th {
    background: #d4eaf7;
    font-weight: 700;
    color: #092c42;
}
table.annual-register thead tr.sub-head th {
    background: #eef6fc;
    font-size: 11px;
    font-weight: 600;
}
table.annual-register tbody td.st-name {
    text-align: left;
    padding-left: 6px;
    font-weight: 600;
    white-space: nowrap;
    min-width: 150px;
}
table.annual-register tbody tr.sem1-row {
    background: #ffffff;
}
table.annual-register tbody tr.sem2-row {
    background: #f7fafc;
    border-bottom: 2px solid #4a779d;
}
.signature-box {
    display: flex;
    justify-content: space-around;
    padding-top: 25px;
    margin-top: 15px;
    page-break-inside: avoid;
}
.sig-line {
    text-align: center;
    border-top: 1px dashed #666;
    padding-top: 4px;
    width: 25%;
    font-weight: 600;
    font-size: 12px;
}

@media print {
    body { background: #fff; }
    .no-print-bar { display: none !important; }
    .sheet-container {
        width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
    }
    @page {
        size: A4 landscape;
        margin: 6mm 8mm;
    }
}
</style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn btn-success btn-sm" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> प्रिंट / PDF सेव्ह करा
    </button>
    <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left"></i> अहवाल केंद्र
    </a>
    <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-speedometer2"></i> सातत्यपूर्ण मूल्यमापन डॅशबोर्ड
    </a>
    <!-- Unified Theme & Font Switcher -->
    <?= cce_theme_toolbar_controls(false) ?>
    <form method="GET" class="d-flex align-items-center gap-2 ms-auto">
        <label class="small mb-0 text-white fw-bold">इयत्ता:</label>
        <select name="std" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
            <?php foreach ($standards as $s_num => $s_lbl): ?>
                <option value="<?= $s_num ?>" <?= $std === (int)$s_num ? 'selected' : '' ?>>इयत्ता <?= sanitize($s_lbl) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="sheet-container">
    <div class="sheet-header">
        <h1><?= sanitize($s_name) ?></h1>
        <h2>सातत्यपूर्ण सर्वंकष मूल्यमापन — दोन्ही सत्र एकत्र वार्षिक निकालपत्रक</h2>
        <div class="sheet-meta">
            <div><b>युडायस:</b> <?= sanitize($s_udise ?: '—') ?></div>
            <div><b>शैक्षणिक वर्ष:</b> <?= sanitize($year) ?></div>
            <div><b>सत्र:</b> प्रथम व द्वितीय सत्र (एकत्रित)</div>
            <div><b>इयत्ता:</b> <?= sanitize($label) ?></div>
        </div>
    </div>

    <table class="annual-register">
        <thead>
            <tr class="main-head">
                <th rowspan="2" style="width:2.5%;">ह.क्र.</th>
                <th rowspan="2" style="width:16%;">विद्यार्थ्याचे नाव</th>
                <th rowspan="2" style="width:3%;">उप.</th>
                <th rowspan="2" style="width:2.5%;">सत्र</th>
                <?php foreach ($subjects as $sub): ?>
                    <th colspan="2"><?= sanitize($sub['short_name'] ?: $sub['name_mr']) ?></th>
                <?php endforeach; ?>
                <th colspan="3" style="background:#e0f2f1; color:#004d40;">सत्र एकूण</th>
                <th colspan="4" style="background:#EFF8F4; color:#167A5B;">वार्षिक एकत्रित निकाल</th>
            </tr>
            <tr class="sub-head">
                <?php foreach ($subjects as $sub): ?>
                    <th>गुण</th>
                    <th>श्रेणी</th>
                <?php endforeach; ?>
                <th style="background:#e0f2f1;">एकूण</th>
                <th style="background:#e0f2f1;">%</th>
                <th style="background:#e0f2f1;">श्रेणी</th>
                <th style="background:#EFF8F4;">वार्षिक गुण</th>
                <th style="background:#EFF8F4;">वार्षिक %</th>
                <th style="background:#EFF8F4;">अंतिम श्रेणी</th>
                <th style="background:#EFF8F4;">निकाल</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="<?= 7 + (count($subjects) * 2) + 4 ?>" class="py-4 text-center text-muted">या इयत्तेत विद्यार्थी उपलब्ध नाहीत.</td></tr>
            <?php else:
                foreach ($students as $idx => $st):
                    $sid = (int)$st['id'];
                    $att = $att_map[$sid] ?? 0;
                    
                    // Sem 1 calculations
                    $tot1 = 0;
                    foreach ($subjects as $sub) {
                        $m1 = $marks_s1[$sid][(int)$sub['id']]['grand_total'] ?? 0;
                        $tot1 += (float)$m1;
                    }
                    $pct1 = $total_max_per_sem > 0 ? round(($tot1 / $total_max_per_sem) * 100, 2) : 0;
                    $grd1 = cce_grade_for_percent($pct1);

                    // Sem 2 calculations
                    $tot2 = 0;
                    foreach ($subjects as $sub) {
                        $m2 = $marks_s2[$sid][(int)$sub['id']]['grand_total'] ?? 0;
                        $tot2 += (float)$m2;
                    }
                    $pct2 = $total_max_per_sem > 0 ? round(($tot2 / $total_max_per_sem) * 100, 2) : 0;
                    $grd2 = cce_grade_for_percent($pct2);

                    // Combined Annual calculations
                    $tot_annual = $tot1 + $tot2;
                    $pct_annual = $annual_max > 0 ? round(($tot_annual / $annual_max) * 100, 2) : 0;
                    $grd_annual = cce_grade_for_percent($pct_annual);
                    $result_text = ($pct_annual >= 33) ? 'उत्तीर्ण' : 'सुधारणा आवश्यक';
                ?>
                <tr class="sem1-row">
                    <td rowspan="2"><b><?= to_mr_digits($st['roll_no'] ?: ($idx + 1)) ?></b></td>
                    <td rowspan="2" class="st-name"><?= sanitize($st['name_mr'] ?: $st['name']) ?></td>
                    <td rowspan="2"><?= to_mr_digits($att) ?></td>
                    <td><b>I</b></td>
                    <?php foreach ($subjects as $sub): 
                        $m = $marks_s1[$sid][(int)$sub['id']] ?? null;
                        $val = $m ? (float)$m['grand_total'] : null;
                        $grd = $m['grade'] ?? '-';
                    ?>
                        <td><?= $val !== null ? to_mr_digits($val) : '-' ?></td>
                        <td><?= sanitize($grd) ?></td>
                    <?php endforeach; ?>
                    <td style="font-weight:700; background:#f1f8f7;"><?= to_mr_digits($tot1) ?></td>
                    <td style="background:#f1f8f7;"><?= to_mr_digits(number_format($pct1, 1, '.', '')) ?></td>
                    <td style="font-weight:700; background:#f1f8f7;"><?= sanitize($grd1) ?></td>
                    <td rowspan="2" style="font-weight:700; background:#fff8e1; font-size:12px;"><?= to_mr_digits($tot_annual) ?></td>
                    <td rowspan="2" style="font-weight:700; background:#fff8e1;"><?= to_mr_digits(number_format($pct_annual, 1, '.', '')) ?>%</td>
                    <td rowspan="2" style="font-weight:700; background:#fff8e1; color:#0F4F3B; font-size:12.5px;"><?= sanitize($grd_annual) ?></td>
                    <td rowspan="2" style="font-weight:700; background:#fff8e1; color:<?= $pct_annual >= 33 ? '#2e7d32' : '#c62828' ?>;"><?= $result_text ?></td>
                </tr>
                <tr class="sem2-row">
                    <td><b>II</b></td>
                    <?php foreach ($subjects as $sub): 
                        $m = $marks_s2[$sid][(int)$sub['id']] ?? null;
                        $val = $m ? (float)$m['grand_total'] : null;
                        $grd = $m['grade'] ?? '-';
                    ?>
                        <td><?= $val !== null ? to_mr_digits($val) : '-' ?></td>
                        <td><?= sanitize($grd) ?></td>
                    <?php endforeach; ?>
                    <td style="font-weight:700; background:#f1f8f7;"><?= to_mr_digits($tot2) ?></td>
                    <td style="background:#f1f8f7;"><?= to_mr_digits(number_format($pct2, 1, '.', '')) ?></td>
                    <td style="font-weight:700; background:#f1f8f7;"><?= sanitize($grd2) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="signature-box">
        <div class="sig-line">
            वर्गशिक्षक स्वाक्षरी<br>
            <small class="text-muted"><?= sanitize($teacher_name ?: 'वर्गशिक्षक') ?></small>
        </div>
        <div class="sig-line">
            मुख्याध्यापक स्वाक्षरी<br>
            <small class="text-muted"><?= sanitize($hm_name ?: 'मुख्याध्यापक') ?></small>
        </div>
        <div class="sig-line">
            दिनांक व शिक्का<br>
            <small class="text-muted"><?= to_mr_digits(date('d/m/Y')) ?></small>
        </div>
    </div>
</div>

</body>
</html>
