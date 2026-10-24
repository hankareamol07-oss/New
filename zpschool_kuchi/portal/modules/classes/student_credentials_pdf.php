<?php
/**
 * विद्यार्थी लॉगिन आयडी व पासवर्ड यादी (Student Login Credentials Report)
 * Printable A4 PDF Report for teachers and parents.
 */
require_once __DIR__ . '/../../includes/tenant_db.php';
require_once __DIR__ . '/../../api/helpers.php';

$schoolId = 1;
$tdb = getSchoolTenantDB('27350302306');
if (!$tdb) {
    die('शाळेचा डेटाबेस उपलब्ध नाही.');
}

$std = isset($_GET['std']) && $_GET['std'] !== '' ? (int)$_GET['std'] : 0;
$sec = trim($_GET['section'] ?? '');

$where = ["(s.is_active = 1 OR s.is_active IS NULL)"];
$params = [];

if ($std > 0) {
    $where[] = "s.std = ?";
    $params[] = $std;
}
if (!empty($sec)) {
    $where[] = "s.section = ?";
    $params[] = $sec;
}

$whereClause = implode(' AND ', $where);

// Fetch students from tenant SQLite
$sql = "
    SELECT s.id, s.name, s.name_mr, s.std, s.section, s.roll_no, s.student_pen, s.gender
    FROM students s
    WHERE $whereClause
    ORDER BY s.std ASC, CAST(s.roll_no AS UNSIGNED) ASC, s.name_mr ASC
";

$stmt = $tdb->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Optional MySQL usernames map
$usernames = [];
try {
    require_once __DIR__ . '/../../includes/db.php';
    if (isset($db)) {
        $uStmt = $db->query("SELECT linked_id, username FROM users WHERE role = 'student' AND school_id = 1");
        foreach ($uStmt->fetchAll(PDO::FETCH_ASSOC) as $ur) {
            $usernames[(int)$ur['linked_id']] = $ur['username'];
        }
    }
} catch (Throwable $e) {}

