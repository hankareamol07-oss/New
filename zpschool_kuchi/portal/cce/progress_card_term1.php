<?php
/**
 * प्रथम सत्र प्रगतीपत्रक (A4 Landscape Single Sheet)
 * Vijeta CCE Report-18 Port: Mid-Term / Semester 1 Official Progress Card
 * Features:
 * - Left: School details, Student bio, June-November Term 1 attendance table
 * - Right: Subjects 1-9 Term 1 Grades, 3 Key Descriptive Notes (विशेष प्रगती, आवड-छंद, सुधारणा), Signatures
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
$s_dist = $school['district'] ?: '';
$s_tal = $school['taluka'] ?: '';
$s_addr = implode(', ', array_filter([$school['address'] ?? '', $s_tal ? ('ता. ' . $s_tal) : '', $s_dist ? ('जि. ' . $s_dist) : '']));
$s_udise = $school['udise_code'] ?: '';

// Logo
$s_logo = '';
if (!empty($school['logo'])) {
    $clean_logo = ltrim($school['logo'], '/');
    if (file_exists(__DIR__ . '/../' . $clean_logo)) {
        $s_logo = APP_URL . '/' . $clean_logo;
    }
}

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
    if ($sd) $hm_name = $sd['sem1_headmaster_name'] ?: $sd['sem2_headmaster_name'];
} catch (Exception $e) {}
if (!$hm_name) $hm_name = 'मुख्याध्यापक';

// Standard 9 Subject Mappings
$std_subjects_def = [
    1 => 'प्रथम भाषा मराठी',
    2 => 'द्वितीय भाषा हिंदी',
    3 => 'तृतीय भाषा इंग्रजी',
    4 => 'गणित',
    5 => 'सामान्य विज्ञान / परिसर अभ्यास',
    6 => 'सामाजिक शास्त्रे / परिसर अभ्यास २',
    7 => 'कला',
    8 => 'कार्यानुभव',
    9 => 'शारीरिक शिक्षण व आरोग्य',
];

$mapped_subjects = [];
foreach ($std_subjects_def as $sno => $def_nm) {
    $found = null;
    foreach ($subjects as $sb) {
        if ($sb['subject_code'] === ('s' . $sno) || mb_strpos($sb['name_mr'], mb_substr($def_nm, 0, 4)) !== false) {
            $found = $sb;
            break;
        }
    }
    $mapped_subjects[$sno] = [
        'sno' => $sno,
        'name' => $def_nm,
        'id' => $found ? (int)$found['id'] : 0,
    ];
}

// Data loaders
$marks = [];
$notes = [];
$attendance = [];
if (!empty($students)) {
    $sids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($sids), '?'));

    // Marks for Semester 1
    $stmt = $db->prepare("SELECT student_id, subject_id, grade, percent, grand_total FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = 1 AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $marks[(int)$r['student_id']][(int)$r['subject_id']] = $r;
    }

    // Notes for Semester 1
    $stmt = $db->prepare("SELECT student_id, slot_no, note_text FROM cce_student_notes WHERE school_id = ? AND academic_year = ? AND semester = 1 AND student_id IN ($in) AND note_text <> ''");
    $stmt->execute(array_merge([$school_id, $year], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $notes[(int)$r['student_id']][(int)$r['slot_no']] = $r['note_text'];
    }

    // Attendance
    $stmt = $db->prepare("SELECT * FROM attendance WHERE academic_year = ? AND student_id IN ($in)");
    $stmt->execute(array_merge([$year], $sids));
    foreach ($stmt->fetchAll() as $r) {
        $attendance[(int)$r['student_id']][(int)$r['month']] = [
            'working_days' => $r['working_days'] ?? 0,
            'present_days' => $r['days_present'] ?? ($r['present_days'] ?? 0)
        ];
    }
}

// Sem 1 Months: June to November (6 to 11)
$sem1_months = [
    6 => 'जून',
    7 => 'जुलै',
    8 => 'ऑग.',
    9 => 'सप्टें.',
    10 => 'ऑक्टो.',
    11 => 'नोव्हें.'
];

if (!function_exists('calc_age_mr')) {
    function calc_age_mr($dob) {
        if (!$dob || $dob === '0000-00-00') return '—';
        try {
            $diff = (new DateTime($dob))->diff(new DateTime());
            return $diff->y . ' वर्षे ' . $diff->m . ' महिने';
        } catch (Exception $e) {
            return '—';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>प्रथम सत्र प्रगतीपत्रक - इयत्ता <?= sanitize(std_label($std)) ?> - <?= sanitize($year) ?></title>
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
  margin: 7mm 8mm;
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
  font-size: 13.5px;
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

/* SHEET (A4 Landscape: 281mm × 196mm printable area) */
.pc-sheet {
  width: 281mm;
  height: 196mm;
  margin: 14px auto;
  background: #ffffff;
  border: 2px solid #5D9BC0;
  border-radius: 6px;
  padding: 5mm 6mm;
  box-shadow: 0 3px 12px rgba(0,0,0,0.15);
  page-break-after: always;
  break-after: page;
  page-break-inside: avoid;
  break-inside: avoid;
  overflow: hidden;
  display: flex;
  gap: 5mm;
  justify-content: space-between;
}
.pc-sheet:last-child {
  page-break-after: auto;
  break-after: auto;
}

