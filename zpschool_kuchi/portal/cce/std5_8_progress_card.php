<?php
/**
 * इयत्ता ५ वी व ८ वी वार्षिक परीक्षा गुणपत्रक (RTE Annual Marksheet)
 * Vijeta CCE Report-12 Port: Class 5 & 8 Special RTE Annual Exam Marksheet
 * Features:
 * - 35% Minimum passing criteria
 * - Grace Marks (सवलतीचे गुण) calculation & display
 * - Pass / Re-exam (उत्तीर्ण / पुनर्परीक्षा) status
 * - Co-scholastic subjects grade display
 * - Final percentage, Result date & Official Signatures
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

// Default standard 5 (or 8)
$std = (int)($_GET['std'] ?? 5);
if (!in_array($std, [5, 8], true) && isset($standards[5])) {
    $std = 5;
} elseif (!in_array($std, [5, 8], true)) {
    $std = (int)array_key_first($standards);
}

$all = !empty($_GET['all']) || !isset($_GET['student_id']);
$sid_get = (int)($_GET['student_id'] ?? 0);

// Ensure subjects
$subjects = cce_subjects($school_id, $std);
if (empty($subjects)) {
    cce_seed_subjects($school_id, $std);
    $subjects = cce_subjects($school_id, $std);
}

// Fetch students
$all_students_for_std = cce_students($school_id, $std);
$students = $all_students_for_std;
if (!$all && $sid_get > 0) {
    $students = array_filter($all_students_for_std, fn($s) => (int)$s['id'] === $sid_get);
}
if (empty($students) && !empty($all_students_for_std)) {
    $students = [$all_students_for_std[0]];
}

// Short Year
$year_short = $year;
if (preg_match('/^(\d{4})-(\d{4})$/', $year, $ym)) {
    $year_short = $ym[1] . '-' . substr($ym[2], 2);
}

// School details
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद प्राथमिक शाळा');
$s_udise = $school['udise_code'] ?: '';

// Section
$section = trim((string)($_GET['section'] ?? ($students ? ($students[array_key_first($students)]['section'] ?? '') : '')));

// Teachers & HM
$teacher_name = cce_class_teacher_name($school_id, $std, $section);
if (!$teacher_name) $teacher_name = 'वर्गशिक्षक';

$hm_name = '';
$res_date = '';
try {
    $stt = $db->prepare("SELECT sem2_headmaster_name, result_date FROM school_defaults WHERE school_id = ?");
    $stt->execute([$school_id]);
    $sd = $stt->fetch();
    if ($sd) {
        $hm_name = $sd['sem2_headmaster_name'];
        if (!empty($sd['result_date'])) {
            $res_date = date('d-m-Y', strtotime($sd['result_date']));
        }
    }
} catch (Exception $e) {}
if (!$hm_name) $hm_name = 'मुख्याध्यापक';
if (!$res_date) {
    $y_parts = explode('-', $year);
    $y2 = isset($y_parts[1]) ? ((strlen($y_parts[1]) === 2) ? ('20' . $y_parts[1]) : $y_parts[1]) : date('Y');
    $res_date = '01-05-' . $y2;
}

// Load Sem 2 Marks (Annual Evaluation)
$marks = [];
if (!empty($students)) {
    $sids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($sids), '?'));

    // We take Semester 2 (or Annual Grand Total)
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = 2 AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $marks[(int)$r['student_id']][(int)$r['subject_id']] = $r;
    }
}

// Academic subjects vs Co-scholastic subjects
$acad_subs = [];
$cosch_subs = [];
foreach ($subjects as $sb) {
    if (!empty($sb['is_coscholastic']) && (int)$sb['is_coscholastic'] === 1) {
        $cosch_subs[] = $sb;
    } else {
        $acad_subs[] = $sb;
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>इयत्ता <?= sanitize(std_label($std)) ?> - वार्षिक परीक्षा गुणपत्रक (RTE)</title>
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
  font-size: 15px;
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

/* MARKSHEET CARD (A4 Portrait) */
.rte-card {
  width: 190mm;
  min-height: 270mm;
  margin: 16px auto;
  background: #ffffff;
  border: 2.5px solid #000;
  border-radius: 8px;
  padding: 8mm 10mm;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  page-break-after: always;
  break-after: page;
  page-break-inside: avoid;
  break-inside: avoid;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.rte-card:last-child {
  page-break-after: auto;
  break-after: auto;
}

/* HEADERS */
.rte-header {
  text-align: center;
  margin-bottom: 3mm;
}
.rte-main-title {
  color: #d81b60;
  font-size: 26px;
  font-weight: 700;
  letter-spacing: 0.5px;
}
.rte-sub-title {
  font-size: 17px;
  font-weight: 700;
  color: #111;
  margin-top: 1mm;
}
.meta-box {
  margin-top: 3mm;
  border: 1px solid #999;
  background: #fafafa;
  border-radius: 4px;
  padding: 2.5mm 4mm;
  font-size: 15px;
  line-height: 1.45;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 1.5px;
}
.meta-row b {
  color: #000;
  font-weight: 700;
}

/* MARKS TABLE */
table.rte-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.5px solid #000;
  margin-top: 4mm;
  font-size: 15px;
}
table.rte-table th, table.rte-table td {
  border: 1px solid #444;
  padding: 3mm 2.5mm;
  text-align: center;
  vertical-align: middle;
}
table.rte-table thead th {
  background: #d4e6f1;
  color: #000;
  font-weight: 700;
  font-size: 15px;
  line-height: 1.2;
}
table.rte-table td.sb-name {
  text-align: left;
  font-weight: 700;
  padding-left: 4mm;
}
table.rte-table td.pass-lbl {
  color: #1b5e20;
  font-weight: 700;
}
table.rte-table td.fail-lbl {
  color: #b71c1c;
  font-weight: 700;
}
table.rte-table td.grace-val {
  color: #167A5B;
  font-weight: 700;
}

