<?php
declare(strict_types=1);

/**
 * Smart School Hub — Single-Page A4 Daily Tachan Print Layout (दैनिक पाठ टाचण छपाई)
 * Aligned with institutional single A4 portrait standard:
 * - 6-column clear curriculum grid
 * - Integrated Paripath Suvichar & Academic Calendar Upakram
 * - Strict single-page fit guarantee with automated font/zoom scaling
 */
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';

$u = require_login();
$sid = (int) $u['school_id'];
$pdo = db();

$allTeachers = school_teachers($sid);
$teacherById = [];
foreach ($allTeachers as $t) $teacherById[(int)$t['id']] = $t;

$teacherId = !empty($_REQUEST['teacher_id']) && isset($teacherById[(int)$_REQUEST['teacher_id']])
    ? (int)$_REQUEST['teacher_id']
    : (int)$u['id'];

$classes = school_classes($sid);
$byId = [];
foreach ($classes as $c) $byId[(int) $c['id']] = $c;

$pref = tachan_prefs_get($pdo, $sid, $teacherId);
$teacherTT = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
$hasTeacherTT = !empty($teacherTT);

$mode = isset($_REQUEST['mode']) && in_array($_REQUEST['mode'], ['teacher', 'class'], true)
    ? (string) $_REQUEST['mode']
    : (($pref['teach_type'] ?? '') === 'periodwise' || ($hasTeacherTT && !isset($_REQUEST['class_id'])) ? 'teacher' : 'class');

$sel = isset($_REQUEST['class_id']) && isset($byId[(int) $_REQUEST['class_id']]) ? (int) $_REQUEST['class_id'] : (int) ($pref['class_id'] ?? ($classes[0]['id'] ?? 0));
$class = $byId[$sel] ?? null;

