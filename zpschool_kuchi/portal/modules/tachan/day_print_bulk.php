<?php
declare(strict_types=1);

/**
 * Smart School Hub — Bulk Day-Wise Lesson Plan Print Engine (एकत्रित दिवसनिहाय टाचण छपाई)
 * Prints each day as a complete, separate A4 portrait lesson plan as per timetable.
 * Supports:
 * - Selected dates list (dates=YYYY-MM-DD,YYYY-MM-DD,...)
 * - Date range (start_date=... & end_date=...)
 * - Entire month (month=... & year=...)
 * - Teacher timetable mode vs Class mode
 * - Half-day / upto period filter
 */
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';

$u = require_login();
$sid = (int) $u['school_id'];
$pdo = db();

$school = school_row($sid) ?: [];
$schoolName = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));

// Freemium / Paid Check: Bulk download is strictly for users with separate module purchase or All-in-One
$isPaid = is_tachan_module_purchased($sid);
if (!$isPaid) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="mr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>स्वतंत्र टाचण मॉड्युल खरेदी आवश्यक — स्मार्ट स्कूल हब</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Mukta', -apple-system, sans-serif; background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .upgrade-box { max-width: 580px; width: 100%; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0; }
            .upgrade-top { background: linear-gradient(135deg, #E11D48 0%, #BE123C 100%); color: white; padding: 36px 24px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="upgrade-box">
            <div class="upgrade-top">
                <div class="display-5 mb-2">🔒</div>
                <h3 class="fw-bold mb-1">एकत्रित दिवसनिहाय टाचण छपाई (Bulk Print)</h3>
                <p class="text-white-50 mb-0 small">फक्त स्वतंत्र टाचण मॉड्युल किंवा ऑल-इन-वन पॅकसाठी उपलब्ध</p>
            </div>
            <div class="p-4 p-md-5 text-center">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill fw-bold mb-3">
                    <i class="bi bi-lock-fill me-1"></i> सशुल्क मॉड्युल (या सुविधेसाठी मोफत चाचणी लागू नाही)
                </span>
                <h5 class="fw-bold text-dark mb-2">एकत्रित डाऊनलोडसाठी स्वतंत्र मॉड्युल खरेदी आवश्यक</h5>
                <p class="text-muted mb-4 small" style="line-height: 1.8;">
                    सर्व जुने व भावी टाचण एकाच क्लिकवर दिवसनिहाय एकत्रित (Bulk PDF) डाऊनलोड व प्रिंट करण्याची सुविधा फक्त <strong>स्वतंत्र दैनिक टाचण वही मॉड्युल (₹४९९)</strong> किंवा <strong>ऑल-इन-वन शाळा पॅक (₹१,४९९)</strong> खरेदी केलेल्या ग्राहकांसाठीच उपलब्ध आहे.<br>
                    <strong>या विशेष सुविधेसाठी कोणतीही मोफत चाचणी (Trial) उपलब्ध नाही.</strong>
                </p>
                <div class="d-flex flex-column gap-2 mb-3">
                    <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=24" class="btn btn-warning fw-bold text-dark rounded-pill py-2.5 shadow-sm" style="background:#F59E0B; border-color:#F59E0B;">
                        <i class="bi bi-cart-check-fill me-1"></i> फक्त दैनिक टाचण वही मॉड्युल खरेदी करा (&#8377;४९९/वर्ष)
                    </a>
                    <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=4" class="btn btn-outline-primary fw-bold rounded-pill py-2.5">
                        <i class="bi bi-rocket-takeoff-fill me-1"></i> संपूर्ण शाळा ऑल-इन-वन पॅक (&#8377;१,४९९/वर्ष)
                    </a>
                    <a href="<?= APP_URL ?>/modules/tachan/backdate.php" class="btn btn-link text-muted text-decoration-none small mt-1">
                        <i class="bi bi-arrow-left me-1"></i> मागे जा
                    </a>
                </div>
                <div class="pt-3 border-top text-muted small">
                    📞 अधिक माहितीसाठी संपर्क: ९८२२९९५५३३ | TechGuruji Smart School Hub
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$allTeachers = school_teachers($sid);
$teacherById = [];
foreach ($allTeachers as $t) {
    $teacherById[(int) $t['id']] = $t;
}

$teacherId = !empty($_REQUEST['teacher_id']) && isset($teacherById[(int) $_REQUEST['teacher_id']])
    ? (int) $_REQUEST['teacher_id']
    : (int) $u['id'];

$classes = school_classes($sid);
$byId = [];
foreach ($classes as $c) {
    $byId[(int) $c['id']] = $c;
}

$pref = tachan_prefs_get($pdo, $sid, $teacherId);
$teacherTT = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
$hasTeacherTT = !empty($teacherTT);

$mode = isset($_REQUEST['mode']) && in_array($_REQUEST['mode'], ['teacher', 'class'], true)
    ? (string) $_REQUEST['mode']
    : (($pref['teach_type'] ?? '') === 'periodwise' || ($hasTeacherTT && !isset($_REQUEST['class_id'])) ? 'teacher' : 'class');

$sel = isset($_REQUEST['class_id']) && isset($byId[(int) $_REQUEST['class_id']]) 
    ? (int) $_REQUEST['class_id'] 
    : (int) ($pref['class_id'] ?? ($classes[0]['id'] ?? 0));
$class = $byId[$sel] ?? null;

$medium = isset($_REQUEST['medium']) 
    ? tachan_norm_medium((string) $_REQUEST['medium']) 
    : ($pref['medium'] ?: tachan_norm_medium((string) ($class['medium'] ?? 'marathi')));

$dayLen = isset($_REQUEST['dl']) && in_array($_REQUEST['dl'], ['full', 'half', 'upto'], true) 
    ? (string) $_REQUEST['dl'] 
    : ($pref['day_length'] ?: 'full');
$upto = isset($_REQUEST['upto']) && (int)$_REQUEST['upto'] > 0 ? max(1, min(9, (int) $_REQUEST['upto'])) : (int)($pref['upto_period'] ?: 8);

if (isset($_REQUEST['dl'])) {
    $dayLen = in_array($_REQUEST['dl'], ['full', 'half', 'upto'], true) ? (string) $_REQUEST['dl'] : 'full';
} elseif (isset($_REQUEST['upto']) && (int)$_REQUEST['upto'] > 0 && ($pref['day_length'] ?? '') === 'upto') {
    $dayLen = 'upto';
}

if ($dayLen === 'full') {
    $upto = 9;
} elseif ($dayLen === 'half') {
    $upto = 4;
} else {
    if ($upto < 4) {
        $upto = 8;
        $dayLen = 'full';
    }
}

$teacherName = resolve_teacher_name($mode === 'class' ? $class : null, $sid, $u);

// Gather dates to print
$dates = [];
if (!empty($_POST['selected_dates']) && is_array($_POST['selected_dates'])) {
    foreach ($_POST['selected_dates'] as $d) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d)) $dates[] = (string)$d;
    }
} elseif (!empty($_GET['dates'])) {
    foreach (explode(',', (string)$_GET['dates']) as $d) {
        $d = trim($d);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $dates[] = $d;
    }
} elseif (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $cur = strtotime((string)$_GET['start_date']);
    $end = strtotime((string)$_GET['end_date']);
    while ($cur <= $end) {
        $dates[] = date('Y-m-d', $cur);
        $cur = strtotime('+1 day', $cur);
    }
} elseif (!empty($_GET['month']) && !empty($_GET['year'])) {
    $m = max(1, min(12, (int)$_GET['month']));
    $y = (int)$_GET['year'];
    $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dates[] = sprintf('%04d-%02d-%02d', $y, $m, $day);
    }
} else {
    // Default: This week (Monday to Saturday)
    $mon = strtotime('monday this week');
    for ($i = 0; $i < 6; $i++) {
        $dates[] = date('Y-m-d', strtotime("+$i day", $mon));
    }
}

