<?php
/**
 * सातत्यपूर्ण मूल्यमापन नोंदवही (Class Record Register)
 * Official Maharashtra CCE 3-Page Register Format:
 * Page 1: Cover Page (कव्हर पान)
 * Page 2: Index (अनुक्रमणिका)
 * Page 3+: Student Evaluation Page (प्रत्येक विद्यार्थ्याचे मूल्यमापन पान)
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
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    if (!in_array($std, $tScope['stds'], true)) {
        $std = (int)$tScope['stds'][0];
    }
}
$semester = (int)($_GET['semester'] ?? 1) === 2 ? 2 : 1;
$view = $_GET['view'] ?? 'all'; // all, cover, index, student
$sid_get = (int)($_GET['student_id'] ?? 0);

// Cover Graphic toggle (1 = Show Educational Graphic, 0 = Clean Title Only)
$graphic_param = $_GET['graphic'] ?? ($_SESSION['cce_cover_graphic'] ?? '1');
if (isset($_GET['graphic'])) {
    $_SESSION['cce_cover_graphic'] = $_GET['graphic'];
}
$show_graphic = ($graphic_param === '1' || $graphic_param === 'true');

if (!function_exists('cce_nondvahi_cover_graphic_svg')) {
    function cce_nondvahi_cover_graphic_svg($size = 310) {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 360" width="{$size}" height="{$size}">
  <circle cx="180" cy="180" r="168" fill="#EFF8F4"/>
  <circle cx="180" cy="180" r="150" fill="none" stroke="#CFE7DD" stroke-width="2"/>
  <circle cx="180" cy="180" r="132" fill="none" stroke="#E4F2EC" stroke-width="10"/>
  <g stroke="#E9A23B" stroke-width="6" stroke-linecap="round"><line x1="180" y1="52" x2="180" y2="74"/><line x1="124" y1="70" x2="135" y2="89"/><line x1="236" y1="70" x2="225" y2="89"/></g>
  <path d="M180 88 Q186 120 216 125 Q186 130 180 162 Q174 130 144 125 Q174 120 180 88Z" fill="#E9A23B"/>
  <path d="M180 266 C150 242 110 233 72 235 V146 C110 144 150 153 180 176Z" fill="#167A5B"/>
  <path d="M180 266 C210 242 250 233 288 235 V146 C250 144 210 153 180 176Z" fill="#0B3D2E"/>
  <path d="M180 254 C152 233 116 226 84 228 V157 C116 155 152 163 180 184Z" fill="#FFFFFF"/>
  <path d="M180 254 C208 233 244 226 276 228 V157 C244 155 208 163 180 184Z" fill="#F4FAF7"/>
  <path d="M180 184 V254" stroke="#0B3D2E" stroke-width="3"/>
  <text x="102" y="190" font-family="'Mukta',sans-serif" font-size="18" font-weight="800" fill="#167A5B">अ आ इ</text>
  <text x="200" y="190" font-family="'Mukta',sans-serif" font-size="17" font-weight="800" fill="#0B3D2E">A B C</text>
  <g stroke="#9FC3B5" stroke-width="3" stroke-linecap="round" fill="none"><path d="M98 206 C122 205 148 211 168 222"/><path d="M98 222 C122 221 148 227 168 238"/><path d="M262 206 C238 205 212 211 192 222"/><path d="M262 222 C238 221 212 227 192 238"/></g>
  <rect x="112" y="274" width="136" height="15" rx="5" fill="#E9A23B"/>
  <rect x="98" y="292" width="164" height="15" rx="5" fill="#167A5B"/>
  <rect x="104" y="296" width="14" height="7" rx="2" fill="#CFE7DD"/>
</svg>
SVG;
    }
}

// Ensure subjects exist for standard
$subjects = cce_subjects($school_id, $std);
if (empty($subjects)) {
    cce_seed_subjects($school_id, $std);
    $subjects = cce_subjects($school_id, $std);
}

// Fetch students
$all_students_for_std = cce_students($school_id, $std);
$students = $all_students_for_std;
if ($sid_get > 0) {
    $students = array_filter($all_students_for_std, fn($s) => (int)$s['id'] === $sid_get);
}

// Academic year short label (e.g. 2025-2026 -> 2025-26)
$year_short = $year;
if (preg_match('/^(\d{4})-(\d{4})$/', $year, $ym)) {
    $year_short = $ym[1] . '-' . substr($ym[2], 2);
}

// School details
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद प्राथमिक शाळा');
$s_dist = $school['district'] ?: 'सांगली';
$s_tal = $school['taluka'] ?: '';
$s_udise = preg_replace('/\D/', '', (string)($school['udise_code'] ?: '27350302306'));
if (strlen($s_udise) < 11) {
    $s_udise = str_pad($s_udise, 11, '0', STR_PAD_LEFT);
}
$udise_digits = str_split($s_udise);

// Division
$section = $students ? ($students[array_key_first($students)]['section'] ?? '') : '';

// Active teachers in this school (for toolbar dropdown & fallback)
$school_teachers = [];
try {
    $st_all = $db->prepare("SELECT id, name_mr, name, teacher_code, class_assigned, role FROM teachers WHERE school_id = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY name_mr ASC, id ASC");
    $st_all->execute([$school_id]);
    $school_teachers = $st_all->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Class Teacher
$auto_teacher_name = cce_class_teacher_name($school_id, $std, $section);
$teacher_name = trim($_GET['teacher_name'] ?? '');
if (!$teacher_name) {
    $teacher_name = $auto_teacher_name;
}
if (!$teacher_name && !empty($school_teachers)) {
    $teacher_name = $school_teachers[0]['name_mr'] ?: $school_teachers[0]['name'];
}
if (!$teacher_name) {
    $teacher_name = 'वर्गशिक्षक';
}

// Headmaster
$auto_hm_name = cce_headmaster_name($school_id, $semester);
$hm_name = trim($_GET['hm_name'] ?? '');
if (!$hm_name) {
    $hm_name = $auto_hm_name;
}
if (!$hm_name) {
    foreach ($school_teachers as $st) {
        if (($st['role'] ?? '') === 'headmaster') {
            $hm_name = $st['name_mr'] ?: $st['name'];
            break;
        }
    }
}
if (!$hm_name) {
    $hm_name = 'मुख्याध्यापक';
}

// Load Marks & Notes for students
$marks = [];
$notes = [];
if ($students) {
    $ids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));

    // Dual-database marks matrix (MySQL + SQLite tenant sync)
    $marks = cce_student_marks_matrix($school_id, $year, $semester, $ids);

    // Load descriptive notes (MySQL first, fallback to SQLite)
    try {
        $stmt = $db->prepare("SELECT student_id, slot_no, note_text FROM cce_student_notes WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in) AND note_text <> ''");
        $stmt->execute(array_merge([$school_id, $year, $semester], $ids));
        foreach ($stmt->fetchAll() as $row) {
            $notes[(int)$row['student_id']][(int)$row['slot_no']] = $row['note_text'];
        }
    } catch (Throwable $e) {}

    try {
        $sqlite = cce_db($school_id);
        $sq_stmt = $sqlite->prepare("SELECT student_id, slot_no, note_text FROM cce_student_notes WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in) AND note_text <> ''");
        $sq_stmt->execute(array_merge([$school_id, $year, $semester], $ids));
        foreach ($sq_stmt->fetchAll() as $row) {
            $sid = (int)$row['student_id'];
            $slot = (int)$row['slot_no'];
            if (empty($notes[$sid][$slot])) {
                $notes[$sid][$slot] = $row['note_text'];
            }
        }
    } catch (Throwable $e) {}
}

// Standard 9 Subject Definitions (for Left Table)
$std_subjects_def = [
    1 => ['id_code' => 's1', 'name' => 'प्रथम भाषा मराठी', 'is_cosch' => false],
    2 => ['id_code' => 's2', 'name' => 'द्वितीय भाषा हिंदी', 'is_cosch' => false],
    3 => ['id_code' => 's3', 'name' => 'तृतीय भाषा इंग्रजी', 'is_cosch' => false],
    4 => ['id_code' => 's4', 'name' => 'गणित', 'is_cosch' => false],
    5 => ['id_code' => 's5', 'name' => 'सा.वि. / प.अ. /', 'is_cosch' => false],
    6 => ['id_code' => 's6', 'name' => 'सा.शास्त्रे / प.अ. 2', 'is_cosch' => false],
    7 => ['id_code' => 's7', 'name' => 'कला', 'is_cosch' => true],
    8 => ['id_code' => 's8', 'name' => 'कार्यानुभव', 'is_cosch' => true],
    9 => ['id_code' => 's9', 'name' => 'शा. शिक्षण व आरोग्य', 'is_cosch' => true],
];

// Map school's configured subjects to the 9 slots
$mapped_subjects = [];
foreach ($std_subjects_def as $sno => $sdef) {
    $found = null;
    foreach ($subjects as $sb) {
        if ($sb['subject_code'] === $sdef['id_code'] || mb_strpos($sb['name_mr'], mb_substr($sdef['name'], 0, 4)) !== false) {
            $found = $sb;
            break;
        }
    }
    $mapped_subjects[$sno] = [
        'sno' => $sno,
        'name' => $sdef['name'],
        'is_cosch' => $sdef['is_cosch'],
        'sb' => $found,
        'id' => $found ? (int)$found['id'] : 0,
    ];
}

// Right Table: 13 Descriptive Note Slots
$notes_slots_def = [
    1 => ['name' => 'प्रथम भाषा मराठी', 'sub' => ''],
    2 => ['name' => 'द्वितीय भाषा हिंदी', 'sub' => ''],
    3 => ['name' => 'तृतीय भाषा इंग्रजी', 'sub' => ''],
    4 => ['name' => 'गणित', 'sub' => ''],
    5 => ['name' => 'सा. विज्ञान / प.अ. /', 'sub' => ''],
    6 => ['name' => 'सामाजिक शास्त्रे / परिसर अभ्यास 2', 'sub' => ''],
    7 => ['name' => 'कला', 'sub' => ''],
    8 => ['name' => 'कार्यानुभव', 'sub' => ''],
    9 => ['name' => 'शा. शिक्षण व आरोग्य', 'sub' => ''],
    10 => ['name' => 'विशेष प्रगती', 'sub' => ''],
    11 => ['name' => 'आवड, छंद', 'sub' => 'कला, क्रीडा...'],
    12 => ['name' => 'सुधारणा आवश्यक', 'sub' => ''],
    13 => ['name' => 'व्यक्तिमत्व गुण विशेष', 'sub' => ''],
];

// Helper to format mark numbers
if (!function_exists('fmt_mk')) {
    function fmt_mk($val, $dash_if_zero = false) {
        if ($val === null || $val === '') return '-';
        $fl = (float)$val;
        if ($fl == 0 && $dash_if_zero) return '-';
        return (string)(rtrim(rtrim(number_format($fl, 1, '.', ''), '0'), '.'));
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>नोंदवही - <?= sanitize(std_label($std)) ?> - <?= sanitize(semester_label($semester)) ?> - <?= sanitize($s_name) ?></title>
<link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
/* =========================================================
   GLOBAL PRINT & RESET FOR सातत्यपूर्ण मूल्यमापन NONDVAHI
   Exact A4 Landscape: 297mm × 210mm
   ========================================================= */