$date = isset($_REQUEST['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_REQUEST['date']) ? (string) $_REQUEST['date'] : date('Y-m-d');
$medium = isset($_REQUEST['medium']) ? tachan_norm_medium((string) $_REQUEST['medium']) : ($pref['medium'] ?: tachan_norm_medium((string) ($class['medium'] ?? 'marathi')));
$jod = isset($_REQUEST['jod']) && isset($byId[(int) $_REQUEST['jod']]) && (int) $_REQUEST['jod'] !== $sel ? (int) $_REQUEST['jod'] : 0;

$dayLen = isset($_REQUEST['dl']) && in_array($_REQUEST['dl'], ['full', 'half', 'upto'], true) ? (string) $_REQUEST['dl'] : ($pref['day_length'] ?: 'full');
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
$dow = (int) date('N', strtotime($date));
$periods = planner_periods($dow === 6 ? 6 : 1);
$isSat = $dow === 6;

$title = $isSat ? 'आनंददायी शनिवार दैनिक टाचण २०२६-२७' : 'दैनिक पाठ टाचण २०२६-२७';
$dnames = [1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार', 7 => 'रविवार'];
$mediums = ['marathi' => 'मराठी माध्यम', 'semi_english' => 'सेमी-इंग्रजी'];

// आजचा सुविचार (परिपाठ डेटाबेसवरून आपोआप)
$suvicharObj = tachan_fetch_suvichar($date);
$suvichar = trim((string)($suvicharObj['text'] ?? ''));
if (!empty($suvicharObj['author'])) {
    $suvichar .= ' — ' . trim((string)$suvicharObj['author']);
}

// आजचे उपक्रम व दिनविशेष (शैक्षणिक दिनदर्शिका + परिपाठ वरून)
$upakram = tachan_fetch_upakram($pdo, $sid, $date);

$school = school_row($sid) ?: [];
$schoolName = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));

$teacherName = resolve_teacher_name($mode === 'class' ? $class : null, $sid, $u);
$allTeachers = school_teachers($sid);

$print = [];
$classLabel = '';

if ($mode === 'teacher') {
    $rows = $dow === 7 ? [] : tachan_apply_day_length(tachan_teacher_day_rows($pdo, $sid, $teacherId, $date, true, $medium), $dayLen, $upto);
    $clsNames = [];
    foreach ($rows as $r) {
        $cname = trim((string)($r['class_name'] ?? ''));
        if ($cname !== '' && !in_array($cname, $clsNames, true)) {
            $clsNames[] = $cname;
        }
        $r['__class'] = $cname;
        $print[] = $r;
    }
    $classLabel = $clsNames ? implode(', ', $clsNames) : 'शिक्षकाचे वेळापत्रक';
} else {
    if (!$class) { http_response_code(404); exit('वर्ग सापडला नाही'); }
    $rows = $dow === 7 ? [] : tachan_apply_day_length(tachan_day_rows($pdo, $sid, $teacherId, $class, $date, true, $medium), $dayLen, $upto);
    $jodClass = $jod ? $byId[$jod] : null;
    $jodRows = [];
    if ($jodClass) {
        $jodRows = $dow === 7 ? [] : tachan_apply_day_length(tachan_day_rows($pdo, $sid, $teacherId, $jodClass, $date, true, tachan_norm_medium((string) ($jodClass['medium'] ?? 'marathi'))), $dayLen, $upto);
    }
    foreach ($rows as $r) { $r['__class'] = $class['name']; $print[] = $r; }
    foreach ($jodRows as $r) { $r['__class'] = $jodClass['name']; $print[] = $r; }
    $stdLbl = class_label(planner_class_std($class));
    $classLabel = $stdLbl . ($jodClass ? ' + ' . class_label(planner_class_std($jodClass)) : '');
}

// खात्री करा की सर्व तासिकानिहाय (१ ते शेवटची तासिका) नोंदी उपलब्ध आहेत
$maxDayPeriods = $isSat ? 6 : 8;
$targetPeriods = ($dayLen === 'half') ? 4 : (($dayLen === 'upto') ? min($maxDayPeriods, max(4, $upto)) : $maxDayPeriods);
$havePeriods = [];
foreach ($print as $r) {
    $havePeriods[(int)$r['period']] = true;
}

$ttGrid = $mode === 'teacher' ? ($teacherTT[$dow] ?? []) : (tachan_timetable($pdo, $sid, $class)[$dow] ?? []);
$pDefs = planner_periods($dow === 6 ? 6 : 1);
$defaultSubjects = [1 => 'मराठी', 2 => 'गणित', 3 => 'इंग्रजी', 4 => 'परिसर अभ्यास / विज्ञान', 5 => 'कला / कार्यानुभव', 6 => 'शारीरिक शिक्षण व आरोग्य', 7 => 'स्वाध्याय', 8 => 'वर्ग वाचन / खेळ'];

foreach ($pDefs as $p => $time) {
    if ((int)$p > $targetPeriods) continue;
    if (!isset($havePeriods[(int)$p])) {
        $sub = '';
        $cName = $class['name'] ?? '';
        if ($mode === 'teacher') {
            $slot = $todaySlots[(int)$p] ?? null;
            $sub = $slot ? (string)$slot['subject'] : '';
            $cName = $slot ? (string)($slot['class_name'] ?: ('इ. ' . $slot['std'])) : ($class['name'] ?? '');
        } else {
            $sub = (string)($ttGrid[(int)$p] ?? '');
        }
        if ($sub === '') {
            $sub = $defaultSubjects[(int)$p] ?? 'सामान्य ज्ञान / खेळ';
        }
        $print[] = [
            'period' => (int)$p,
            '__class' => $cName,
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

usort($print, static function ($a, $b) {
    $pa = (int) $a['period']; $pb = (int) $b['period'];
    return $pa === $pb ? strcmp((string) ($a['__class'] ?? ''), (string) ($b['__class'] ?? '')) : $pa <=> $pb;
});

$boxBadge = str_starts_with($classLabel, 'इयत्ता') || str_starts_with($classLabel, 'इ.') 
    ? $classLabel 
    : 'इयत्ता ' . $classLabel;

print_start('दैनिक टाचण — ' . $classLabel, [
    'compact' => true,
    'raw_header' => true,
    'margin' => '4mm 5mm 4mm 5mm'
]);
?>
<!-- Web Fonts for High-Quality Marathi Educational Typography -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Martel:wght@600;700;800;900&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* A4 उभी (portrait) — 1 पानाची काटेकोर छपाई (Single-Page Fit) */
@page {
    size: A4 portrait;
    margin: 3.5mm 4.5mm 3.5mm 4.5mm;
}
@media print {
    .no-print, body > header, .t-toolbar { display: none !important; }
    html, body {
        width: 100% !important;
        height: 100% !important;
        max-height: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        overflow: hidden !important;
    }
    .tachan-day {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
body {
    font-family: 'Mukta', 'Noto Sans Devanagari', -apple-system, BlinkMacSystemFont, sans-serif !important;
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
.btn-zoom:hover { background: rgba(255,255,255,0.3); }

/* The A4 Container */
.tachan-day {
    font-size: 8.5px;
    color: #0f172a;
    width: 200mm;
    max-height: 288mm;
    margin: 8px auto;
    background: #fff;
    box-sizing: border-box;
    padding: 3mm 4mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
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
        <a href="<?= APP_URL ?>/modules/tachan/day.php?mode=<?= $mode ?>&class_id=<?= $sel ?>&date=<?= e($date) ?>" style="color:#fde047;text-decoration:none;font-weight:700">
            ← दैनिक टाचण परत
        </a>
        <span>शाळा : <strong><?= e($schoolName) ?></strong></span>
        <?php if (!empty($allTeachers)): ?>
        <label style="display:inline-flex;align-items:center;gap:5px">
            शिक्षक :
            <select onchange="location.href='?mode=<?= $mode ?>&class_id=<?= $sel ?>&date=<?= e($date) ?>&teacher_name='+encodeURIComponent(this.value)">
                <?php foreach ($allTeachers as $tr): $tn = (string)($tr['name_mr'] ?: $tr['name']); ?>
                <option value="<?= e($tn) ?>" <?= $tn === $teacherName ? 'selected' : '' ?>><?= e($tn) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
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
        <button class="btn-zoom" onclick="tachanZoom(-0.03)" title="फॉन्ट लहान करा">A-</button>
        <button class="btn-zoom" onclick="tachanZoom(0.03)" title="फॉन्ट मोठा करा">A+</button>
        <button class="btn-zoom" onclick="tachanFit()">🔄 १ पानात अचूक बसवा</button>
        <button class="btn-print" onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button>
    </div>
</div>

<div class="tachan-day font-mukta" id="tachanDayContent">
    <!-- Header: Official layout -->
    <div class="t-header-box">
        <div class="t-badge-box"><?= e($boxBadge) ?></div>
        <h1 class="t-title-main"><?= e($title) ?></h1>
        <div class="t-meta-row">
            <div><strong>शिक्षक :</strong> <?= e($teacherName) ?></div>
            <div><strong>दिनांक :</strong> <?= e(marathi_date_words($date)) ?></div>
        </div>
        <div class="t-meta-row">
            <div class="t-suvichar-text"><strong>सुविचार :</strong> <?= e($suvichar ?: 'संकटाशी लढणे हाच जगण्याचा मार्ग आहे.') ?></div>
            <div><strong>वार :</strong> <?= e($dnames[$dow] ?? '') ?></div>
        </div>
        <?php if (!empty($upakram)): ?>
        <div class="t-extra-strip">
            <strong>आजचे उपक्रम / दिनविशेष :</strong> <?= e(implode('  •  ', array_map('strval', $upakram))) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tachan Data Grid (6 Columns: Standard Standard) -->
    <?php if (!$print): ?>
        <p style="text-align:center;padding:2rem;font-size:12px">या दिवसासाठी टाचण उपलब्ध नाही (सुट्टी किंवा वेळापत्रक नोंद नाही).</p>
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
            <?php foreach ($print as $r): 
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

<script>
var currentZoom = 1.0;
function tachanZoom(delta) {
    currentZoom = Math.max(0.5, Math.min(1.2, currentZoom + delta));
    applyZoom(currentZoom);
}

function applyZoom(z) {
    var el = document.getElementById('tachanDayContent');
    if (!el) return;
    if ('zoom' in el.style) {
        el.style.zoom = z;
    } else {
        el.style.transform = 'scale(' + z + ')';
        el.style.transformOrigin = 'top center';
    }
}

function changeFont(fontClass) {
    var el = document.getElementById('tachanDayContent');
    if (!el) return;
    el.classList.remove('font-mukta', 'font-martel', 'font-noto');
    el.classList.add(fontClass);
    try { localStorage.setItem('tachan_font', fontClass); } catch(e){}
    setTimeout(tachanFit, 50);
}

// Load saved font preference
(function() {
    try {
        var saved = localStorage.getItem('tachan_font');
        if (saved) {
            var sel = document.getElementById('fontSelector');
            if (sel) sel.value = saved;
            var el = document.getElementById('tachanDayContent');
            if (el) {
                el.classList.remove('font-mukta', 'font-martel', 'font-noto');
                el.classList.add(saved);
            }
        }
    } catch(e){}
})();

// एका A4 पानात अचूक बसवणे: (297mm - 8mm) * 96 / 25.4 = ~1092px
function tachanFit() {
    var el = document.getElementById('tachanDayContent');
    if (!el) return;
    applyZoom(1.0);
    currentZoom = 1.0;
    var maxH = (297 - 10) * 96 / 25.4;
    var actualH = el.scrollHeight;
    if (actualH > maxH) {
        var ratio = Math.max(0.60, (maxH / actualH) * 0.985);
        currentZoom = ratio;
        applyZoom(ratio);
    }
}

window.addEventListener('load', tachanFit);
window.addEventListener('beforeprint', tachanFit);
window.addEventListener('resize', tachanFit);
<?php if (isset($_GET['dl_pdf'])): ?>
window.addEventListener('load', function() {
    setTimeout(function() {
        tachanFit();
        window.print();
    }, 200);
});
<?php endif; ?>
</script>
<?php print_end(); ?>