/* COLUMNS */
.pc-col-left {
  width: 49%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.pc-col-right {
  width: 49%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

/* SCHOOL HEADER */
.sch-header {
  text-align: center;
  border-bottom: 1.5px solid #00bcd4;
  padding-bottom: 2mm;
  margin-bottom: 2mm;
}
.sch-name {
  font-size: 20px;
  font-weight: 700;
  color: #0b3b60;
  line-height: 1.2;
}
.sch-meta {
  font-size: 13px;
  color: #333;
  margin-top: 1px;
}
.card-title-pill {
  display: inline-block;
  background: #fce4ec;
  border: 1px solid #f06292;
  color: #c2185b;
  font-size: 17px;
  font-weight: 700;
  padding: 2px 14px;
  border-radius: 14px;
  margin: 2mm 0;
  letter-spacing: 0.3px;
}

/* STUDENT BIO TABLE */
.bio-box {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 5px;
  padding: 2.5mm;
  margin-bottom: 2.5mm;
}
table.bio-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
table.bio-table td {
  padding: 1.5px 2px;
  vertical-align: top;
}
table.bio-table td.b-lbl {
  color: #b71c1c;
  font-weight: 700;
  white-space: nowrap;
  width: 25%;
}
table.bio-table td.b-sep {
  width: 3%;
  font-weight: 700;
  text-align: center;
}
table.bio-table td.b-val {
  color: #000;
  font-weight: 700;
  width: 72%;
}

/* ATTENDANCE TABLE */
.sec-title {
  font-size: 14.5px;
  font-weight: 700;
  color: #0288d1;
  margin-bottom: 1.5mm;
  display: flex;
  align-items: center;
  gap: 4px;
}
table.att-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #00bcd4;
  text-align: center;
  font-size: 13px;
}
table.att-table th, table.att-table td {
  border: 1.2px solid #00bcd4;
  padding: 1.5px 1px;
}
table.att-table thead th {
  background: #e0f7fa;
  color: #006064;
  font-weight: 700;
  font-size: 12.5px;
}
table.att-table td.lbl {
  font-weight: 700;
  background: #fafafa;
  text-align: left;
  padding-left: 3px;
  font-size: 12px;
}

/* RIGHT COLUMN: SUBJECT & NOTES TABLE */
table.subj-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #00bcd4;
  font-size: 13px;
}
table.subj-table th, table.subj-table td {
  border: 1.2px solid #00bcd4;
  padding: 1.8px 3px;
}
table.subj-table thead th {
  background: #6fe0ee;
  color: #000;
  font-weight: 700;
  text-align: center;
  font-size: 13.5px;
  height: 7mm;
}
table.subj-table td.c-no {
  text-align: center;
  font-weight: 700;
  width: 7%;
}
table.subj-table td.c-name {
  font-weight: 700;
  width: 48%;
}
table.subj-table td.c-grd {
  text-align: center;
  font-weight: 700;
  font-size: 14.5px;
  color: #000;
  width: 12%;
  background: #f0fdfa;
}
table.subj-table td.c-note-title {
  background: #EFF8F4;
  color: #167A5B;
  font-weight: 700;
  font-size: 13px;
  border-bottom: none;
}
table.subj-table td.c-note-text {
  vertical-align: top;
  font-size: 12.5px;
  line-height: 1.25;
  color: #111;
  background: #ffffff;
  border-top: none;
}

/* SIGNATURES ROW */
.sign-row {
  display: flex;
  justify-content: space-between;
  margin-top: 3mm;
  padding: 0 4mm;
}
.sign-box {
  text-align: center;
  font-size: 13.5px;
}
.sign-box .s-title {
  font-weight: 700;
  color: #000;
}
.sign-box .s-name {
  font-weight: 600;
  color: #222;
  margin-top: 1mm;
  font-size: 12.5px;
}

