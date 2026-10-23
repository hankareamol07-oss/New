<?php
/**
 * A4 Portrait श्रेणी तक्ता (Formative & Summative Grade Sheet)
 * Vijeta CCE Report-05 Port: Class Grade Table (आकारिक व संकलित श्रेणी तक्ता)
 * Features:
 * - A4 Portrait page setup
 * - For each student: FE Grade (आकारिक) and SE Grade (संकलित) per subject
 * - Clean vertical rotated headers for maximum columns fit
 * - Official signatures
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
    $stt = $db->prepare("SELECT sem1_headmaster_name, sem2_headmaster_name FROM school_defaults WHERE school_id = ?");
    $stt->execute([$school_id]);
    $sd = $stt->fetch();
    if ($sd) {
        $hm_name = $semester === 2 ? ($sd['sem2_headmaster_name'] ?: $sd['sem1_headmaster_name']) : $sd['sem1_headmaster_name'];
    }
} catch (Exception $e) {}
if (!$hm_name) $hm_name = 'मुख्याध्यापक';

// Load Marks for Selected Semester
$marks = [];
if (!empty($students)) {
    $sids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($sids), '?'));

    $stmt = $db->prepare("SELECT student_id, subject_id, fe_total, se_total, grade, percent FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year, $semester], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $marks[(int)$r['student_id']][(int)$r['subject_id']] = $r;
    }
}

// Up to 25 students per page on portrait
$chunks = array_chunk($students, 25);
if (empty($chunks)) $chunks = [[]];
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>श्रेणी तक्ता - इयत्ता <?= sanitize(std_label($std)) ?> - <?= sanitize(semester_label($semester)) ?></title>
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
  size: A4 portrait;
  margin: 10mm 10mm;
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
  font-size: 13px;
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
.tb a.btn-link.active {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}
.tb select {
  background: #223246;
  color: #fff;
  border: 1px solid #476585;
  border-radius: 4px;
  padding: 5px 8px;
  font-size: 12px;
}
.tb .tb-sep {
  width: 1px;
  height: 20px;
  background: #475569;
  margin: 0 4px;
}
.tb .print-tip {
  margin-left: auto;
  font-size: 11.5px;
  color: #94a3b8;
}

/* PORTRAIT SHEET */
.grd-sheet {
  width: 190mm;
  min-height: 275mm;
  margin: 16px auto;
  background: #ffffff;
  padding: 6mm 8mm;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  page-break-after: always;
  break-after: page;
  page-break-inside: avoid;
  break-inside: avoid;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.grd-sheet:last-child {
  page-break-after: auto;
  break-after: auto;
}

/* HEADER */
.grd-header {
  margin-bottom: 3mm;
}
.title-row {
  text-align: center;
  font-size: 20px;
  font-weight: 700;
  color: #b71c1c;
  letter-spacing: 0.5px;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-top: 1.5mm;
  border-bottom: 1.5px solid #5D9BC0;
  padding-bottom: 1.5mm;
}
.meta-row b {
  color: #000;
  font-weight: 700;
}

/* TABLE */
table.grd-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #5D9BC0;
  font-size: 12px;
  text-align: center;
}
table.grd-table th, table.grd-table td {
  border: 1px solid #5D9BC0;
  padding: 1.8px 1px;
  vertical-align: middle;
}
table.grd-table thead th {
  background: #d4dce9;
  color: #000;
  font-weight: 700;
  line-height: 1.15;
}
table.grd-table thead th.vert-head {
  font-size: 10px;
  padding: 2px 0;
  writing-mode: vertical-lr;
  transform: rotate(180deg);
  white-space: nowrap;
  height: 18mm;
  max-width: 7mm;
  margin: auto;
}
table.grd-table td.s-name-cell {
  text-align: left;
  padding-left: 4px;
  font-weight: 700;
  font-size: 12.5px;
  white-space: nowrap;
}
table.grd-table td.g-cell {
  font-weight: 700;
  font-size: 12.5px;
  color: #0b3b60;
}

/* SIGNATURES */
.sign-row {
  display: flex;
  justify-content: space-between;
  margin-top: 4mm;
  padding: 0 8mm;
  font-size: 14px;
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
  font-size: 13px;
}

