<?php
require_once __DIR__ . '/functions.php';
$epPage = $epPage ?? '';
$epTitle = $epTitle ?? 'Exam Paper Generator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($epTitle) ?> - <?= h(ep_setting('school_name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= EP_BASE_URL ?>/assets/exam_paper.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= EP_BASE_URL ?>/index.php">
      <?php if (ep_setting('school_logo')): ?>
        <img src="<?= EP_BASE_URL ?>/uploads/<?= h(ep_setting('school_logo')) ?>" alt="logo" style="height:32px">
      <?php endif; ?>
      <span><?= h(ep_setting('school_name')) ?> &middot; Exam Papers</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#epNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="epNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link <?= $epPage === 'papers' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/index.php"><i class="bi bi-files"></i> My Papers</a></li>
        <li class="nav-item"><a class="nav-link <?= $epPage === 'create' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/create_paper.php"><i class="bi bi-plus-circle"></i> Create Paper</a></li>
        <li class="nav-item"><a class="nav-link <?= $epPage === 'competitive' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/competitive_paper.php"><i class="bi bi-trophy"></i> Scholarship / Navodaya</a></li>
        <li class="nav-item"><a class="nav-link <?= $epPage === 'bank' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/question_bank.php"><i class="bi bi-collection"></i> Question Bank</a></li>
        <li class="nav-item"><a class="nav-link <?= $epPage === 'settings' ? 'active' : '' ?>" href="<?= EP_BASE_URL ?>/settings.php"><i class="bi bi-gear"></i> School Settings</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container-fluid px-4">
