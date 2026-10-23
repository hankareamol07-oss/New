<?php
/**
 * प्रगती पुस्तक (Pragati Pustak) — CCE Pro-Level अधिकृत छपाईयोग्य प्रगती पुस्तक
 * A4 Landscape (बुकलेट / २ पाने एका पानावर) आणि A5 Landscape (प्रमाणबद्ध A5 बुकलेट)
 * अचूक अधिकृत लेआउट: Screenshot व PDF नुसार 100% तंतोतंत
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

// Sizing mode: 'a4' (Landscape 297mm x 210mm) or 'a5' (Landscape 210mm x 148.5mm)
$size = in_array($_GET['size'] ?? '', ['a4', 'a5', 'a5l'], true) ? $_GET['size'] : 'a4';
$is_a5 = in_array($size, ['a5', 'a5l'], true);

// View mode: 'all' (Sheet 1 + Sheet 2), 'sheet1' (Cover spread only), 'sheet2' (Inside spread only)
$view = in_array($_GET['view'] ?? '', ['all', 'sheet1', 'sheet2'], true) ? $_GET['view'] : 'all';

$all = !empty($_GET['all']);
$sid_get = (int)($_GET['student_id'] ?? 0);
$std = (int)($_GET['std'] ?? 0);
if ($std <= 0 && !empty($standards)) {
    $std = (int)(array_key_first($standards) ?? 1);
}
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    if (!in_array($std, $tScope['stds'], true)) {
        $std = (int)$tScope['stds'][0];
    }
}

if ($all) {
    $list = cce_students($school_id, $std);
} else {
    if ($sid_get > 0) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
        $stmt->execute([$sid_get, $school_id]);
        $s = $stmt->fetch();
    } else {
        $list_tmp = cce_students($school_id, $std);
        $s = $list_tmp[0] ?? null;
    }
    if (!$s) {
        http_response_code(404);
        exit('कोणताही विद्यार्थी सापडला नाही. कृपया इयत्ता निवडा.');
    }
    $list = [$s];
    $std = std_from_grade($s['grade'] ?? '') ?: $std;
}

if (!$list) {
    exit('या इयत्तेत विद्यार्थी नाहीत.');
}

if ($std === 0 && !empty($list[0]['grade'])) {
    $std = std_from_grade($list[0]['grade']) ?: 1;
}

/**
 * Fetch or seed 9 subjects for the standard to ensure 100% visual alignment
 * with the 3 descriptive note categories (Rows 1-3, 4-6, 7-9).
 */
if (!function_exists('ppGetSubjects')) {
    function ppGetSubjects($school_id, $std) {
        $subs = cce_subjects($school_id, $std);
        if (!$subs) {
            cce_seed_subjects($school_id, $std);
            $subs = cce_subjects($school_id, $std);
        }
        if (!$subs) {
            $default_names = [
                'मराठी', 'हिंदी', 'इंग्रजी', 'गणित',
                'सा. विज्ञान', 'सा. शास्त्रे', 'कला', 'कार्यानुभव', 'शा. शिक्षण'
            ];
            foreach ($default_names as $idx => $dname) {
                $subs[] = [
                    'id' => $idx + 1,
                    'name_mr' => $dname,
                    'short_name' => $dname,
                ];
            }
        }
        while (count($subs) < 9) {
            $subs[] = [
                'id' => 0,
                'name_mr' => '',
                'short_name' => '',
            ];
        }
        return array_slice($subs, 0, 9);
    }
}

$subjects = ppGetSubjects($school_id, $std);

$months = [
    6 => 'जून', 7 => 'जुलै', 8 => 'ऑगस्ट', 9 => 'सप्टेंबर',
    10 => 'ऑक्टोबर', 11 => 'नोव्हेंबर', 12 => 'डिसेंबर',
    1 => 'जानेवारी', 2 => 'फेब्रुवारी', 3 => 'मार्च', 4 => 'एप्रिल', 5 => 'मे'
];

if (!function_exists('ppM')) {
    function ppM($db, $sid, $year, $sem) {
        $s = $db->prepare("SELECT * FROM cce_marks WHERE student_id = ? AND academic_year = ? AND semester = ?");
        $s->execute([$sid, $year, $sem]);
        $o = [];
        foreach ($s->fetchAll() as $r) {
            $o[(int)$r['subject_id']] = $r;
        }
        return $o;
    }
}

if (!function_exists('ppN')) {
    function ppN($db, $sid, $year, $sem) {
        $s = $db->prepare("SELECT slot_no, note_text FROM cce_student_notes WHERE student_id = ? AND academic_year = ? AND semester = ?");
        $s->execute([$sid, $year, $sem]);
        $o = [];
        foreach ($s->fetchAll() as $r) {
            if (trim($r['note_text'] ?? '') !== '') {
                $o[(int)$r['slot_no']] = $r['note_text'];
            }
        }
        return $o;
    }
}