@media print {
  body { background: #fff !important; }
  .tb { display: none !important; }
  .grd-sheet {
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
  <button class="btn-print" onclick="window.print()">🖨 श्रेणी तक्ता छापा / PDF</button>
  <div class="tb-sep"></div>

  <!-- Semester Toggle -->
  <a class="btn-link <?= $semester === 1 ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['semester' => 1])) ?>">
    🌱 प्रथम सत्र
  </a>
  <a class="btn-link <?= $semester === 2 ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['semester' => 2])) ?>">
    🍂 द्वितीय सत्र
  </a>
  <div class="tb-sep"></div>

  <!-- Standard Switcher -->
  <select onchange="location.href='?std=' + this.value + '&semester=<?= $semester ?>'">
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
    💡 Destination: <b>Save as PDF</b> | Layout: <b>Portrait</b> | Margins: <b>Default/None</b>.
  </span>
</div>

<?php
$global_sr = 0;
foreach ($chunks as $page_idx => $c_students):
?>
<div class="grd-sheet">

  <div>
    <!-- HEADER -->
    <div class="grd-header">
      <div class="title-row">● आकारिक व संकलित श्रेणी तक्ता (Report-05) ●</div>
      <div class="meta-row">
        <div>शाळा : <b><?= sanitize($s_name) ?></b></div>
        <div>युडायस : <b><?= sanitize($s_udise) ?></b></div>
        <div>इयत्ता : <b><?= sanitize(std_label($std)) ?></b></div>
        <div>सत्र : <b><?= sanitize(semester_label($semester)) ?></b></div>
        <div>सन : <b><?= sanitize($year_short) ?></b></div>
        <div>पृष्ठ : <b><?= $page_idx + 1 ?> / <?= count($chunks) ?></b></div>
      </div>
    </div>

    <!-- GRADE TABLE -->
    <table class="grd-table">
      <thead>
        <tr>
          <th rowspan="2" style="width:5%;">अ.<br>नं.</th>
          <th rowspan="2" style="width:23%;">विद्यार्थ्याचे नाव</th>
          <?php foreach ($subjects as $sb): ?>
            <th colspan="2"><?= sanitize($sb['name_mr'] ?: $sb['name']) ?></th>
          <?php endforeach; ?>
        </tr>
        <tr>
          <?php foreach ($subjects as $sb): ?>
            <th class="vert-head">आकारिक</th>
            <th class="vert-head">संकलित</th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        if (empty($c_students)):
        ?>
          <tr><td colspan="30" style="padding:15px; color:#666;">विद्यार्थी उपलब्ध नाहीत.</td></tr>
        <?php
        else:
          foreach ($c_students as $stu):
              $global_sr++;
              $sid = (int)$stu['id'];
              $st_nm = $stu['name_mr'] ?: $stu['name'];
              $st_m = $marks[$sid] ?? [];
        ?>
        <tr>
          <td style="font-weight:700;"><?= $global_sr ?></td>
          <td class="s-name-cell"><?= sanitize($st_nm) ?></td>
          <?php foreach ($subjects as $sb):
              $sbid = (int)$sb['id'];
              $mk = $st_m[$sbid] ?? null;

              // Calculate FE Grade & SE Grade
              $fe_grd = '-';
              $se_grd = '-';
              if ($mk) {
                  $fe_max = (float)(($sb['fe_max'] ?? $sb['fe_total_max'] ?? 50) ?: 50);
                  $se_max = (float)(($sb['se_max'] ?? $sb['se_total_max'] ?? 50) ?: 50);
                  if ($mk['fe_total'] !== null && $fe_max > 0) {
                      $fe_pct = ($mk['fe_total'] / $fe_max) * 100;
                      $fe_grd = cce_grade_from_percent($fe_pct);
                  }
                  if ($mk['se_total'] !== null && $se_max > 0) {
                      $se_pct = ($mk['se_total'] / $se_max) * 100;
                      $se_grd = cce_grade_from_percent($se_pct);
                  } elseif ($mk['grade']) {
                      $se_grd = $mk['grade'];
                  }
              }
          ?>
            <td class="g-cell"><?= sanitize($fe_grd) ?></td>
            <td class="g-cell" style="background:#fafafa;"><?= sanitize($se_grd) ?></td>
          <?php endforeach; ?>
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

</div><!-- /grd-sheet -->
<?php endforeach; ?>

</body>
</html>