@page {
  size: A4 landscape;
  margin: 7mm 8mm;
}
* {
  box-sizing: border-box;
}
body {
  margin: 0;
  padding: 0;
  background: #eef2f5;
  color: #000;
  font-family: 'Utsaah', 'Mukta', 'Nirmala UI', sans-serif;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

/* TOP INTERACTIVE TOOLBAR */
.tb {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: #1e293b;
  color: #fff;
  padding: 8px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  font-family: system-ui, -apple-system, sans-serif;
  font-size: 13px;
}
.tb-sep {
  width: 1px;
  height: 22px;
  background: #475569;
  margin: 0 4px;
}
.tb button, .tb .btn-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 4px;
  border: none;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.15s;
}
.tb .btn-print {
  background: #10b981;
  color: #fff;
}
.tb .btn-print:hover { background: #059669; }
.tb .btn-link {
  background: #334155;
  color: #e2e8f0;
}
.tb .btn-link:hover { background: #475569; }
.tb .btn-link.active {
  background: #2563eb;
  color: #fff;
}
.tb select {
  padding: 5px 8px;
  border-radius: 4px;
  border: 1px solid #475569;
  background: #0f172a;
  color: #fff;
  font-size: 12.5px;
  cursor: pointer;
}
.tb .print-tip {
  margin-left: auto;
  font-size: 11.5px;
  color: #94a3b8;
}
.tb .print-tip b { color: #f1f5f9; }

/* SHEET CONTAINER (A4 Landscape) */
.nond-page {
  width: 281mm;
  height: 196mm;
  margin: 12px auto;
  background: #ffffff;
  box-shadow: 0 2px 10px rgba(0,0,0,0.12);
  page-break-after: always;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.nond-page:last-child {
  page-break-after: auto;
}
@media screen and (max-width: 991.98px) {
  html, body { overflow-x: hidden !important; width: 100% !important; }
  .tb { padding: 10px 12px !important; gap: 8px !important; width: 100% !important; flex-wrap: wrap !important; }
  .tb button, .tb .btn-link { font-size: 14px !important; padding: 10px 14px !important; min-height: 44px !important; }
  .tb select { font-size: 14px !important; padding: 10px 12px !important; min-height: 44px !important; }
  .tb .print-tip { display: none !important; }
  .tb-sep { display: none !important; }
  .nond-page {
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: auto !important;
    margin: 10px 0 !important;
    padding: 10px !important;
    overflow-x: hidden !important;
    border-radius: 8px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.1) !important;
  }
  .ev-top-bar {
    flex-direction: column !important;
    gap: 6px !important;
    text-align: center !important;
  }
  .ev-top-left, .ev-top-center, .ev-top-right {
    width: 100% !important;
    text-align: center !important;
  }
  /* Mobile stack: Left table and Right table stack vertically so no overlap occurs */
  .ev-content-row {
    flex-direction: column !important;
    display: flex !important;
    width: 100% !important;
    gap: 16px !important;
  }
  .ev-table-wrap-left {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    padding-bottom: 8px !important;
    border-bottom: 2px dashed #94a3b8;
  }
  .ev-table-wrap-left table.mk-table {
    min-width: 720px !important;
    width: 100% !important;
  }
  .ev-table-wrap-right {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    padding-bottom: 8px !important;
  }
  .ev-table-wrap-right table.nt-table {
    min-width: 500px !important;
    width: 100% !important;
  }
  .index-page {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
  }
  .index-page table.idx-table {
    min-width: 650px !important;
  }
  .ev-sign-row {
    flex-direction: column !important;
    gap: 12px !important;
    padding: 0 !important;
  }
}

/* =========================================================
   PAGE 1: COVER PAGE (कव्हर पान)
   100% तंतोतंत: media_1789890332865.pdf प्रमाणे
   ========================================================= */
.cover-page {
  padding: 10mm 15mm;
  justify-content: space-between;
}
.cv-header-block {
  text-align: right;
  padding-right: 15mm;
  margin-top: 5mm;
}
.cv-dist-title {
  font-size: 16px;
  color: #333333;
  margin-bottom: 2px;
}
.cv-main-title {
  font-size: 36px;
  font-weight: 700;
  color: #b71c1c;
  letter-spacing: 0.5px;
  line-height: 1.15;
}
.cv-year-title {
  font-size: 22px;
  font-weight: 700;
  color: #111111;
  margin-top: 3px;
}

/* Middle: Educational Graphic + Huge "नोंदवही" Title */
.cv-middle-block {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 4mm;
  padding-left: 10mm;
}
.cover-page.no-graphic-mode .cv-middle-block {
  justify-content: center;
  padding-left: 0;
}
.cv-logo-wrap {
  width: 95mm;
  height: 85mm;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cv-logo-wrap svg {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.cv-title-wrap {
  flex: 1;
  text-align: center;
  padding-right: 20mm;
}
.cover-page.no-graphic-mode .cv-title-wrap {
  padding-right: 0;
}
.cv-big-title {
  font-size: 88px;
  font-weight: 700;
  color: #b81b34;
  letter-spacing: 2px;
  line-height: 1;
  margin: 0;
}
.cover-page.no-graphic-mode .cv-big-title {
  font-size: 96px;
  letter-spacing: 4px;
}

/* Divider & Info Card */
.cv-bottom-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  padding-right: 18mm;
  margin-bottom: 8mm;
}
.cv-cyan-line {
  width: 155mm;
  height: 4px;
  background-color: #00bcd4;
  margin-bottom: 6mm;
}
.cv-info-card {
  width: 155mm;
  font-size: 17.5px;
  line-height: 1.55;
  color: #000;
}
.cv-info-row {
  display: flex;
  align-items: center;
  margin-bottom: 3.5px;
}
.cv-info-lbl {
  width: 25mm;
  font-weight: 700;
  white-space: nowrap;
}
.cv-info-sep {
  width: 6mm;
  font-weight: 700;
  text-align: center;
}
.cv-info-val {
  flex: 1;
  font-weight: 700;
}
.cv-udise-boxes {
  display: inline-flex;
  gap: 2.5px;
  vertical-align: middle;
}
.cv-udise-box {
  width: 7.5mm;
  height: 7.5mm;
  border: 1px solid #777;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 15px;
  background: #fff;
}

/* =========================================================
   PAGE 2: INDEX (अनुक्रमणिका)
   ========================================================= */
.index-page {
  padding: 8mm 12mm;
}
.idx-title-row {
  text-align: center;
  font-size: 19px;
  font-weight: 700;
  margin-bottom: 4mm;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.idx-title-row .center-tag {
  color: #c2185b;
  font-size: 22px;
}
table.idx-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #00bcd4;
}
table.idx-table th, table.idx-table td {
  border: 1.2px solid #00bcd4;
  padding: 2.5px 5px;
  text-align: center;
  font-size: 14.5px;
}
table.idx-table thead th {
  background: #6fe0ee;
  color: #000;
  font-weight: 700;
  height: 8.5mm;
}
table.idx-table tbody tr {
  height: 6.8mm;
}
table.idx-table tbody td.name-cell {
  text-align: left;
  padding-left: 10px;
  font-weight: 700;
}

/* =========================================================
   PAGE 3+: STUDENT EVALUATION PAGE (सातत्यपूर्ण सर्वंकष मूल्यमापन)
   ========================================================= */
.eval-page {
  padding: 6mm 7mm;
}

/* Top Metadata Bar */
.ev-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  font-size: 15px;
  margin-bottom: 2mm;
  line-height: 1.35;
}
.ev-top-left {
  width: 32%;
}
.ev-top-center {
  width: 44%;
  text-align: center;
}
.ev-top-right {
  width: 24%;
  text-align: right;
}
.ev-top-center .c-title {
  color: #c2185b;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.ev-top-bar b {
  font-weight: 700;
  color: #000;
}

/* Two-Column Container */
.ev-content-row {
  display: flex;
  gap: 3mm;
  justify-content: space-between;
  align-items: flex-start;
}

/* Left Table: Formative & Summative Marksheet */
.ev-table-wrap-left {
  width: 59.5%;
}
table.mk-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #00bcd4;
  font-size: 11px;
  text-align: center;
}
table.mk-table th, table.mk-table td {
  border: 1.2px solid #00bcd4;
  padding: 0.5px 1px;
  vertical-align: middle;
}
table.mk-table thead th {
  background: #6fe0ee;
  color: #000;
  font-weight: 700;
  line-height: 1.05;
}
table.mk-table thead th.sub-th {
  background: #a9eef7;
}
table.mk-table thead th.vert-th {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 0;
  line-height: 1.0;
}
table.mk-table tbody tr {
  height: 6.6mm;
}
table.mk-table tbody td.sb-name-cell {
  text-align: left;
  padding-left: 3px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}
table.mk-table tbody td.tp-lbl {
  font-weight: 700;
  font-size: 10.5px;
  background: #fafafa;
}
table.mk-table tbody td.mk-val {
  font-size: 11.5px;
  font-weight: 600;
}
table.mk-table tbody td.mk-tot {
  font-size: 11.5px;
  font-weight: 700;
}
table.mk-table tbody td.mk-grd {
  font-size: 12px;
  font-weight: 700;
}

/* Distinct colors for each subject topic (विषयनिहाय रंग) */
.sb-topic-1 .sb-name-cell { background: #e0f2fe !important; color: #0369a1 !important; border-left: 3.5px solid #0284c7 !important; }
.sb-topic-2 .sb-name-cell { background: #fef3c7 !important; color: #b45309 !important; border-left: 3.5px solid #d97706 !important; }
.sb-topic-3 .sb-name-cell { background: #dcfce7 !important; color: #15803d !important; border-left: 3.5px solid #16a34a !important; }
.sb-topic-4 .sb-name-cell { background: #ede9fe !important; color: #6d28d9 !important; border-left: 3.5px solid #7c3aed !important; }
.sb-topic-5 .sb-name-cell { background: #ccfbf1 !important; color: #0f766e !important; border-left: 3.5px solid #0d9488 !important; }
.sb-topic-6 .sb-name-cell { background: #ffedd5 !important; color: #c2410c !important; border-left: 3.5px solid #ea580c !important; }
.sb-topic-7 .sb-name-cell { background: #fce7f3 !important; color: #be185d !important; border-left: 3.5px solid #db2777 !important; }
.sb-topic-8 .sb-name-cell { background: #fae8ff !important; color: #a21caf !important; border-left: 3.5px solid #c026d3 !important; }
.sb-topic-9 .sb-name-cell { background: #fef9c3 !important; color: #a16207 !important; border-left: 3.5px solid #ca8a04 !important; }

/* Distinction for पैकी (Max marks) row with subtle tool accents */
tr.tr-paiki { background-color: #f8fafc !important; }
tr.tr-paiki td.tp-lbl { background-color: #e2e8f0 !important; color: #1e293b !important; font-weight: 700 !important; }
tr.tr-paiki td.col-fe-tool { background-color: #f0fdfa !important; color: #0e7490 !important; font-weight: 700 !important; }
tr.tr-paiki td.col-fe-tot { background-color: #cffafe !important; color: #083344 !important; font-weight: 800 !important; }
tr.tr-paiki td.col-se-tool { background-color: #fffbeb !important; color: #b45309 !important; font-weight: 700 !important; }
tr.tr-paiki td.col-se-tot { background-color: #fed7aa !important; color: #7c2d12 !important; font-weight: 800 !important; }
tr.tr-paiki td.col-grand-tot { background-color: #dcfce7 !important; color: #14532d !important; font-weight: 800 !important; }

/* Distinction for प्राप्त (Obtained marks) row */
tr.tr-prapt { background-color: #ffffff !important; }
tr.tr-prapt td.tp-lbl { background-color: #f1f5f9 !important; color: #0f172a !important; font-weight: 700 !important; }
tr.tr-prapt td.col-fe-tool { color: #000000 !important; font-weight: 600 !important; }
tr.tr-prapt td.col-fe-tot { background-color: #ecfeff !important; color: #083344 !important; font-weight: 800 !important; }
tr.tr-prapt td.col-se-tool { color: #000000 !important; font-weight: 600 !important; }
tr.tr-prapt td.col-se-tot { background-color: #fff7ed !important; color: #9a3412 !important; font-weight: 800 !important; }
tr.tr-prapt td.col-grand-tot { background-color: #f0fdf4 !important; color: #166534 !important; font-weight: 800 !important; }
tr.tr-prapt td.mk-grd { background-color: #eff6ff !important; color: #1e40af !important; font-weight: 800 !important; font-size: 13px !important; }

/* Right Table: Descriptive Notes (वर्णनात्मक नोंदी) */
.ev-table-wrap-right {
  width: 39.5%;
}
table.nt-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #00bcd4;
  font-size: 11.5px;
}
table.nt-table th, table.nt-table td {
  border: 1.2px solid #00bcd4;
  padding: 1px 3px;
  vertical-align: middle;
}
table.nt-table thead th {
  background: #6fe0ee;
  color: #000;
  font-weight: 700;
  font-size: 13px;
  height: 15.5mm;
}
table.nt-table tbody tr {
  height: 9.5mm;
}
table.nt-table td.nt-no {
  text-align: center;
  font-weight: 700;
  font-size: 12px;
}
table.nt-table td.nt-sub {
  font-weight: 700;
  font-size: 11.5px;
  line-height: 1.15;
}
table.nt-table td.nt-sub .sub-note {
  font-size: 10px;
  font-weight: 500;
  color: #333;
}
table.nt-table td.nt-text {
  font-size: 11px;
  line-height: 1.2;
  color: #000;
  font-weight: 500;
}

/* Signatures Row */
.ev-sign-row {
  display: flex;
  justify-content: space-around;
  margin-top: 5mm;
  padding: 0 10mm;
}
.ev-sign-box {
  text-align: center;
  font-size: 14px;
}
.ev-sign-title {
  font-weight: 700;
  color: #000;
}
.ev-sign-name {
  font-weight: 600;
  color: #111;
  margin-top: 1.5mm;
  font-size: 13px;
}

/* PRINT MEDIA */
@media print {
  body {
    background: #fff;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .tb {
    display: none !important;
  }
  .nond-page {
    width: 100% !important;
    height: auto !important;
    min-height: 190mm !important;
    max-height: 195mm !important;
    margin: 0 !important;
    box-shadow: none !important;
    page-break-after: always !important;
    break-after: page !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    overflow: hidden !important;
  }
  .nond-page:last-child {
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .cover-page {
    padding: 8mm 12mm !important;
  }
  .index-page {
    padding: 6mm 10mm !important;
  }
  .eval-page {
    padding: 4mm 6mm !important;
  }
  table.mk-table thead th, table.nt-table thead th, table.idx-table thead th {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .ev-top-bar {
    flex-direction: row !important;
  }
  .ev-top-left { width: 32% !important; text-align: left !important; }
  .ev-top-center { width: 44% !important; text-align: center !important; }
  .ev-top-right { width: 24% !important; text-align: right !important; }
  .ev-content-row {
    display: flex !important;
    flex-direction: row !important;
    gap: 3mm !important;
    width: 100% !important;
  }
  .ev-table-wrap-left {
    width: 59.5% !important;
    max-width: 59.5% !important;
    overflow: visible !important;
    padding-bottom: 0 !important;
    border-bottom: none !important;
  }
  .ev-table-wrap-left table.mk-table {
    min-width: unset !important;
    width: 100% !important;
  }
  .ev-table-wrap-right {
    width: 39.5% !important;
    max-width: 39.5% !important;
    overflow: visible !important;
    padding-bottom: 0 !important;
  }
  .ev-table-wrap-right table.nt-table {
    min-width: unset !important;
    width: 100% !important;
  }
  .ev-sign-row {
    flex-direction: row !important;
    display: flex !important;
    justify-content: space-around !important;
  }
}
</style>
<link href="<?= APP_URL ?>/assets/css/report_pro.css?v=3" rel="stylesheet">
</head>
<body>

<!-- TOP INTERACTIVE TOOLBAR -->
<div class="tb">
  <button class="btn-print" onclick="window.print()">🖨 छापा / PDF सेव्ह करा</button>

  <div class="tb-sep"></div>

  <!-- Semester Toggle: Semester 1 / Semester 2 -->
  <a class="btn-link <?= $semester === 1 ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['semester' => 1])) ?>">
    🌱 प्रथम सत्र
  </a>
  <a class="btn-link <?= $semester === 2 ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['semester' => 2])) ?>">
    🍂 द्वितीय सत्र
  </a>

  <div class="tb-sep"></div>

  <!-- View Mode Toggles: All, Cover only, Index only, Student Sheet only -->
  <a class="btn-link <?= $view === 'all' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view' => 'all'])) ?>">
    📑 सर्व (Cover + Index + Students)
  </a>
  <a class="btn-link <?= $view === 'cover' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view' => 'cover'])) ?>">
    🎴 कव्हर पान
  </a>
  <a class="btn-link <?= $view === 'index' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view' => 'index'])) ?>">
    📋 अनुक्रमणिका
  </a>
  <a class="btn-link <?= $view === 'students' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view' => 'students'])) ?>">
    👤 विद्यार्थी नोंद पाने
  </a>

  <div class="tb-sep"></div>

  <!-- Standard Switcher -->
  <select onchange="location.href='?std=' + this.value + '&semester=<?= $semester ?>&view=<?= $view ?>'">
    <?php foreach ($standards as $st_num => $st_lbl): ?>
      <option value="<?= (int)$st_num ?>" <?= (int)$st_num === $std ? 'selected' : '' ?>>
        इयत्ता <?= sanitize($st_lbl) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- Student Switcher -->
  <select onchange="location.href=this.value">
    <option value="?std=<?= $std ?>&semester=<?= $semester ?>&view=<?= $view ?>&student_id=0" <?= $sid_get === 0 ? 'selected' : '' ?>>
      👥 सर्व विद्यार्थी (<?= count($all_students_for_std) ?>)
    </option>
    <?php foreach ($all_students_for_std as $st_opt): ?>
      <option value="?std=<?= $std ?>&semester=<?= $semester ?>&view=students&student_id=<?= (int)$st_opt['id'] ?>" <?= $sid_get === (int)$st_opt['id'] ? 'selected' : '' ?>>
        <?= sanitize($st_opt['roll_no'] ? ($st_opt['roll_no'] . '. ') : '') ?><?= sanitize($st_opt['name_mr'] ?: $st_opt['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <div class="tb-sep"></div>

  <!-- Class Teacher Switcher -->
  <select class="cce-tb-select" title="वर्गशिक्षक निवडा (Class Teacher)" onchange="cceUpdateParam('teacher_name', this.value)">
    <option value="">🧑‍🏫 वर्गशिक्षक: <?= sanitize($auto_teacher_name ?: 'स्वयं') ?> (Auto)</option>
    <?php foreach ($school_teachers as $st): 
        $st_name = $st['name_mr'] ?: $st['name'];
        $is_sel = (!empty($_GET['teacher_name']) && $_GET['teacher_name'] === $st_name);
    ?>
      <option value="<?= sanitize($st_name) ?>" <?= $is_sel ? 'selected' : '' ?>>
        <?= sanitize($st_name) ?><?= $st['class_assigned'] ? ' (' . sanitize($st['class_assigned']) . ')' : '' ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- Cover Graphic Toggle -->
  <?php if ($view === 'all' || $view === 'cover'): ?>
    <select class="cce-tb-select" title="कव्हर ग्राफिक निवडा (Cover Graphic)" onchange="cceUpdateParam('graphic', this.value)">
      <option value="1" <?= $show_graphic ? 'selected' : '' ?>>🎨 कव्हर ग्राफिक: चालू</option>
      <option value="0" <?= !$show_graphic ? 'selected' : '' ?>>✍️ कव्हर ग्राफिक: बंद (फक्त शीर्षक)</option>
    </select>
  <?php endif; ?>

  <div class="tb-sep"></div>

  <!-- Unified Theme & Font Switcher -->
  <?= cce_theme_toolbar_controls(false) ?>

  <div class="tb-sep"></div>

  <a class="btn-link" href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>">
    ← अहवाल यादी
  </a>

  <span class="print-tip">
    💡 PDF सेव्ह करताना: Destination: <b>Save as PDF</b> | Layout: <b>Landscape</b> | Margins: <b>None</b>.
  </span>
</div>


<?php if ($view === 'all' || $view === 'cover'): ?>
<!-- ══════════════════════════════════════════════════════════════
     PAGE 1: COVER PAGE (कव्हर पान)
     100% तंतोतंत: media_1789890332865.pdf प्रमाणे
     ══════════════════════════════════════════════════════════════ -->
<div class="nond-page cover-page <?= !$show_graphic ? 'no-graphic-mode' : '' ?>">
  <div class="cv-header-block">
    <div class="cv-dist-title">जिल्हा परिषद <?= sanitize($s_dist) ?></div>
    <div class="cv-main-title">सातत्यपूर्ण सर्वंकष मूल्यमापन</div>
    <div class="cv-year-title">सन : <?= sanitize($year_short) ?></div>
  </div>

  <div class="cv-middle-block">
    <?php if ($show_graphic): ?>
      <div class="cv-logo-wrap">
        <?= cce_nondvahi_cover_graphic_svg(310) ?>
      </div>
    <?php endif; ?>
    <div class="cv-title-wrap">
      <h1 class="cv-big-title">नोंदवही</h1>
    </div>
  </div>

  <div class="cv-bottom-block">
    <div class="cv-cyan-line"></div>
    <div class="cv-info-card">
      <div class="cv-info-row">
        <div class="cv-info-lbl">UDISE</div>
        <div class="cv-info-sep">:</div>
        <div class="cv-info-val">
          <div class="cv-udise-boxes">
            <?php foreach ($udise_digits as $d): ?>
              <span class="cv-udise-box"><?= sanitize($d) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="cv-info-row">
        <div class="cv-info-lbl">शाळा</div>
        <div class="cv-info-sep">:</div>
        <div class="cv-info-val"><?= sanitize($s_name) ?></div>
      </div>

      <div class="cv-info-row">
        <div class="cv-info-lbl">वर्गशिक्षक</div>
        <div class="cv-info-sep">:</div>
        <div class="cv-info-val"><?= sanitize($teacher_name) ?></div>
      </div>

      <div class="cv-info-row">
        <div class="cv-info-lbl">इयत्ता</div>
        <div class="cv-info-sep">:</div>
        <div class="cv-info-val" style="display:flex; justify-content:space-between; width:100%;">
          <span><?= sanitize(std_label($std)) ?></span>
          <span style="padding-right:20mm;">तुकडी &nbsp;: &nbsp;<?= sanitize($section ?: '—') ?></span>
        </div>
      </div>
    </div>
  </div>
</div><!-- /Cover Page -->
<?php endif; ?>


<?php if ($view === 'all' || $view === 'index'): ?>
<!-- ══════════════════════════════════════════════════════════════
     PAGE 2: INDEX (अनुक्रमणिका)
     100% तंतोतंत: media_1789890332865.pdf प्रमाणे
     ══════════════════════════════════════════════════════════════ -->
<?php
// Chunk students for index pagination (up to 25 students per index page)
$idx_chunks = array_chunk($all_students_for_std, 25);
if (empty($idx_chunks)) $idx_chunks = [[]];

$global_idx = 0;
foreach ($idx_chunks as $ch_no => $chunk_students):
?>
<div class="nond-page index-page">
  <div class="idx-title-row">
    <span>सत्र : <b><?= sanitize(semester_label($semester)) ?></b></span>
    <span class="center-tag">● अनुक्रमणिका ●</span>
    <span>इयत्ता : <b><?= sanitize(std_label($std)) ?></b></span>
  </div>

  <table class="idx-table">
    <thead>
      <tr>
        <th style="width:8%">अ.नं.</th>
        <th style="width:46%">विद्यार्थ्याचे नाव</th>
        <th style="width:16%">रजि. नं.</th>
        <th style="width:18%">जन्मतारीख</th>
        <th style="width:12%">पृष्ठ क्र.</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if (empty($chunk_students)):
      ?>
        <tr><td colspan="5" style="padding:15px; color:#666;">या वर्गात विद्यार्थी उपलब्ध नाहीत.</td></tr>
      <?php
      else:
        foreach ($chunk_students as $st):
            $global_idx++;
            $st_nm = $st['name_mr'] ?: $st['name'];
            $st_reg = $st['registration_no'] ?: ($st['gr_no'] ?: '—');
            $st_dob = $st['date_of_birth'] ?? $st['dob'] ?? '';
            $st_dob_fmt = ($st_dob && $st_dob !== '0000-00-00') ? date('d-m-Y', strtotime($st_dob)) : '—';
      ?>
        <tr>
          <td><?= $global_idx ?></td>
          <td class="name-cell"><?= sanitize($st_nm) ?></td>
          <td><?= sanitize($st_reg) ?></td>
          <td><?= sanitize($st_dob_fmt) ?></td>
          <td><?= $global_idx ?></td>
        </tr>
      <?php
        endforeach;
      endif;
      ?>
    </tbody>
  </table>
</div><!-- /Index Page -->
<?php endforeach; ?>
<?php endif; ?>


<?php if ($view === 'all' || $view === 'students' || $view === 'student'): ?>
<!-- ══════════════════════════════════════════════════════════════
     PAGE 3+: STUDENT EVALUATION PAGE (प्रत्येक विद्यार्थ्याचे नोंद पान)
     100% तंतोतंत: media_1789890332865.pdf प्रमाणे
     ══════════════════════════════════════════════════════════════ -->
<?php
$stu_idx = 0;
foreach ($students as $stu):
    $stu_idx++;
    $sid = (int)$stu['id'];
    $s_name_disp = $stu['name_mr'] ?: $stu['name'];
    $s_roll = $stu['roll_no'] ?: $stu_idx;
    $s_sec = $stu['section'] ?: $section;
    $st_marks = $marks[$sid] ?? [];
    $st_notes = $notes[$sid] ?? [];
?>
<div class="nond-page eval-page">
  <!-- Top Bar -->
  <div class="ev-top-bar">
    <div class="ev-top-left">
      <div>इयत्ता : <b><?= sanitize(std_label($std)) ?></b> &nbsp;&nbsp;&nbsp;&nbsp; तुकडी : <b><?= sanitize($s_sec ?: '—') ?></b></div>
      <div>विद्यार्थ्याचे नाव : <b><?= sanitize($s_name_disp) ?></b></div>
    </div>
    <div class="ev-top-center">
      <div class="c-title">● सातत्यपूर्ण सर्वंकष मूल्यमापन ●</div>
      <div>हजेरी क्र. : <b><?= sanitize($s_roll) ?></b></div>
    </div>
    <div class="ev-top-right">
      <div>सन : <b><?= sanitize($year_short) ?></b></div>
      <div><b><?= sanitize(semester_label($semester)) ?></b></div>
    </div>
  </div>

  <!-- Two Main Tables -->
  <div class="ev-content-row">

    <!-- LEFT: Formative & Summative Marksheet Table -->
    <div class="ev-table-wrap-left">
      <table class="mk-table">
        <thead>
          <tr>
            <th rowspan="3" style="width:4.5%;">अ.<br>नं.</th>
            <th colspan="2" style="width:23%;">तपशील</th>
            <th colspan="9" style="width:40.5%;">(अ) आकारिक मूल्यमापन</th>
            <th colspan="4" style="width:19%;">(ब)<br>संकलित मूल्यमापन</th>
            <th rowspan="3" class="vert-th" style="width:4%;">अ<br>+<br>ब</th>
            <th rowspan="3" class="vert-th" style="width:4.5%;">शे.<br>गुण</th>
            <th rowspan="3" style="width:4.5%;">श्रेणी</th>
          </tr>
          <tr>
            <th rowspan="2" class="sub-th" style="width:16%;">विषय</th>
            <th rowspan="2" class="sub-th" style="width:7%;">गुण</th>
            <th class="vert-th" style="width:4.3%;">नि<br>री<br>क्ष<br>ण</th>
            <th class="vert-th" style="width:4.3%;">तों<br>डी<br>का<br>म</th>
            <th class="vert-th" style="width:4.3%;">प्रा<br>त्य.<br>प्र<br>यो<br>ग</th>
            <th class="vert-th" style="width:4.3%;">उप<br>क्रम<br>कृ<br>ती</th>
            <th class="vert-th" style="width:4.3%;">प्र<br>क<br>ल्प</th>
            <th class="vert-th" style="width:4.3%;">चा<br>च<br>णी</th>
            <th class="vert-th" style="width:4.3%;">स्वा<br>ध्याय<br>वर्गका<br>र्य</th>
            <th class="vert-th" style="width:4.3%;">इतर</th>
            <th rowspan="2" class="vert-th" style="width:6.1%;">ए<br>कू<br>ण</th>
            <th class="vert-th" style="width:4.7%;">तों<br>डी</th>
            <th class="vert-th" style="width:4.7%;">प्रा<br>त्य</th>
            <th class="vert-th" style="width:4.7%;">ले<br>खी</th>
            <th rowspan="2" class="vert-th" style="width:4.9%;">ए<br>कू<br>ण</th>
          </tr>
          <tr>
            <th class="sub-th">1</th>
            <th class="sub-th">2</th>
            <th class="sub-th">3</th>
            <th class="sub-th">4</th>
            <th class="sub-th">5</th>
            <th class="sub-th">6</th>
            <th class="sub-th">7</th>
            <th class="sub-th">8</th>
            <th class="sub-th" colspan="3"></th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($mapped_subjects as $sno => $m_sub):
              $sb_info = $m_sub['sb'];
              $is_cosch = $m_sub['is_cosch'];
              $sb_id = $m_sub['id'];
              $mk = ($sb_id > 0 && isset($st_marks[$sb_id])) ? $st_marks[$sb_id] : null;

              // Default standard max marks fallback (matching Maharashtra CCE pattern)
              if ($is_cosch) {
                  // कला, कार्यानुभव, शा.शिक्षण: 100 marks total (tools 2: 30, 3: 30, 4: 40, एकूण 100)
                  $fe_max_row = [
                      1 => ($sb_info && $sb_info['fe1_max'] !== null) ? $sb_info['fe1_max'] : null,
                      2 => ($sb_info && $sb_info['fe2_max'] !== null) ? $sb_info['fe2_max'] : 30,
                      3 => ($sb_info && $sb_info['fe3_max'] !== null) ? $sb_info['fe3_max'] : 30,
                      4 => ($sb_info && $sb_info['fe4_max'] !== null) ? $sb_info['fe4_max'] : 40,
                      5 => ($sb_info && $sb_info['fe5_max'] !== null) ? $sb_info['fe5_max'] : null,
                      6 => ($sb_info && $sb_info['fe6_max'] !== null) ? $sb_info['fe6_max'] : null,
                      7 => ($sb_info && $sb_info['fe7_max'] !== null) ? $sb_info['fe7_max'] : null,
                      8 => ($sb_info && $sb_info['fe8_max'] !== null) ? $sb_info['fe8_max'] : null,
                  ];
                  $fe_tot_max = 100;
                  $se_max_row = [1 => null, 2 => null, 3 => null];
                  $se_tot_max = null;
                  $grand_max = 100;
              } else {
                  // Academic subjects: 50 Formative + 50 Summative = 100
                  $fe_max_row = [
                      1 => ($sb_info && $sb_info['fe1_max'] !== null) ? $sb_info['fe1_max'] : null,
                      2 => ($sb_info && $sb_info['fe2_max'] !== null) ? $sb_info['fe2_max'] : ($sno === 5 ? null : 10),
                      3 => ($sb_info && $sb_info['fe3_max'] !== null) ? $sb_info['fe3_max'] : ($sno === 4 ? 10 : null),
                      4 => ($sb_info && $sb_info['fe4_max'] !== null) ? $sb_info['fe4_max'] : ($sno === 4 || $sno === 5 ? null : 10),
                      5 => ($sb_info && $sb_info['fe5_max'] !== null) ? $sb_info['fe5_max'] : null,
                      6 => ($sb_info && $sb_info['fe6_max'] !== null) ? $sb_info['fe6_max'] : 20,
                      7 => ($sb_info && $sb_info['fe7_max'] !== null) ? $sb_info['fe7_max'] : ($sno === 5 ? null : 10),
                      8 => ($sb_info && $sb_info['fe8_max'] !== null) ? $sb_info['fe8_max'] : null,
                  ];
                  $fe_tot_max = 50;
                  $se_max_row = [
                      1 => ($sb_info && $sb_info['se1_max'] !== null) ? $sb_info['se1_max'] : ($sno === 2 || $sno === 5 ? null : 10),
                      2 => ($sb_info && $sb_info['se2_max'] !== null) ? $sb_info['se2_max'] : null,
                      3 => ($sb_info && $sb_info['se3_max'] !== null) ? $sb_info['se3_max'] : ($sno === 2 || $sno === 5 ? 50 : 40),
                  ];
                  $se_tot_max = 50;
                  $grand_max = 100;
              }

              // Student's obtained marks
              $fe_got = [];
              for ($i = 1; $i <= 8; $i++) {
                  $fe_got[$i] = ($mk && isset($mk['fe' . $i]) && $mk['fe' . $i] !== null && $fe_max_row[$i] !== null) ? $mk['fe' . $i] : null;
              }
              $fe_tot_got = $mk ? $mk['fe_total'] : null;

              $se_got = [
                  1 => ($mk && isset($mk['se1']) && $mk['se1'] !== null && $se_max_row[1] !== null) ? $mk['se1'] : null,
                  2 => ($mk && isset($mk['se2']) && $mk['se2'] !== null && $se_max_row[2] !== null) ? $mk['se2'] : null,
                  3 => ($mk && isset($mk['se3']) && $mk['se3'] !== null && $se_max_row[3] !== null) ? $mk['se3'] : null,
              ];
              $se_tot_got = $mk ? $mk['se_total'] : null;
              $grand_got = $mk ? $mk['grand_total'] : null;
              $percent_got = $mk ? round((float)$mk['percent']) : null;
              $grade_got = $mk ? cce_normalize_grade($mk['grade'] ?? '') : '';
              if (empty($grade_got) && $percent_got !== null && $percent_got > 0) {
                  $grade_got = cce_calc_grade((float)$percent_got);
              }
          ?>
          <!-- ROW 1: पैकी (Max Marks) -->
          <tr class="sb-topic-<?= $sno ?> tr-paiki">
            <td rowspan="2" style="font-weight:700;"><?= $sno ?></td>
            <td rowspan="2" class="sb-name-cell"><?= sanitize($m_sub['name']) ?></td>
            <td class="tp-lbl">पैकी</td>
            <?php for ($i = 1; $i <= 8; $i++): ?>
              <td class="mk-val col-fe-tool"><?= $fe_max_row[$i] !== null ? fmt_mk($fe_max_row[$i]) : '-' ?></td>
            <?php endfor; ?>
            <td class="mk-tot col-fe-tot"><?= $fe_tot_max !== null ? fmt_mk($fe_tot_max) : '-' ?></td>
            <td class="mk-val col-se-tool"><?= $se_max_row[1] !== null ? fmt_mk($se_max_row[1]) : '-' ?></td>
            <td class="mk-val col-se-tool"><?= $se_max_row[2] !== null ? fmt_mk($se_max_row[2]) : '-' ?></td>
            <td class="mk-val col-se-tool"><?= $se_max_row[3] !== null ? fmt_mk($se_max_row[3]) : '-' ?></td>
            <td class="mk-tot col-se-tot"><?= $se_tot_max !== null ? fmt_mk($se_tot_max) : '-' ?></td>
            <td class="mk-tot col-grand-tot"><?= fmt_mk($grand_max) ?></td>
            <td rowspan="2" class="mk-tot"><?= $percent_got !== null ? sanitize($percent_got) : '-' ?></td>
            <td rowspan="2" class="mk-grd"><?= sanitize($grade_got ?: '-') ?></td>
          </tr>

          <!-- ROW 2: प्राप्त (Obtained Marks) -->
          <tr class="sb-topic-<?= $sno ?> tr-prapt">
            <td class="tp-lbl">प्राप्त</td>
            <?php for ($i = 1; $i <= 8; $i++): ?>
              <td class="mk-val col-fe-tool"><?= $fe_got[$i] !== null ? fmt_mk($fe_got[$i]) : '-' ?></td>
            <?php endfor; ?>
            <td class="mk-tot col-fe-tot"><?= $fe_tot_got !== null ? fmt_mk($fe_tot_got) : '-' ?></td>
            <td class="mk-val col-se-tool"><?= $se_got[1] !== null ? fmt_mk($se_got[1]) : '-' ?></td>
            <td class="mk-val col-se-tool"><?= $se_got[2] !== null ? fmt_mk($se_got[2]) : '-' ?></td>
            <td class="mk-val col-se-tool"><?= $se_got[3] !== null ? fmt_mk($se_got[3]) : '-' ?></td>
            <td class="mk-tot col-se-tot"><?= $se_tot_got !== null ? fmt_mk($se_tot_got) : '-' ?></td>
            <td class="mk-tot col-grand-tot"><?= $grand_got !== null ? fmt_mk($grand_got) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- RIGHT: 13 Descriptive Notes Table (वर्णनात्मक नोंदी) -->
    <div class="ev-table-wrap-right">
      <table class="nt-table">
        <thead>
          <tr>
            <th style="width:9%;">नं.</th>
            <th style="width:27%;">विषय</th>
            <th style="width:64%;">वर्णनात्मक नोंदी</th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($notes_slots_def as $slot_no => $s_note):
              $txt = $st_notes[$slot_no] ?? '';
          ?>
          <tr>
            <td class="nt-no"><?= $slot_no ?></td>
            <td class="nt-sub">
              <?= sanitize($s_note['name']) ?>
              <?php if (!empty($s_note['sub'])): ?>
                <div class="sub-note"><?= sanitize($s_note['sub']) ?></div>
              <?php endif; ?>
            </td>
            <td class="nt-text"><?= sanitize($txt) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /ev-content-row -->

  <!-- Bottom Signatures Row: Ample headroom for physical signing -->
  <div class="ev-sign-row">
    <div class="ev-sign-box">
      <div class="ev-sign-title">वर्गशिक्षक स्वाक्षरी</div>
      <div class="ev-sign-name"><?= sanitize($teacher_name) ?></div>
    </div>
    <div class="ev-sign-box">
      <div class="ev-sign-title">मुख्याध्यापक स्वाक्षरी</div>
      <div class="ev-sign-name"><?= sanitize($hm_name) ?></div>
    </div>
  </div>

</div><!-- /Student Evaluation Page -->
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