function mrStdLabel($num) {
    $map = [1 => 'पहिली', 2 => 'दुसरी', 3 => 'तिसरी', 4 => 'चौथी', 5 => 'पाचवी', 6 => 'सहावी', 7 => 'सातवी', 8 => 'आठवी'];
    return $map[(int)$num] ?? "इ. $num";
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
  <meta charset="UTF-8">
  <title>विद्यार्थी लॉगिन आयडी व पासवर्ड यादी - जि. प. शाळा कुची क्र. २</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    @page {
      size: A4 portrait;
      margin: 10mm 8mm 10mm 8mm;
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    body {
      font-family: 'Mukta', sans-serif;
      margin: 0;
      padding: 12px;
      color: #0f172a;
      background: #f1f5f9;
    }
    .print-bar {
      max-width: 210mm;
      margin: 0 auto 16px auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #1e293b;
      padding: 10px 16px;
      border-radius: 8px;
      color: #fff;
    }
    .print-bar button {
      background: #10b981;
      color: #fff;
      border: none;
      padding: 8px 18px;
      border-radius: 6px;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .paper {
      width: 100%;
      max-width: 210mm;
      margin: 0 auto;
      background: #fff;
      padding: 12mm 10mm;
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      border-radius: 4px;
    }
    .header {
      text-align: center;
      border-bottom: 2.5px solid #1e3a8a;
      padding-bottom: 8px;
      margin-bottom: 10px;
    }
    .tagline {
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
    }
    .school-name {
      font-size: 21px;
      font-weight: 800;
      color: #1e3a8a;
      margin: 2px 0;
    }
    .sub-head {
      font-size: 13px;
      color: #334155;
      font-weight: 600;
    }
    .report-title {
      display: inline-block;
      margin-top: 6px;
      background: #eff6ff;
      border: 1.5px solid #bfdbfe;
      color: #1e40af;
      padding: 3px 14px;
      border-radius: 18px;
      font-weight: 800;
      font-size: 14px;
    }
    .meta-bar {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
      font-size: 12.5px;
      font-weight: 600;
      color: #475569;
      background: #f8fafc;
      padding: 5px 10px;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }
    th, td {
      border: 1px solid #cbd5e1;
      padding: 4.5px 6px;
      vertical-align: middle;
    }
    th {
      background: #f1f5f9;
      color: #1e293b;
      font-weight: 700;
      text-align: center;
    }
    td.center {
      text-align: center;
    }
    .student-name {
      font-weight: 700;
      color: #0f172a;
    }
    .login-id {
      font-family: monospace;
      font-size: 12px;
      font-weight: 700;
      color: #1d4ed8;
      background: #eff6ff;
      padding: 1px 5px;
      border-radius: 4px;
      display: inline-block;
    }
    .pass-pin {
      font-family: monospace;
      font-size: 12px;
      font-weight: 700;
      color: #047857;
      background: #ecfdf5;
      padding: 1px 5px;
      border-radius: 4px;
      display: inline-block;
    }
    .pen-code {
      font-family: monospace;
      font-size: 11px;
      color: #475569;
    }
    .instructions {
      margin-top: 12px;
      padding: 6px 10px;
      background: #fffbeb;
      border: 1px solid #fef3c7;
      border-radius: 6px;
      font-size: 11px;
      color: #92400e;
      line-height: 1.45;
    }
    .sign-row {
      margin-top: 28px;
      display: flex;
      justify-content: space-between;
      padding: 0 20px;
      font-weight: 700;
      font-size: 12px;
      color: #334155;
    }
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .print-bar {
        display: none !important;
      }
      .paper {
        padding: 0;
        box-shadow: none;
        max-width: 100%;
      }
      table {
        page-break-inside: auto;
      }
      tr {
        page-break-inside: avoid;
        page-break-after: auto;
      }
    }
  </style>
</head>
<body>

<div class="print-bar">
  <div>
    <b>विद्यार्थी ॲप लॉगिन माहिती यादी (PDF)</b> — एकूण विद्यार्थी: <?= count($students) ?>
  </div>
  <div>
    <button onclick="window.print()">🖨️ ही यादी प्रिंट करा / PDF सेव्ह करा</button>
  </div>
</div>

<div class="paper">
  <div class="header">
    <div class="tagline">॥ विद्या विनयेन शोभते ॥ • महाराष्ट्र शासन मान्य</div>
    <div class="school-name">जिल्हा परिषद प्राथमिक शाळा कुची क्र. २</div>
    <div class="sub-head">ता. कवठे महांकाळ, जि. सांगली • UDISE कोड: 27350302306</div>
    <div class="report-title">
      📱 विद्यार्थी ॲप लॉगिन आयडी व पासवर्ड यादी (२०२६-२७)
      <?= $std > 0 ? " — " . mrStdLabel($std) : " (सर्व वर्ग)" ?>
    </div>
  </div>

  <div class="meta-bar">
    <div>वर्ग: <b><?= $std > 0 ? mrStdLabel($std) : "इयत्ता १ ली ते ८ वी (सर्व)" ?></b></div>
    <div>तुकडी: <b><?= $sec ? $sec : "सर्व तुकड्या" ?></b></div>
    <div>एकूण विद्यार्थी: <b><?= count($students) ?></b></div>
    <div>डिफॉल्ट पासवर्ड / PIN: <b style="color:#047857;">123456</b></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 5%;">अ.क्र.</th>
        <th style="width: 8%;">इयत्ता</th>
        <th style="width: 6%;">ह.क्र.</th>
        <th style="width: 33%; text-align: left;">विद्यार्थ्याचे नाव</th>
        <th style="width: 17%;">लॉगिन User ID</th>
        <th style="width: 17%;">Student PEN</th>
        <th style="width: 14%;">पासवर्ड / PIN</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($students)): ?>
        <tr><td colspan="7" class="center" style="padding: 20px; color: #64748b;">कोणतेही विद्यार्थी आढळले नाहीत.</td></tr>
      <?php else: ?>
        <?php foreach ($students as $i => $s): 
          $sid = (int)$s['id'];
          $sStd = (int)($s['std'] ?? 1);
          $sRoll = $s['roll_no'] ?: ($i + 1);
          $username = $usernames[$sid] ?? ('kuchi' . $sStd . str_pad($sRoll, 2, '0', STR_PAD_LEFT));
          $sPen = $s['student_pen'] ?: '—';
          $sName = $s['name_mr'] ?: $s['name'];
        ?>
        <tr>
          <td class="center"><?= $i + 1 ?></td>
          <td class="center"><?= mrStdLabel($sStd) ?></td>
          <td class="center"><b><?= $sRoll ?></b></td>
          <td class="student-name"><?= htmlspecialchars($sName) ?></td>
          <td class="center"><span class="login-id"><?= htmlspecialchars($username) ?></span></td>
          <td class="center"><span class="pen-code"><?= htmlspecialchars($sPen) ?></span></td>
          <td class="center"><span class="pass-pin">123456</span></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="instructions">
    <b>पालक व विद्यार्थ्यांसाठी सूचना:</b><br>
    १. मोबाईल ॲपमध्ये किंवा पोर्टलवर लॉगिन करताना वरील <b>लॉगिन User ID</b> किंवा <b>Student PEN</b> टाकावा.<br>
    २. पासवर्ड / पिन च्या जागी <b>123456</b> प्रविष्ट करावा.<br>
    ३. ॲपमध्ये "विद्यार्थी" निवडून आपली इयत्ता व नाव निवडूनही थेट १-क्लिक लॉगिन करता येते.
  </div>

  <div class="sign-row">
    <div>वर्गशिक्षक स्वाक्षरी</div>
    <div>मुख्याध्यापक स्वाक्षरी व शिक्का</div>
  </div>
</div>

</body>
</html>
