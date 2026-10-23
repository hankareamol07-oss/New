<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_login();
$sid = (int)$u['school_id'];
$pdo = db();

$group = (string)($_GET['class_group'] ?? '1-5');
if (!in_array($group, ['1-5', '6-8'], true)) $group = '1-5';

$academicYear = (int)($_GET['year'] ?? (date('n') < 4 ? (int)date('Y') - 1 : (int)date('Y')));

$school = school_row($sid);
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$commodities = mdm_official_commodities();

// Aggregate data across the 12 academic months: April ($academicYear) to March ($academicYear + 1)
$monthsSequence = [
    ['m' => 4, 'y' => $academicYear],
    ['m' => 5, 'y' => $academicYear],
    ['m' => 6, 'y' => $academicYear],
    ['m' => 7, 'y' => $academicYear],
    ['m' => 8, 'y' => $academicYear],
    ['m' => 9, 'y' => $academicYear],
    ['m' => 10, 'y' => $academicYear],
    ['m' => 11, 'y' => $academicYear],
    ['m' => 12, 'y' => $academicYear],
    ['m' => 1, 'y' => $academicYear + 1],
    ['m' => 2, 'y' => $academicYear + 1],
    ['m' => 3, 'y' => $academicYear + 1],
];

// Initialize row values per commodity
$row1_opening = array_fill(1, 21, 0.0);
$row2_received = array_fill(1, 21, 0.0);
$row3_borrowed = array_fill(1, 21, 0.0);
$row5_consumed = array_fill(1, 21, 0.0);
$row6_returned = array_fill(1, 21, 0.0);
$totalBeneficiariesYear = 0;

foreach ($monthsSequence as $idx => $my) {
    $mData = mdm_month_data($pdo, $sid, $my['y'], $my['m'], $group);
    $totalBeneficiariesYear += $mData['total_beneficiaries'];

    // April opening is Row 1
    if ($idx === 0) {
        foreach ($mData['commodities'] as $gid => $c) {
            $row1_opening[$gid] = $c['opening'];
        }
    }
    // Sum received, borrowed, consumed, returned over the 12 months
    foreach ($mData['commodities'] as $gid => $c) {
        $row2_received[$gid] += $c['received'];
        $row3_borrowed[$gid] += $c['borrowed'];
        $row5_consumed[$gid] += $c['consumed'];
        $row6_returned[$gid] += $c['returned'];
    }
}

// Compute derived rows
$row4_total_available = [];
$row7_total_used = [];
$row8_closing_balance = [];

foreach ($commodities as $gid => $c) {
    $row4_total_available[$gid] = $row1_opening[$gid] + $row2_received[$gid] + $row3_borrowed[$gid];
    $row7_total_used[$gid] = $row5_consumed[$gid] + $row6_returned[$gid];
    $row8_closing_balance[$gid] = max(0.0, $row4_total_available[$gid] - $row7_total_used[$gid]);
}

$rowsDef = [
    1 => ['num' => '१', 'text' => '३१ मार्च अखेर शिल्लक (मागील वर्षाची)', 'data' => $row1_opening],
    2 => ['num' => '२', 'text' => 'पुरवठा धारकाकडून प्राप्त एकूण माल', 'data' => $row2_received],
    3 => ['num' => '३', 'text' => 'लोकसहभाग उसनवार खुल्या बाजारातून प्राप्त माल', 'data' => $row3_borrowed],
    4 => ['num' => '४', 'text' => 'एकूण प्राप्त माल (१+२+३)', 'data' => $row4_total_available, 'bold' => true],
    5 => ['num' => '५', 'text' => 'वापरण्यात आलेला माल (एकूण वार्षिक वापर)', 'data' => $row5_consumed],
    6 => ['num' => '६', 'text' => 'चोरी नैसर्गिक आपत्तीने खराब उसनवार परत करणे', 'data' => $row6_returned],
    7 => ['num' => '७', 'text' => 'एकूण वापरण्यात आलेला माल (५+६)', 'data' => $row7_total_used, 'bold' => true],
    8 => ['num' => '८', 'text' => 'दि. ३१/०३/' . ($academicYear + 1) . ' अखेर निव्वळ शिल्लक (४-७)', 'data' => $row8_closing_balance, 'highlight' => true],
];