if (!function_exists('ppX')) {
    function ppX($db, $sid) {
        try {
            $s = $db->prepare("SELECT * FROM cce_student_extra WHERE student_id = ?");
            $s->execute([$sid]);
            return $s->fetch() ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
}

if (!function_exists('ppA')) {
    function ppA($db, $sid, $year) {
        $s = $db->prepare("SELECT * FROM attendance WHERE student_id = ? AND academic_year = ?");
        $s->execute([$sid, $year]);
        $o = [];
        foreach ($s->fetchAll() as $r) {
            $o[(int)$r['month']] = $r;
        }
        return $o;
    }
}

if (!function_exists('ppAge')) {
    function ppAge($dob) {
        if (!$dob || $dob === '0000-00-00') return '';
        try {
            $diff = (new DateTime($dob))->diff(new DateTime());
            return $diff->y . ' व ' . $diff->m . ' म';
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('ppMrNum')) {
    function ppMrNum($num) {
        if ($num === '' || $num === null || $num === '—') return '—';
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $mr = ['०','१','२','३','४','५','६','७','८','९'];
        return str_replace($en, $mr, (string)$num);
    }
}

// School info
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद प्राथमिक शाळा');
$s_udise = $school['udise_code'] ?: '';
$s_taluka = trim($school['taluka'] ?? '');
$s_dist = trim($school['district'] ?? '');
$s_loc = implode(' , ', array_filter([
    $s_taluka ? ('ता. ' . $s_taluka) : '',
    $s_dist ? ('जि. ' . $s_dist) : ''
]));

// Logo
$s_logo = '';
if (!empty($school['logo'])) {
    $clean_logo = ltrim($school['logo'], '/');
    if (file_exists(__DIR__ . '/../' . $clean_logo)) {
        $s_logo = APP_URL . '/' . $clean_logo;
    } elseif (file_exists(__DIR__ . '/../uploads/' . basename($school['logo']))) {
        $s_logo = APP_URL . '/uploads/' . basename($school['logo']);
    }
}

// Result Date
$res_date_val = '';
try {
    $sd = $db->prepare("SELECT result_date FROM school_defaults WHERE school_id = ?");
    $sd->execute([$school_id]);
    $res_date_val = $sd->fetchColumn();
} catch (Exception $e) {}

if (!$res_date_val) {
    $y_parts = explode('-', $year);
    $y2 = isset($y_parts[1]) ? ((strlen($y_parts[1]) === 2) ? ('20' . $y_parts[1]) : $y_parts[1]) : date('Y');
    $res_date_val = '01-05-' . $y2;
} else {
    $res_date_val = date('d-m-Y', strtotime($res_date_val));
}

// Class standards for toolbar switcher
$standards = cce_school_standards($school_id);
$all_students_for_std = cce_students($school_id, $std);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="print-orientation" content="landscape">
<meta name="print-paper-size" content="<?= $is_a5 ? 'A5' : 'A4' ?>">
<title>प्रगती पुस्तक — इयत्ता <?= sanitize(std_label($std)) ?> (<?= sanitize($year) ?>)</title>
<?= cce_theme_head_tags() ?>
<style>
/* === OFFICIAL UTSAAH FONT DEFINITIONS === */
@font-face {
  font-family: 'Utsaah';
  font-style: normal;
  font-weight: 400;
  src: local('Utsaah'),
       url('<?= APP_URL ?>/assets/fonts/utsaah.ttf') format('truetype');
}
@font-face {
  font-family: 'Utsaah';
  font-style: normal;
  font-weight: 700;
  src: local('Utsaah Bold'), local('Utsaah-Bold'),
       url('<?= APP_URL ?>/assets/fonts/utsaahb.ttf') format('truetype');
}
@font-face {
  font-family: 'Utsaah';
  font-style: italic;
  font-weight: 400;
  src: local('Utsaah Italic'), local('Utsaah-Italic'),
       url('<?= APP_URL ?>/assets/fonts/utsaahi.ttf') format('truetype');
}
@font-face {
  font-family: 'Utsaah';
  font-style: italic;
  font-weight: 700;
  src: local('Utsaah Bold Italic'), local('Utsaah-BoldItalic'),
       url('<?= APP_URL ?>/assets/fonts/utsaahbi.ttf') format('truetype');
}

/* === RESET & SCREEN SETUP === */
*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}
html, body {
  margin: 0;
  padding: 0;
  background: #dfe6eb;
  font-family: 'Utsaah', 'Mukta', 'Nirmala UI', sans-serif;
  color: #111;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

/* === TOP TOOLBAR === */
.tb {
  position: sticky;
  top: 0;
  z-index: 9999;
  background: #111c2a;
  color: #fff;
  padding: 8px 16px;
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  box-shadow: 0 2px 10px rgba(0,0,0,0.35);
}
.tb button.btn-print {
  background: #10b981;
  color: #fff;
  border: 0;
  border-radius: 5px;
  padding: 7px 16px;
  font: 700 13px/1 inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.25);
  transition: background 0.15s;
}
.tb button.btn-print:hover {
  background: #059669;
}
.tb a.btn-link {
  border-radius: 5px;
  padding: 6px 11px;
  font-size: 12px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  transition: all 0.15s;
}
.tb .sz-btn {
  background: #223246;
  color: #c7d9ed;
  border: 1.5px solid #3c5472;
}
.tb .sz-btn:hover {
  background: #2e445e;
  color: #fff;
}
.tb .sz-btn.active {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}
.tb .vw-btn {
  background: #223246;
  color: #fed7aa;
  border: 1.5px solid #475569;
}
.tb .vw-btn:hover {
  background: #334155;
  color: #fff;
}
.tb .vw-btn.active {
  background: #d97706;
  color: #fff;
  border-color: #d97706;
}
.tb .bk-btn {
  background: #475569;
  color: #f1f5f9;
}
.tb .bk-btn:hover {
  background: #334155;
}
.tb select {
  background: #223246;
  color: #fff;
  border: 1px solid #476585;
  border-radius: 4px;
  padding: 5px 8px;
  font: 500 12px/1 inherit;
}
.tb .tb-sep {
  width: 1px;
  height: 20px;
  background: rgba(255,255,255,0.2);
  margin: 0 4px;
}
.tb .print-tip {
  font-size: 11px;
  color: #94a3b8;
  margin-left: auto;
}
.tb .print-tip b {
  color: #f1f5f9;
}

/* === SPREAD / SHEET CONTAINERS === */
/* Standard A4 Landscape: 297mm x 204mm (safe printable bounds) */
.sh.a4l {
  width: 297mm;
  height: 204mm;
  margin: 16px auto;
  background: #fff;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 22px rgba(0,0,0,0.22);
  page-break-after: always;
  break-after: page;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}
.sh.a4l:last-child {
  page-break-after: avoid;
  break-after: avoid;
}

/* Screen fold guide indicator (A4 Landscape) */
.sh.a4l::after {
  content: '';
  position: absolute;
  left: 148.5mm;
  top: 0;
  bottom: 0;
  border-left: 1.5px dashed rgba(0, 0, 0, 0.16);
  pointer-events: none;
  z-index: 60;
}

/* Proportional A5 Landscape Mode: 210mm x 142mm (safe printable bounds) */
.sh.a5l {
  width: 210mm;
  height: 142mm;
  margin: 14px auto;
  background: #fff;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 18px rgba(0,0,0,0.22);
  page-break-after: always;
  break-after: page;
  box-sizing: border-box;
}
.sh.a5l:last-child {
  page-break-after: avoid;
  break-after: avoid;
}
.sh.a5l .sh-scaler {
  width: 297mm;
  height: 204mm;
  position: absolute;
  left: 0;
  top: 0;
  transform: scale(0.695);
  transform-origin: 0 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.sh.a5l::after {
  content: '';
  position: absolute;
  left: 105mm;
  top: 0;
  bottom: 0;
  border-left: 1px dashed rgba(0, 0, 0, 0.16);
  pointer-events: none;
  z-index: 60;
}

/* Base Panel */
.pn {
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}

/* === BACKGROUNDS & SPREAD DIMENSIONS === */
/* In official Maharashtra CCE reference PDF, outer spread card-01.png is sized to
   275.5mm x 188mm with 10.75mm white margins around the A4 paper */
.outer-spread {
  width: 275.5mm;
  height: 188mm;
  max-width: 275.5mm;
  max-height: 188mm;
  margin: auto;
  align-self: center;
  position: relative;
  background-image: url('<?= APP_URL ?>/<?= cce_get_bg_file() ?>?v=<?= @filemtime(__DIR__ . '/../' . cce_get_bg_file()) ?>');
  background-size: 275.5mm 188mm;
  background-repeat: no-repeat;
  background-position: center top;
  display: flex;
  flex-shrink: 0;
}
.inner-spread {
  width: 297mm;
  height: 204mm;
  display: flex;
  background: #ffffff;
  flex-shrink: 0;
  overflow: hidden;
}

/* === PRINT STYLES === */
@media print {
  html, body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    height: auto !important;
    overflow: visible !important;
  }
  .tb {
    display: none !important;
  }
  .sh {
    margin: 0 auto !important;
    box-shadow: none !important;
    border: none !important;
    page-break-after: always !important;
    break-after: page !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
  }
  .sh.a4l {
    width: 297mm !important;
    height: 204mm !important;
    max-height: 204mm !important;
    max-width: 297mm !important;
  }
  .sh.a5l {
    width: 210mm !important;
    height: 142mm !important;
    max-height: 142mm !important;
    max-width: 210mm !important;
  }
  .sh.a5l .sh-scaler {
    width: 297mm !important;
    height: 204mm !important;
    transform: scale(0.695) !important;
    transform-origin: 0 0 !important;
  }
  .inner-spread {
    width: 297mm !important;
    height: 204mm !important;
    max-height: 204mm !important;
  }
  .pn-inside {
    height: 204mm !important;
    max-height: 204mm !important;
  }
  .pn-inside.pn-left {
    padding: 6.5mm 7.8mm 6.5mm 11.5mm !important;
  }
  .pn-inside.pn-right {
    padding: 6.5mm 11.7mm 6.5mm 7.6mm !important;
  }
  table.sem-table {
    height: 140mm !important;
  }
  .sem-sign-row {
    margin-top: 8mm !important;
  }
  .sh:last-child,
  .sh:last-of-type {
    page-break-after: avoid !important;
    break-after: avoid !important;
  }
  .sh::after {
    display: none !important;
  }
}
</style>

<?php if ($is_a5): ?>
<style>
@page {
  size: A5 landscape;
  margin: 0;
}
</style>
<?php else: ?>
<style>
@page {
  size: A4 landscape;
  margin: 0;
}
</style>
<?php endif; ?>

<style>
/* =========================================================
   PANEL 1: BACK COVER (LEFT OF SHEET 1)
   निकाल दिनांक, उपस्थिती तक्ता, आरोग्यविषयक तक्ता
   ========================================================= */
.p-back {
  width: 138mm;
  height: 188mm;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}

/* Result Date (Over green header pill, top left) */
.bk-res-date {
  position: absolute;
  left: 2.5mm;
  top: 4.8mm;
  font-size: 13px;
  font-weight: 700;
  color: #111;
  z-index: 10;
  letter-spacing: 0.2px;
}

/* Attendance Section Header */
.bk-att-title {
  position: absolute;
  left: 2.5mm;
  top: 12.0mm;
  font-size: 15px;
  font-weight: 700;
  color: #111;
  z-index: 10;
}

/* Attendance Table */
.bk-att-wrap {
  position: absolute;
  left: 2.5mm;
  width: 125.5mm;
  top: 18.0mm;
  z-index: 10;
}
table.bk-att-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  border: 1.2px solid #00bcd4;
}
table.bk-att-table th,
table.bk-att-table td {
  border: 1.2px solid #00bcd4;
  padding: 1.2px 1px;
  text-align: center;
  line-height: 1.2;
  font-size: 12.5px;
  vertical-align: middle;
}
table.bk-att-table thead th {
  background: #7fe1eb;
  color: #0c3340;
  font-weight: 700;
  font-size: 11px;
  padding: 2.2px 1px;
  line-height: 1.15;
}
table.bk-att-table td.mn,
table.bk-att-table td.dy {
  font-weight: 700;
  font-size: 13px;
  color: #111;
}
table.bk-att-table td.sign-cell {
  background: rgba(255, 255, 255, 0.4);
}

/* Health Details Section (Back Cover, Left side, below attendance table) */
.bk-hlth-wrap {
  position: absolute;
  left: 2.5mm;
  width: 125.5mm;
  top: 90mm;
  z-index: 10;
}
table.bk-hlth-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  background: rgba(255, 255, 255, 0.95);
  border: 1.2px solid #00bcd4;
  border-radius: 4px;
}
table.bk-hlth-table th,
table.bk-hlth-table td {
  border: 1.2px solid #00bcd4;
  padding: 2px 6px;
  line-height: 1.25;
}
table.bk-hlth-table th {
  font-size: 12.5px;
  font-weight: 700;
  color: #0c3340;
  background: #7fe1eb;
  text-align: center;
}
table.bk-hlth-table th.hl-head-title {
  text-align: left;
  color: #8c271e;
  font-size: 13px;
  padding-left: 8px;
  background: #e0f7fa;
}
table.bk-hlth-table td.hl-lbl {
  color: #8c271e;
  font-weight: 700;
  text-align: left;
  white-space: nowrap;
  font-size: 13px;
  width: 38%;
  padding-left: 8px;
}
table.bk-hlth-table td.hl-val {
  color: #0b3b60;
  font-weight: 700;
  text-align: center;
  font-size: 14px;
  width: 31%;
}

/* =========================================================
   PANEL 2: FRONT COVER (RIGHT OF SHEET 1)
   UDISE, Logo, School Name, Student Info Table
   ========================================================= */
.p-front {
  width: 137.5mm;
  height: 188mm;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}

/* =========================================================
   PANEL 2: FRONT COVER — PRESTIGIOUS SCHOOL BANNER & INFO
   ========================================================= */
.fc-school-card {
  position: absolute;
  left: 6.5mm;
  right: 6.5mm;
  top: 54mm;
  z-index: 20;
  text-align: center;
  background: #ffffff;
  border: 1.5px solid #d4af37;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  padding: 2.2mm 3.5mm;
  box-sizing: border-box;
}

.fc-school-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3.5mm;
}

