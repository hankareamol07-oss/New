<?php
/**
 * दोन्ही सत्र एकत्र सर्वसमावेशीत निकालपत्रक (Annual Comprehensive 2-Row Master Register)
 * Vijeta CCE Report-16 Port: Complete Annual Marksheet with Sem 1 & Sem 2 Tools Breakdown
 * Features:
 * - 2 rows per student (Row 1: Sem 1, Row 2: Sem 2)
 * - Academic subjects: आकारिक (FE), संकलित (SE), एकूण (Total), श्रेणी (Grade)
 * - Co-scholastic subjects: एकूण (Total), श्रेणी (Grade)
 * - Overall Grand Total, Percentage, and Final Grade for both semesters
 * - A4 Landscape print pagination
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

// Ensure subjects
$subjects = cce_subjects($school_id, $std);
if (empty($subjects)) {
    cce_seed_subjects($school_id, $std);
    $subjects = cce_subjects($school_id, $std);
}

// Fetch all students for standard
$students = cce_students($school_id, $std);

// School & Year Info
$year_short = $year;
if (preg_match('/^(\d{4})-(\d{4})$/', $year, $ym)) {
    $year_short = $ym[1] . '-' . substr($ym[2], 2);
}
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद प्राथमिक शाळा');
$s_udise = $school['udise_code'] ?: '';

// Section
$section = trim((string)($_GET['section'] ?? ($students ? ($students[array_key_first($students)]['section'] ?? '') : '')));

// Teachers & HM
$teacher_name = cce_class_teacher_name($school_id, $std, $section);
if (!$teacher_name) $teacher_name = 'वर्गशिक्षक';

$hm_name = '';
try {
    $stt = $db->prepare("SELECT sem2_headmaster_name, sem1_headmaster_name FROM school_defaults WHERE school_id = ?");
    $stt->execute([$school_id]);
    $sd = $stt->fetch();
    if ($sd) $hm_name = $sd['sem2_headmaster_name'] ?: $sd['sem1_headmaster_name'];
} catch (Exception $e) {}
if (!$hm_name) $hm_name = 'मुख्याध्यापक';

// Split Academic vs Co-scholastic subjects
$acad_subs = [];
$cosch_subs = [];
foreach ($subjects as $sb) {
    $is_cosch = (!empty($sb['is_coscholastic'])) || in_array($sb['subject_code'] ?? '', ['s7', 's8', 's9'], true) || preg_match('/(कला|कार्यानुभव|शारीरिक|शा\.शि)/u', $sb['name_mr'] ?? '');
    if ($is_cosch) {
        $cosch_subs[] = $sb;
    } else {
        $acad_subs[] = $sb;
    }
}

// Load Sem 1 and Sem 2 Marks for all students
$marks_s1 = [];
$marks_s2 = [];
$attendance_tot = [];
if (!empty($students)) {
    $sids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($sids), '?'));

    // Sem 1
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = 1 AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $marks_s1[(int)$r['student_id']][(int)$r['subject_id']] = $r;
    }

    // Sem 2
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = 2 AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $marks_s2[(int)$r['student_id']][(int)$r['subject_id']] = $r;
    }

    // Attendance
    $stmt = $db->prepare("SELECT * FROM attendance WHERE academic_year = ? AND student_id IN ($in)");
    $stmt->execute(array_merge([$year], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $p = (int)($r['days_present'] ?? ($r['present_days'] ?? 0));
        $attendance_tot[(int)$r['student_id']] = ($attendance_tot[(int)$r['student_id']] ?? 0) + $p;
    }
}

// Pagination: 10 students (20 rows) per page fits A4 landscape comfortably
$chunks = array_chunk($students, 10);
if (empty($chunks)) $chunks = [[]];

if (!function_exists('fmt_reg_mk')) {
    function fmt_reg_mk($v) {
        if ($v === null || $v === '') return '-';
        $fl = (float)$v;
        return (string)(rtrim(rtrim(number_format($fl, 1, '.', ''), '0'), '.'));
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>दोन्ही सत्र एकत्र सर्वसमावेशीत निकालपत्रक - इयत्ता <?= sanitize(std_label($std)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
@font-face {
  font-family: 'Utsaah';
  font-style: normal;
  font-weight: 400;
  src: local('Utsaah'), url('<?= APP_URL ?>/assets/fonts/utsaah.ttf') format('truetype');
}
@font-face {
  font-family: 'Utsaah';
  font-style: normal;
  font-weight: 700;
  src: local('Utsaah Bold'), local('Utsaah-Bold'), url('<?= APP_URL ?>/assets/fonts/utsaahb.ttf') format('truetype');
}

@page {
  size: A4 landscape;
  margin: 6mm 8mm;
}
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}
body {
  background: #dfe6eb;
  font-family: 'Utsaah', 'Mukta', 'Nirmala UI', sans-serif;
  color: #111;
  font-size: 11.5px;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

/* TOOLBAR */
.tb {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: #111c2a;
  color: #fff;
  padding: 8px 16px;
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  box-shadow: 0 2px 10px rgba(0,0,0,0.3);
  font-family: system-ui, -apple-system, sans-serif;
  font-size: 12.5px;
}
.tb button.btn-print {
  background: #10b981;
  color: #fff;
  border: 0;
  border-radius: 4px;
  padding: 6px 14px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.tb button.btn-print:hover { background: #059669; }
.tb a.btn-link {
  background: #223246;
  color: #e2e8f0;
  border: 1px solid #3c5472;
  border-radius: 4px;
  padding: 5px 10px;
  text-decoration: none;
  font-weight: 600;
}
.tb a.btn-link:hover { background: #334155; }
.tb select {
  background: #223246;
  color: #fff;
  border: 1px solid #476585;
  border-radius: 4px;
  padding: 5px 8px;
  font-size: 12px;
}
.tb .print-tip {
  margin-left: auto;
  font-size: 11.5px;
  color: #94a3b8;
}

/* REGISTER SHEET */
.reg-sheet {
  width: 281mm;
  min-height: 196mm;
  margin: 14px auto;
  background: #ffffff;
  padding: 5mm 6mm;
  box-shadow: 0 3px 12px rgba(0,0,0,0.15);
  page-break-after: always;
  break-after: page;
  page-break-inside: avoid;
  break-inside: avoid;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.reg-sheet:last-child {
  page-break-after: auto;
  break-after: auto;
}

/* HEADER */
.reg-header {
  margin-bottom: 2mm;
}
.title-row {
  text-align: center;
  font-size: 19px;
  font-weight: 700;
  color: #c2185b;
  letter-spacing: 0.5px;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 13.5px;
  margin-top: 1.5mm;
  border-bottom: 1.5px solid #00bcd4;
  padding-bottom: 1.5mm;
}
.meta-row b {
  color: #000;
  font-weight: 700;
}

/* TABLE */
table.reg-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #5D9BC0;
  font-size: 11px;
  text-align: center;
}
table.reg-table th, table.reg-table td {
  border: 1px solid #5D9BC0;
  padding: 1.2px 1px;
  vertical-align: middle;
}
table.reg-table thead th {
  background: #d4dce9;
  color: #000;
  font-weight: 700;
  line-height: 1.1;
}
table.reg-table thead th.vert-th {
  font-size: 10px;
  padding: 2px 1px;
}
table.reg-table tbody tr.row-s1 {
  background: #ffffff;
  height: 6.2mm;
}
table.reg-table tbody tr.row-s2 {
  background: #f8fafc;
  height: 6.2mm;
  border-bottom: 1.5px solid #5D9BC0;
}
table.reg-table td.s-name-cell {
  text-align: left;
  padding-left: 4px;
  font-weight: 700;
  font-size: 11.5px;
  white-space: nowrap;
}
table.reg-table td.sem-tag {
  font-weight: 700;
  font-size: 11px;
}
table.reg-table td.mk-cell {
  font-size: 11px;
}
table.reg-table td.grd-cell {
  font-weight: 700;
  font-size: 11.5px;
}
table.reg-table td.tot-cell {
  font-weight: 700;
  font-size: 11.5px;
  background: #e0f2fe;
}

/* SIGNATURES */
.sign-row {
  display: flex;
  justify-content: space-between;
  margin-top: 3mm;
  padding: 0 8mm;
  font-size: 13.5px;
}
.sign-box {
  text-align: center;
}
.sign-box .s-title {
  font-weight: 700;
}
.sign-box .s-name {
  font-weight: 600;
  margin-top: 1mm;
  font-size: 12.5px;
}

@media print {
  body { background: #fff !important; }
  .tb { display: none !important; }
  .reg-sheet {
    width: 100% !important;
    min-height: auto !important;
    margin: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
  }
}
</style>
</head>
<body>

<!-- TOOLBAR -->
<div class="tb">
  <button class="btn-print" onclick="window.print()">🖨 निकालपत्रक छापा / PDF</button>
  <div class="tb-sep"></div>

  <!-- Standard Switcher -->
  <select onchange="location.href='?std=' + this.value">
    <?php foreach ($standards as $st_num => $st_lbl): ?>
      <option value="<?= (int)$st_num ?>" <?= (int)$st_num === $std ? 'selected' : '' ?>>
        इयत्ता <?= sanitize($st_lbl) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <a class="btn-link" href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>">
    ← अहवाल यादी
  </a>

  <!-- Unified Theme & Font Switcher -->
  <?= cce_theme_toolbar_controls(false) ?>

  <span class="print-tip">
    💡 Destination: <b>Save as PDF</b> | Layout: <b>Landscape</b> | Margins: <b>None</b>.
  </span>
</div>

<?php
$global_sr = 0;
foreach ($chunks as $page_idx => $c_students):
?>
<div class="reg-sheet">

  <div>
    <!-- HEADER -->
    <div class="reg-header">
      <div class="titleRow title-row">● दोन्ही सत्र एकत्र सर्वसमावेशीत निकालपत्रक (वार्षिक निकाल) ●</div>
      <div class="meta-row">
        <div>शाळा : <b><?= sanitize($s_name) ?></b></div>
        <div>युडायस : <b><?= sanitize($s_udise) ?></b></div>
        <div>इयत्ता : <b><?= sanitize(std_label($std)) ?></b></div>
        <div>शैक्षणिक वर्ष : <b><?= sanitize($year_short) ?></b></div>
        <div>पृष्ठ : <b><?= $page_idx + 1 ?> / <?= count($chunks) ?></b></div>
      </div>
    </div>

    <!-- MAIN 2-ROW TABLE -->
    <table class="reg-table">
      <thead>
        <tr>
          <th rowspan="3" style="width:2.8%;">अ.<br>नं.</th>
          <th rowspan="3" style="width:16%;">विद्यार्थ्याचे नाव</th>
          <th rowspan="3" style="width:4.5%;">उप.<br>दिवस</th>
          <th rowspan="3" style="width:3%;">सत्र</th>
          <?php foreach ($acad_subs as $asb): ?>
            <th colspan="4"><?= sanitize($asb['name_mr'] ?: $asb['name']) ?></th>
          <?php endforeach; ?>
          <?php foreach ($cosch_subs as $csb): ?>
            <th colspan="2"><?= sanitize($csb['name_mr'] ?: $csb['name']) ?></th>
          <?php endforeach; ?>
          <th colspan="3" style="background:#bae6fd;">एकत्रित निकाल</th>
        </tr>
        <tr>
          <?php foreach ($acad_subs as $asb): ?>
            <th class="vert-th">आका.</th>
            <th class="vert-th">संक.</th>
            <th class="vert-th">एकूण</th>
            <th class="vert-th">श्रेणी</th>
          <?php endforeach; ?>
          <?php foreach ($cosch_subs as $csb): ?>
            <th class="vert-th">एकूण</th>
            <th class="vert-th">श्रेणी</th>
          <?php endforeach; ?>
          <th rowspan="2" class="vert-th" style="background:#bae6fd; width:4.5%;">एकूण<br>गुण</th>
          <th rowspan="2" class="vert-th" style="background:#bae6fd; width:4%;">टक्के<br>%</th>
          <th rowspan="2" class="vert-th" style="background:#bae6fd; width:4%;">अंतिम<br>श्रेणी</th>
        </tr>
        <tr>
          <?php foreach ($acad_subs as $asb): 
            $f_max = (int)(($asb['fe_max'] ?? $asb['fe_total_max'] ?? 50) ?: 50);
            $s_max = (int)(($asb['se_max'] ?? $asb['se_total_max'] ?? 50) ?: 50);
          ?>
            <th><?= $f_max ?></th>
            <th><?= $s_max ?></th>
            <th><?= $f_max + $s_max ?></th>
            <th>-</th>
          <?php endforeach; ?>
          <?php foreach ($cosch_subs as $csb): ?>
            <th>100</th>
            <th>-</th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        if (empty($c_students)):
        ?>
          <tr><td colspan="40" style="padding:15px; color:#666;">विद्यार्थी उपलब्ध नाहीत.</td></tr>
        <?php
        else:
          foreach ($c_students as $stu):
              $global_sr++;
              $sid = (int)$stu['id'];
              $st_nm = $stu['name_mr'] ?: $stu['name'];
              $st_att = $attendance_tot[$sid] ?? '-';

              $m1 = $marks_s1[$sid] ?? [];
              $m2 = $marks_s2[$sid] ?? [];

              // Overall totals for Sem 1
              $tot_got1 = 0; $tot_max1 = 0;
              foreach ($subjects as $sb) {
                  $sbid = (int)$sb['id'];
                  if (isset($m1[$sbid])) {
                      $tot_got1 += (float)($m1[$sbid]['grand_total'] ?? 0);
                      $tot_max1 += (float)(($sb['fe_max'] ?? $sb['fe_total_max'] ?? 50) + ($sb['se_max'] ?? $sb['se_total_max'] ?? 50));
                  }
              }
              $pct1 = $tot_max1 > 0 ? round(($tot_got1 / $tot_max1) * 100, 1) : '-';
              $grd1 = $pct1 !== '-' ? cce_grade_from_percent((float)$pct1) : '-';

              // Overall totals for Sem 2
              $tot_got2 = 0; $tot_max2 = 0;
              foreach ($subjects as $sb) {
                  $sbid = (int)$sb['id'];
                  if (isset($m2[$sbid])) {
                      $tot_got2 += (float)($m2[$sbid]['grand_total'] ?? 0);
                      $tot_max2 += (float)(($sb['fe_max'] ?? $sb['fe_total_max'] ?? 50) + ($sb['se_max'] ?? $sb['se_total_max'] ?? 50));
                  }
              }
              $pct2 = $tot_max2 > 0 ? round(($tot_got2 / $tot_max2) * 100, 1) : '-';
              $grd2 = $pct2 !== '-' ? cce_grade_from_percent((float)$pct2) : '-';
        ?>
        <!-- ROW 1: SEMESTER 1 -->
        <tr class="row-s1">
          <td rowspan="2" style="font-weight:700;"><?= $global_sr ?></td>
          <td rowspan="2" class="s-name-cell"><?= sanitize($st_nm) ?></td>
          <td rowspan="2" style="font-weight:600;"><?= $st_att ?></td>
          <td class="sem-tag" style="color:#0288d1;">I</td>

          <!-- Academic subjects S1 -->
          <?php foreach ($acad_subs as $asb):
              $sbid = (int)$asb['id'];
              $mk = $m1[$sbid] ?? null;
          ?>
            <td class="mk-cell"><?= $mk ? fmt_reg_mk($mk['fe_total']) : '-' ?></td>
            <td class="mk-cell"><?= $mk ? fmt_reg_mk($mk['se_total']) : '-' ?></td>
            <td class="mk-cell" style="font-weight:700;"><?= $mk ? fmt_reg_mk($mk['grand_total']) : '-' ?></td>
            <td class="grd-cell"><?= $mk ? sanitize($mk['grade'] ?: '-') : '-' ?></td>
          <?php endforeach; ?>

          <!-- Co-scholastic subjects S1 -->
          <?php foreach ($cosch_subs as $csb):
              $csbid = (int)$csb['id'];
              $mk = $m1[$csbid] ?? null;
          ?>
            <td class="mk-cell" style="font-weight:700;"><?= $mk ? fmt_reg_mk($mk['grand_total']) : '-' ?></td>
            <td class="grd-cell"><?= $mk ? sanitize($mk['grade'] ?: '-') : '-' ?></td>
          <?php endforeach; ?>

          <!-- S1 Summary -->
          <td class="tot-cell"><?= $tot_got1 > 0 ? fmt_reg_mk($tot_got1) : '-' ?></td>
          <td class="tot-cell"><?= $pct1 !== '-' ? ($pct1 . '%') : '-' ?></td>
          <td class="tot-cell" style="font-size:12px; color:#0b3b60;"><?= sanitize($grd1) ?></td>
        </tr>

        <!-- ROW 2: SEMESTER 2 -->
        <tr class="row-s2">
          <td class="sem-tag" style="color:#c2185b;">II</td>

          <!-- Academic subjects S2 -->
          <?php foreach ($acad_subs as $asb):
              $sbid = (int)$asb['id'];
              $mk = $m2[$sbid] ?? null;
          ?>
            <td class="mk-cell"><?= $mk ? fmt_reg_mk($mk['fe_total']) : '-' ?></td>
            <td class="mk-cell"><?= $mk ? fmt_reg_mk($mk['se_total']) : '-' ?></td>
            <td class="mk-cell" style="font-weight:700;"><?= $mk ? fmt_reg_mk($mk['grand_total']) : '-' ?></td>
            <td class="grd-cell"><?= $mk ? sanitize($mk['grade'] ?: '-') : '-' ?></td>
          <?php endforeach; ?>

          <!-- Co-scholastic subjects S2 -->
          <?php foreach ($cosch_subs as $csb):
              $csbid = (int)$csb['id'];
              $mk = $m2[$csbid] ?? null;
          ?>
            <td class="mk-cell" style="font-weight:700;"><?= $mk ? fmt_reg_mk($mk['grand_total']) : '-' ?></td>
            <td class="grd-cell"><?= $mk ? sanitize($mk['grade'] ?: '-') : '-' ?></td>
          <?php endforeach; ?>

          <!-- S2 Summary -->
          <td class="tot-cell"><?= $tot_got2 > 0 ? fmt_reg_mk($tot_got2) : '-' ?></td>
          <td class="tot-cell"><?= $pct2 !== '-' ? ($pct2 . '%') : '-' ?></td>
          <td class="tot-cell" style="font-size:12px; color:#0b3b60;"><?= sanitize($grd2) ?></td>
        </tr>
        <?php
          endforeach;
        endif;
        ?>
      </tbody>
    </table>
  </div>

  <!-- FOOTER SIGNATURES -->
  <div class="sign-row">
    <div class="sign-box">
      <div class="s-title">वर्गशिक्षक स्वाक्षरी</div>
      <div class="s-name"><?= sanitize($teacher_name) ?></div>
    </div>
    <div class="sign-box">
      <div class="s-title">मुख्याध्यापक स्वाक्षरी व शिक्का</div>
      <div class="s-name"><?= sanitize($hm_name) ?></div>
    </div>
  </div>

</div><!-- /reg-sheet -->
<?php endforeach; ?>

</body>
</html>
