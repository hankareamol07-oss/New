<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/../../includes/tg_shell.php';
require_once __DIR__ . '/module_errors.php';

function app_modules(): array
{
    $b = defined('APP_URL') ? APP_URL : '';
    return [
        ['key'=>'classes','label'=>'वर्ग व्यवस्थापन','icon'=>'bi-easel','href'=>$b.'/modules/classes/index.php','roles'=>['admin','principal','teacher']],
        ['key'=>'cce','label'=>'सातत्यपूर्ण मूल्यमापन','icon'=>'bi-clipboard2-check','href'=>$b.'/cce/index.php','roles'=>['admin','principal','teacher']],
        ['key'=>'mdm','label'=>'पोषण आहार','icon'=>'bi-basket','href'=>$b.'/modules/mdm/dashboard.php','roles'=>['admin','principal','clerk','teacher']],
        ['key'=>'hpc','label'=>'समग्र प्रगती पत्रक','icon'=>'bi-file-earmark-bar-graph','href'=>$b.'/hpc/index.php','roles'=>['admin','principal','teacher']],
        ['key'=>'tachan','label'=>'दैनिक टाचण','icon'=>'bi-pencil-square','href'=>$b.'/modules/tachan/index.php','roles'=>['admin','principal','teacher']],
        ['key'=>'que','label'=>'प्रश्नपत्रिका','icon'=>'bi-file-earmark-text','href'=>$b.'/modules/exam_paper/index.php','roles'=>['admin','principal','teacher']],
    ];
}

function layout_nav_active(string $href, string $current): bool
{
    $path = (string) parse_url($href, PHP_URL_PATH);
    return $path !== '' && $path === $current;
}

function layout_start(string $title, string $module = ''): void
{
    $b = defined('APP_URL') ? APP_URL : '';
    $user = require_login();
    $school = school_row((int) $user['school_id']) ?: [];
    $schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? 'शाळा व्यवस्थापन'));
    $year = current_academic_year((int) $user['school_id']);
    $flashes = flash_get();
    $current = $_SERVER['SCRIPT_NAME'] ?? '';
    $isAdmin = in_array($user['role'], ['admin', 'principal'], true);

    $items = [];
    if ($module === 'mdm') {
        foreach ([
            ['डॅशबोर्ड','bi-speedometer2','/modules/mdm/dashboard.php'],['दैनिक हजेरी/नोंद','bi-calendar-check','/modules/mdm/daily/entry.php'],
            ['दिनदर्शिका','bi-calendar3','/modules/mdm/daily/calendar.php'],['साठा पावती','bi-box-seam','/modules/mdm/balance/receipt.php'],
            ['प्रपत्र ब (मासिक)','bi-file-earmark-text','/modules/mdm/reports/prapatr_b.php'],['भाग-१ तांदूळ हिशोब','bi-journal','/modules/mdm/reports/tandul_monthly.php'],
            ['भाग-२ धान्य नोंदवही','bi-journal-text','/modules/mdm/reports/nodvahi2.php'],['चव रजिस्टर','bi-cup-hot','/modules/mdm/reports/chav_nondavahi.php'],
            ['४ त्रैमासिक BMI','bi-heart-pulse','/modules/mdm/bmi/entry.php'],['विद्यार्थी आयात','bi-upload','/modules/mdm/bmi/import.php'],
            ['उपयोगिता पत्र (UC)','bi-patch-check','/modules/mdm/reports/annual_cert.php'],['१२ महिने सारांश','bi-bar-chart','/modules/mdm/reports/annual_summary.php'],
            ['सर्व अहवाल','bi-folder2-open','/modules/mdm/reports/index.php'],['शाळा MDM सेटिंग','bi-gear','/modules/mdm/school/settings.php'],
        ] as [$l, $i, $h]) {
            $items[] = [$b . $h, $i, $l, layout_nav_active($b . $h, $current)];
        }
        $groupLabel = 'पोषण आहार';
    } elseif ($module === 'tachan') {
        foreach ([
            ['मुख्यपृष्ठ','bi-house','/modules/tachan/index.php'],['टाचण','bi-pencil-square','/modules/tachan/day.php'],
            ['साप्ताहिक टाचण','bi-calendar-week','/modules/tachan/weekly.php'],['वेळापत्रक','bi-table','/modules/tachan/timetable.php'],
            ['वार्षिक नियोजन','bi-calendar-range','/modules/tachan/annual.php'],['सुट्ट्या','bi-brightness-high','/modules/tachan/holidays.php'],
            ['जुने टाचण','bi-clock-history','/modules/tachan/list.php'],['सेटिंग्ज','bi-sliders','/modules/tachan/class_pref.php'],
        ] as [$l, $i, $h]) {
            $items[] = [$b . $h, $i, $l, layout_nav_active($b . $h, $current)];
        }
        $groupLabel = 'दैनिक टाचण';
    } else {
        foreach (app_modules() as $m) {
            if (!in_array($user['role'], $m['roles'], true) && !$isAdmin) continue;
            $items[] = [$m['href'], $m['icon'], $m['label'], $module === $m['key']];
        }
        $groupLabel = 'मॉड्युल्स';
    }

    $groups = [
        ['label' => '', 'items' => [[$b . '/dashboard.php', 'bi-grid-1x2', 'डॅशबोर्ड', $module === 'hub']]],
        ['label' => $groupLabel, 'items' => $items],
    ];
    if ($isAdmin) {
        $groups[] = ['label' => 'शाळा', 'items' => [
            [$b . '/teachers/list.php', 'bi-people', 'वापरकर्ते', false],
            [$b . '/school/profile.php', 'bi-building-gear', 'शाळा सेटिंग', false],
        ]];
    }

    if (!defined('TG_FILTER_ON')) {
        define('TG_FILTER_ON', 1);
        ob_start('tg_text_filter');
    }
    define('TG_ICONS_LOADED', 1);
    ?>
