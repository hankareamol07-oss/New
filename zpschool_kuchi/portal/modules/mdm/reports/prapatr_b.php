<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
require_once __DIR__ . '/../../lib/print_layout.php';

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
    $filename = "prapatr_b_{$group}_{$month}_{$year}.csv";
    $csvHeaders = ['अ.क्र.', 'वस्तूचे नाव', 'मागील शिल्लक (Kg)', 'चालू महिना प्राप्त (Kg)', 'उसनवार/लोकसहभाग (Kg)', 'एकूण वस्तू (Kg)', 'अन्न शिजवण्यासाठी वापर (Kg)', 'उसनवार परत/खराब (Kg)', 'शिल्लक वस्तू (Kg)', 'पुढील मागणी (Kg)', 'शेरा'];
    $csvRows = [];
    foreach ($data['commodities'] as $i => $c) {
        $csvRows[] = [
            $i,
            $c['name_mr'],
            number_format($c['opening'], 4, '.', ''),
            number_format($c['received'], 4, '.', ''),
            number_format($c['borrowed'], 4, '.', ''),
            number_format($c['total_available'], 4, '.', ''),
            number_format($c['consumed'], 4, '.', ''),
            number_format($c['returned'], 4, '.', ''),
            number_format($c['closing'], 4, '.', ''),
            number_format($c['demand_next'], 2, '.', ''),
            '',
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
<title>प्रपत्र ब — मासिक अहवाल (<?= e($data['group_label']) ?> - <?= e($data['month_name']) ?> <?= $year ?>)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Martel:wght@700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 portrait; margin: 8mm; }
* { box-sizing: border-box; }
body { font-family: 'Mukta', system-ui, sans-serif; color: #000; background: #fff; margin: 0; padding: 10px; font-size: 11pt; line-height: 1.3; }
.no-print { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px; margin: -10px -10px 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
.btn { font-family: inherit; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
.btn-primary { background: #1e3a8a; color: #fff; border: 1px solid #1e3a8a; }
.btn-outline { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
.btn-success { background: #166534; color: #fff; border: 1px solid #166534; }
.select-ctl { font-family: inherit; font-size: 13px; padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; }

.sheet { max-width: 210mm; margin: 0 auto; }
.head-title { text-align: center; margin-bottom: 4px; }
.head-title h1 { font-family: 'Martel', serif; font-size: 14pt; margin: 0 0 2px; font-weight: 800; color: #000; }
.head-title h2 { font-size: 11.5pt; margin: 0; font-weight: 700; }
.meta-box { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-top: 6px; margin-bottom: 6px; font-size: 9.5pt; }
.meta-box td { border: 1px solid #000; padding: 3px 6px; }

.rpt-table { width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 9pt; }
.rpt-table th, .rpt-table td { border: 1px solid #000; padding: 2.5px 4px; text-align: center; }
.rpt-table th { background: #f1f5f9; font-weight: 700; }
.rpt-table td.l { text-align: left; }
.rpt-table td.r { text-align: right; }
.rpt-table tr.total-row { font-weight: 700; background: #f8fafc; }

.note-box { font-size: 8.5pt; margin-top: 6px; padding: 4px; border: 1px dashed #64748b; }
.calc-box { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-top: 6px; font-size: 9pt; }
.calc-box td { border: 1px solid #000; padding: 3px 6px; }

.sign-area { margin-top: 15px; display: flex; justify-content: space-between; text-align: center; font-size: 10pt; font-weight: 700; page-break-inside: avoid; }
.sign-box { min-width: 160px; border-top: 1px solid #000; padding-top: 4px; }

@media print {
  .no-print { display: none !important; }
  body { padding: 0; }
  .sheet { max-width: 100%; }
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
      <input type="number" name="year" value="<?= $year ?>" class="select-ctl" style="width:80px;" onchange="this.form.submit()">
    </label>
    <button type="submit" class="btn btn-outline">पहा</button>
  </form>

  <div style="display:flex;gap:8px;">
    <a href="?class_group=<?= $group ?>&month=<?= $month ?>&year=<?= $year ?>&excel=1" class="btn btn-success">📥 Excel (CSV)</a>
    <button onclick="window.print()" class="btn btn-primary">🖨️ प्रिंट / PDF</button>
    <a href="<?= url('modules/mdm/reports/index.php') ?>" class="btn btn-outline">सर्व अहवाल</a>
    <a href="<?= url('modules/mdm/dashboard.php') ?>" class="btn btn-outline">डॅशबोर्ड</a>
  </div>
</div>

<div class="sheet">
  <div class="head-title">
    <h1>प्रधानमंत्री पोषण शक्ती निर्माण योजना (<?= e($data['group_label']) ?>)</h1>
    <h2>शाळेने केंद्रप्रमुखांना दरमहा १ तारखेपर्यंत द्यावयाचा अहवाल (२ प्रतीत)</h2>
  </div>

  <table class="meta-box">
    <tr>
      <td><b>शाळेचे नाव :</b> <?= e($schoolName) ?></td>
      <td><b>केंद्र :</b> <?= e((string)($school['center'] ?? ($school['village'] ?? '—'))) ?></td>
      <td><b>तालुका :</b> <?= e((string)($school['taluka'] ?? '—')) ?></td>
      <td><b>जिल्हा :</b> <?= e((string)($school['district'] ?? '—')) ?></td>
    </tr>
    <tr>
      <td><b>पटसंख्या (<?= e($data['group_label']) ?>) :</b> <?= (int)$data['pat'] ?></td>
      <td><b>महिन्यातील एकूण उपस्थित विद्यार्थी :</b> <?= (int)$data['total_beneficiaries'] ?></td>
      <td><b>एकूण कार्य दिवस :</b> <?= (int)$data['working_days'] ?></td>
      <td><b>अन्न शिजवलेले दिवस :</b> <?= (int)$data['cooked_days'] ?></td>
    </tr>
    <tr>
      <td colspan="2"><b>तांदूळ प्राप्त दिनांक व पावती क्र. :</b> ______________________</td>
      <td colspan="2"><b>धान्यादी वस्तू प्राप्त दिनांक व पावती क्र. :</b> ______________________</td>
    </tr>
    <tr>
      <td colspan="4" style="background:#f8fafc;font-weight:700;text-align:center;">
        माहे : <?= e($data['month_name']) ?> <?= $year ?>
      </td>
    </tr>
  </table>

  <table class="rpt-table">
    <thead>
      <tr>
        <th style="width:30px;">अ.क्र.</th>
        <th>वस्तूचे नाव</th>
        <th>मागील शिल्लक<br>(Kg)</th>
        <th>चालू महिना प्राप्त<br>(Kg)</th>
        <th>उसनवार/लोकसहभाग<br>(Kg)</th>
        <th>एकूण वस्तू<br>(३+४+५) (Kg)</th>
        <th>वापरलेल्या वस्तू<br>(Kg)</th>
        <th>उसनवार परत/खराब<br>(Kg)</th>
        <th>शिल्लक वस्तू<br>(६-७-८) (Kg)</th>
        <th>पुढील मागणी<br>(Kg)</th>
        <th style="width:50px;">शेरा</th>
      </tr>
      <tr style="font-size:8pt;background:#f8fafc;color:#475569;">
        <th>१</th><th>२</th><th>३</th><th>४</th><th>५</th><th>६</th><th>७</th><th>८</th><th>९</th><th>१०</th><th>११</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data['commodities'] as $gid => $c): ?>
      <tr>
        <td><?= $gid ?></td>
        <td class="l fw-semibold"><?= e($c['name_mr']) ?></td>
        <td class="r"><?= number_format($c['opening'], ($gid >= 10 && $gid <= 13 ? 4 : 3)) ?></td>
        <td class="r"><?= $c['received'] > 0 ? number_format($c['received'], 3) : '०' ?></td>
        <td class="r"><?= $c['borrowed'] > 0 ? number_format($c['borrowed'], 3) : '०' ?></td>
        <td class="r fw-bold"><?= number_format($c['total_available'], ($gid >= 10 && $gid <= 13 ? 4 : 3)) ?></td>
        <td class="r"><?= number_format($c['consumed'], ($gid >= 10 && $gid <= 13 ? 4 : 3)) ?></td>
        <td class="r"><?= $c['returned'] > 0 ? number_format($c['returned'], 3) : '०' ?></td>
        <td class="r fw-bold"><?= number_format($c['closing'], ($gid >= 10 && $gid <= 13 ? 4 : 3)) ?></td>
        <td class="r"><?= $c['demand_next'] > 0 ? number_format($c['demand_next'], 2) : '—' ?></td>
        <td></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="note-box">
    <b>टीप :</b> मागणी नोंदवताना शाळेकडे वीस दिवसांचा साठा शिल्लक राहील याची दक्षता घेऊन मागणी नोंदवावी. आवश्यकतेपेक्षा जास्त साठा करून धान्य खराब होणार नाही याची दक्षता घ्यावी.
  </div>

  <table class="calc-box">
    <tr>
      <td style="width:50%;">
        <b>१) महिन्यातील ताटांची संख्या :</b> <?= (int)$data['total_beneficiaries'] ?> &nbsp;&nbsp; 
        <b>दर =</b> ₹ <?= number_format($data['cost_rate'], 2) ?> &nbsp;&nbsp; 
        <b>एकूण खर्च =</b> ₹ <?= number_format($data['total_cost'], 2) ?>
      </td>
      <td style="width:50%;">
        <b>३) स्वयंपाकी तथा मदतनीस मानधन :</b> 
        ₹ <?= number_format($data['cooks_honorarium'] > 0 ? $data['cooks_honorarium'] : 2500, 2) ?>
      </td>
    </tr>
    <tr>
      <td colspan="2">
        <b>२) इंधन व भाजीपाल्यासाठी खर्च केलेले अनुदान विभाजन :</b>
        भाजीपाला (३८%) = ₹ <?= number_format($data['grant_veg'], 2) ?> | 
        इंधन (३४%) = ₹ <?= number_format($data['grant_fuel'], 2) ?> | 
        पूरक आहार (२८%) = ₹ <?= number_format($data['grant_suppl'], 2) ?> | 
        <b>एकूण (१००%) =</b> ₹ <?= number_format($data['total_cost'], 2) ?>
      </td>
    </tr>
  </table>

  <p style="font-size:9pt;margin:10px 0 4px;font-style:italic;">
    प्रमाणित करण्यात येते कि वर नमूद केलेली माहिती दैनंदिन नोंदवहीवरून घेतलेली आहे. ती तपासली आहे व बरोबर आहे.
  </p>

  <div class="sign-area">
    <div><b>दिनांक :</b> <?= date('01/m/Y', strtotime("+1 month", strtotime(sprintf('%04d-%02d-01', $year, $month)))) ?></div>
    <div class="sign-box">अध्यक्ष<br>शाळा व्यवस्थापन समिती</div>
    <div class="sign-box">मुख्याध्यापक<br><?= e($schoolName) ?></div>
  </div>
</div>

</body>
</html>