.fc-school-logo-wrap {
  width: 14.5mm;
  height: 14.5mm;
  border-radius: 50%;
  background: #ffffff;
  border: 1.5px solid #d4af37;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.fc-school-logo-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.fc-school-logo-placeholder {
  color: #1e3a8a;
  font-size: 20px;
}

.fc-school-content {
  flex: 1;
  text-align: center;
  min-width: 0;
}

.fc-school-tagline {
  font-size: 11px;
  font-weight: 700;
  color: #13644B; /* Rich saffron gold */
  letter-spacing: 0.6px;
  line-height: 1.15;
  margin-bottom: 1.5px;
}

.fc-school-name {
  font-size: 22px;
  font-weight: 800;
  color: #0c2340; /* Deep royal navy */
  line-height: 1.18;
  letter-spacing: 0.25px;
  margin-top: 1px;
  text-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
}

.fc-school-meta {
  font-size: 12px;
  font-weight: 700;
  color: #334155;
  margin-top: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2mm;
  flex-wrap: wrap;
  line-height: 1.2;
}

.fc-meta-loc {
  color: #1e293b;
}

.fc-meta-sep {
  color: #d4af37;
  font-weight: 900;
}

.fc-meta-udise {
  color: #0369a1;
  font-family: 'Utsaah', 'Mukta', monospace;
  font-size: 13px;
  font-weight: 700;
}

