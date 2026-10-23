<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/tg_shell.php';
if (!defined('TG_FILTER_ON')) { define('TG_FILTER_ON', 1); ob_start('tg_text_filter'); }
$current_school = isLoggedIn() ? getSchool() : null;
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$tg_in_shell = isLoggedIn() || isAdmin();
$tg_has = fn(string $needle) => strpos($current_path, $needle) !== false;
$tg_ends = fn(string $suffix) => str_ends_with($current_path, $suffix);
$logo_ver = (int) @filemtime(__DIR__ . '/../assets/images/logo_light.png') ?: (int) @filemtime(__DIR__ . '/../assets/images/logo-icon.svg') ?: 4;
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= sanitize($page_title ?? 'TechGuruji') ?> — TechGuruji Smart School Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= APP_URL ?>/assets/images/techguruji_logo.png?v=<?= $logo_ver ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/images/favicon.png?v=<?= $logo_ver ?>">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/images/favicon.svg?v=<?= $logo_ver ?>">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/images/techguruji_logo.png?v=<?= $logo_ver ?>">
    <link rel="shortcut icon" href="<?= APP_URL ?>/favicon.ico?v=<?= $logo_ver ?>">
    <link href="<?= APP_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/cce.css?v=<?= @filemtime(__DIR__ . '/../assets/css/cce.css') ?>" rel="stylesheet">
    <?php tg_head_assets(APP_URL); ?>
</head>
<body class="<?= $tg_in_shell ? 'tg-app' : 'tg-public' ?>">
<?php if (isLoggedIn()):
    $tg_school_name = $current_school['name_mr'] ?? $current_school['name'] ?? SCHOOL_NAME_MR;
    $tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
    $logged_user_name = $_SESSION['user']['name'] ?? $tg_school_name;
    $logged_user_sub = (!empty($_SESSION['role']) && $_SESSION['role'] === 'teacher' && $tScope)
        ? ($tScope['class_name'] . ' वर्गशिक्षक')
        : ('UDISE: ' . ($current_school['udise_code'] ?? UDISE_CODE));

    $sidebar_nav = [
        ['label' => '', 'items' => [
            [APP_URL . '/index.php', 'bi-grid-1x2', 'मुख्य डॅशबोर्ड', $tg_ends('/index.php') || $tg_ends('/dashboard.php')],
        ]],
        ['label' => 'शैक्षणिक मॉड्युल्स', 'items' => [
            [APP_URL . '/hpc/index.php', 'bi-clipboard2-data', 'समग्र प्रगती पत्रक (HPC)', $tg_has('/hpc/')],
            [APP_URL . '/cce/index.php', 'bi-journal-check', 'सातत्यपूर्ण मूल्यमापन (CCE)', $tg_has('/cce/') && !$tg_ends('/export_csv.php')],
            [APP_URL . '/modules/tachan/index.php', 'bi-journal-text', 'दैनिक टाचण बँक', $tg_has('/modules/tachan/')],
            [APP_URL . '/modules/daily_study/index.php', 'bi-journal-bookmark-fill', 'दैनिक अभ्यास (Daily Homework)', $tg_has('/modules/daily_study/')],
            [APP_URL . '/modules/exam_paper/index.php', 'bi-file-earmark-text', 'प्रश्नपत्रिका जनरेटर', $tg_has('/modules/exam_paper/')],
            [APP_URL . '/modules/paripath/index.php', 'bi-sun', 'दैनिक परिपाठ', $tg_has('/modules/paripath/')],
        ]],
        ['label' => 'वर्ग व शाळा व्यवस्थापन', 'items' => [
            [APP_URL . '/admin/teachers.php', 'bi-person-gear', 'शिक्षक माहिती व्यवस्थापन', $tg_has('/admin/teachers.php')],
            [APP_URL . '/students/list.php', 'bi-people', 'माझे विद्यार्थी यादी', $tg_has('/students/')],
            [APP_URL . '/modules/attendance/index.php', 'bi-calendar-check', 'दैनिक उपस्थिती (हजेरी)', $tg_has('/modules/attendance/')],
            [APP_URL . '/modules/mdm/dashboard.php', 'bi-basket', 'शालेय पोषण आहार (MDM)', $tg_has('/modules/mdm/')],
            [APP_URL . '/modules/classes/bonafide.php', 'bi-patch-check', 'बोनाफाईड दाखला', $tg_ends('/bonafide.php')],
            [APP_URL . '/modules/classes/subjects.php', 'bi-easel2', 'वर्ग विषय व वेळापत्रक', $tg_has('/modules/classes/') && !$tg_ends('/bonafide.php')],
        ]],
    ];

    tg_shell_open($sidebar_nav, [
        'brand_title' => 'जि. प. शाळा नं. २ कुची',
        'brand_sub' => ($tScope ? $tScope['class_name'] : 'स्मार्ट शाळा पोर्टल'),
        'home' => APP_URL . '/index.php',
        'title' => $page_title ?? 'शाळा पोर्टल',
        'chip' => $tScope ? $tScope['class_name'] : ('UDISE ' . ($current_school['udise_code'] ?? UDISE_CODE)),
        'user_name' => $logged_user_name,
        'user_sub' => $logged_user_sub,
        'user_menu' => [
            [APP_URL . '/index.php', 'bi-house-door', 'मुख्य डॅशबोर्ड'],
            [APP_URL . '/students/list.php', 'bi-people', 'विद्यार्थी यादी'],
            [APP_URL . '/modules/attendance/index.php', 'bi-calendar-check', 'दैनिक हजेरी'],
            '-',
            [APP_URL . '/logout.php', 'bi-box-arrow-right', 'बाहेर पडा (Logout)', 'text-danger'],
        ],
        'side_foot' => '<div class="text-center p-2 text-white-50 small"><i class="bi bi-shield-check text-warning me-1"></i>सिंगल-स्कूल ॲडिशन | कुची</div>',
    ]);
