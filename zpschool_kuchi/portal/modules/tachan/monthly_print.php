<?php
declare(strict_types=1);

/**
 * Smart School Hub — Monthly Lesson Planner Register A4 Print / PDF (मासिक टाचण वही छपाई)
 * 👑 STRICTLY RESTRICTED TO PREMIUM (PRO) SUBSCRIBERS ONLY
 * 
 * Formatted for Single/Multi-Page High-Density A4 Landscape Print:
 * - Institutional Maharashtra School Standard
 * - NEP 2020 Compliant Learning Outcomes, TLM & Pedagogical Activities
 * - Teacher & Headmaster Seal / Signature Blocks
 */
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/planner_seed.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';
require_once __DIR__ . '/../../includes/module_access.php';

$u = require_login();
$sid = (int)$u['school_id'];
$pdo = db();
$mysql = getMainDB();

// 1. Premium / PRO Gatekeeper Check
$isPaid = is_tachan_module_purchased($sid);
if (!$isPaid) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="mr">
    <head>
        <meta charset="utf-8">
        <title>मासिक टाचण प्रिंट — स्वतंत्र मॉड्युल आवश्यक</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&family=Martel:wght@700;800&display=swap" rel="stylesheet">
        <style>body { font-family: 'Mukta', sans-serif; background: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }</style>
    </head>
    <body>
        <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 text-center" style="max-width: 560px; background: white;">
            <div class="display-3 text-warning mb-2">👑</div>
            <h4 class="fw-bold text-dark mb-2" style="font-family: 'Martel', serif;">मासिक टाचण वही (PRO) सुविधा</h4>
            <div class="badge bg-warning-subtle text-dark border border-warning px-3 py-1.5 rounded-pill fw-bold mb-3">
                केवळ प्रीमियम (PRO) सदस्यांसाठी उपलब्ध
            </div>
            <p class="text-muted small mb-4" style="line-height: 1.8;">
                महिनानिहाय संपूर्ण पाठ टाचण नोंदवही पाहणे व <strong>अधिकृत A4 रजिस्टर प्रिंट / PDF डाऊनलोड करणे</strong> ही सुविधा फक्त <strong>दैनिक टाचण वही मॉड्युल (₹४९९)</strong> किंवा <strong>शाळा ऑल-इन-वन पॅक (₹१,४९९)</strong> खरेदी केलेल्या ग्राहकांसाठी उपलब्ध आहे.<br>
                <strong>(या विशेष सुविधेसाठी मोफत चाचणी लागू नाही.)</strong>
            </p>
            <div class="d-flex flex-column gap-2">
                <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=24" class="btn btn-warning fw-bold text-dark rounded-pill py-2.5 shadow-sm">
                    फक्त दैनिक टाचण वही मॉड्युल घ्या (&#8377;४९९/वर्ष)
                </a>
                <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=4" class="btn btn-outline-primary fw-bold rounded-pill py-2.5">
                    संपूर्ण शाळा ऑल-इन-वन पॅक (&#8377;१,४९९/वर्ष)
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/monthly.php" class="btn btn-link text-muted text-decoration-none small mt-2">
                    मागे जा
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. Fetch Data for Printing
$classes = school_classes($sid);
$byId = [];
foreach ($classes as $c) $byId[(int)$c['id']] = $c;
if (!$byId) { http_response_code(404); exit('वर्ग उपलब्ध नाहीत.'); }

$pref = tachan_prefs_get($pdo, $sid, (int)$u['id']);
$sel = isset($_GET['class_id']) && isset($byId[(int)$_GET['class_id']]) 
    ? (int)$_GET['class_id'] 
    : (int)($pref['class_id'] ?: array_key_first($byId));
if (!isset($byId[$sel])) $sel = (int)array_key_first($byId);
$class = $byId[$sel];
$std = planner_class_std($class);

$months = planner_months();
$mSel = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if (!isset($months[$mSel])) $mSel = 6;

$medium = isset($_GET['medium']) ? tachan_norm_medium((string)$_GET['medium']) : $pref['medium'];
$disp = static fn(string $s): string => tachan_display_subject($s, $medium);

$school = school_row($sid) ?: [];
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$udise = (string)($school['udise_code'] ?? ($school['udise'] ?? ''));
$teacher = resolve_teacher_name($class, $sid, $u);

// Fetch custom plans from annual_plans
$planRows = [];
try {
    $stmtPlan = $pdo->prepare('SELECT * FROM annual_plans WHERE school_id = ? AND class_id = ? AND month = ?');
    $stmtPlan->execute([$sid, $sel, $mSel]);
    foreach ($stmtPlan->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $planRows[$pr['subject']] = $pr;
    }
} catch (Throwable $e) {}

// Official week-by-week curriculum bank
$allWeeks = planner_annual_weeks();
$stdWeeks = $allWeeks[$std][$mSel] ?? [];

// Resolve subject order
$coreSubjects = planner_subjects_for_std($std);
$extraSubjects = planner_extra_subjects($std);
$masterSubjects = array_unique(array_merge($coreSubjects, array_keys($stdWeeks), array_keys($planRows), $extraSubjects));

$activeSubjects = [];
foreach ($masterSubjects as $sub) {
    if (trim($sub) === '') continue;
    $activeSubjects[] = $sub;
}

// Bank cache
$bankCache = [];
try {
    $stmtBank = $mysql->prepare('SELECT subject, topic, learning_outcome, materials, tl_activity, eval_tool FROM tachan_bank WHERE std = ? AND month_no = ? ORDER BY seq');
    $stmtBank->execute([$std, $mSel]);
    while ($brow = $stmtBank->fetch(PDO::FETCH_ASSOC)) {
        $bankCache[$brow['subject']][] = $brow;
    }
} catch (Throwable $e) {}

$templates = planner_tachan_templates();

// Co-curricular activities & holidays
$monthlyEvents = planner_calendar()[$mSel] ?? [];
$allHolidays = planner_holidays();
$monthHolidays = [];
$yearNumber = ($mSel >= 6) ? 2026 : 2027;
$prefixDate = sprintf('%04d-%02d-', $yearNumber, $mSel);
foreach ($allHolidays as $h) {
    if (strpos($h[0], $prefixDate) === 0) {
        $monthHolidays[] = $h;
    }
}

// Calculate working days
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $mSel, $yearNumber);
$workingDays = 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dt = sprintf('%04d-%02d-%02d', $yearNumber, $mSel, $d);
    $dow = (int)date('N', strtotime($dt));
    if ($dow === 7) continue;
    $isHol = false;
    foreach ($allHolidays as $h) {
        if ($h[0] === $dt) { $isHol = true; break; }
    }
    if (!$isHol) $workingDays++;
}