.fc-meta-year {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  border-radius: 4px;
  padding: 0.5px 6px;
  font-size: 11.5px;
  font-weight: 700;
}

.fc-school-divider {
  display: none;
}

/* Student Information Block (Kept down, clean spacing) */
.fc-student-box {
  position: absolute;
  left: 7.5mm;
  right: 5mm;
  top: 80mm;
  z-index: 10;
}
table.fc-info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
table.fc-info-table td {
  padding: 1.2px 1px;
  vertical-align: top;
  line-height: 1.35;
}
table.fc-info-table td.lbl {
  color: #8c271e;
  font-weight: 700;
  white-space: nowrap;
  font-size: 14px;
}
table.fc-info-table td.val {
  color: #0d3257;
  font-weight: 700;
  font-size: 14px;
}
table.fc-info-table td.val.big-name {
  font-size: 16px;
  font-weight: 700;
  color: #082442;
}
table.fc-info-table td.val.addr-text {
  font-size: 13px;
  font-weight: 700;
  line-height: 1.22;
}

/* =========================================================
   SHEET 2: INSIDE SPREAD (भाग २)
   प्रथम सत्र (Left) & द्वितीय सत्र (Right)
   100% तंतोतंत: media_1789887104523.png व PDF प्रमाणे
   ========================================================= */
.pn-inside {
  width: 148.5mm;
  height: 204mm;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  box-sizing: border-box;
}
/* Exact margins: Left page (11.5mm left margin, 7.8mm inner margin to fold) */
.pn-inside.pn-left {
  padding: 7.5mm 7.8mm 7.5mm 11.5mm;
}
/* Exact margins: Right page (7.6mm inner margin from fold, 11.7mm right margin) */
.pn-inside.pn-right {
  padding: 7.5mm 11.7mm 7.5mm 7.6mm;
}

/* Session Title */
.sem-head {
  text-align: center;
  font-size: 20px;
  font-weight: 700;
  color: #c2185b;
  margin-bottom: 2mm;
  letter-spacing: 0.5px;
}

/* Student Meta Line above Table */
.sem-meta {
  font-size: 14px;
  margin-bottom: 2.5mm;
  line-height: 1.38;
  color: #111;
}
.sem-meta .m-name {
  margin-bottom: 1px;
}
.sem-meta .m-name b {
  font-weight: 700;
  color: #000;
  font-size: 15.5px;
}
.sem-meta .m-row {
  display: flex;
  justify-content: space-between;
}
.sem-meta .m-row b {
  font-weight: 700;
  color: #000;
  font-size: 15px;
}

/* Inside Table Architecture: Exact 145mm height matching PDF stream */
table.sem-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  border: 1.2px solid #00bcd4;
  height: 145mm;
}
table.sem-table thead tr {
  height: 9mm;
}
table.sem-table tbody tr {
  height: 15.1mm;
}
table.sem-table th,
table.sem-table td {
  border: 1.2px solid #00bcd4;
  padding: 1px 2px;
  text-align: center;
  vertical-align: middle;
}
table.sem-table thead th {
  background: #6fe0ee;
  color: #000;
  font-weight: 700;
  font-size: 14px;
  padding: 3px 2px;
  height: 9mm;
}

/* Row Background Grouping (Rows 1-3 cyan tint, 4-6 white, 7-9 cyan tint) */
table.sem-table tr.bg-tint td.sb-no,
table.sem-table tr.bg-tint td.sb-name,
table.sem-table tr.bg-tint td.sb-grd {
  background: #e0f7fa;
}
table.sem-table tr.bg-white td.sb-no,
table.sem-table tr.bg-white td.sb-name,
table.sem-table tr.bg-white td.sb-grd {
  background: #ffffff;
}

