<?php
declare(strict_types=1);

/**
 * जातनिहाय श्रेणी तक्ता (Caste/Category-wise Grade Matrix)
 * Ported and enhanced from Vijeta CCE App report-17.html
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

// Official Maharashtra caste category list with Marathi display labels
$caste_categories = [
    'sc'    => 'अ.जा. (SC)',
    'st'    => 'अ.ज. (ST)',
    'vj'    => 'वि.जा.-अ (VJ-A)',
    'nt_b'  => 'भ.ज.-ब (NT-B)',
    'nt_c'  => 'भ.ज.-क (NT-C)',
    'nt_d'  => 'भ.ज.-ड (NT-D)',
    'sbc'   => 'वि.मा.प्र. (SBC)',
    'obc'   => 'इ.मा.व. (OBC)',
    'ews'   => 'ई.डब्ल्यू.एस. (EWS)',
    'open'  => 'खुला (OPEN)',
];

if (!function_exists('normalize_caste_cat')) {
    function normalize_caste_cat(?string $raw): string
    {
        $val = mb_strtolower(trim((string)$raw));
        if ($val === '') return 'open';
        if (str_contains($val, 'sc') || str_contains($val, 'अ.जा') || str_contains($val, 'अनुसूचित जाती')) return 'sc';
        if (str_contains($val, 'st') || str_contains($val, 'अ.ज') || str_contains($val, 'अनुसूचित जमाती')) return 'st';
        if (str_contains($val, 'vj') || str_contains($val, 'वि.जा') || str_contains($val, 'dt')) return 'vj';
        if (str_contains($val, 'nt-b') || str_contains($val, 'ntb') || str_contains($val, 'भ.ज.-ब') || str_contains($val, 'भ.ज.ब')) return 'nt_b';
        if (str_contains($val, 'nt-c') || str_contains($val, 'ntc') || str_contains($val, 'भ.ज.-क') || str_contains($val, 'भ.ज.क')) return 'nt_c';
        if (str_contains($val, 'nt-d') || str_contains($val, 'ntd') || str_contains($val, 'भ.ज.-ड') || str_contains($val, 'भ.ज.ड')) return 'nt_d';
        if (str_contains($val, 'nt') || str_contains($val, 'भ.ज')) return 'nt_b';
        if (str_contains($val, 'sbc') || str_contains($val, 'वि.मा.प्र')) return 'sbc';
        if (str_contains($val, 'obc') || str_contains($val, 'इ.मा.व')) return 'obc';
        if (str_contains($val, 'ews')) return 'ews';
        return 'open';
    }
}

// Student demographic & marks aggregation
$student_meta = [];
$boys_count = 0;
$girls_count = 0;

foreach ($students as $st) {
    $sid = (int)$st['id'];
    $is_girl = cce_is_female_student($st);
    $cat = normalize_caste_cat($st['caste_category'] ?? $st['caste'] ?? 'open');
    $student_meta[$sid] = [
        'gender' => $is_girl ? 'girl' : 'boy',
        'cat'    => $cat,
    ];
    if ($is_girl) $girls_count++; else $boys_count++;
}
$total_enrolled = count($students);

// Overall student percent and grade calculation for semester
$student_overall_grades = [];
if ($students && $subjects) {
    $ids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT student_id, grand_total, grade FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year, $semester], $ids));
    
    $student_totals = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['student_id'];
        $student_totals[$sid] = ($student_totals[$sid] ?? 0) + (float)$row['grand_total'];
    }

    $total_max = 0;
    foreach ($subjects as $sub) {
        $total_max += (float)$sub['fe_max'] + (float)$sub['se_max'];
    }

    foreach ($students as $st) {
        $sid = (int)$st['id'];
        $tot = $student_totals[$sid] ?? 0;
        $pct = $total_max > 0 ? ($tot / $total_max) * 100 : 0;
        $student_overall_grades[$sid] = cce_grade_for_percent($pct);
    }
}

// Matrix: caste_cat -> grade -> [boy => count, girl => count]
$matrix = [];
$cat_totals = [];

foreach (array_keys($caste_categories) as $ck) {
    foreach ($grade_keys as $g) {
        $matrix[$ck][$g] = ['boy' => 0, 'girl' => 0];
    }
    $cat_totals[$ck] = ['boy' => 0, 'girl' => 0];
}

foreach ($students as $st) {
    $sid = (int)$st['id'];
    $cat = $student_meta[$sid]['cat'] ?? 'open';
    $gender = $student_meta[$sid]['gender'] ?? 'boy';
    $grade = $student_overall_grades[$sid] ?? 'ड';

    // Normalize grade
    $norm_grade = $grade;
    if ($norm_grade === 'अ1') $norm_grade = 'अ-1';
    elseif ($norm_grade === 'अ2') $norm_grade = 'अ-2';
    elseif ($norm_grade === 'ब1') $norm_grade = 'ब-1';
    elseif ($norm_grade === 'ब2') $norm_grade = 'ब-2';
    elseif ($norm_grade === 'क1') $norm_grade = 'क-1';
    elseif ($norm_grade === 'क2') $norm_grade = 'क-2';
    elseif ($norm_grade === 'इ1') $norm_grade = 'इ-1';
    elseif ($norm_grade === 'इ2') $norm_grade = 'इ-2';

    if (!isset($matrix[$cat][$norm_grade])) {
        $norm_grade = 'ड';
    }

    $matrix[$cat][$norm_grade][$gender]++;
    $cat_totals[$cat][$gender]++;
}

// Attendance aggregation
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
            $gender = $student_meta[$sid]['gender'] ?? 'boy';
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
<title>जातनिहाय श्रेणी तक्ता - इयत्ता <?= sanitize($label) ?> - <?= sanitize($s_name) ?></title>
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
    border-bottom: 2px solid #6a1b9a;
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
    color: #6a1b9a;
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
    border: 1px solid #7b1fa2;
    text-align: center;
    padding: 4px 2px;
    font-size: 12.5px;
}
table.grade-matrix thead tr.main-head th {
    background: #f3e5f5;
    font-weight: 700;
    color: #4a148c;
}
table.grade-matrix thead tr.sub-head th {
    background: #faf4fc;
    font-size: 11px;
    font-weight: 600;
}
table.grade-matrix tbody td.cat-name {
    text-align: left;
    padding-left: 8px;
    font-weight: 600;
    white-space: nowrap;
}
table.grade-matrix tbody tr:nth-child(even) {
    background: #fdfcfe;
}
table.grade-matrix tfoot tr {
    background: #ede7f6;
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
    border: 1px solid #7b1fa2;
    padding: 4px 8px;
    text-align: center;
    font-size: 12.5px;
}
.enrolment-table th {
    background: #f3e5f5;
    font-weight: 700;
    color: #4a148c;
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
        <h2>सातत्यपूर्ण सर्वंकष मूल्यमापन — जातनिहाय श्रेणी तक्ता (शासकीय अहवाल)</h2>
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
                <th rowspan="2" style="width:4%;">अ.नं.</th>
                <th rowspan="2" style="width:16%;">प्रवर्ग / जात (Category)</th>
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
            $sr = 1;

            foreach ($caste_categories as $ck => $cat_lbl): 
                $b_row_tot = $cat_totals[$ck]['boy'] ?? 0;
                $g_row_tot = $cat_totals[$ck]['girl'] ?? 0;
                $all_row_tot = $b_row_tot + $g_row_tot;
                $grand_b += $b_row_tot;
                $grand_g += $g_row_tot;
            ?>
                <tr>
                    <td><?= to_mr_digits($sr++) ?></td>
                    <td class="cat-name"><?= sanitize($cat_lbl) ?></td>
                    <?php foreach ($grade_keys as $gk): 
                        $b_cnt = $matrix[$ck][$gk]['boy'] ?? 0;
                        $g_cnt = $matrix[$ck][$gk]['girl'] ?? 0;
                        $tot_b_col[$gk] += $b_cnt;
                        $tot_g_col[$gk] += $g_cnt;
                    ?>
                        <td><?= $b_cnt > 0 ? to_mr_digits($b_cnt) : '-' ?></td>
                        <td><?= $g_cnt > 0 ? to_mr_digits($g_cnt) : '-' ?></td>
                    <?php endforeach; ?>
                    <td style="font-weight:700; background:#f0f7fc;"><?= to_mr_digits($b_row_tot) ?></td>
                    <td style="font-weight:700; background:#fdf2f7;"><?= to_mr_digits($g_row_tot) ?></td>
                    <td style="font-weight:700; background:#ede7f6;"><?= to_mr_digits($all_row_tot) ?></td>
                </tr>
            <?php endforeach; ?>
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
