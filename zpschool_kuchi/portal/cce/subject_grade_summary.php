<?php
/**
 * विषयनिहाय श्रेणी एकवट तक्ता (Subject-wise Grade Distribution Summary)
 * Vijeta CCE Report-06 Port: Subject-wise Grade Statistical Matrix (एकवट तक्ता)
 * Features:
 * - Count of students obtaining each grade: अ-1, अ-2, ब-1, ब-2, क-1, क-2, ड, इ-1, इ-2
 * - Row totals per subject and column totals per grade
 * - Perfect for UDISE submission and government quality audits
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
$total_students_count = count($students);

// School & Year Info
$year_short = $year;
if (preg_match('/^(\d{4})-(\d{4})$/', $year, $ym)) {
    $year_short = $ym[1] . '-' . substr($ym[2], 2);
}
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद प्राथमिक शाळा');
$s_udise = $school['udise_code'] ?: '';

// Section
$section = trim((string)($_GET['section'] ?? ''));

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

// Standard Grade Scale
$grades = ['अ-1', 'अ-2', 'ब-1', 'ब-2', 'क-1', 'क-2', 'ड', 'इ-1', 'इ-2'];

// Matrix [subject_id][grade] = count
$matrix = [];
foreach ($subjects as $sb) {
    foreach ($grades as $g) {
        $matrix[(int)$sb['id']][$g] = 0;
    }
}

if (!empty($students)) {
    $sids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($sids), '?'));

    $stmt = $db->prepare("SELECT subject_id, grade FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in) AND grade <> ''");
    $stmt->execute(array_merge([$school_id, $year, $semester], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $sbid = (int)$r['subject_id'];
        $grd = trim($r['grade']);
        if (isset($matrix[$sbid][$grd])) {
            $matrix[$sbid][$grd]++;
        }
    }
}

// Compute Column Totals
$col_totals = array_fill_keys($grades, 0);
$grand_cells_total = 0;
foreach ($subjects as $sb) {
    $sbid = (int)$sb['id'];
    foreach ($grades as $g) {
        $c = $matrix[$sbid][$g] ?? 0;
        $col_totals[$g] += $c;
        $grand_cells_total += $c;
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>विषयनिहाय श्रेणी एकवट तक्ता - इयत्ता <?= sanitize(std_label($std)) ?> - <?= sanitize(semester_label($semester)) ?></title>
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
  margin: 10mm 12mm;
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
  font-size: 14.5px;
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
  background: #167A5B;
  color: #fff;
  border-color: #167A5B;
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

/* MAIN SHEET */
.sum-sheet {
  width: 275mm;
  min-height: 185mm;
  margin: 16px auto;
  background: #ffffff;
  padding: 8mm 10mm;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  page-break-after: always;
  break-after: page;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

/* HEADER */
.sum-header {
  margin-bottom: 4mm;
}
.title-row {
  text-align: center;
  font-size: 22px;
  font-weight: 700;
  color: #b71c1c;
  letter-spacing: 0.5px;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 15px;
  margin-top: 2mm;
  border-bottom: 1.5px solid #00bcd4;
  padding-bottom: 2mm;
}
.meta-row b {
  color: #000;
  font-weight: 700;
}

/* TABLE */
table.sum-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.5px solid #5D9BC0;
  font-size: 14.5px;
  text-align: center;
}
table.sum-table th, table.sum-table td {
  border: 1px solid #5D9BC0;
  padding: 2.5mm 2mm;
  vertical-align: middle;
}
table.sum-table thead th {
  background: #d4dce9;
  color: #000;
  font-weight: 700;
  font-size: 15px;
}
table.sum-table td.sb-cell {
  text-align: left;
  padding-left: 4mm;
  font-weight: 700;
  font-size: 15px;
}
table.sum-table td.cnt-cell {
  font-weight: 600;
  font-size: 15px;
}
table.sum-table td.cnt-cell.has-val {
  font-weight: 700;
  color: #0b3b60;
  background: #f0fdfa;
}
table.sum-table td.row-tot {
  font-weight: 700;
  font-size: 15px;
  background: #f1f5f9;
}
table.sum-table tfoot th {
  background: #cce5ff;
  font-weight: 700;
  font-size: 15.5px;
  color: #000;
}

/* SIGNATURES */
.sign-row {
  display: flex;
  justify-content: space-between;
  margin-top: 8mm;
  padding: 0 10mm;
  font-size: 15px;
}
.sign-box {
  text-align: center;
}
.sign-box .s-title {
  font-weight: 700;
}
.sign-box .s-name {
  font-weight: 600;
  margin-top: 1.5mm;
  font-size: 14px;
}

@media print {
  body { background: #fff !important; }
  .tb { display: none !important; }
  .sum-sheet {
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
  <button class="btn-print" onclick="window.print()">🖨 एकवट तक्ता छापा / PDF</button>
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
    💡 Destination: <b>Save as PDF</b> | Layout: <b>Landscape</b> | Margins: <b>Default/None</b>.
  </span>
</div>

<div class="sum-sheet">

  <div>
    <!-- HEADER -->
    <div class="sum-header">
      <div class="title-row">● विषयनिहाय श्रेणी एकवट तक्ता (Report-06 Summary) ●</div>
      <div class="meta-row">
        <div>शाळा : <b><?= sanitize($s_name) ?></b></div>
        <div>युडायस : <b><?= sanitize($s_udise) ?></b></div>
        <div>इयत्ता : <b><?= sanitize(std_label($std)) ?></b> (पट: <b><?= $total_students_count ?></b>)</div>
        <div>सत्र : <b><?= sanitize(semester_label($semester)) ?></b></div>
        <div>सन : <b><?= sanitize($year_short) ?></b></div>
      </div>
    </div>

    <!-- SUMMARY MATRIX TABLE -->
    <table class="sum-table">
      <thead>
        <tr>
          <th style="width:6%;">अ.नं.</th>
          <th style="width:30%;">विषय</th>
          <?php foreach ($grades as $g): ?>
            <th style="width:7%;"><?= sanitize($g) ?></th>
          <?php endforeach; ?>
          <th style="width:9%;">एकूण</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sr = 0;
        foreach ($subjects as $sb):
            $sr++;
            $sbid = (int)$sb['id'];
            $row_tot = 0;
        ?>
        <tr>
          <td><?= $sr ?></td>
          <td class="sb-cell"><?= sanitize($sb['name_mr'] ?: $sb['name']) ?></td>
          <?php foreach ($grades as $g):
              $cnt = $matrix[$sbid][$g] ?? 0;
              $row_tot += $cnt;
          ?>
            <td class="cnt-cell <?= $cnt > 0 ? 'has-val' : '' ?>"><?= $cnt > 0 ? $cnt : '-' ?></td>
          <?php endforeach; ?>
          <td class="row-tot"><?= $row_tot ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2">एकूण (सर्व विषय)</th>
          <?php foreach ($grades as $g): ?>
            <th><?= $col_totals[$g] > 0 ? $col_totals[$g] : '-' ?></th>
          <?php endforeach; ?>
          <th><?= $grand_cells_total ?></th>
        </tr>
      </tfoot>
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

</div><!-- /sum-sheet -->

</body>
</html>