table.sem-table td.sb-no {
  font-weight: 700;
  font-size: 14px;
  color: #111;
}
table.sem-table td.sb-name {
  text-align: center;
  font-weight: 700;
  font-size: 15px;
  color: #111;
}
table.sem-table td.sb-grd {
  font-weight: 700;
  font-size: 15.5px;
  color: #000;
}

/* Column 4: Descriptive Notes Subheaders & Merged Cells */
table.sem-table td.note-head-cell {
  background: #d6f5f9;
  color: #000;
  font-size: 14.5px;
  font-weight: 700;
  text-align: center;
  vertical-align: middle;
  height: 25px;
}
table.sem-table td.note-body-cell {
  background: #ffffff;
  color: #111;
  font-size: 13.5px;
  font-weight: 500;
  line-height: 1.35;
  text-align: center;
  vertical-align: middle;
  padding: 3px 8px;
}

/* Signatures Row: Ample space for physical signatures/stamps */
.sem-sign-row {
  display: flex;
  justify-content: space-between;
  margin-top: 9mm;
  padding: 0 4mm;
}
.sem-sign-item {
  flex: 1;
  text-align: center;
  font-size: 13.5px;
  font-weight: 700;
  color: #111;
}
</style>
</head>
<body class="pragati-pustak-body">

