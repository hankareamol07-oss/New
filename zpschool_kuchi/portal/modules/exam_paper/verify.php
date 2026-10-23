<?php
require_once __DIR__ . '/includes/functions.php';

$paperId = (int)($_GET['id'] ?? 0);
$sig = trim($_GET['sig'] ?? '');
$paper = $paperId > 0 ? ep_paper($paperId) : null;

$isValid = false;
$expectedSig = '';
if ($paper) {
    $expectedSig = ep_paper_signature($paper);
    if ($sig === '' || hash_equals($expectedSig, $sig)) {
        $isValid = true;
    }
}

$schoolName = ep_setting('school_name', 'माझी शाळा');
$schoolAddr = ep_setting('school_address', 'महाराष्ट्र राज्य');
$hubSchool = ep_get_hub_school();
if ($hubSchool) {
    $schoolName = $hubSchool['name_mr'] ?: ($hubSchool['name'] ?: $schoolName);
    if (!empty($hubSchool['formatted_address'])) {
        $schoolAddr = $hubSchool['formatted_address'];
    }
}
$logoUrl = ep_logo_url();
$setCode = strtoupper(trim($_GET['set'] ?? 'A'));
if (!in_array($setCode, ['A', 'B', 'C', 'D'], true)) {
    $setCode = 'A';
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>प्रश्नपत्रिका पडताळणी प्रमाणपत्र (Exam Verification Certificate)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body {
    background: #f1f5f9;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Mukta', sans-serif;
    color: #1e293b;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 12px;
  }
  .cert-card {
    max-width: 650px;
    width: 100%;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
  }
  .cert-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #ffffff;
    padding: 28px 24px;
    text-align: center;
    position: relative;
  }
  .cert-badge {
    position: absolute;
    top: 18px;
    right: 20px;
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid #10b981;
    color: #34d399;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
  }
  .cert-seal {
    width: 72px;
    height: 72px;
    background: #10b981;
    color: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.2);
    margin-bottom: 12px;
  }
  .cert-seal.invalid {
    background: #ef4444;
    box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.2);
  }
  .cert-body {
    padding: 28px 24px;
  }
  .info-table {
    width: 100%;
    margin-top: 14px;
    border-collapse: collapse;
  }
  .info-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.95rem;
  }
  .info-table td.label {
    width: 38%;
    font-weight: 600;
    color: #64748b;
  }
  .info-table td.value {
    font-weight: 700;
    color: #0f172a;
  }
  .hash-code {
    font-family: monospace;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-all;
  }
</style>
</head>
<body>