/* SUMMARY ROWS */
.summary-box {
  margin-top: 4mm;
  border: 1.5px solid #000;
  background: #fdfefe;
  padding: 3mm 4mm;
  border-radius: 4px;
  font-size: 16px;
  line-height: 1.5;
}
.summary-row {
  display: flex;
  justify-content: space-between;
}
.summary-row b {
  font-weight: 700;
  color: #000;
}

/* SIGNATURES */
.sign-row {
  display: flex;
  justify-content: space-between;
  margin-top: 8mm;
  padding: 0 8mm;
}
.sign-box {
  text-align: center;
  font-size: 15px;
}
.sign-box .s-title {
  font-weight: 700;
  color: #000;
}
.sign-box .s-name {
  font-weight: 600;
  color: #222;
  margin-top: 2mm;
  font-size: 14px;
}

@media print {
  body { background: #fff !important; }
  .tb { display: none !important; }
  .rte-card {
    width: 100% !important;
    min-height: auto !important;
    margin: 0 !important;
    box-shadow: none !important;
    border: 2px solid #000 !important;
  }
}
</style>
</head>
<body>

<!-- TOOLBAR -->
<div class="tb">
  <button class="btn-print" onclick="window.print()">🖨 गुणपत्रक छापा / PDF</button>
  <div class="tb-sep"></div>

  <!-- Standard Switcher (5 or 8) -->
  <select onchange="location.href='?std=' + this.value + '&all=1'">
    <?php foreach ($standards as $st_num => $st_lbl): ?>
      <?php if (in_array((int)$st_num, [5, 8], true)): ?>
        <option value="<?= (int)$st_num ?>" <?= (int)$st_num === $std ? 'selected' : '' ?>>
          इयत्ता <?= sanitize($st_lbl) ?> (RTE)
        </option>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!isset($standards[5]) && !isset($standards[8])): ?>
      <?php foreach ($standards as $st_num => $st_lbl): ?>
        <option value="<?= (int)$st_num ?>" <?= (int)$st_num === $std ? 'selected' : '' ?>>
          इयत्ता <?= sanitize($st_lbl) ?>
        </option>
      <?php endforeach; ?>
    <?php endif; ?>
  </select>

  <!-- Student Switcher -->
  <select onchange="location.href=this.value">
    <option value="?std=<?= $std ?>&all=1" <?= $all ? 'selected' : '' ?>>
      👥 सर्व विद्यार्थी (<?= count($all_students_for_std) ?>)
    </option>
    <?php foreach ($all_students_for_std as $st_opt): ?>
      <option value="?std=<?= $std ?>&student_id=<?= (int)$st_opt['id'] ?>" <?= (!$all && $sid_get === (int)$st_opt['id']) ? 'selected' : '' ?>>
        <?= sanitize($st_opt['roll_no'] ? ($st_opt['roll_no'] . '. ') : '') ?><?= sanitize($st_opt['name_mr'] ?: $st_opt['name']) ?>
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
foreach ($students as $stu):
    $sid = (int)$stu['id'];
    $st_name = $stu['name_mr'] ?: $stu['name'];
    $st_roll = $stu['roll_no'] ?: '—';
    $st_sec = $stu['section'] ?: '—';
    $st_marks = $marks[$sid] ?? [];

    // Calculate Grace & Status for Academic Subjects
    $total_max = 0;
    $total_got = 0;
    $academic_rows = [];
    $student_failed = false;
    $grace_pool = 15; // Max 15 marks grace under RTE guidelines

    foreach ($acad_subs as $s_idx => $sb) {
        $sbid = (int)$sb['id'];
        $mk = $st_marks[$sbid] ?? null;
        $max_m = (float)($sb['se_total_max'] ?: 50);
        $min_pass = round($max_m * 0.35); // 35% passing
        $got = $mk !== null && $mk['se_total'] !== null ? (float)$mk['se_total'] : null;

        $grace = 0;
        $status = 'उत्तीर्ण';
        if ($got !== null) {
            $total_max += $max_m;
            $total_got += $got;
            if ($got < $min_pass) {
                $deficit = $min_pass - $got;
                if ($deficit <= 5 && $grace_pool >= $deficit) {
                    $grace = $deficit;
                    $grace_pool -= $deficit;
                    $status = 'सवलतीसह उत्तीर्ण';
                } else {
                    $status = 'पुनर्परीक्षा';
                    $student_failed = true;
                }
            }
        } else {
            $status = '-';
        }

        $academic_rows[] = [
            'sno' => $s_idx + 1,
            'name' => $sb['name_mr'] ?: $sb['name'],
            'min_pass' => $min_pass,
            'max_m' => $max_m,
            'got' => $got,
            'grace' => $grace,
            'status' => $status
        ];
    }

    $pct = $total_max > 0 ? round(($total_got / $total_max) * 100, 2) : 0;
    $final_result = $student_failed ? 'पुनर्परीक्षा' : 'उत्तीर्ण';
