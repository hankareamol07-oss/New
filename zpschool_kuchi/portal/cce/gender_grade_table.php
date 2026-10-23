<?php
declare(strict_types=1);

/**
 * एकवट मुले-मुली श्रेणी तक्ता (Boys/Girls Grade Matrix)
 * Ported and enhanced from Vijeta CCE App report-07.html
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
$label = $standards[$std] ?? std_label($std);
$subjects = cce_subjects($school_id, $std);
$students = cce_students($school_id, $std);

// Standard 9 Maharashtra CCE Grades
$grade_keys = ['अ-1', 'अ-2', 'ब-1', 'ब-2', 'क-1', 'क-2', 'ड', 'इ-1', 'इ-2'];

// Student classification
$boys_count = 0;
$girls_count = 0;
$student_gender_map = [];

foreach ($students as $st) {
    $sid = (int)$st['id'];
    $is_girl = cce_is_female_student($st);
    $student_gender_map[$sid] = $is_girl ? 'girl' : 'boy';
    if ($is_girl) {
        $girls_count++;
    } else {
        $boys_count++;
    }
}
$total_enrolled = count($students);

// Fetch Marks for all students in this std/semester
$student_subject_grades = [];
if ($students && $subjects) {
    $ids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT student_id, subject_id, grand_total, grade FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year, $semester], $ids));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $student_subject_grades[(int)$row['student_id']][(int)$row['subject_id']] = $row['grade'];
    }
}

// Compute matrix: subject_id -> grade -> [boy => count, girl => count]
$matrix = [];
$subject_totals = [];

foreach ($subjects as $sub) {
    $sub_id = (int)$sub['id'];
    $sub_max = (float)$sub['fe_max'] + (float)$sub['se_max'];
    
    foreach ($grade_keys as $g) {
        $matrix[$sub_id][$g] = ['boy' => 0, 'girl' => 0];
    }
    $subject_totals[$sub_id] = ['boy' => 0, 'girl' => 0];

    foreach ($students as $st) {
        $sid = (int)$st['id'];
        $gender = $student_gender_map[$sid] ?? 'boy';
        
        $grade = $student_subject_grades[$sid][$sub_id] ?? null;
        if (!$grade) {
            // Default grade if marks not yet recorded
            $grade = 'ड';
        }
        
        // Normalize grade format (e.g. A1 -> अ-1, अ1 -> अ-1)
        $norm_grade = $grade;
        if ($norm_grade === 'अ1') $norm_grade = 'अ-1';
        elseif ($norm_grade === 'अ2') $norm_grade = 'अ-2';
        elseif ($norm_grade === 'ब1') $norm_grade = 'ब-1';
        elseif ($norm_grade === 'ब2') $norm_grade = 'ब-2';
        elseif ($norm_grade === 'क1') $norm_grade = 'क-1';
        elseif ($norm_grade === 'क2') $norm_grade = 'क-2';
        elseif ($norm_grade === 'इ1') $norm_grade = 'इ-1';
        elseif ($norm_grade === 'इ2') $norm_grade = 'इ-2';

        if (!isset($matrix[$sub_id][$norm_grade])) {
            $norm_grade = 'ड';
        }

        $matrix[$sub_id][$norm_grade][$gender]++;
        $subject_totals[$sub_id][$gender]++;
    }
}

// Attendance summary
$att_boys = 0;
$att_girls = 0;
try {
    if ($students) {
        $ids = array_map('intval', array_column($students, 'id'));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stt = $db->prepare("SELECT student_id, AVG(present_days) as avg_p FROM attendance WHERE academic_year = ? AND student_id IN ($in) GROUP BY student_id");
        $stt->execute(array_merge([$year], $ids));
        foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $ar) {
            $sid = (int)$ar['student_id'];
            $gender = $student_gender_map[$sid] ?? 'boy';
            if ($gender === 'girl') {
                $att_girls += (float)$ar['avg_p'];
            } else {
                $att_boys += (float)$ar['avg_p'];
            }
        }
    }
} catch (Exception $e) {}

$att_boys_avg = $boys_count > 0 ? round($att_boys / $boys_count) : 0;
$att_girls_avg = $girls_count > 0 ? round($att_girls / $girls_count) : 0;
$att_total_avg = $total_enrolled > 0 ? round(($att_boys + $att_girls) / $total_enrolled) : 0;

$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद शाळा');
$s_udise = preg_replace('/\D/', '', (string)($school['udise_code'] ?: ''));
$hm_name = cce_headmaster_name($school_id, $semester);
$teacher_name = cce_class_teacher_name($school_id, $std);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>एकवट मुले-मुली श्रेणी तक्ता - इयत्ता <?= sanitize($label) ?> - <?= sanitize($s_name) ?></title>
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
    font-size: 13.5px;
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
    padding: 12mm 15mm;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-radius: 4px;
}
.sheet-header {
    text-align: center;
    border-bottom: 2px solid #2b547e;
    padding-bottom: 8px;
    margin-bottom: 12px;
}
.sheet-header h1 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: #1e3a5f;
}
.sheet-header h2 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: #d32f2f;
}
.sheet-meta {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 600;
    color: #333;
}
table.grade-matrix {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
table.grade-matrix th, table.grade-matrix td {
    border: 1px solid #4a779d;
    text-align: center;
    padding: 4px 2px;
    font-size: 12.5px;
}
table.grade-matrix thead tr.main-head th {
    background: #d9e7f5;
    font-weight: 700;
    color: #0d233a;
}
table.grade-matrix thead tr.sub-head th {
    background: #eef5fb;
    font-size: 11px;
    font-weight: 600;
}
table.grade-matrix tbody td.sub-name {
    text-align: left;
    padding-left: 8px;
    font-weight: 600;
    white-space: nowrap;
}
table.grade-matrix tbody tr:nth-child(even) {
    background: #fafcfe;
}
table.grade-matrix tfoot tr {
    background: #e3edf7;
    font-weight: 700;
}
.bottom-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-top: 16px;
    page-break-inside: avoid;
}
.enrolment-table {
    width: 40%;
    border-collapse: collapse;
}
.enrolment-table th, .enrolment-table td {
    border: 1px solid #5a82a6;
    padding: 4px 8px;
    text-align: center;
    font-size: 12.5px;
}
.enrolment-table th {
    background: #e1ebf5;
    font-weight: 700;
}
.signature-box {
    width: 55%;
    display: flex;
    justify-content: space-around;
    padding-top: 25px;
}
.sig-line {
    text-align: center;
    border-top: 1px dashed #666;
    padding-top: 4px;
    width: 40%;
    font-weight: 600;
    font-size: 12.5px;
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
        margin: 8mm 10mm;
    }
}
</style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn btn-success btn-sm" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> प्रिंट / PDF सेव्ह करा
    </button>
    <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>&semester=<?= $semester ?>" class="btn btn-outline-light btn-sm">
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
        <label class="small mb-0 text-white fw-bold ms-2">सत्र:</label>
        <select name="semester" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
            <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>प्रथम सत्र (सत्र १)</option>
            <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>द्वितीय सत्र (सत्र २)</option>
        </select>
    </form>
</div>

<div class="sheet-container">
    <div class="sheet-header">
        <h1><?= sanitize($s_name) ?></h1>
        <h2>सातत्यपूर्ण सर्वंकष मूल्यमापन — एकवट मुले-मुली श्रेणी तक्ता</h2>
        <div class="sheet-meta">
            <div><b>युडायस:</b> <?= sanitize($s_udise ?: '—') ?></div>
            <div><b>सत्र:</b> <?= $semester === 1 ? 'प्रथम सत्र' : 'द्वितीय सत्र' ?></div>
            <div><b>शैक्षणिक वर्ष:</b> <?= sanitize($year) ?></div>
            <div><b>इयत्ता:</b> <?= sanitize($label) ?></div>
        </div>
    </div>

    <table class="grade-matrix">
        <thead>
            <tr class="main-head">
                <th rowspan="2" style="width:3%;">अ.नं.</th>
                <th rowspan="2" style="width:17%;">विषय</th>
                <?php foreach ($grade_keys as $gk): ?>
                    <th colspan="2" style="width:7%;"><?= $gk ?></th>
                <?php endforeach; ?>
                <th colspan="3" style="width:10%;">एकूण</th>
            </tr>
            <tr class="sub-head">
                <?php foreach ($grade_keys as $gk): ?>
                    <th style="color:#0277bd;">मुले</th>
                    <th style="color:#d81b60;">मुली</th>
                <?php endforeach; ?>
                <th style="color:#0277bd;">मुले</th>
                <th style="color:#d81b60;">मुली</th>
                <th>एकवट</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $tot_b_col = array_fill_keys($grade_keys, 0);
            $tot_g_col = array_fill_keys($grade_keys, 0);
            $grand_b = 0;
            $grand_g = 0;

            if (empty($subjects)): ?>
                <tr><td colspan="23" class="py-4 text-center text-muted">या इयत्तेसाठी विषय उपलब्ध नाहीत.</td></tr>
            <?php else:
                foreach ($subjects as $idx => $sub): 
                    $sub_id = (int)$sub['id'];
                    $b_row_tot = $subject_totals[$sub_id]['boy'] ?? 0;
                    $g_row_tot = $subject_totals[$sub_id]['girl'] ?? 0;
                    $all_row_tot = $b_row_tot + $g_row_tot;
                    $grand_b += $b_row_tot;
                    $grand_g += $g_row_tot;
                ?>
                <tr>
                    <td><?= to_mr_digits($idx + 1) ?></td>
                    <td class="sub-name"><?= sanitize($sub['name_mr'] ?: $sub['name']) ?></td>
                    <?php foreach ($grade_keys as $gk): 
                        $b_cnt = $matrix[$sub_id][$gk]['boy'] ?? 0;
                        $g_cnt = $matrix[$sub_id][$gk]['girl'] ?? 0;
                        $tot_b_col[$gk] += $b_cnt;
                        $tot_g_col[$gk] += $g_cnt;
                    ?>
                        <td><?= $b_cnt > 0 ? to_mr_digits($b_cnt) : '-' ?></td>
                        <td><?= $g_cnt > 0 ? to_mr_digits($g_cnt) : '-' ?></td>
                    <?php endforeach; ?>
                    <td style="font-weight:700; background:#f0f7fc;"><?= to_mr_digits($b_row_tot) ?></td>
                    <td style="font-weight:700; background:#fdf2f7;"><?= to_mr_digits($g_row_tot) ?></td>
                    <td style="font-weight:700; background:#e8eff7;"><?= to_mr_digits($all_row_tot) ?></td>
                </tr>
            <?php endforeach; 
            endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">एकूण</th>
                <?php foreach ($grade_keys as $gk): ?>
                    <th><?= to_mr_digits($tot_b_col[$gk]) ?></th>
                    <th><?= to_mr_digits($tot_g_col[$gk]) ?></th>
                <?php endforeach; ?>
                <th><?= to_mr_digits($grand_b) ?></th>
                <th><?= to_mr_digits($grand_g) ?></th>
                <th><?= to_mr_digits($grand_b + $grand_g) ?></th>
            </tr>
        </tfoot>
    </table>

    <div class="bottom-section">
        <table class="enrolment-table">
            <thead>
                <tr>
                    <th>तपशील</th>
                    <th style="color:#0277bd;">मुले</th>
                    <th style="color:#d81b60;">मुली</th>
                    <th>एकूण</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight:600; text-align:left;">पट (Enrolment)</td>
                    <td><b><?= to_mr_digits($boys_count) ?></b></td>
                    <td><b><?= to_mr_digits($girls_count) ?></b></td>
                    <td><b><?= to_mr_digits($total_enrolled) ?></b></td>
                </tr>
                <tr>
                    <td style="font-weight:600; text-align:left;">सरासरी उपस्थिती</td>
                    <td><?= to_mr_digits($att_boys_avg) ?></td>
                    <td><?= to_mr_digits($att_girls_avg) ?></td>
                    <td><?= to_mr_digits($att_total_avg) ?></td>
                </tr>
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
        </div>
    </div>
</div>

</body>
</html>
