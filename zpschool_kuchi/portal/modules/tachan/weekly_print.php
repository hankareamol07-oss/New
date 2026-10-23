<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';

$u = require_login();
$sid = (int) $u['school_id'];
$pdo = db();
$classes = school_classes($sid);
$byId = [];
foreach ($classes as $c) $byId[(int) $c['id']] = $c;
$sel = isset($_GET['class_id']) && isset($byId[(int) $_GET['class_id']]) ? (int) $_GET['class_id'] : (int) ($classes[0]['id'] ?? 0);
$class = $byId[$sel] ?? null;
if (!$class) { http_response_code(404); exit('वर्ग सापडला नाही'); }

$medium = isset($_GET['medium']) ? tachan_norm_medium((string) $_GET['medium']) : tachan_norm_medium((string) ($class['medium'] ?? 'marathi'));
$base = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date']) ? (string) $_GET['date'] : date('Y-m-d', strtotime('next monday'));
$monday = date('Y-m-d', strtotime('monday this week', strtotime($base)));
$thisMonday = date('Y-m-d', strtotime('monday this week'));
$isPaid = is_tachan_module_purchased($sid);
if (!$isPaid && $monday !== $thisMonday) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="mr">
    <head>
        <meta charset="utf-8">
        <title>स्वतंत्र टाचण मॉड्युल आवश्यक — स्मार्ट स्कूल हब</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>body { font-family: 'Mukta', sans-serif; background: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }</style>
    </head>
    <body>
        <div class="card border-0 shadow-lg rounded-4 p-4 text-center" style="max-width: 520px; background: white;">
            <div class="display-4 text-danger mb-3">🔒</div>
            <h4 class="fw-bold text-dark mb-2">मागील व भावी आठवडे प्रिंटसाठी स्वतंत्र मॉड्युल आवश्यक</h4>
            <p class="text-muted small mb-4" style="line-height: 1.7;">
                मागील (जुने) किंवा पुढील (भावी) आठवड्यांचे एकत्रित नियोजन डाऊनलोड व प्रिंट करण्यासाठी कृपया <strong>स्वतंत्र दैनिक टाचण वही मॉड्युल (₹४९९)</strong> किंवा <strong>ऑल-इन-वन शाळा पॅक</strong> खरेदी करा.<br>
                <strong>(या विशेष सुविधेसाठी कोणतीही मोफत चाचणी लागू नाही.)</strong>
            </p>
            <div class="d-flex flex-column gap-2">
                <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=24" class="btn btn-warning fw-bold text-dark rounded-pill py-2 shadow-sm">
                    फक्त दैनिक टाचण वही मॉड्युल घ्या (&#8377;४९९/वर्ष)
                </a>
                <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=4" class="btn btn-outline-primary fw-bold rounded-pill py-2">
                    संपूर्ण शाळा ऑल-इन-वन पॅक (&#8377;१,४९९/वर्ष)
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/weekly.php" class="btn btn-link text-muted text-decoration-none small mt-2">
                    मागे चालू आठवड्यावर जा
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
$dnames = [1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार'];
$mediums = ['marathi' => 'मराठी माध्यम', 'semi_english' => 'सेमी-इंग्रजी'];

$school = school_row($sid) ?: [];
$schoolName = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));
$teacherName = resolve_teacher_name($class, $sid, $u);
$allTeachers = school_teachers($sid);

$holq = $pdo->prepare('SELECT name FROM holidays WHERE school_id=? AND date=?');
$days = [];
for ($i = 0; $i < 6; $i++) {
    $d = date('Y-m-d', strtotime($monday . ' +' . $i . ' day'));
    $holq->execute([$sid, $d]);
    $hol = $holq->fetchColumn();
    $rows = $hol !== false ? [] : tachan_day_rows($pdo, $sid, (int) $u['id'], $class, $d, true, $medium);
    $days[] = ['date' => $d, 'dow' => $i + 1, 'hol' => $hol, 'rows' => $rows];
}

print_start('साप्ताहिक टाचण — ' . class_label(planner_class_std($class)), [
    'compact' => true,
    'raw_header' => true,
    'margin' => '5mm 6mm'
]);
?>
<!-- Web Fonts for High-Quality Marathi Educational Typography -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Martel:wght@600;700;800;900&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
@page { size: A4 portrait; margin: 5mm 6mm; }
@media print {
    .no-print, body > header, .tw-toolbar { display: none !important; }
    body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
    .tw { width: 100% !important; max-width: 100% !important; margin: 0 !important; }
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
    line-height: 1.2;
}

.tw-toolbar {
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
.tw-toolbar a, .tw-toolbar button, .tw-toolbar select {
    font-size: 12px;
    border-radius: 4px;
}
.tw-toolbar select {
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
}
.btn-print:hover { background: #b7791f; }

.tw {
    font-size: 9px;
    color: #0f172a;
    width: 198mm;
    margin: 8px auto;
    background: #fff;
    box-sizing: border-box;
    padding: 4mm 5mm;
}

/* Font theme classes */
.font-mukta {
    font-family: 'Mukta', 'Mukta', -apple-system, BlinkMacSystemFont, sans-serif !important;
}
.font-mukta .tw-sch-title {
    font-family: 'Martel', 'Mukta', serif !important;
    font-weight: 800 !important;
}

.font-martel {
    font-family: 'Martel', 'Mukta', Georgia, serif !important;
}
.font-martel .tw-sch-title {
    font-family: 'Martel', serif !important;
    font-weight: 900 !important;
}

.font-noto {
    font-family: 'Mukta', -apple-system, BlinkMacSystemFont, sans-serif !important;
}

.tw-head-strip {
    text-align: center;
    border-bottom: 1.5px solid #1e293b;
    padding-bottom: 3px;
    margin-bottom: 4px;
}
.tw-sch-title {
    font-size: 14.5px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    letter-spacing: 0.2px;
}
.tw-sub-title {
    font-size: 11.5px;
    font-weight: 700;
    color: #1e3a8a;
    margin: 2px 0 0;
    letter-spacing: 0.2px;
}

.tw-head {
    width: 100%;
    border-collapse: collapse;
    margin: 3px 0 6px;
    font-size: 9.5px;
    font-weight: 500;
}
.tw-head td { padding: 1.5px 3px; vertical-align: middle; }
.tw-head .r { text-align: right; }

.tw-day {
    font-weight: 700;
    background: #1A365D;
    color: #fff;
    padding: 2.5px 6px;
    margin: 6px 0 2px;
    border-radius: 3px;
    font-size: 9.5px;
    letter-spacing: 0.2px;
}
.tw table.grid {
    font-size: 8.5px;
    line-height: 1.18;
    table-layout: fixed;
    width: 100%;
    border-collapse: collapse;
}
.tw table.grid th, .tw table.grid td {
    border: 1px solid #334155;
    padding: 2px 4px;
    vertical-align: top;
    word-wrap: break-word;
    overflow-wrap: anywhere;
}
.tw table.grid th {
    background: #e2e8f0;
    color: #0f172a;
    text-align: center;
    font-weight: 700;
    font-size: 8.5px;
    padding: 2px 3px;
    letter-spacing: 0.2px;
}
.tw table.grid td {
    font-weight: 500;
    color: #1e293b;
}
.tw table.grid tr { break-inside: avoid; }
.tw-hol { color: #0f766e; font-weight: 700; padding: 3px 4px; font-size: 8.5px; }

.tw-foot {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 9.5px;
}
.tw-foot td { padding: 2px 4px; vertical-align: bottom; }
.tw-foot .r { text-align: right; }
.tw-foot .c { text-align: center; }
.tw-sign {
    border-top: 1px solid #334155;
    display: inline-block;
    min-width: 130px;
    padding-top: 2px;
    font-weight: 700;
}
</style>

<!-- Toolbar -->
<div class="tw-toolbar no-print">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="/modules/tachan/weekly.php?class_id=<?= $sel ?>&date=<?= e($base) ?>" style="color:#fde047;text-decoration:none;font-weight:700">← साप्ताहिक टाचण परत</a>
        <span>शाळा : <strong><?= e($schoolName) ?></strong></span>
        <?php if (!empty($allTeachers)): ?>
        <label style="display:inline-flex;align-items:center;gap:5px">
            शिक्षक निवडा :
            <select onchange="location.href='?class_id=<?= $sel ?>&date=<?= e($base) ?>&teacher_name='+encodeURIComponent(this.value)">
                <?php foreach ($allTeachers as $tr): $tn = (string)($tr['name_mr'] ?: $tr['name']); ?>
                <option value="<?= e($tn) ?>" <?= $tn === $teacherName ? 'selected' : '' ?>><?= e($tn) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label style="display:inline-flex;align-items:center;gap:5px">
            फॉन्ट :
            <select onchange="changeTwFont(this.value)" id="twFontSelector">
                <option value="font-mukta" selected>मुक्ता (Mukta - आधुनिक व सुस्पष्ट)</option>
                <option value="font-martel">मार्टेल (Martel - शासकीय / दर्जेदार)</option>
                <option value="font-noto">नोटो सॅन्स (Noto Sans - पारंपरिक)</option>
            </select>
        </label>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <button class="btn-print" onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button>
    </div>
</div>

<div class="tw font-mukta" id="twContent">
    <div class="tw-head-strip">
        <h1 class="tw-sch-title"><?= e($schoolName) ?></h1>
        <div class="tw-sub-title">साप्ताहिक पाठ टाचण २०२६-२७</div>
    </div>

    <table class="tw-head">
        <tr>
            <td style="width:35%"><strong>शिक्षक :</strong> <?= e($teacherName) ?></td>
            <td class="r" style="width:65%"><strong>इयत्ता :</strong> <?= e(class_label(planner_class_std($class))) ?> · <?= e($mediums[$medium]) ?></td>
        </tr>
        <tr>
            <td><strong>आठवडा :</strong> <?= e(marathi_date_words($monday)) ?> ते <?= e(marathi_date_words(date('Y-m-d', strtotime($monday . ' +5 day')))) ?></td>
            <td class="r"><?php if ($udise): ?><strong>यु-डायस :</strong> <?= e($udise) ?><?php endif; ?></td>
        </tr>
    </table>

    <?php foreach ($days as $d): ?>
    <div class="tw-day"><?= e($dnames[$d['dow']]) ?> · <?= e(marathi_date_words($d['date'])) ?><?= $d['hol'] !== false ? ' — सुट्टी : ' . e((string) $d['hol']) : '' ?></div>
    <?php if ($d['hol'] === false && $d['rows']): ?>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:8%">तासिका</th>
                <th style="width:20%">विषय</th>
                <th style="width:32%">घटक / पाठांश</th>
                <th>अध्ययन-अध्यापन कृती</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($d['rows'] as $r): ?>
            <tr>
                <td style="text-align:center;font-weight:700"><?= (int) $r['period'] ?></td>
                <td><strong><?= e($r['subject']) ?></strong></td>
                <td><?= e($r['topic']) ?></td>
                <td><?= e((string) ($r['activities'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php elseif ($d['hol'] === false): ?>
        <div class="tw-hol">वेळापत्रक नोंद नाही.</div>
    <?php endif; ?>
    <?php endforeach; ?>

    <table class="tw-foot">
        <tr>
            <td style="width:33%"><span class="tw-sign">वर्गशिक्षक स्वाक्षरी</span></td>
            <td class="c"><?php if ($udise): ?>युडायस कोड : <?= e($udise) ?><?php endif; ?></td>
            <td class="r" style="width:33%"><span class="tw-sign">मुख्याध्यापक स्वाक्षरी व शिक्का</span></td>
        </tr>
        <tr>
            <td><strong><?= e($teacherName) ?></strong></td>
            <td class="c"></td>
            <td class="r"><strong><?= e($schoolName) ?></strong></td>
        </tr>
    </table>
</div>

<script>
function changeTwFont(fontClass) {
    var el = document.getElementById('twContent');
    if (!el) return;
    el.classList.remove('font-mukta', 'font-martel', 'font-noto');
    el.classList.add(fontClass);
    try { localStorage.setItem('tachan_font', fontClass); } catch(e){}
}

(function() {
    try {
        var saved = localStorage.getItem('tachan_font');
        if (saved) {
            var sel = document.getElementById('twFontSelector');
            if (sel) sel.value = saved;
            var el = document.getElementById('twContent');
            if (el) {
                el.classList.remove('font-mukta', 'font-martel', 'font-noto');
                el.classList.add(saved);
            }
        }
    } catch(e){}
})();
</script>
<?php print_end(); ?>