?>
<div class="rte-card">

  <div>
    <!-- HEADER -->
    <div class="rte-header">
      <div class="rte-main-title">● वार्षिक परीक्षा गुणपत्रक ●</div>
      <div class="rte-sub-title">इयत्ता ५ वी व ८ वी वार्षिक परीक्षा (RTE मूल्यमापन)</div>
      
      <div class="meta-box">
        <div class="meta-row">
          <div>शाळेचे नाव : <b><?= sanitize($s_name) ?></b></div>
          <div>युडायस : <b><?= sanitize($s_udise) ?></b></div>
        </div>
        <div class="meta-row">
          <div>विद्यार्थ्याचे नाव : <b style="font-size:16px; color:#0b3b60;"><?= sanitize($st_name) ?></b></div>
          <div>सन : <b><?= sanitize($year_short) ?></b></div>
        </div>
        <div class="meta-row">
          <div>हजेरी क्रमांक : <b><?= sanitize($st_roll) ?></b></div>
          <div>इयत्ता : <b><?= sanitize(std_label($std)) ?></b> &nbsp;|&nbsp; तुकडी : <b><?= sanitize($st_sec) ?></b></div>
        </div>
      </div>
    </div>

    <!-- TABLE -->
    <table class="rte-table">
      <thead>
        <tr>
          <th style="width:8%;">अ.नं.</th>
          <th style="width:36%;">विषय</th>
          <th style="width:14%;">किमान<br>आवश्यक गुण</th>
          <th style="width:14%;">प्राप्त गुण</th>
          <th style="width:14%;">सवलतीचे<br>गुण (Grace)</th>
          <th style="width:14%;">शेरा</th>
        </tr>
      </thead>
      <tbody>
        <!-- Academic Subjects -->
        <?php foreach ($academic_rows as $row): ?>
        <tr>
          <td><?= $row['sno'] ?></td>
          <td class="sb-name"><?= sanitize($row['name']) ?></td>
          <td><?= $row['min_pass'] ?></td>
          <td style="font-weight:700;"><?= $row['got'] !== null ? $row['got'] : '-' ?></td>
          <td class="grace-val"><?= $row['grace'] > 0 ? ('+' . $row['grace']) : '-' ?></td>
          <td class="<?= $row['status'] === 'पुनर्परीक्षा' ? 'fail-lbl' : 'pass-lbl' ?>">
            <?= sanitize($row['status']) ?>
          </td>
        </tr>
        <?php endforeach; ?>

        <!-- Co-scholastic Subjects -->
        <?php
        $cosch_start = count($academic_rows);
        foreach ($cosch_subs as $c_idx => $csb):
            $csbid = (int)$csb['id'];
            $cmk = $st_marks[$csbid] ?? null;
            $cgrd = $cmk ? ($cmk['grade'] ?: '-') : '-';
        ?>
        <tr>
          <td><?= $cosch_start + $c_idx + 1 ?></td>
          <td class="sb-name"><?= sanitize($csb['name_mr'] ?: $csb['name']) ?></td>
          <td>श्रेणी</td>
          <td colspan="3" style="font-weight:700; font-size:16px; background:#f8fafc; color:#0b3b60;">
            श्रेणी : <?= sanitize($cgrd) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- SUMMARY -->
    <div class="summary-box">
      <div class="summary-row">
        <div>एकूण प्राप्त गुण : <b><?= $total_got ?> / <?= $total_max ?></b></div>
        <div>शेकडा गुण : <b><?= $pct ?>%</b></div>
      </div>
      <div class="summary-row" style="margin-top:2mm;">
        <div>निकाल दिनांक : <b><?= sanitize($res_date) ?></b></div>
        <div>अंतिम निकाल शेरा : <b style="font-size:18px; color:<?= $final_result === 'पुनर्परीक्षा' ? '#b71c1c' : '#1b5e20' ?>;"><?= sanitize($final_result) ?></b></div>
      </div>
    </div>
  </div>

  <!-- OFFICIAL SIGNATURES -->
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

</div><!-- /rte-card -->
<?php endforeach; ?>

</body>
</html>