<!-- TOP INTERACTIVE TOOLBAR -->
<div class="tb">
  <button class="btn-print" onclick="window.print()">🖨 छापा / PDF सेव्ह करा</button>

  <div class="tb-sep"></div>

  <!-- Size Toggles: A4 Landscape & A5 Landscape -->
  <a class="btn-link sz-btn <?= !$is_a5 ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['size' => 'a4'])) ?>">
    📄 A4 Landscape (बुकलेट)
  </a>
  <a class="btn-link sz-btn <?= $is_a5 ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['size' => 'a5'])) ?>">
    📋 A5 Landscape (लहान बुकलेट)
  </a>

  <div class="tb-sep"></div>

  <!-- Sheet View Toggles: All, Sheet 1 only (Cover), Sheet 2 only (Inside) -->
  <a class="btn-link vw-btn <?= $view === 'all' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view' => 'all'])) ?>">
    📑 सर्व पाने (Sheet 1+2)
  </a>
  <a class="btn-link vw-btn <?= $view === 'sheet1' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view' => 'sheet1'])) ?>">
    🎴 फक्त कव्हर (Sheet 1)
  </a>
  <a class="btn-link vw-btn <?= $view === 'sheet2' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['view' => 'sheet2'])) ?>">
    📖 फक्त आतले पान (Sheet 2)
  </a>

  <div class="tb-sep"></div>

  <!-- Standard Switcher -->
  <select onchange="location.href='?std=' + this.value + '&all=1&size=<?= $size ?>&view=<?= $view ?>'">
    <?php foreach ($standards as $st_num => $st_lbl): ?>
      <option value="<?= (int)$st_num ?>" <?= (int)$st_num === $std ? 'selected' : '' ?>>
        इयत्ता <?= sanitize($st_lbl) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- Student Switcher -->
  <select onchange="location.href=this.value">
    <option value="?std=<?= $std ?>&all=1&size=<?= $size ?>&view=<?= $view ?>" <?= $all ? 'selected' : '' ?>>
      👥 सर्व विद्यार्थी (<?= count($all_students_for_std) ?>)
    </option>
    <?php foreach ($all_students_for_std as $st_opt): ?>
      <option value="?std=<?= $std ?>&student_id=<?= (int)$st_opt['id'] ?>&size=<?= $size ?>&view=<?= $view ?>" <?= (!$all && $sid_get === (int)$st_opt['id']) ? 'selected' : '' ?>>
        <?= sanitize($st_opt['roll_no'] ? ($st_opt['roll_no'] . '. ') : '') ?><?= sanitize($st_opt['name_mr'] ?: $st_opt['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <div class="tb-sep"></div>

  <!-- Color Theme, Font & Cover Template Controls -->
  <?= cce_theme_toolbar_controls(true) ?>

  <div class="tb-sep"></div>

  <a class="btn-link bk-btn" href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>">
    ← अहवाल यादी
  </a>

  <span class="print-tip">
    💡 PDF सेव्ह करताना: Destination: <b>Save as PDF</b> | Margins: <b>None</b> | <b>Background graphics</b> चेक करा.
  </span>
</div>

<?php
// Bulk prefetch all data for students to eliminate N+1 queries (180+ queries -> 4 fast queries)
$student_ids = array_map(fn($stu) => (int)$stu['id'], $list);
$allMarks = [];
$allNotes = [];
$allExtra = [];
$allAtt = [];

if (!empty($student_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    
    // 1. Bulk marks
    try {
        $stmt_m = $db->prepare("SELECT * FROM cce_marks WHERE student_id IN ($in_placeholders) AND academic_year = ?");
        $stmt_m->execute(array_merge($student_ids, [$year]));
        foreach ($stmt_m->fetchAll() as $r) {
            $sid = (int)$r['student_id'];
            $sem = (int)$r['semester'];
            $sbid = (int)$r['subject_id'];
            $allMarks[$sid][$sem][$sbid] = $r;
        }
    } catch (Exception $e) {}

    // 2. Bulk notes
    try {
        $stmt_n = $db->prepare("SELECT student_id, semester, slot_no, note_text FROM cce_student_notes WHERE student_id IN ($in_placeholders) AND academic_year = ?");
        $stmt_n->execute(array_merge($student_ids, [$year]));
        foreach ($stmt_n->fetchAll() as $r) {
            $sid = (int)$r['student_id'];
            $sem = (int)$r['semester'];
            $slot = (int)$r['slot_no'];
            if (trim($r['note_text'] ?? '') !== '') {
                $allNotes[$sid][$sem][$slot] = $r['note_text'];
            }
        }
    } catch (Exception $e) {}

    // 3. Bulk extra
    try {
        $stmt_x = $db->prepare("SELECT * FROM cce_student_extra WHERE student_id IN ($in_placeholders)");
        $stmt_x->execute($student_ids);
        foreach ($stmt_x->fetchAll() as $r) {
            $allExtra[(int)$r['student_id']] = $r;
        }
    } catch (Exception $e) {}

    // 4. Bulk attendance
    try {
        $stmt_a = $db->prepare("SELECT * FROM attendance WHERE student_id IN ($in_placeholders) AND academic_year = ?");
        $stmt_a->execute(array_merge($student_ids, [$year]));
        foreach ($stmt_a->fetchAll() as $r) {
            $sid = (int)$r['student_id'];
            $m = (int)$r['month'];
            $allAtt[$sid][$m] = $r;
        }
    } catch (Exception $e) {}
}

foreach ($list as $stu):
    $sid = (int)$stu['id'];
    $nm = $stu['name_mr'] ?: $stu['name'];
    $m1 = $allMarks[$sid][1] ?? (function_exists('ppM') ? ppM($db, $sid, $year, 1) : []);
    $m2 = $allMarks[$sid][2] ?? (function_exists('ppM') ? ppM($db, $sid, $year, 2) : []);
    $n1 = $allNotes[$sid][1] ?? (function_exists('ppN') ? ppN($db, $sid, $year, 1) : []);
    $n2 = $allNotes[$sid][2] ?? (function_exists('ppN') ? ppN($db, $sid, $year, 2) : []);
    $ex = $allExtra[$sid] ?? (function_exists('ppX') ? ppX($db, $sid) : []);
    $at = $allAtt[$sid] ?? (function_exists('ppA') ? ppA($db, $sid, $year) : []);

    $dob = $stu['date_of_birth'] ?? $stu['dob'] ?? '';
    $dobf = ($dob && $dob !== '0000-00-00') ? date('d-m-Y', strtotime($dob)) : '—';
    $age = ppAge($dob);

    // Height & Weight
    $w1 = trim(($ex['weight_1'] ?? '') ?: ($stu['weight_sem1'] ?? ''));
    $w2 = trim(($ex['weight_2'] ?? '') ?: ($stu['weight_sem2'] ?? ''));
    $h1 = trim(($ex['height_1'] ?? '') ?: ($stu['height_sem1'] ?? ''));
    $h2 = trim(($ex['height_2'] ?? '') ?: ($stu['height_sem2'] ?? ''));
    if ($w1 === '0' || $w1 === '0.00') $w1 = '';
    if ($w2 === '0' || $w2 === '0.00') $w2 = '';
    if ($h1 === '0' || $h1 === '0.00') $h1 = '';
    if ($h2 === '0' || $h2 === '0.00') $h2 = '';

    // Identification & Meta
    $roll = $stu['roll_no'] ?: '—';
    $sec = $stu['section'] ?? '';
    $reg = $stu['registration_no'] ?: ($stu['gr_no'] ?: '—');
    $apaar = $stu['student_pen'] ?: ($stu['apaar_id'] ?: ($stu['apaar'] ?: ($stu['saral_id'] ?: '')));
    $moth = $stu['mother_name'] ?: '—';
    $fath = $stu['father_name'] ?: '—';
    $mtng = $stu['mother_tongue'] ?: 'मराठी';
    $med = $stu['medium_of_instruction'] ?: ($stu['medium'] ?: 'मराठी');
    $stl = std_label($std);

    // Address
    $addr = trim($stu['address'] ?? $stu['address_line1'] ?? '');
    if (!$addr) {
        $v = trim($stu['village'] ?? '');
        $t = trim($stu['birth_taluka'] ?? $school['taluka'] ?? '');
        $d = trim($stu['birth_district'] ?? $school['district'] ?? '');
        $addr = implode(' , ', array_filter([
            $v ? ('मु. पो. ' . $v) : '',
            $t ? ('ता. ' . $t) : '',
            $d ? ('जि. ' . $d . '.') : ''
        ]));
    }

    // Descriptive Notes fallback (Sem 1)
    $n1_vishesh = trim($n1[10] ?? '');
    $n1_aavad   = trim($n1[11] ?? '');
    $n1_sudhar  = trim($n1[12] ?? '');
    if ($n1_vishesh === '' && !empty($n1[13])) $n1_vishesh = trim($n1[13]);

    // Descriptive Notes fallback (Sem 2)
    $n2_vishesh = trim($n2[10] ?? '');
    $n2_aavad   = trim($n2[11] ?? '');
    $n2_sudhar  = trim($n2[12] ?? '');
    if ($n2_vishesh === '' && !empty($n2[13])) $n2_vishesh = trim($n2[13]);
?>

<?php if ($view === 'all' || $view === 'sheet1'): ?>
<!-- ══════════════════════════════════════════════════════════════
     SHEET 1: OUTER / COVER SPREAD (भाग १)
     Left: Back Cover (Attendance + Health)
     Right: Front Cover (School Banner + Student Info)
     ══════════════════════════════════════════════════════════════ -->
<div class="sh <?= $is_a5 ? 'a5l' : 'a4l' ?>">
  <?php if ($is_a5): ?><div class="sh-scaler"><?php endif; ?>

  <div class="outer-spread">

    <!-- LEFT PANEL: BACK COVER -->
    <div class="p-back">
      <div class="bk-res-date">
        निकाल दिनांक : <?= sanitize($res_date_val) ?>
      </div>

      <div class="bk-att-title">
        ● उपस्थिती :
      </div>

      <div class="bk-att-wrap">
        <table class="bk-att-table">
          <thead>
            <tr>
              <th style="width:12%">महिना</th>
              <th style="width:14%">कामाचे<br>दिवस</th>
              <th style="width:14%">हजर<br>दिवस</th>
              <th style="width:20%">वर्गशिक्षक<br>स्वाक्षरी</th>
              <th style="width:20%">पालक<br>स्वाक्षरी</th>
              <th style="width:20%">मुख्याध्यापक<br>स्वाक्षरी</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $idx = 0;
          foreach ($months as $mn => $lbl):
              $w = isset($at[$mn]) && (int)$at[$mn]['working_days'] > 0 ? (int)$at[$mn]['working_days'] : '-';
              $p = isset($at[$mn]) && (int)$at[$mn]['days_present'] >= 0 && $w !== '-' ? (int)$at[$mn]['days_present'] : '-';
          ?>
            <tr>
              <td class="mn"><?= sanitize($lbl) ?></td>
              <td class="dy"><?= $w ?></td>
              <td class="dy"><?= $p ?></td>
              <?php if ($idx === 0): ?>
                <td class="sign-cell" rowspan="4"></td>
                <td class="sign-cell" rowspan="4"></td>
                <td class="sign-cell" rowspan="12"></td>
              <?php elseif ($idx === 4): ?>
                <td class="sign-cell" rowspan="2"></td>
                <td class="sign-cell" rowspan="2"></td>
              <?php elseif ($idx === 6): ?>
                <td class="sign-cell" rowspan="4"></td>
                <td class="sign-cell" rowspan="4"></td>
              <?php elseif ($idx === 10): ?>
                <td class="sign-cell" rowspan="2"></td>
                <td class="sign-cell" rowspan="2"></td>
              <?php endif; ?>
            </tr>
          <?php
              $idx++;
          endforeach;
          ?>
          </tbody>
        </table>
      </div>

      <!-- आरोग्यविषयक तक्ता (डाव्या बाजूस उपस्थिती तक्त्याखाली) -->
      <div class="bk-hlth-wrap">
        <table class="bk-hlth-table">
          <thead>
            <tr>
              <th class="hl-head-title" style="width:38%">● आरोग्यविषयक :</th>
              <th style="width:31%">प्रथम सत्र</th>
              <th style="width:31%">द्वितीय सत्र</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="hl-lbl">वजन किग्रॅ &nbsp;:</td>
              <td class="hl-val"><?= sanitize(ppMrNum($w1)) ?></td>
              <td class="hl-val"><?= sanitize(ppMrNum($w2)) ?></td>
            </tr>
            <tr>
              <td class="hl-lbl">उंची सेंमी &nbsp;:</td>
              <td class="hl-val"><?= sanitize(ppMrNum($h1)) ?></td>
              <td class="hl-val"><?= sanitize(ppMrNum($h2)) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div><!-- /p-back -->

    <!-- RIGHT PANEL: FRONT COVER -->
    <div class="p-front">
      <!-- Prestigious School Header (Below 3D Pragati Patrak Banner) -->
      <div class="fc-school-card">
        <div class="fc-school-row">
          <div class="fc-school-logo-wrap">
            <?php if ($s_logo): ?>
              <img src="<?= sanitize($s_logo) ?>" alt="Logo" class="fc-school-logo-img">
            <?php else: ?>
              <span class="fc-school-logo-placeholder"><i class="bi bi-mortarboard-fill"></i>🏛</span>
            <?php endif; ?>
          </div>

          <div class="fc-school-content">
            <div class="fc-school-tagline">॥ विद्या विनयेन शोभते ॥ • महाराष्ट्र शासन मान्य</div>
            <div class="fc-school-name"><?= sanitize($s_name) ?></div>
            <div class="fc-school-meta">
              <?php if ($s_loc): ?>
                <span class="fc-meta-loc"><?= sanitize($s_loc) ?></span>
              <?php endif; ?>
              <?php if ($s_udise): ?>
                <span class="fc-meta-sep">•</span>
                <span class="fc-meta-udise">UDISE : <?= sanitize($s_udise) ?></span>
              <?php endif; ?>
              <span class="fc-meta-sep">•</span>
              <span class="fc-meta-year">सन : <?= sanitize($year) ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="fc-student-box">
        <table class="fc-info-table">
          <tr>
            <td class="lbl" style="width:23%">स्टुडेंट आयडी :</td>
            <td class="val" colspan="3"><?= sanitize($apaar) ?></td>
          </tr>
          <tr>
            <td class="lbl">नाव :</td>
            <td class="val big-name" colspan="3"><?= sanitize($nm) ?></td>
          </tr>
          <tr>
            <td class="lbl" style="width:23%">हजेरी क्र. :</td>
            <td class="val" style="width:27%"><?= sanitize($roll) ?></td>
            <td class="lbl" style="width:20%">रजि. नं :</td>
            <td class="val" style="width:30%"><?= sanitize($reg) ?></td>
          </tr>
          <tr>
            <td class="lbl">इयत्ता :</td>
            <td class="val"><?= sanitize($stl) ?></td>
            <td class="lbl">तुकडी :</td>
            <td class="val"><?= sanitize($sec ?: '—') ?></td>
          </tr>
          <tr>
            <td class="lbl">जन्मतारीख :</td>
            <td class="val"><?= sanitize($dobf) ?></td>
            <td class="lbl">वय :</td>
            <td class="val"><?= sanitize($age ?: '—') ?></td>
          </tr>
          <tr>
            <td class="lbl">मातृभाषा :</td>
            <td class="val"><?= sanitize($mtng) ?></td>
            <td class="lbl">माध्यम :</td>
            <td class="val"><?= sanitize($med) ?></td>
          </tr>
          <tr>
            <td class="lbl">आईचे नाव :</td>
            <td class="val" colspan="3"><?= sanitize($moth) ?></td>
          </tr>
          <tr>
            <td class="lbl">वडिलांचे नाव :</td>
            <td class="val" colspan="3"><?= sanitize($fath) ?></td>
          </tr>
          <tr>
            <td class="lbl">पत्ता :</td>
            <td class="val addr-text" colspan="3"><?= sanitize($addr) ?></td>
          </tr>
        </table>
      </div>
    </div><!-- /p-front -->

  </div><!-- /outer-spread -->

  <?php if ($is_a5): ?></div><?php endif; ?>
</div><!-- /Sheet 1 -->
<?php endif; ?>


<?php if ($view === 'all' || $view === 'sheet2'): ?>
<!-- ══════════════════════════════════════════════════════════════
     SHEET 2: INSIDE SPREAD (भाग २)
     Left: प्रथम सत्र (Semester 1)
     Right: द्वितीय सत्र (Semester 2)
     100% तंतोतंत: media_1789887104523.png प्रमाणे
     ══════════════════════════════════════════════════════════════ -->
<div class="sh <?= $is_a5 ? 'a5l' : 'a4l' ?>">
  <?php if ($is_a5): ?><div class="sh-scaler"><?php endif; ?>

  <div class="inner-spread">

    <!-- ── LEFT PANEL: प्रथम सत्र ── -->
    <div class="pn pn-inside pn-left">
      <div class="sem-head">● प्रथम सत्र ●</div>

      <div class="sem-meta">
        <div class="m-name">विद्यार्थ्याचे नाव : <b><?= sanitize($nm) ?></b></div>
        <div class="m-row">
          <span>इयत्ता : <b><?= sanitize($stl) ?></b></span>
          <span>तुकडी : <b><?= sanitize($sec ?: '—') ?></b></span>
          <span>हजेरी क्र.: <b><?= sanitize($roll) ?></b></span>
        </div>
      </div>

      <table class="sem-table">
        <thead>
          <tr>
            <th style="width:8%">अनं.</th>
            <th style="width:22%">विषय</th>
            <th style="width:14%">श्रेणी</th>
            <th style="width:56%">वर्णनात्मक नोंदी</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($subjects as $i => $sb):
            $sb_id = (int)$sb['id'];
            $mk = ($sb_id > 0 && isset($m1[$sb_id])) ? $m1[$sb_id] : null;
            $gr = $mk ? trim($mk['grade'] ?? '') : '';
            if (!$gr && $sb_id > 0) $gr = '—';
            $bg_cls = ($i <= 2 || $i >= 6) ? 'bg-tint' : 'bg-white';
        ?>
          <tr class="<?= $bg_cls ?>">
            <td class="sb-no"><?= ($i + 1) ?></td>
            <td class="sb-name"><?= sanitize($sb['name_mr'] ?: ($sb['short_name'] ?: '')) ?></td>
            <td class="sb-grd"><?= sanitize($gr) ?></td>

            <?php if ($i === 0): ?>
              <td class="note-head-cell">विशेष प्रगती</td>
            <?php elseif ($i === 1): ?>
              <td rowspan="2" class="note-body-cell"><?= sanitize($n1_vishesh) ?></td>
            <?php elseif ($i === 3): ?>
              <td class="note-head-cell">आवड छंद</td>
            <?php elseif ($i === 4): ?>
              <td rowspan="2" class="note-body-cell"><?= sanitize($n1_aavad) ?></td>
            <?php elseif ($i === 6): ?>
              <td class="note-head-cell">सुधारणा आवश्यक</td>
            <?php elseif ($i === 7): ?>
              <td rowspan="2" class="note-body-cell"><?= sanitize($n1_sudhar) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="sem-sign-row">
        <div class="sem-sign-item">वर्गशिक्षक स्वाक्षरी</div>
        <div class="sem-sign-item">मुख्याध्यापक स्वाक्षरी</div>
        <div class="sem-sign-item">पालक स्वाक्षरी</div>
      </div>
    </div><!-- /Inside Left -->

    <!-- ── RIGHT PANEL: द्वितीय सत्र ── -->
    <div class="pn pn-inside pn-right">
      <div class="sem-head">● द्वितीय सत्र ●</div>

      <div class="sem-meta">
        <div class="m-name">विद्यार्थ्याचे नाव : <b><?= sanitize($nm) ?></b></div>
        <div class="m-row">
          <span>इयत्ता : <b><?= sanitize($stl) ?></b></span>
          <span>तुकडी : <b><?= sanitize($sec ?: '—') ?></b></span>
          <span>हजेरी क्र.: <b><?= sanitize($roll) ?></b></span>
        </div>
      </div>

      <table class="sem-table">
        <thead>
          <tr>
            <th style="width:8%">अनं.</th>
            <th style="width:22%">विषय</th>
            <th style="width:14%">श्रेणी</th>
            <th style="width:56%">वर्णनात्मक नोंदी</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($subjects as $i => $sb):
            $sb_id = (int)$sb['id'];
            $mk = ($sb_id > 0 && isset($m2[$sb_id])) ? $m2[$sb_id] : null;
            $gr = $mk ? trim($mk['grade'] ?? '') : '';
            if (!$gr && $sb_id > 0) $gr = '—';
            $bg_cls = ($i <= 2 || $i >= 6) ? 'bg-tint' : 'bg-white';
        ?>
          <tr class="<?= $bg_cls ?>">
            <td class="sb-no"><?= ($i + 1) ?></td>
            <td class="sb-name"><?= sanitize($sb['name_mr'] ?: ($sb['short_name'] ?: '')) ?></td>
            <td class="sb-grd"><?= sanitize($gr) ?></td>

            <?php if ($i === 0): ?>
              <td class="note-head-cell">विशेष प्रगती</td>
            <?php elseif ($i === 1): ?>
              <td rowspan="2" class="note-body-cell"><?= sanitize($n2_vishesh) ?></td>
            <?php elseif ($i === 3): ?>
              <td class="note-head-cell">आवड छंद</td>
            <?php elseif ($i === 4): ?>
              <td rowspan="2" class="note-body-cell"><?= sanitize($n2_aavad) ?></td>
            <?php elseif ($i === 6): ?>
              <td class="note-head-cell">सुधारणा आवश्यक</td>
            <?php elseif ($i === 7): ?>
              <td rowspan="2" class="note-body-cell"><?= sanitize($n2_sudhar) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="sem-sign-row">
        <div class="sem-sign-item">वर्गशिक्षक स्वाक्षरी</div>
        <div class="sem-sign-item">मुख्याध्यापक स्वाक्षरी</div>
        <div class="sem-sign-item">पालक स्वाक्षरी</div>
      </div>
    </div><!-- /Inside Right -->

  </div><!-- /inner-spread -->

  <?php if ($is_a5): ?></div><?php endif; ?>
</div><!-- /Sheet 2 -->
<?php endif; ?>

<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/anti_copy.php'; ?>
</body>
</html>