<div class="cert-card">
  <div class="cert-header">
    <div class="cert-badge">
      <i class="bi bi-shield-lock-fill me-1"></i> HMAC-SHA256 SECURED
    </div>
    <div class="cert-seal <?= $isValid ? '' : 'invalid' ?>">
      <i class="bi <?= $isValid ? 'bi-patch-check-fill' : 'bi-shield-x' ?>"></i>
    </div>
    <h4 class="mb-1 text-white"><?= $isValid ? 'अधिकृत प्रश्नपत्रिका पडताळणी प्रमाणपत्र' : 'अवैध / अनधिकृत प्रश्नपत्रिका' ?></h4>
    <p class="text-white-50 mb-0 small">
      <?= $isValid ? 'Official Examination Paper Authenticity Certificate' : 'The digital verification signature did not match.' ?>
    </p>
  </div>

  <div class="cert-body">
    <?php if ($isValid && $paper): ?>
      <div class="alert alert-success d-flex align-items-center mb-3 py-2 px-3">
        <i class="bi bi-check-circle-fill fs-4 me-2 text-success"></i>
        <div>
          <strong>सत्यप्रत पडताळणी यशस्वी!</strong><br>
          <small class="text-muted">ही प्रश्नपत्रिका शाळेच्या मूळ डिजिटल प्रणालीद्वारे निर्गमित करण्यात आलेली अधिकृत परीक्षा प्रत आहे.</small>
        </div>
      </div>

      <div class="text-center mb-3">
        <?php if ($logoUrl): ?>
          <img src="<?= h($logoUrl) ?>" alt="School Logo" style="height: 52px; max-width: 140px; object-fit: contain;" class="mb-2">
        <?php endif; ?>
        <h5 class="fw-bold text-dark mb-0"><?= h($schoolName) ?></h5>
        <?php if ($schoolAddr): ?>
          <div class="text-muted small"><?= h($schoolAddr) ?></div>
        <?php endif; ?>
      </div>

      <table class="info-table">
        <tr>
          <td class="label"><i class="bi bi-journal-text me-1 text-primary"></i> परीक्षेचे नाव (Exam):</td>
          <td class="value"><?= h($paper['title']) ?></td>
        </tr>
        <tr>
          <td class="label"><i class="bi bi-layers me-1 text-primary"></i> संच कोड (Set):</td>
          <td class="value"><span class="badge bg-primary fs-6">संच <?= h($setCode) ?></span></td>
        </tr>
        <tr>
          <td class="label"><i class="bi bi-mortarboard me-1 text-primary"></i> इयत्ता / वर्ग (Class):</td>
          <td class="value"><?= h($paper['std_label'] ?: ($paper['standard_name'] ?? '—')) ?></td>
        </tr>
        <tr>
          <td class="label"><i class="bi bi-book me-1 text-primary"></i> विषय (Subject):</td>
          <td class="value"><?= h($paper['exam_name'] ?: ($paper['subject_name'] ?? '—')) ?></td>
        </tr>
        <tr>
          <td class="label"><i class="bi bi-award me-1 text-primary"></i> एकूण गुण (Total Marks):</td>
          <td class="value"><?= (float)$paper['total_marks'] ?> गुण</td>
        </tr>
        <tr>
          <td class="label"><i class="bi bi-calendar-event me-1 text-primary"></i> परीक्षा दिनांक (Date):</td>
          <td class="value"><?= $paper['exam_date'] ? date('d/m/Y', strtotime($paper['exam_date'])) : 'अधिकृत सत्र परीक्षा' ?></td>
        </tr>
        <tr>
          <td class="label"><i class="bi bi-clock-history me-1 text-primary"></i> वेळ (Duration):</td>
          <td class="value"><?= h($paper['duration'] ?: 'वेळ नियमाप्रमाणे') ?></td>
        </tr>
      </table>

      <div class="mt-4">
        <label class="small text-muted fw-bold mb-1">डिजिटल सुरक्षा स्वाक्षरी कोड (Digital Security Token):</label>
        <div class="hash-code d-flex justify-content-between align-items-center">
          <span><?= h($expectedSig) ?></span>
          <span class="badge bg-success-subtle text-success border border-success-subtle">VERIFIED</span>
        </div>
      </div>

      <div class="text-center mt-4">
        <a href="paper_view.php?id=<?= (int)$paper['paper_id'] ?>&set=<?= h($setCode) ?>" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-file-earmark-text me-1"></i> प्रश्नपत्रिका पहा (View Paper)
        </a>
      </div>

    <?php else: ?>
      <div class="alert alert-danger py-3 text-center">
        <i class="bi bi-exclamation-triangle-fill fs-2 text-danger d-block mb-2"></i>
        <h5 class="fw-bold">पडताळणी अयशस्वी (Verification Failed)</h5>
        <p class="mb-0 text-muted small">
          दिलेला सुरक्षा कोड किंवा प्रश्नपत्रिका आयडी सिस्टीममध्ये उपलब्ध नाही किंवा प्रश्नपत्रिकेत फेरफार (tamper) झालेली असू शकते. कृपया योग्य मूळ प्रत तपासावी.
        </p>
      </div>
      <div class="text-center mt-3">
        <a href="index.php" class="btn btn-secondary btn-sm">डॅशबोर्डवर परत जा</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-light py-2 text-center text-muted small border-top">
    स्मार्ट स्कूल हब परीक्षा सुरक्षा प्रणाली • Powered by ExamVantage Anti-Cheat Core
  </div>
</div>

</body>
</html>