$monthLabel = $months[$mSel] . ' ' . $yearNumber;
$mediumLabel = $medium === 'semi_english' ? 'सेमी-इंग्रजी' : 'मराठी माध्यम';
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <title>मासिक पाठ टाचण नोंदवही — <?= e($monthLabel) ?> — <?= e($class['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Martel:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 7mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Mukta', 'Mukta', -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 9px;
            line-height: 1.25;
            background: #ffffff;
        }
        .no-print {
            padding: 8px 12px;
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .no-print .btn-print {
            background: #167A5B;
            border: 0;
            border-radius: 6px;
            padding: 7px 16px;
            font-weight: 700;
            cursor: pointer;
            color: #ffffff;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .no-print .btn-back {
            background: #ffffff;
            border: 1px solid #94a3b8;
            border-radius: 6px;
            padding: 6px 14px;
            color: #334155;
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
        }
        .print-container {
            padding: 4px;
        }
        .head {
            text-align: center;
            border: 1.5px solid #1e293b;
            padding: 4px 6px;
            margin-bottom: 5px;
            background: #f8fafc;
        }
        .head h1 {
            margin: 0;
            font-family: 'Martel', serif;
            font-weight: 800;
            font-size: 15px;
            color: #0f172a;
            letter-spacing: 0.3px;
        }
        .head h2 {
            margin: 2px 0 3px 0;
            font-family: 'Mukta', sans-serif;
            font-size: 12px;
            font-weight: 800;
            color: #0b3d2e;
        }
        .meta-strip {
            display: flex;
            justify-content: space-between;
            font-size: 9.5px;
            font-weight: 600;
            border-top: 1px dashed #94a3b8;
            padding-top: 3px;
            margin-top: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        th, td {
            border: 1px solid #1e293b;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        th {
            background: #e2e8f0;
            text-align: center;
            font-weight: 800;
            font-size: 9px;
            color: #0f172a;
        }
        td.sub-name {
            font-weight: 700;
            background: #f8fafc;
            color: #0f172a;
        }
        .wk-item {
            margin-bottom: 2px;
        }
        .wk-item:last-child {
            margin-bottom: 0;
        }
        .wk-num {
            font-weight: 700;
            color: #0369a1;
        }
        .info-strip {
            display: flex;
            gap: 8px;
            margin-top: 5px;
            font-size: 8.5px;
        }
        .info-box {
            flex: 1;
            border: 1px solid #94a3b8;
            padding: 3px 5px;
            background: #f8fafc;
        }
        .info-box strong {
            display: block;
            margin-bottom: 1px;
            color: #1e293b;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 14px;
            font-weight: 700;
            font-size: 9.5px;
            page-break-inside: avoid;
        }
        .signatures div {
            width: 28%;
            text-align: center;
            border-top: 1px solid #1e293b;
            padding-top: 3px;
        }
        @media print {
            .no-print { display: none; }
            body { font-size: 8.5px; }
            th { background: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            td.sub-name { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .head { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-box { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <a href="<?= APP_URL ?>/modules/tachan/monthly.php?class_id=<?= $sel ?>&month=<?= $mSel ?>&medium=<?= $medium ?>" class="btn-back">
        &larr; मासिक टाचणकडे परत जा
    </a>
    <button onclick="window.print()" class="btn-print">
        &#128438; प्रिंट / PDF जतन करा (A4 Landscape)
    </button>
</div>

<div class="print-container">
    <!-- Header -->
    <div class="head">
        <h1><?= e($schoolName) ?></h1>
        <h2>मासिक पाठ टाचण व अभ्यासक्रम नियोजन नोंदवही — <?= e($monthLabel) ?></h2>
        <div class="meta-strip">
            <span><strong>यू-डायस :</strong> <?= e($udise) ?></span>
            <span><strong>इयत्ता :</strong> <?= e($class['name']) ?> &nbsp;|&nbsp; <strong>माध्यम :</strong> <?= e($mediumLabel) ?></span>
            <span><strong>कामकाजाचे दिवस :</strong> <?= $workingDays ?> दिवस</span>
            <span><strong>वर्ग / विषय शिक्षक :</strong> <?= e($teacher) ?></span>
        </div>
    </div>

    <!-- Monthly Matrix Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 3.5%;">अ.क्र.</th>
                <th style="width: 9.5%;">विषय</th>
                <th style="width: 17%;">नियोजित घटक व उपघटक</th>
                <th style="width: 19%;">४ आठवड्यांचे नियोजन</th>
                <th style="width: 21%;">अपेक्षित अध्ययन निष्पत्ती</th>
                <th style="width: 11%;">शैक्षणिक साधने (TLM)</th>
                <th style="width: 10%;">अध्यापन पद्धती व कृती</th>
                <th style="width: 5.5%;">मूल्यमापन</th>
                <th style="width: 3.5%;">तासिका</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sr = 0;
            $totalPeriods = 0;
            foreach ($activeSubjects as $sub): 
                $sr++;
                $plan = $planRows[$sub] ?? null;

                $w1 = trim((string)($plan['week1'] ?? ($stdWeeks[$sub][0] ?? '')));
                $w2 = trim((string)($plan['week2'] ?? ($stdWeeks[$sub][1] ?? '')));
                $w3 = trim((string)($plan['week3'] ?? ($stdWeeks[$sub][2] ?? '')));
                $w4 = trim((string)($plan['week4'] ?? ($stdWeeks[$sub][3] ?? '')));

                $topic = trim((string)($plan['topics'] ?? ''));
                if ($topic === '') {
                    $nonEmptyWeeks = array_filter([$w1, $w2, $w3, $w4]);
                    $topic = $nonEmptyWeeks ? implode(' | ', $nonEmptyWeeks) : $sub;
                }

                $bankSub = tachan_bank_subject($std, $sub, $medium);
                $matchedBank = $bankCache[$bankSub] ?? ($bankCache[$sub] ?? []);

                $loList = [];
                $tlmList = [];
                $actList = [];
                $evalList = [];
                foreach ($matchedBank as $b) {
                    if (!empty($b['learning_outcome']) && !in_array($b['learning_outcome'], $loList, true)) $loList[] = $b['learning_outcome'];
                    if (!empty($b['materials']) && !in_array($b['materials'], $tlmList, true)) $tlmList[] = $b['materials'];
                    if (!empty($b['tl_activity']) && !in_array($b['tl_activity'], $actList, true)) $actList[] = $b['tl_activity'];
                    if (!empty($b['eval_tool']) && !in_array($b['eval_tool'], $evalList, true)) $evalList[] = $b['eval_tool'];
                }

                $tpl = $templates[$sub] ?? ($templates[planner_canonical_subject($sub)] ?? null);

                $loText = $loList ? implode('; ', array_slice($loList, 0, 2)) : ($tpl['objectives'] ?? 'घटकाचे वाचन, आकलन व संकल्पना स्पष्ट होणे.');
                $tlmText = $tlmList ? implode(', ', array_slice($tlmList, 0, 2)) : 'पाठ्यपुस्तक, फलक, चित्रतक्ते, प्रत्यक्ष वस्तू.';
                $actText = $actList ? implode('; ', array_slice($actList, 0, 2)) : ($tpl['activities'] ?? 'चर्चा, वाचन, फलकलेखन व स्वाध्याय सराव.');
                $evalText = $evalList ? implode(', ', array_slice($evalList, 0, 2)) : 'निरीक्षण, तोंडी काम, स्वाध्याय.';

                $periods = 20;
                if (in_array($sub, ['मराठी', 'गणित'], true)) $periods = 24;
                elseif (in_array($sub, ['इंग्रजी', 'परिसर अभ्यास', 'विज्ञान'], true)) $periods = 20;
                elseif (in_array($sub, ['कला शिक्षण', 'शारीरिक शिक्षण', 'कार्यशिक्षण', 'ग्रंथालय'], true)) $periods = 8;
                $totalPeriods += $periods;
            ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;"><?= $sr ?></td>
                    <td class="sub-name"><?= e($disp($sub)) ?></td>
                    <td><strong><?= e($topic) ?></strong></td>
                    <td>
                        <?php if ($w1 !== ''): ?><div class="wk-item"><span class="wk-num">१.</span> <?= e($w1) ?></div><?php endif; ?>
                        <?php if ($w2 !== ''): ?><div class="wk-item"><span class="wk-num">२.</span> <?= e($w2) ?></div><?php endif; ?>
                        <?php if ($w3 !== ''): ?><div class="wk-item"><span class="wk-num">३.</span> <?= e($w3) ?></div><?php endif; ?>
                        <?php if ($w4 !== ''): ?><div class="wk-item"><span class="wk-num">४.</span> <?= e($w4) ?></div><?php endif; ?>
                        <?php if ($w1 === '' && $w2 === '' && $w3 === '' && $w4 === ''): ?>-<?php endif; ?>
                    </td>
                    <td><?= e($loText) ?></td>
                    <td><?= e($tlmText) ?></td>
                    <td><?= e($actText) ?></td>
                    <td><?= e($evalText) ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= $periods ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f1f5f9; font-weight: bold;">
                <td colspan="8" style="text-align: right; padding-right: 8px;">एकूण नियोजित तासिका :</td>
                <td style="text-align: center; font-weight: bold;"><?= $totalPeriods ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Co-curricular activities & holidays strip -->
    <div class="info-strip">
        <div class="info-box">
            <strong>मासिक शालेय व सहशालेय उपक्रम :</strong>
            <span><?= $monthlyEvents ? e(implode(' • ', $monthlyEvents)) : 'नियमित वेळापत्रक व परिपाठ उपक्रम.' ?></span>
        </div>
        <div class="info-box" style="flex: 0.85;">
            <strong>या महिन्यातील शासकीय सुट्ट्या व सण :</strong>
            <span>
                <?php 
                if ($monthHolidays) {
                    $holLabels = [];
                    foreach ($monthHolidays as $h) {
                        $holLabels[] = e($h[1]) . ' (' . date('d/m', strtotime($h[0])) . ')';
                    }
                    echo implode(', ', $holLabels);
                } else {
                    echo 'नियमित रविवार व्यतिरिक्त सार्वजनिक सुट्टी नाही.';
                }
                ?>
            </span>
        </div>
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div>विषय शिक्षक स्वाक्षरी<br><small><?= e($teacher) ?></small></div>
        <div>वर्ग शिक्षक स्वाक्षरी<br><small>इयत्ता <?= e($class['name']) ?></small></div>
        <div>मुख्याध्यापक स्वाक्षरी व शाळा शिक्का<br><small><?= e($schoolName) ?></small></div>
    </div>
</div>

</body>
</html>