@media print {
  body { background: #fff !important; }
  .tb { display: none !important; }
  .pc-sheet {
    width: 100% !important;
    height: auto !important;
    max-height: 195mm !important;
    margin: 0 !important;
    box-shadow: none !important;
  }
}
</style>
</head>
<body>

<!-- TOP INTERACTIVE TOOLBAR -->
<div class="tb">
  <button class="btn-print" onclick="window.print()">🖨 छापा / PDF सेव्ह करा</button>
  <div class="tb-sep"></div>

  <!-- Standard Switcher -->
  <select onchange="location.href='?std=' + this.value + '&all=1'">
    <?php foreach ($standards as $st_num => $st_lbl): ?>
      <option value="<?= (int)$st_num ?>" <?= (int)$st_num === $std ? 'selected' : '' ?>>
        इयत्ता <?= sanitize($st_lbl) ?>
      </option>
    <?php endforeach; ?>
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
    💡 Destination: <b>Save as PDF</b> | Layout: <b>Landscape</b> | Margins: <b>None</b>.
  </span>
</div>

<?php
foreach ($students as $stu):
    $sid = (int)$stu['id'];
    $s_name_disp = $stu['name_mr'] ?: $stu['name'];
    $s_roll = $stu['roll_no'] ?: '—';
    $s_reg = $stu['registration_no'] ?: ($stu['gr_no'] ?: '—');
    $s_saral = ($stu['student_id_no'] ?? '') ?: (($stu['saral_id'] ?? '') ?: '—');
    $s_sec = $stu['section'] ?? '—';
    $s_dob = ($stu['date_of_birth'] ?? $stu['dob'] ?? '');
    $s_dob_fmt = ($s_dob && $s_dob !== '0000-00-00') ? date('d-m-Y', strtotime($s_dob)) : '—';
    $s_age = calc_age_mr($s_dob);
    $s_mother = ($stu['mother_name'] ?? '') ?: '—';
    $s_father = ($stu['father_name'] ?? '') ?: (($stu['parent_name'] ?? '') ?: '—');
    $s_res_addr = ($stu['address'] ?? '') ?: '—';
    $st_marks = $marks[$sid] ?? [];
    $st_notes = $notes[$sid] ?? [];
    $st_att = $attendance[$sid] ?? [];

    // Calculate attendance totals for Sem 1
    $tot_working = 0;
    $tot_present = 0;
    foreach ($sem1_months as $m_num => $m_lbl) {
        $tot_working += (int)($st_att[$m_num]['working_days'] ?? 0);
        $tot_present += (int)($st_att[$m_num]['present_days'] ?? 0);
    }
    $att_pct = $tot_working > 0 ? round(($tot_present / $tot_working) * 100, 1) . '%' : '—';
?>
<div class="pc-sheet">

  <!-- LEFT COLUMN -->
  <div class="pc-col-left">
    <div>
      <div class="sch-header">
        <div class="sch-name"><?= sanitize($s_name) ?></div>
        <div class="sch-meta">
          <?= sanitize($s_addr) ?><?= $s_udise ? (' · UDISE: ' . sanitize($s_udise)) : '' ?>
        </div>
        <div class="card-title-pill">● प्रथम सत्र प्रगतीपत्रक ●</div>
        <div style="font-weight:700; font-size:14px; color:#333;">शैक्षणिक वर्ष : <?= sanitize($year_short) ?></div>
      </div>

      <!-- Student Bio -->
      <div class="bio-box">
        <table class="bio-table">
          <tr>
            <td class="b-lbl">विद्यार्थ्याचे नाव</td>
            <td class="b-sep">:</td>
            <td class="b-val" style="font-size:15px; color:#0b3b60;"><?= sanitize($s_name_disp) ?></td>
          </tr>
          <tr>
            <td class="b-lbl">सरल आयडी</td>
            <td class="b-sep">:</td>
            <td class="b-val"><?= sanitize($s_saral) ?></td>
          </tr>
          <tr>
            <td class="b-lbl">इयत्ता व तुकडी</td>
            <td class="b-sep">:</td>
            <td class="b-val">
              इयत्ता <?= sanitize(std_label($std)) ?> &nbsp;&nbsp;|&nbsp;&nbsp; तुकडी : <?= sanitize($s_sec) ?>
              &nbsp;&nbsp;|&nbsp;&nbsp; हजेरी क्र. : <?= sanitize($s_roll) ?>
            </td>
          </tr>
          <tr>
            <td class="b-lbl">जनरल रजिस्टर नं.</td>
            <td class="b-sep">:</td>
            <td class="b-val">
              <?= sanitize($s_reg) ?> &nbsp;&nbsp;|&nbsp;&nbsp; माध्यम : मराठी
            </td>
          </tr>
          <tr>
            <td class="b-lbl">जन्मतारीख</td>
            <td class="b-sep">:</td>
            <td class="b-val">
              <?= sanitize($s_dob_fmt) ?> &nbsp;&nbsp;(वय: <?= sanitize($s_age) ?>)
            </td>
          </tr>
          <tr>
            <td class="b-lbl">आईचे नाव</td>
            <td class="b-sep">:</td>
            <td class="b-val"><?= sanitize($s_mother) ?></td>
          </tr>
          <tr>
            <td class="b-lbl">वडिलांचे नाव</td>
            <td class="b-sep">:</td>
            <td class="b-val"><?= sanitize($s_father) ?></td>
          </tr>
          <tr>
            <td class="b-lbl">पत्ता</td>
            <td class="b-sep">:</td>
            <td class="b-val" style="font-size:12.5px;"><?= sanitize($s_res_addr) ?></td>
          </tr>
        </table>
      </div>

      <!-- Attendance Table -->
      <div class="sec-title">📅 प्रथम सत्र उपस्थिती तक्ता (जून ते नोव्हेंबर)</div>
      <table class="att-table">
        <thead>
          <tr>
            <th style="width:28%;">तपशील</th>
            <?php foreach ($sem1_months as $m_num => $m_lbl): ?>
              <th><?= sanitize($m_lbl) ?></th>
            <?php endforeach; ?>
            <th>एकूण</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="lbl">कामाचे दिवस</td>
            <?php foreach ($sem1_months as $m_num => $m_lbl): ?>
              <td><?= isset($st_att[$m_num]) ? (int)$st_att[$m_num]['working_days'] : '-' ?></td>
            <?php endforeach; ?>
            <td style="font-weight:700; background:#f1f5f9;"><?= $tot_working ?: '-' ?></td>
          </tr>
          <tr>
            <td class="lbl">हजर दिवस</td>
            <?php foreach ($sem1_months as $m_num => $m_lbl): ?>
              <td><?= isset($st_att[$m_num]) ? (int)$st_att[$m_num]['present_days'] : '-' ?></td>
            <?php endforeach; ?>
            <td style="font-weight:700; background:#f1f5f9;"><?= $tot_present ?: '-' ?></td>
          </tr>
        </tbody>
      </table>
      <div style="font-size:12.5px; font-weight:700; margin-top:2mm; text-align:right; color:#0b3b60;">
        सत्र १ उपस्थिती टक्केवारी : <?= sanitize($att_pct) ?>
      </div>
    </div>

    <!-- Instructions / Note -->
    <div style="font-size:11.5px; color:#555; background:#f8fafc; border:1px dashed #cbd5e1; padding:2mm; border-radius:4px; line-height:1.2;">
      📌 <b>पालकांसाठी सूचना :</b> सदर प्रगतीपत्रक प्रथम सत्रातील सातत्यपूर्ण सर्वंकष मूल्यमापनावर आधारित असून विद्यार्थ्याच्या प्रगतीबाबत पालकांनी स्वाक्षरी करून परत पाठवावे.
    </div>
  </div><!-- /Left Col -->

  <!-- RIGHT COLUMN -->
  <div class="pc-col-right">
    <div>
      <div class="sec-title" style="justify-content:center; font-size:16px; margin-bottom:2mm;">
        ● प्रथम सत्र संकलित मूल्यमापन श्रेणी व नोंदी ●
      </div>

      <table class="subj-table">
        <thead>
          <tr>
            <th>अ.नं.</th>
            <th>विषय</th>
            <th>श्रेणी</th>
            <th style="width:33%;">वर्णनात्मक नोंदी</th>
          </tr>
        </thead>
        <tbody>
          <!-- Subject 1 -->
          <?php
          $sb1_id = $mapped_subjects[1]['id'];
          $grd1 = ($sb1_id && isset($st_marks[$sb1_id])) ? ($st_marks[$sb1_id]['grade'] ?: '-') : '-';
          ?>
          <tr>
            <td class="c-no">1</td>
            <td class="c-name"><?= sanitize($mapped_subjects[1]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd1) ?></td>
            <td class="c-note-title">विशेष प्रगती :</td>
          </tr>

          <!-- Subject 2 -->
          <?php
          $sb2_id = $mapped_subjects[2]['id'];
          $grd2 = ($sb2_id && isset($st_marks[$sb2_id])) ? ($st_marks[$sb2_id]['grade'] ?: '-') : '-';
          $txt_sp = $st_notes[10] ?? '—';
          ?>
          <tr>
            <td class="c-no">2</td>
            <td class="c-name"><?= sanitize($mapped_subjects[2]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd2) ?></td>
            <td rowspan="2" class="c-note-text"><?= sanitize($txt_sp) ?></td>
          </tr>

          <!-- Subject 3 -->
          <?php
          $sb3_id = $mapped_subjects[3]['id'];
          $grd3 = ($sb3_id && isset($st_marks[$sb3_id])) ? ($st_marks[$sb3_id]['grade'] ?: '-') : '-';
          ?>
          <tr>
            <td class="c-no">3</td>
            <td class="c-name"><?= sanitize($mapped_subjects[3]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd3) ?></td>
          </tr>

          <!-- Subject 4 -->
          <?php
          $sb4_id = $mapped_subjects[4]['id'];
          $grd4 = ($sb4_id && isset($st_marks[$sb4_id])) ? ($st_marks[$sb4_id]['grade'] ?: '-') : '-';
          ?>
          <tr>
            <td class="c-no">4</td>
            <td class="c-name"><?= sanitize($mapped_subjects[4]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd4) ?></td>
            <td class="c-note-title">आवड, छंद :</td>
          </tr>

          <!-- Subject 5 -->
          <?php
          $sb5_id = $mapped_subjects[5]['id'];
          $grd5 = ($sb5_id && isset($st_marks[$sb5_id])) ? ($st_marks[$sb5_id]['grade'] ?: '-') : '-';
          $txt_hb = $st_notes[11] ?? '—';
          ?>
          <tr>
            <td class="c-no">5</td>
            <td class="c-name"><?= sanitize($mapped_subjects[5]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd5) ?></td>
            <td rowspan="2" class="c-note-text"><?= sanitize($txt_hb) ?></td>
          </tr>

          <!-- Subject 6 -->
          <?php
          $sb6_id = $mapped_subjects[6]['id'];
          $grd6 = ($sb6_id && isset($st_marks[$sb6_id])) ? ($st_marks[$sb6_id]['grade'] ?: '-') : '-';
          ?>
          <tr>
            <td class="c-no">6</td>
            <td class="c-name"><?= sanitize($mapped_subjects[6]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd6) ?></td>
          </tr>

          <!-- Subject 7 -->
          <?php
          $sb7_id = $mapped_subjects[7]['id'];
          $grd7 = ($sb7_id && isset($st_marks[$sb7_id])) ? ($st_marks[$sb7_id]['grade'] ?: '-') : '-';
          ?>
          <tr>
            <td class="c-no">7</td>
            <td class="c-name"><?= sanitize($mapped_subjects[7]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd7) ?></td>
            <td class="c-note-title">सुधारणा आवश्यक :</td>
          </tr>

          <!-- Subject 8 -->
          <?php
          $sb8_id = $mapped_subjects[8]['id'];
          $grd8 = ($sb8_id && isset($st_marks[$sb8_id])) ? ($st_marks[$sb8_id]['grade'] ?: '-') : '-';
          $txt_im = $st_notes[12] ?? '—';
          ?>
          <tr>
            <td class="c-no">8</td>
            <td class="c-name"><?= sanitize($mapped_subjects[8]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd8) ?></td>
            <td rowspan="2" class="c-note-text"><?= sanitize($txt_im) ?></td>
          </tr>

          <!-- Subject 9 -->
          <?php
          $sb9_id = $mapped_subjects[9]['id'];
          $grd9 = ($sb9_id && isset($st_marks[$sb9_id])) ? ($st_marks[$sb9_id]['grade'] ?: '-') : '-';
          ?>
          <tr>
            <td class="c-no">9</td>
            <td class="c-name"><?= sanitize($mapped_subjects[9]['name']) ?></td>
            <td class="c-grd"><?= sanitize($grd9) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Signatures -->
    <div class="sign-row">
      <div class="sign-box">
        <div class="s-title">वर्गशिक्षक स्वाक्षरी</div>
        <div class="s-name"><?= sanitize($teacher_name) ?></div>
      </div>
      <div class="sign-box">
        <div class="s-title">पालक स्वाक्षरी</div>
        <div class="s-name">स्वाक्षरी / दिनांक</div>
      </div>
      <div class="sign-box">
        <div class="s-title">मुख्याध्यापक स्वाक्षरी</div>
        <div class="s-name"><?= sanitize($hm_name) ?></div>
      </div>
    </div>

  </div><!-- /Right Col -->

</div><!-- /pc-sheet -->
<?php endforeach; ?>

</body>
</html>