<!doctype html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | <?= e($schoolName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={corePlugins:{preflight:false},theme:{extend:{fontFamily:{sans:['Mukta','system-ui','sans-serif']},colors:{navy:'#0F4F3B',gold:'#E9A23B',teal:'#167A5B',pagebg:'#F5F7F6',orange:{50:'#EFF8F4',100:'#DCF0E7',200:'#BFE3D3',300:'#8FCDB2',400:'#4FAE87',500:'#1D9370',600:'#167A5B',700:'#13644B',800:'#0F4F3B',900:'#0B3D2E'},yellow:{50:'#FDF8EC',100:'#FBEFD3',200:'#F6DDA6',300:'#F0C878',400:'#EBB357',500:'#E9A23B',600:'#167A5B',700:'#13644B',800:'#0F4F3B',900:'#0B3D2E'}}}}};</script>
<link rel="stylesheet" href="<?= $b ?>/assets/vij/css/site.css">
<?php tg_head_assets($b); ?>
<style>.table-wrap{overflow-x:auto}.tg-main a{text-decoration:none}.tg-main svg{display:inline-block;vertical-align:middle}.tg-main img{max-width:100%;height:auto}.tg-main-inner{max-width:1280px;margin-inline:auto}@media print{.no-print,.tg-sidebar,.tg-topbar,.ssh-bottom-nav{display:none!important}.tg-content{margin:0!important}.tg-main{padding:0!important}}</style>
</head>
<body class="tg-app font-sans">
<?php tg_shell_open($groups, [
        'brand_sub' => $schoolName,
        'title' => $title,
        'chip' => (string) ($year['label'] ?? ''),
        'user_name' => (string) ($user['name'] ?? ''),
        'user_sub' => (string) ($user['role'] ?? ''),
        'user_menu' => [
            [$b . '/dashboard.php', 'bi-grid-1x2', 'डॅशबोर्ड'],
            '-',
            [$b . '/auth/logout.php', 'bi-box-arrow-right', 'बाहेर पडा', 'text-danger'],
        ],
    ]); ?>
<main class="tg-main">
<div class="tg-main-inner">
<?php foreach ($flashes as $flash): ?>
<div class="no-print alert <?= $flash['type'] === 'error' ? 'alert-danger' : 'alert-success' ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<div class="mb-4">
<p class="small fw-semibold mb-1" style="color:var(--tg-600)"><?= e($schoolName) ?></p>
<h1 class="tg-title mb-0"><?= e($title) ?></h1>
</div>
    <?php
}

function layout_end(): void
{
    echo '</div></main></div>';
    require_once __DIR__ . '/../../includes/mobile_bottom_nav.php';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    tg_shell_script();
    echo '<script src="' . (defined('APP_URL') ? APP_URL : '') . '/assets/vij/js/main.js"></script></body></html>';
}