$dates = array_values(array_unique($dates));
sort($dates);

// Filter holidays and Sundays by default unless requested
$holStmt = $pdo->prepare('SELECT name FROM holidays WHERE school_id=? AND date=?');
$workingDays = [];

foreach ($dates as $d) {
    $dow = (int)date('N', strtotime($d));
    if ($dow === 7) continue; // Skip Sunday

    $holStmt->execute([$sid, $d]);
    $hol = $holStmt->fetchColumn();
    if ($hol !== false) continue; // Skip holiday

    $workingDays[] = [
        'date' => $d,
        'dow'  => $dow,
    ];
}

$dnames = [1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार', 7 => 'रविवार'];

print_start('एकत्रित दैनिक टाचण छपाई (Day-Wise Bulk Print)', [
    'compact' => true,
    'raw_header' => true,
    'margin' => '4mm 5mm 4mm 5mm'
]);
?>
<!-- Web Fonts for Marathi Educational Typography -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Martel:wght@600;700;800;900&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">

<?php if (isset($_GET['dl_pdf'])): ?>
<script>window.addEventListener('load', function(){ window.print(); });</script>
<?php endif; ?>

<style>
/* A4 Portrait: 1 day per page strictly */
@page {
    size: A4 portrait;
    margin: 4mm 5mm 4mm 5mm;
}
@media print {
    .no-print, body > header, .t-toolbar { display: none !important; }
    body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
    .tachan-day-page {
        page-break-after: always !important;
        break-after: page !important;
        width: 100% !important;
        max-width: 100% !important;
        height: 287mm !important;
        max-height: 287mm !important;
        margin: 0 !important;
        padding: 3mm 4mm !important;
        box-sizing: border-box !important;
    }
    .tachan-day-page:last-child {
        page-break-after: avoid !important;
        break-after: avoid !important;
    }
}

body {
    font-family: 'Mukta', 'Mukta', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
    color: #0f172a;
    background: #f1f5f9;
    margin: 0;
    padding: 0;
    line-height: 1.18;
}

/* Control toolbar on screen */
.t-toolbar {
    background: #1A365D;
    color: #fff;
    padding: 8px 14px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 13px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.t-toolbar a, .t-toolbar button, .t-toolbar select {
    font-size: 12px;
    border-radius: 4px;
}
.t-toolbar select {
    padding: 4px 8px;
    background: #fff;
    color: #1A365D;
    border: 1px solid #cbd5e1;
    font-weight: 600;
}
.btn-print {
    background: #D69E2E;
    color: #1A365D;
    font-weight: 700;
    border: none;
    padding: 6px 14px;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.btn-print:hover { background: #b7791f; }
.btn-zoom {
    background: rgba(255,255,255,0.18);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 4px 10px;
    cursor: pointer;
    font-weight: 600;
}

/* Day Container (A4 single page per day) */
.tachan-day-page {
    font-size: 8.5px;
    color: #0f172a;
    width: 200mm;
    min-height: 285mm;
    max-height: 288mm;
    margin: 12px auto;
    background: #fff;
    box-sizing: border-box;
    padding: 3mm 4mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

/* Font theme classes */
.font-mukta {
    font-family: 'Mukta', 'Mukta', -apple-system, BlinkMacSystemFont, sans-serif !important;
}
.font-mukta .t-title-main {
    font-family: 'Martel', 'Mukta', serif !important;
    font-weight: 800 !important;
}
.font-martel {
    font-family: 'Martel', 'Mukta', Georgia, serif !important;
}
.font-martel .t-title-main {
    font-family: 'Martel', serif !important;
    font-weight: 900 !important;
}
.font-noto {
    font-family: 'Mukta', -apple-system, BlinkMacSystemFont, sans-serif !important;
}
.font-noto .t-title-main {
    font-family: 'Mukta', sans-serif !important;
    font-weight: 800 !important;
}

/* Header matching official format */
.t-header-box {
    position: relative;
    margin-bottom: 4px;
    padding-bottom: 2px;
}
.t-badge-box {
    position: absolute;
    right: 0;
    top: 0;
    border: 1.5px solid #1e293b;
    padding: 1.5px 8px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 11px;
    background: #fff;
    color: #0f172a;
}
.t-title-main {
    text-align: center;
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
    line-height: 1.2;
    letter-spacing: 0.2px;
}
.t-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.25;
}
.t-meta-row + .t-meta-row {
    margin-top: 2px;
}
.t-suvichar-text {
    max-width: 75%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Upakram strip */
.t-extra-strip {
    font-size: 8px;
    font-weight: 600;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    padding: 1.5px 4px;
    margin: 2px 0 3px 0;
    color: #334155;
    line-height: 1.2;
}

/* Data Table Grid (6 columns) */
table.grid {
    font-size: 8.5px;
    line-height: 1.16;
    table-layout: fixed;
    width: 100%;
    border-collapse: collapse;
    flex-grow: 1;
}
table.grid th, table.grid td {
    border: 1px solid #334155;
    padding: 2px 3px;
    vertical-align: top;
    word-wrap: break-word;
    overflow-wrap: anywhere;
}
table.grid th {
    background: #f1f5f9;
    color: #0f172a;
    text-align: center;
    font-weight: 700;
    font-size: 8.5px;
    padding: 2.5px 2px;
    letter-spacing: 0.1px;
}
table.grid td {
    font-weight: 500;
    color: #1e293b;
}
table.grid tr {
    break-inside: avoid;
    page-break-inside: avoid;
}
.t-slot {
    font-weight: 700;
    color: #0f172a;
    line-height: 1.15;
}
.t-slot .cls {
    font-size: 8px;
    color: #1e293b;
    font-weight: 700;
}
.t-slot .sub {
    font-size: 8.5px;
    font-weight: 700;
    color: #0f172a;
    display: block;
    margin-top: 1px;
}
.t-hw {
    font-size: 7.5px;
    color: #13644B;
    font-weight: 600;
    margin-top: 1px;
}

/* Footer Signatures */
.t-foot-grid {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
    font-size: 9.5px;
    line-height: 1.2;
}
.t-foot-grid td {
    padding: 1px 3px;
    vertical-align: top;
}
.t-sign-title {
    font-size: 9.5px;
    color: #334155;
}
.t-sign-name {
    font-weight: 700;
    font-size: 9.5px;
    color: #0f172a;
    margin-top: 2px;
}
</style>

<!-- Screen Toolbar -->
<div class="t-toolbar no-print">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="<?= APP_URL ?>/modules/tachan/backdate.php" style="color:#fde047;text-decoration:none;font-weight:700">
            ← जुने व पुढील टाचण यादीकडे परत
        </a>
        <span>एकूण दिवस : <strong><?= count($workingDays) ?> दिवस</strong> (प्रत्येक दिवस स्वतंत्र A4 पानात छपाई)</span>
        <span>शाळा : <strong><?= e($schoolName) ?></strong></span>
        <label style="display:inline-flex;align-items:center;gap:5px">
            फॉन्ट :
            <select onchange="changeFont(this.value)" id="fontSelector">
                <option value="font-mukta" selected>मुक्ता (Mukta - आधुनिक व सुस्पष्ट)</option>
                <option value="font-martel">मार्टेल (Martel - शासकीय / दर्जेदार)</option>
                <option value="font-noto">नोटो सॅन्स (Noto Sans - पारंपरिक)</option>
            </select>
        </label>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <button class="btn-print" onclick="window.print()">🖨️ सर्व दिवस प्रिंट / PDF जतन करा</button>
    </div>
</div>

<?php if (empty($workingDays)): ?>
    <div style="text-align:center; padding: 4rem 1rem; color: #64748b;">
        <h3>निवडलेल्या कालावधीत कोणतेही कामकाजाचे दिवस आढळले नाहीत.</h3>
        <p>कृपया मागील किंवा पुढील कामकाजाचे दिवस निवडा.</p>
        <a href="<?= APP_URL ?>/modules/tachan/backdate.php" style="font-weight:700; color:#0284c7;">← परत जा</a>
    </div>
<?php endif; ?>

<!-- MULTI-PAGE CONTAINER: EACH DAY IS 1 FULL A4 PAGE -->
<?php foreach ($workingDays as $idx => $item): 
    $dayDate = $item['date'];
    $dayDow  = $item['dow'];
    $isSat   = ($dayDow === 6);
    $pageTitle = $isSat ? 'आनंददायी शनिवार दैनिक टाचण २०२६-२७' : 'दैनिक पाठ टाचण २०२६-२७';

    // Suvichar & Upakram for this specific day
    $suvObj = tachan_fetch_suvichar($dayDate);
    $daySuvichar = trim((string)($suvObj['text'] ?? ''));
    if (!empty($suvObj['author'])) {
        $daySuvichar .= ' — ' . trim((string)$suvObj['author']);
    }
    $dayUpakram = tachan_fetch_upakram($pdo, $sid, $dayDate);

    // Fetch day rows as per timetable
    $dayPrint = [];
    $dayClassLabel = '';

    if ($mode === 'teacher') {
        $rawRows = tachan_apply_day_length(tachan_teacher_day_rows($pdo, $sid, $teacherId, $dayDate, true, $medium), $dayLen, $upto);
        $clsNames = [];
        foreach ($rawRows as $r) {
            $cname = trim((string)($r['class_name'] ?? ''));
            if ($cname !== '' && !in_array($cname, $clsNames, true)) {
                $clsNames[] = $cname;
            }
            $r['__class'] = $cname;
            $dayPrint[] = $r;
        }
        $dayClassLabel = $clsNames ? implode(', ', $clsNames) : 'शिक्षकाचे वेळापत्रक';
    } else {
        $rawRows = tachan_apply_day_length(tachan_day_rows($pdo, $sid, $teacherId, $class, $dayDate, true, $medium), $dayLen, $upto);
        foreach ($rawRows as $r) {
            $r['__class'] = $class['name'];
            $dayPrint[] = $r;
        }
        $stdLbl = class_label(planner_class_std($class));
        $dayClassLabel = $stdLbl;
    }

    $maxDayPeriods = $dayDow === 6 ? 6 : 8;
    $targetPeriods = ($dayLen === 'half') ? 4 : (($dayLen === 'upto') ? min($maxDayPeriods, max(4, $upto)) : $maxDayPeriods);
    $havePeriods = [];
    foreach ($dayPrint as $r) $havePeriods[(int)$r['period']] = true;
    $ttGrid = $mode === 'teacher' ? ($teacherTT[$dayDow] ?? []) : (tachan_timetable($pdo, $sid, $class)[$dayDow] ?? []);
    $pDefs = planner_periods($dayDow === 6 ? 6 : 1);
    $defaultSubjects = [1 => 'मराठी', 2 => 'गणित', 3 => 'इंग्रजी', 4 => 'परिसर अभ्यास / विज्ञान', 5 => 'कला / कार्यानुभव', 6 => 'शारीरिक शिक्षण व आरोग्य', 7 => 'स्वाध्याय', 8 => 'वर्ग वाचन / खेळ'];
    foreach ($pDefs as $p => $time) {
        if ((int)$p > $targetPeriods) continue;
        if (!isset($havePeriods[(int)$p])) {
            $sub = $mode === 'teacher' ? ((string)($ttGrid[(int)$p]['subject'] ?? '')) : ((string)($ttGrid[(int)$p] ?? ''));
            if ($sub === '') $sub = $defaultSubjects[(int)$p] ?? 'सामान्य ज्ञान / खेळ';
            $dayPrint[] = [
                'period' => (int)$p,
                '__class' => $class['name'] ?? '',
                'subject' => $sub,
                'topic' => 'नियोजित घटक अध्यापन व सराव',
                'activities' => 'प्रश्नोत्तरे, प्रकट वाचन, गटकार्य व स्वाध्याय लेखन',
                'eval_tool' => 'तोंडी काम / निरीक्षण',
                'materials' => 'पाठ्यपुस्तक, फलक, शैक्षणिक साहित्य',
                'learning_outcome' => 'घटकाची संकल्पना समजून घेऊन स्वाध्याय सोडवणे',
                'homework' => '',
            ];
        }
    }
    usort($dayPrint, static fn($a, $b) => ((int)$a['period']) <=> ((int)$b['period']));

    $dayBoxBadge = str_starts_with($dayClassLabel, 'इयत्ता') || str_starts_with($dayClassLabel, 'इ.') 
        ? $dayClassLabel 
        : 'इयत्ता ' . $dayClassLabel;
?>
<div class="tachan-day-page font-mukta" id="page-<?= $idx ?>">
    <!-- Header: Official layout -->
    <div class="t-header-box">
        <div class="t-badge-box"><?= e($dayBoxBadge) ?></div>
        <h1 class="t-title-main"><?= e($pageTitle) ?></h1>
        <div class="t-meta-row">
            <div><strong>शिक्षक :</strong> <?= e($teacherName) ?></div>
            <div><strong>दिनांक :</strong> <?= e(marathi_date_words($dayDate)) ?></div>
        </div>
        <div class="t-meta-row">
            <div class="t-suvichar-text"><strong>सुविचार :</strong> <?= e($daySuvichar ?: 'संकटाशी लढणे हाच जगण्याचा मार्ग आहे.') ?></div>
            <div><strong>वार :</strong> <?= e($dnames[$dayDow] ?? '') ?></div>
        </div>
        <?php if (!empty($dayUpakram)): ?>
        <div class="t-extra-strip">
            <strong>आजचे उपक्रम / दिनविशेष :</strong> <?= e(implode('  •  ', array_map('strval', $dayUpakram))) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tachan Data Grid (6 Columns: Standard Standard) -->
    <?php if (!$dayPrint): ?>
        <p style="text-align:center;padding:2rem;font-size:12px">या दिवसासाठी टाचण नोंद उपलब्ध नाही.</p>
    <?php else: ?>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:13%"><?= $isSat ? 'उपक्रम क्र. / विषय' : 'तासिका /<br>विषय' ?></th>
                <th style="width:18%">अध्ययन मुद्दा /<br>पाठांश</th>
                <th style="width:33%">अध्ययन अनुभव स्वरूप</th>
                <th style="width:11%">मूल्यमापन<br>साधने तंत्रे</th>
                <th style="width:10%">आवश्यक<br>साहित्य</th>
                <th style="width:15%">अध्ययन निष्पत्ती</th>
            </tr>
        </thead>
        <tbody>
            <!-- Periods Rows -->
            <?php foreach ($dayPrint as $r): 
                $cBadge = trim((string)($r['__class'] ?? ''));
                $subName = tachan_display_subject((string)$r['subject'], $medium);
            ?>
            <tr>
                <td class="t-slot">
                    <?= (int) $r['period'] ?>. <span class="cls"><?= e($cBadge) ?></span>
                    <span class="sub"><?= e($subName) ?></span>
                </td>
                <td><strong><?= e($r['topic']) ?></strong></td>
                <td><?= e((string) ($r['activities'] ?? '')) ?></td>
                <td><?= e((string) ($r['eval_tool'] ?? '')) ?></td>
                <td><?= e((string) ($r['materials'] ?? '')) ?></td>
                <td>
                    <?= e((string) ($r['learning_outcome'] ?? '')) ?>
                    <?php if (!empty($r['homework'])): ?><div class="t-hw">गृहपाठ: <?= e((string) $r['homework']) ?></div><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Footer Signatures: Standard 3-column Layout -->
    <table class="t-foot-grid">
        <tr>
            <td style="width:33%">
                <div class="t-sign-title">वर्गशिक्षक स्वाक्षरी</div>
                <div class="t-sign-name"><?= e($teacherName) ?></div>
            </td>
            <td style="width:34%; text-align:center">
                <?php if ($udise): ?><div class="t-sign-title">युडायस : <?= e($udise) ?></div><?php endif; ?>
                <div class="t-sign-name" style="font-size:8.5px; color:#475569;"><?= e($schoolName) ?></div>
            </td>
            <td style="width:33%; text-align:right">
                <div class="t-sign-title">मुख्याध्यापक स्वाक्षरी</div>
                <div class="t-sign-name"><?= e($schoolName) ?></div>
            </td>
        </tr>
    </table>
</div>
<?php endforeach; ?>

<script>
function changeFont(fontClass) {
    document.querySelectorAll('.tachan-day-page').forEach(function(el) {
        el.className = 'tachan-day-page ' + fontClass;
    });
}
</script>
