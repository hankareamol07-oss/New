<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_login();
$sid = (int)$u['school_id'];
$pdo = db();

$group = (string)($_GET['class_group'] ?? '1-5');
if (!in_array($group, ['1-5', '6-8'], true)) $group = '1-5';

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) $month = (int)date('n');

$data = mdm_month_data($pdo, $sid, $year, $month, $group);
$school = $data['school'];
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));

// Excel export
if (isset($_GET['excel'])) {
    $filename = "nodvahi_part2_{$group}_{$month}_{$year}.csv";
    $csvHeaders = [
        'अ.क्र.', 'दिनांक', 'वार', 'पट', 'लाभार्थी',
        'तांदूळ', 'मूगडाळ', 'तूरडाळ', 'मसूरडाळ', 'मटकी', 'अख्खा मूग', 'चवळी', 'हरभरा', 'वाटाणा',
        'जिरे', 'मोहरी', 'हळद', 'तिखट मसाला', 'तेल', 'मीठ', 'मिरची पावडर', 'गूळ/साखर', 'दूध पावडर', 'सोयाबीन वडी', 'नाचणी सत्त्व', 'गरम मसाला',
        'भाजीपाला', 'खर्च (₹)'
    ];
    $csvRows = [];
    foreach ($data['days'] as $d) {
        $csvRows[] = [
            $d['day_no'],
            $d['date'],
            $d['weekday'],
            $d['pat'],
            $d['beneficiaries'],
            number_format($d['items'][1], 3, '.', ''),
            number_format($d['items'][2], 3, '.', ''),
            number_format($d['items'][3], 3, '.', ''),
            number_format($d['items'][4], 3, '.', ''),
            number_format($d['items'][5], 3, '.', ''),
            number_format($d['items'][6], 3, '.', ''),
            number_format($d['items'][7], 3, '.', ''),
            number_format($d['items'][8], 3, '.', ''),
            number_format($d['items'][9], 3, '.', ''),
            number_format($d['items'][10], 4, '.', ''),
            number_format($d['items'][11], 4, '.', ''),
            number_format($d['items'][12], 4, '.', ''),
            number_format($d['items'][13], 4, '.', ''),
            number_format($d['items'][14], 3, '.', ''),
            number_format($d['items'][15], 3, '.', ''),
            number_format($d['items'][16], 4, '.', ''),
            number_format($d['items'][17], 3, '.', ''),
            number_format($d['items'][18], 3, '.', ''),
            number_format($d['items'][19], 3, '.', ''),
            number_format($d['items'][20], 3, '.', ''),
            number_format($d['items'][21], 4, '.', ''),
            number_format($d['veg'], 3, '.', ''),
            number_format($d['cost'], 2, '.', ''),
        ];
    }
    mdm_report_csv($filename, $csvHeaders, $csvRows);
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>दैनिक खर्च नोंदवही भाग - २ (धान्याचे हिशोब) — <?= e($data['group_label']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Martel:wght@700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 landscape; margin: 6mm; }
* { box-sizing: border-box; }
body { font-family: 'Mukta', system-ui, sans-serif; color: #000; background: #fff; margin: 0; padding: 10px; font-size: 8pt; line-height: 1.2; }
.no-print { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px; margin: -10px -10px 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; font-size: 10pt; }
.btn { font-family: inherit; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
.btn-primary { background: #1e3a8a; color: #fff; border: 1px solid #1e3a8a; }
.btn-outline { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
.btn-success { background: #166534; color: #fff; border: 1px solid #166534; }
.select-ctl { font-family: inherit; font-size: 12px; padding: 3px 6px; border-radius: 6px; border: 1px solid #cbd5e1; }

.table-wrap { overflow-x: auto; width: 100%; }
.sheet { width: 100%; margin: 0 auto; }
.head-title { text-align: center; margin-bottom: 3px; }
.head-title h1 { font-family: 'Martel', serif; font-size: 12pt; margin: 0 0 1px; font-weight: 800; }
.head-title h2 { font-size: 10pt; margin: 0; font-weight: 700; }
.meta-box { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-top: 4px; margin-bottom: 4px; font-size: 8.5pt; }
.meta-box td { border: 1px solid #000; padding: 2px 5px; }

.rpt-table { width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 7.5pt; white-space: nowrap; }
.rpt-table th, .rpt-table td { border: 1px solid #000; padding: 1.5px 2px; text-align: center; }
.rpt-table th { background: #f1f5f9; font-weight: 700; font-size: 7pt; }
.rpt-table td.r { text-align: right; }
.rpt-table tr.sunday-row { background: #fff1f2; color: #be123c; }
.rpt-table tr.summary-row { background: #f8fafc; font-weight: 700; }
.rpt-table tr.closing-row { background: #e0f2fe; font-weight: 800; }

.sign-area { margin-top: 10px; display: flex; justify-content: space-between; text-align: center; font-size: 9pt; font-weight: 700; page-break-inside: avoid; }
.sign-box { min-width: 140px; border-top: 1px solid #000; padding-top: 3px; }

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
    <label class="small fw-bold">महिना:
      <select name="month" class="select-ctl" onchange="this.form.submit()">
        <?php foreach (mdm_marathi_months() as $mNum => $mLabel): ?>
          <option value="<?= $mNum ?>" <?= $month === $mNum ? 'selected' : '' ?>><?= $mLabel ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="small fw-bold">वर्ष:
      <input type="number" name="year" value="<?= $year ?>" class="select-ctl" style="width:75px;" onchange="this.form.submit()">
    </label>
    <button type="submit" class="btn btn-outline">पहा</button>
  </form>

  <div style="display:flex;gap:6px;">
    <a href="?class_group=<?= $group ?>&month=<?= $month ?>&year=<?= $year ?>&excel=1" class="btn btn-success">📥 Excel (CSV)</a>
    <button onclick="window.print()" class="btn btn-primary">🖨️ प्रिंट (Landscape)</button>
    <a href="<?= url('modules/mdm/reports/index.php') ?>" class="btn btn-outline">सर्व अहवाल</a>
    <a href="<?= url('modules/mdm/dashboard.php') ?>" class="btn btn-outline">डॅशबोर्ड</a>
  </div>
</div>

<div class="sheet">
  <div class="head-title">
    <h1>प्रधानमंत्री पोषण शक्ती निर्माण योजना (<?= e($data['group_label']) ?>)</h1>
    <h2>शाळा स्तरावर ठेवण्याची दैनंदिन खर्च नोंदवही भाग - २ (धान्याचे हिशोब)</h2>
  </div>

  <table class="meta-box">
    <tr>
      <td><b>शाळेचे नाव :</b> <?= e($schoolName) ?></td>
      <td><b>केंद्र :</b> <?= e((string)($school['center'] ?? ($school['village'] ?? '—'))) ?></td>
      <td><b>तालुका :</b> <?= e((string)($school['taluka'] ?? '—')) ?></td>
      <td><b>जिल्हा :</b> <?= e((string)($school['district'] ?? '—')) ?></td>
      <td><b>पटसंख्या :</b> <?= (int)$data['pat'] ?></td>
      <td><b>माहे :</b> <?= e($data['month_name']) ?> <?= $year ?></td>
    </tr>
  </table>

  <div class="table-wrap">
    <table class="rpt-table">
      <thead>
        <tr>
          <th rowspan="2" style="width:20px;">अ.क्र.</th>
          <th rowspan="2" style="width:45px;">तपशिल</th>
          <th rowspan="2" style="width:25px;">वार</th>
          <th rowspan="2">पट</th>
          <th rowspan="2">लाभार्थी<br>(ताटे)</th>
          <?php foreach ($data['commodities'] as $c): ?>
            <th><?= e($c['name_mr']) ?></th>
          <?php endforeach; ?>
          <th>भाजीपाला</th>
          <th>खर्च (₹)</th>
        </tr>
      </thead>
      <tbody>
        <!-- Top Opening Rows as in Excel -->
        <tr class="summary-row">
          <td colspan="5" style="text-align:left;"><b>मागील शिल्लक (Opening)</b></td>
          <?php foreach ($data['commodities'] as $gid => $c): ?>
            <td class="r"><?= number_format($c['opening'], ($gid >= 10 && $gid <= 13 ? 3 : 2)) ?></td>
          <?php endforeach; ?>
          <td>—</td>
          <td>—</td>
        </tr>
        <tr class="summary-row">
          <td colspan="5" style="text-align:left;"><b>पुरवठादार प्राप्त (Received)</b></td>
          <?php foreach ($data['commodities'] as $c): ?>
            <td class="r"><?= $c['received'] > 0 ? number_format($c['received'], 2) : '०' ?></td>
          <?php endforeach; ?>
          <td>—</td>
          <td>—</td>
        </tr>
        <tr class="summary-row">
          <td colspan="5" style="text-align:left;"><b>उसनवार / लोकसहभाग (Borrowed)</b></td>
          <?php foreach ($data['commodities'] as $c): ?>
            <td class="r"><?= $c['borrowed'] > 0 ? number_format($c['borrowed'], 2) : '०' ?></td>
          <?php endforeach; ?>
          <td>—</td>
          <td>—</td>
        </tr>
        <tr class="summary-row" style="background:#f1f5f9;">
          <td colspan="5" style="text-align:left;"><b>एकूण उपलब्ध (Total)</b></td>
          <?php foreach ($data['commodities'] as $gid => $c): ?>
            <td class="r fw-bold"><?= number_format($c['total_available'], ($gid >= 10 && $gid <= 13 ? 3 : 2)) ?></td>
          <?php endforeach; ?>
          <td>—</td>
          <td>—</td>
        </tr>

        <!-- Daily Rows -->
        <?php foreach ($data['days'] as $d): ?>
        <tr class="<?= $d['is_sunday'] ? 'sunday-row' : '' ?>">
          <td><?= $d['day_no'] ?></td>
          <td><?= date('d/m', strtotime($d['date'])) ?></td>
          <td><?= $d['weekday'] ?></td>
          <td><?= $d['pat'] ?></td>
          <td class="fw-bold"><?= $d['beneficiaries'] > 0 ? $d['beneficiaries'] : '०' ?></td>
          <?php foreach ($data['commodities'] as $gid => $c): 
            $amt = $d['items'][$gid] ?? 0.0;
          ?>
            <td class="r"><?= $amt > 0 ? number_format($amt, ($gid >= 10 && $gid <= 13 ? 4 : 3)) : '०' ?></td>
          <?php endforeach; ?>
          <td class="r"><?= $d['veg'] > 0 ? number_format($d['veg'], 2) : '०' ?></td>
          <td class="r"><?= $d['cost'] > 0 ? number_format($d['cost'], 2) : '०' ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- Bottom Summary Rows -->
        <tr class="summary-row" style="background:#fef3c7;">
          <td colspan="4"><b>एकूण वापर (Total Consumed)</b></td>
          <td class="fw-bold"><?= (int)$data['total_beneficiaries'] ?></td>
          <?php foreach ($data['commodities'] as $gid => $c): ?>
            <td class="r fw-bold"><?= number_format($c['consumed'], ($gid >= 10 && $gid <= 13 ? 3 : 2)) ?></td>
          <?php endforeach; ?>
          <td class="r fw-bold"><?= number_format($data['total_veg'], 2) ?></td>
          <td class="r fw-bold">₹ <?= number_format($data['total_cost'], 2) ?></td>
        </tr>
        <tr class="summary-row">
          <td colspan="5" style="text-align:left;"><b>उसनवार परत / खराब होणे</b></td>
          <?php foreach ($data['commodities'] as $c): ?>
            <td class="r"><?= $c['returned'] > 0 ? number_format($c['returned'], 2) : '०' ?></td>
          <?php endforeach; ?>
          <td>—</td>
          <td>—</td>
        </tr>
        <tr class="closing-row">
          <td colspan="5" style="text-align:left;"><b>शिल्लक साहित्य (Closing Stock)</b></td>
          <?php foreach ($data['commodities'] as $gid => $c): ?>
            <td class="r"><?= number_format($c['closing'], ($gid >= 10 && $gid <= 13 ? 3 : 2)) ?></td>
          <?php endforeach; ?>
          <td>—</td>
          <td>—</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="sign-area">
    <div><b>एकूण लाभार्थी ताटे :</b> <?= (int)$data['total_beneficiaries'] ?> &nbsp;&nbsp;|&nbsp;&nbsp; <b>एकूण खर्च :</b> ₹ <?= number_format($data['total_cost'], 2) ?></div>
    <div class="sign-box">अध्यक्ष<br>शाळा व्यवस्थापन समिती</div>
    <div class="sign-box">मुख्याध्यापक<br><?= e($schoolName) ?></div>
  </div>
</div>

</body>
</html>