// Excel Export
if (isset($_GET['excel'])) {
    $filename = "annual_uc_{$group}_{$academicYear}_" . ($academicYear + 1) . ".csv";
    $csvHeaders = array_merge(['अ.क्र.', 'तपशील'], array_column($commodities, 'name_mr'));
    $csvRows = [];
    foreach ($rowsDef as $r) {
        $rowArr = [$r['num'], $r['text']];
        foreach ($commodities as $gid => $c) {
            $rowArr[] = number_format($r['data'][$gid], ($gid >= 10 && $gid <= 13 ? 3 : 2), '.', '');
        }
        $csvRows[] = $rowArr;
    }
    $csvRows[] = ['९', 'माहे १ एप्रिल ' . $academicYear . ' ते ३१ मार्च ' . ($academicYear + 1) . ' एकूण लाभार्थी संख्या', $totalBeneficiariesYear];
    mdm_report_csv($filename, $csvHeaders, $csvRows);
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>वार्षिक उपयोगिता प्रमाणपत्र — <?= $group === '1-5' ? 'इयत्ता १ ली ते ५ वी' : 'इयत्ता ६ वी ते ८ वी' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Martel:wght@700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 landscape; margin: 8mm; }
* { box-sizing: border-box; }
body { font-family: 'Mukta', system-ui, sans-serif; color: #000; background: #fff; margin: 0; padding: 10px; font-size: 8.5pt; line-height: 1.25; }
.no-print { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px; margin: -10px -10px 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; font-size: 10pt; }
.btn { font-family: inherit; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
.btn-primary { background: #1e3a8a; color: #fff; border: 1px solid #1e3a8a; }
.btn-outline { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
.btn-success { background: #166534; color: #fff; border: 1px solid #166534; }
.select-ctl { font-family: inherit; font-size: 12px; padding: 3px 6px; border-radius: 6px; border: 1px solid #cbd5e1; }

.table-wrap { overflow-x: auto; width: 100%; }
.sheet { width: 100%; margin: 0 auto; }
.head-title { text-align: center; margin-bottom: 3px; }
.head-title h1 { font-family: 'Martel', serif; font-size: 12.5pt; margin: 0 0 1px; font-weight: 800; }
.head-title h2 { font-size: 10.5pt; margin: 0; font-weight: 700; }
.meta-box { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-top: 4px; margin-bottom: 6px; font-size: 9pt; }
.meta-box td { border: 1px solid #000; padding: 2px 6px; }

.rpt-table { width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 8pt; white-space: nowrap; }
.rpt-table th, .rpt-table td { border: 1px solid #000; padding: 3px 3px; text-align: center; }
.rpt-table th { background: #f1f5f9; font-weight: 700; font-size: 7.5pt; }
.rpt-table td.l { text-align: left; }
.rpt-table td.r { text-align: right; }
.rpt-table tr.bold-row { font-weight: 700; background: #f8fafc; }
.rpt-table tr.highlight-row { font-weight: 800; background: #e0f2fe; }

.pledge-box { font-size: 8pt; margin-top: 8px; line-height: 1.35; padding: 6px 8px; border: 1px solid #64748b; background: #fafafa; }
.sign-area { margin-top: 15px; display: flex; justify-content: space-between; text-align: center; font-size: 9.5pt; font-weight: 700; page-break-inside: avoid; }
.sign-box { min-width: 160px; border-top: 1px solid #000; padding-top: 4px; }

@media print {
  .no-print { display: none !important; }
  body { padding: 0; }
  .table-wrap { overflow: visible; }
}
</style>
</head>
<body>

<div class="no-print">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
    <label class="small fw-bold">गट:
      <select name="class_group" class="select-ctl" onchange="this.form.submit()">
        <option value="1-5" <?= $group === '1-5' ? 'selected' : '' ?>>इयत्ता १ ली ते ५ वी (Primary)</option>
        <option value="6-8" <?= $group === '6-8' ? 'selected' : '' ?>>इयत्ता ६ वी ते ८ वी (Upper Primary)</option>
      </select>
    </label>
    <label class="small fw-bold">शैक्षणिक वर्ष:
      <select name="year" class="select-ctl" onchange="this.form.submit()">
        <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
          <option value="<?= $y ?>" <?= $academicYear === $y ? 'selected' : '' ?>><?= $y ?> - <?= $y + 1 ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <button type="submit" class="btn btn-outline">पहा</button>
  </form>

  <div style="display:flex;gap:6px;">
    <a href="?class_group=<?= $group ?>&year=<?= $academicYear ?>&excel=1" class="btn btn-success">📥 Excel (CSV)</a>
    <button onclick="window.print()" class="btn btn-primary">🖨️ प्रिंट (Landscape)</button>
    <a href="<?= url('modules/mdm/reports/index.php') ?>" class="btn btn-outline">सर्व अहवाल</a>
    <a href="<?= url('modules/mdm/dashboard.php') ?>" class="btn btn-outline">डॅशबोर्ड</a>
  </div>
</div>

<div class="sheet">
  <div class="head-title">
    <h1>शालेय पोषण आहार योजना / प्रधानमंत्री पोषण शक्ती निर्माण योजना</h1>
    <h2>वार्षिक उपयोगिता प्रमाणपत्र (<?= $group === '1-5' ? 'इयत्ता १ ली ते ५ वी' : 'इयत्ता ६ वी ते ८ वी' ?>)</h2>
  </div>

  <table class="meta-box">
    <tr>
      <td><b>शाळेचे नाव :</b> <?= e($schoolName) ?></td>
      <td><b>केंद्र :</b> <?= e((string)($school['center'] ?? ($school['village'] ?? '—'))) ?></td>
      <td><b>तालुका :</b> <?= e((string)($school['taluka'] ?? '—')) ?></td>
      <td><b>जिल्हा :</b> <?= e((string)($school['district'] ?? '—')) ?></td>
      <td><b>शैक्षणिक वर्ष :</b> <?= $academicYear ?> - <?= $academicYear + 1 ?></td>
    </tr>
  </table>

  <div class="table-wrap">
    <table class="rpt-table">
      <thead>
        <tr>
          <th style="width:25px;">अ.क्र.</th>
          <th style="min-width:180px;">तपशील</th>
          <?php foreach ($commodities as $c): ?>
            <th><?= e($c['name_mr']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rowsDef as $r): 
          $cls = !empty($r['highlight']) ? 'highlight-row' : (!empty($r['bold']) ? 'bold-row' : '');
        ?>
        <tr class="<?= $cls ?>">
          <td><?= $r['num'] ?></td>
          <td class="l fw-semibold"><?= e($r['text']) ?></td>
          <?php foreach ($commodities as $gid => $c): ?>
            <td class="r"><?= number_format($r['data'][$gid], ($gid >= 10 && $gid <= 13 ? 3 : 2)) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <tr class="bold-row" style="background:#fef3c7;">
          <td>९</td>
          <td class="l"><b>माहे १ एप्रिल <?= $academicYear ?> ते ३१ मार्च <?= $academicYear + 1 ?> एकूण लाभार्थी संख्या</b></td>
          <td colspan="21" class="l fw-bold" style="font-size:9.5pt;padding-left:12px;">
            <?= (int)$totalBeneficiariesYear ?> लाभार्थी (ताटांची संख्या)
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="pledge-box">
    <b>प्रमाणपत्र :</b> प्रमाणित करण्यात येते कि वरील माहिती मी तपासली असून ती नोंदवहीनुसार बरोबर आहे. आणि यात काही तफावत आढळल्यास त्यास मी सर्वस्वी जबाबदार राहील. सदर मालाच्या शिल्लक साठ्याप्रमाणे सर्व साठा बरोबर आहे. केंद्र शासनाने या आधारे तांदूळ वितरीत केल्यास व जिल्ह्यातील शाळांना तांदूळ अपुरा पडल्यास त्याचा खर्च माझ्या वेतनातून वसूल केला जाईल याची मला जाणीव आहे.
  </div>

  <div class="sign-area">
    <div><b>दिनांक :</b> ३१/०३/<?= $academicYear + 1 ?></div>
    <div class="sign-box">अध्यक्ष<br>शाळा व्यवस्थापन समिती</div>
    <div class="sign-box">मुख्याध्यापक सही व शिक्का<br><?= e($schoolName) ?></div>
  </div>
</div>

</body>
</html>
