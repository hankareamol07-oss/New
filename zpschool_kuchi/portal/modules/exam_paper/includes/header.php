<?php
require_once __DIR__ . '/functions.php';
$epPage = $epPage ?? '';
$epTitle = $epTitle ?? 'प्रश्नपत्रिका जनरेटर (Exam Paper Generator)';
$hubUrl = defined('APP_URL') ? APP_URL : '/hpc';
$epAccess = ep_check_access();
$logoUrl = ep_logo_url();
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($epTitle) ?> - <?= h(ep_setting('school_name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= EP_BASE_URL ?>/assets/exam_paper.css" rel="stylesheet">
<link href="<?= (defined('APP_URL') ? APP_URL : '') ?>/assets/css/theme.css" rel="stylesheet">
<style>
@font-face {
  font-family: 'Tiro Devanagari Marathi';
  src: local('Tiro Devanagari Marathi'),
       url('/hpc/assets/fonts/TiroDevanagariMarathi-Regular.ttf') format('truetype'),
       url('/assets/fonts/TiroDevanagariMarathi-Regular.ttf') format('truetype');
  font-weight: 400;
  font-style: normal;
}
@font-face {
  font-family: 'Utsaah';
  src: local('Utsaah'),
       url('/hpc/assets/fonts/utsaah.woff') format('woff'),
       url('/hpc/assets/fonts/utsaah.ttf') format('truetype'),
       url('/assets/fonts/utsaah.woff') format('woff'),
       url('/assets/fonts/utsaah.ttf') format('truetype');
  font-weight: 400;
  font-style: normal;
}
@font-face {
  font-family: 'Utsaah';
  src: local('Utsaah Bold'),
       url('/hpc/assets/fonts/utsaahb.woff') format('woff'),
       url('/hpc/assets/fonts/utsaahb.ttf') format('truetype'),
       url('/assets/fonts/utsaahb.woff') format('woff'),
       url('/assets/fonts/utsaahb.ttf') format('truetype');
  font-weight: 700;
  font-style: normal;
}
body {
    font-family: var(--tg-font);
}
.navbar-brand span {
    font-weight: 700;
}
.nav-link {
    font-weight: 600;
    font-size: 0.92rem;
}
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm" style="background: linear-gradient(135deg, var(--tg-900) 0%, var(--tg-600) 100%) !important;">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-2">
      <a href="<?= $hubUrl ?>/dashboard.php" class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 shadow-sm" style="border-radius:8px; font-weight:600;" title="स्मार्ट स्कूल हब मुख्य डॅशबोर्ड">
        <i class="bi bi-arrow-left"></i> <span class="d-none d-md-inline">हब डॅशबोर्ड</span>
      </a>
      <a class="navbar-brand d-flex align-items-center gap-2 mb-0" href="<?= EP_BASE_URL ?>/index.php">
        <?php if ($logoUrl): ?>
          <img src="<?= h($logoUrl) ?>" alt="logo" style="height:32px" class="rounded bg-white p-0.5">
        <?php else: ?>
          <div class="bg-white text-primary rounded-circle p-1 d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px;">
            <i class="bi bi-file-earmark-text-fill"></i>
          </div>
        <?php endif; ?>
        <span><?= h(ep_setting('school_name')) ?> &middot; <span class="fw-normal opacity-90">प्रश्नपत्रिका जनरेटर</span></span>
      </a>
    </div>

    <!-- ACCESS BADGE -->
    <div class="d-none d-lg-flex align-items-center me-3 ms-2">
      <?php if ($epAccess['type'] === 'trial'): ?>
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm fw-bold">
          <i class="bi bi-clock-history me-1"></i> <?= h($epAccess['badge_text']) ?>
        </span>
      <?php elseif ($epAccess['type'] === 'free'): ?>
        <span class="badge bg-success px-3 py-2 rounded-pill shadow-sm fw-bold">
          <i class="bi bi-gift-fill me-1"></i> १००% मोफत मॉड्युल
        </span>
      <?php elseif ($epAccess['type'] === 'subscribed'): ?>
        <span class="badge bg-light text-primary px-3 py-2 rounded-pill shadow-sm fw-bold">
          <i class="bi bi-gem me-1"></i> <?= h($epAccess['badge_text']) ?>
        </span>
      <?php elseif ($epAccess['type'] === 'combo'): ?>
        <span class="badge bg-info text-dark px-3 py-2 rounded-pill shadow-sm fw-bold">
          <i class="bi bi-layers-fill me-1"></i> <?= h($epAccess['badge_text']) ?>
        </span>
      <?php elseif ($epAccess['type'] === 'expired'): ?>
        <span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm fw-bold">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> चाचणी संपली
        </span>
      <?php endif; ?>
    </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#epNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="epNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $epPage === 'papers' ? 'active' : '' ?>" href="#" id="dashDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-grid-fill me-1"></i> डॅशबोर्ड
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dashDropdown">
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/index.php?tab=daily"><i class="bi bi-calendar2-check-fill text-warning me-2"></i> १. दैनंदिन प्रश्न स्वाध्याय</a></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/index.php?tab=competitive"><i class="bi bi-trophy-fill text-primary me-2"></i> २. स्पर्धा परीक्षा (Scholarship/Navodaya)</a></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/index.php?tab=assessment"><i class="bi bi-journal-check text-success me-2"></i> ३. आकारिक व संकलित मूल्यमापन</a></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/index.php?tab=subjectwise"><i class="bi bi-book-half text-danger me-2"></i> ४. विषयनिहाय घटक चाचणी</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/index.php?tab=all"><i class="bi bi-folder-fill text-secondary me-2"></i> सर्व सेव्ह केलेले पेपर</a></li>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($epPage, ['create', 'competitive', 'assessment'], true) ? 'active' : '' ?>" href="#" id="createDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-plus-circle me-1"></i> पेपर निर्मिती
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="createDropdown">
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/create_paper.php"><i class="bi bi-file-earmark-plus text-primary me-2"></i> नवीन विषयनिहाय घटक चाचणी</a></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/competitive_paper.php"><i class="bi bi-trophy text-warning me-2"></i> शिष्यवृत्ती / नवोदय / NMMS पेपर</a></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/assessment_paper.php"><i class="bi bi-journal-check text-success me-2"></i> संकलित / आकारिक मूल्यमापन चाचणी</a></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/omr_view.php"><i class="bi bi-grid-3x3-gap-fill text-danger me-2"></i> ओ.एम.आर. उत्तरपत्रिका (OMR Sheet)</a></li>
            <li><a class="dropdown-item py-2" href="<?= EP_BASE_URL ?>/homework_new.php"><i class="bi bi-house-check text-info me-2"></i> दैनिक गृहपाठ व ऑनलाईन क्विझ</a></li>
          </ul>
        </li>
        <?php if (!empty($_SESSION['admin_id'])): ?>
          <li class="nav-item"><a class="nav-link <?= $epPage === 'bookbank' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/book_bank.php" title="पाठ्यपुस्तक स्वाध्याय प्रश्न (Admin Only)"><i class="bi bi-book me-1"></i> पाठ्यपुस्तक बँक</a></li>
          <li class="nav-item"><a class="nav-link <?= $epPage === 'bank' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/question_bank.php" title="६५,०००+ प्रश्न बँक (Admin Only)"><i class="bi bi-collection me-1"></i> प्रश्न बँक</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link <?= $epPage === 'homework' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/homework.php" title="दैनिक गृहपाठ"><i class="bi bi-house-check me-1"></i> गृहपाठ</a></li>
        <li class="nav-item"><a class="nav-link <?= $epPage === 'settings' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/settings.php" title="शाळा सेटिंग्ज"><i class="bi bi-gear me-1"></i> सेटिंग्ज</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container-fluid px-4">
<?php if (!$epAccess['allowed']): ?>
  <div class="alert alert-danger shadow-sm border-danger d-flex flex-wrap align-items-center justify-content-between p-3 mb-4 rounded-3">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-exclamation-octagon-fill fs-1 text-danger"></i>
      <div>
        <strong class="d-block fs-5 text-dark"><?= h($epAccess['message']) ?></strong>
        <span class="text-muted">अमर्यादित ५०,०००+ प्रश्नांमधून प्रश्नपत्रिका तयार करण्यासाठी कृपया ॲडमिनशी संपर्क साधा किंवा सदस्यता सक्रिय करा.</span>
      </div>
    </div>
    <div class="mt-2 mt-md-0">
      <a href="<?= $hubUrl ?>/subscription/plans.php" class="btn btn-danger fw-bold text-nowrap me-2">
        <i class="bi bi-credit-card"></i> सदस्यता अपग्रेड करा
      </a>
      <a href="https://wa.me/919988990000?text=I%20want%20to%20activate%20Exam%20Paper%20Module" target="_blank" class="btn btn-success fw-bold text-nowrap">
        <i class="bi bi-whatsapp"></i> ॲडमिनशी बोला
      </a>
    </div>
  </div>
<?php endif; ?>