elseif (isAdmin()):
    tg_shell_open([
        ['label' => 'ॲडमिन', 'items' => [
            [APP_URL . '/admin/dashboard.php', 'bi-grid-1x2', 'डॅशबोर्ड', $tg_ends('/admin/dashboard.php')],
            [APP_URL . '/admin/schools.php', 'bi-building', 'शाळा', $tg_ends('/admin/schools.php')],
            [APP_URL . '/admin/plans.php', 'bi-tags', 'सदस्यता योजना', $tg_ends('/admin/plans.php')],
            [APP_URL . '/admin/payments.php', 'bi-credit-card', 'पेमेंट व व्यवहार', $tg_ends('/admin/payments.php')],
            [APP_URL . '/admin/razorpay.php', 'bi-key', 'Razorpay गेटवे', $tg_ends('/admin/razorpay.php')],
        ]],
        ['label' => 'सामग्री', 'items' => [
            [APP_URL . '/admin/cce.php', 'bi-journal-text', 'मूल्यमापन बँक', $tg_ends('/admin/cce.php')],
            [APP_URL . '/admin/modules.php', 'bi-grid', 'मॉड्युल्स व इमेज', $tg_ends('/admin/modules.php')],
            [APP_URL . '/admin/cms.php', 'bi-file-earmark-richtext', 'CMS पाने', $tg_ends('/admin/cms.php')],
        ]],
    ], [
        'brand_sub' => 'ॲडमिन पॅनेल',
        'home' => APP_URL . '/admin/dashboard.php',
        'title' => $page_title ?? 'ॲडमिन',
        'user_name' => $_SESSION['admin_name'] ?? 'Admin',
        'user_sub' => 'सुपर ॲडमिन',
        'user_menu' => [[APP_URL . '/admin/logout.php', 'bi-box-arrow-right', 'बाहेर पडा', 'text-danger']],
    ]);
else: ?>
<nav class="navbar navbar-expand-lg tg-public-nav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none py-0" href="<?= APP_URL ?>">
            <img src="<?= APP_URL ?>/assets/images/logo.svg?v=<?= (int) @filemtime(__DIR__ . '/../assets/images/logo.svg') ?: $logo_ver ?>" alt="TechGuruji — स्मार्ट स्कूल हब" height="44" style="height:44px;width:auto;max-width:250px;">
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-sm btn-outline-primary d-lg-none fw-bold px-2.5 py-1"><i class="bi bi-box-arrow-in-right me-1"></i>लॉगिन</a>
            <button class="navbar-toggler border-0 shadow-none p-1" type="button" data-bs-toggle="collapse" data-bs-target="#tgPublicNav" aria-controls="tgPublicNav" aria-expanded="false" aria-label="मेनू"><i class="bi bi-list fs-2" style="color:var(--tg-800)"></i></button>
        </div>
        <div class="collapse navbar-collapse" id="tgPublicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1 py-2 py-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#modules"><i class="bi bi-grid-1x2 text-success me-1 d-lg-none"></i>सुविधा</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/subscription/plans.php"><i class="bi bi-gem text-warning me-1 d-lg-none"></i>किंमत</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/modules/paripath/index.php"><i class="bi bi-sun text-warning me-1 d-lg-none"></i>दैनिक परिपाठ</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/pages/view.php?page=contact"><i class="bi bi-headset text-primary me-1 d-lg-none"></i>संपर्क</a></li>
                <li class="nav-item"><a class="nav-link text-success fw-semibold" href="<?= PLAY_STORE_URL ?>" target="_blank" rel="noopener"><i class="bi bi-google-play me-1"></i>Play Store ॲप</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <div class="d-grid d-lg-inline-flex grid-cols-2 gap-2" style="grid-template-columns: 1fr 1fr;">
                        <a class="btn btn-outline-primary px-3 fw-bold" href="<?= APP_URL ?>/auth/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>लॉगिन</a>
                        <a class="btn btn-primary px-3 fw-bold" href="<?= APP_URL ?>/auth/register.php"><i class="bi bi-person-plus-fill me-1"></i>मोफत सुरू करा</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="<?= $tg_in_shell ? 'tg-main' : (!empty($is_landing_page) ? 'p-0 m-0' : 'py-4 py-lg-5') ?>">
    <div class="<?= !empty($is_landing_page) && !$tg_in_shell ? 'container-fluid p-0 m-0' : 'container' ?>">
        <?php displayFlash(); ?>
        <?php if (!empty($_SESSION['impersonated_by_admin'])): ?>
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 px-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-dark"><i class="bi bi-shield-lock me-1"></i>ॲडमिन मोड</span>
                    <span>तुम्ही ॲडमिन म्हणून <strong><?= sanitize($current_school['name_mr'] ?? $current_school['name'] ?? 'शाळा') ?></strong> च्या खात्यात काम करत आहात.</span>
                </div>
                <a href="<?= APP_URL ?>/admin/schools.php?action=exit_impersonate" class="btn btn-dark btn-sm"><i class="bi bi-box-arrow-left me-1"></i>ॲडमिन पॅनेलवर परत जा</a>
            </div>
        <?php endif; ?>
