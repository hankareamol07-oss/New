<?php
/**
 * Native Android API — report renderer.
 *
 * Renders one of the existing PHP report / print pages for the bearer-token authenticated school and returns
 * the exact HTML the web app prints (same CSS, same layout), with automatic orientation and paper size metadata
 * for Android PrintManager / Save as PDF.
 *
 *   GET/POST api/report.php?page=cce/result.php&std=5&semester=1
 */
require_once __DIR__ . '/native_bootstrap.php';

$ctx = native_boot();
$in = native_input();

$ALLOWED = [
    // CCE — निकालपत्रके
    'cce/result.php', 'cce/tool_marksheet_student.php', 'cce/tool_marksheet_subject.php', 'cce/marks_grade_register.php',
    'cce/grade_table_portrait.php', 'cce/grade_table.php', 'cce/subject_grade_summary.php', 'cce/gender_grade_table.php', 'cce/caste_grade_table.php',
    'cce/class_grade_summary.php', 'cce/annual.php', 'cce/annual_comprehensive_register.php', 'cce/annual_combined_register.php',
    'cce/annual_grade_table.php', 'cce/annual_register.php', 'cce/annual_marksheet.php', 'cce/std5_8_progress_card.php', 'cce/std5_8_annual_register.php',
    // CCE — नोंदवही / प्रगती पत्रक
    'cce/nondvahi.php', 'cce/register_cover.php', 'cce/register_index.php', 'cce/pragati_pustak.php', 'cce/progress_card.php', 'cce/progress_card_term1.php',
    'cce/narrative_report.php', 'cce/outcomes_report.php',
    // HPC
    'cards/print_hpc_19.php', 'cards/print_hpc_stage.php',
    // टाचण, प्रश्नपत्रिका, दाखले, पोषण आहार, उपस्थिती, परिपाठ
    'modules/attendance/report.php', 'modules/paripath/print.php',
    'modules/exam_paper/paper_view.php', 'modules/exam_paper/homework_view.php', 'modules/exam_paper/homework_key.php',
    'modules/exam_paper/paper_view_assessment.php', 'modules/exam_paper/omr_view.php', 'modules/exam_paper/quiz_results.php',
    'modules/classes/bonafide.php',
    'modules/fee/report.php', 'modules/fee/receipt.php',
    'modules/mdm/reports/annual_cert.php', 'modules/mdm/reports/annual_summary.php', 'modules/mdm/reports/chav_nondavahi.php',
    'modules/mdm/reports/cumulative.php', 'modules/mdm/reports/nodvahi2.php', 'modules/mdm/reports/prapatr_b.php',
    'modules/mdm/reports/tandul_monthly.php', 'modules/mdm/reports/yearly_report.php',
    'modules/tachan/day_print.php', 'modules/tachan/day_print_bulk.php', 'modules/tachan/weekly_print.php', 'modules/tachan/monthly_print.php', 'modules/tachan/annual_print.php', 'modules/tachan/timetable_print.php', 'modules/tachan/print.php',
];

$page = str_replace('\\', '/', native_str($in, 'page'));
if (!in_array($page, $ALLOWED, true)) {
    apiError('Report page not allowed: ' . $page, 403);
}

// Map redirect-only helper files directly to their render pages
$params = $in;
unset($params['page'], $params['action']);
if (isset($params['params']) && is_array($params['params'])) {
    $params = array_merge($params, $params['params']);
    unset($params['params']);
}

if ($page === 'cce/register_cover.php') {
    $page = 'cce/nondvahi.php';
    $params['view'] = 'cover';
} elseif ($page === 'cce/register_index.php') {
    $page = 'cce/nondvahi.php';
    $params['view'] = 'index';
} elseif ($page === 'cce/annual_grade_table.php') {
    if (($params['view'] ?? 'detailed') === 'summary') {
        $page = 'cce/annual.php';
    } else {
        $page = 'cce/annual_register.php';
    }
}

$file = realpath(__DIR__ . '/../' . $page);
if ($file === false || !is_file($file)) {
    apiError('Report page not found: ' . $page, 404);
}

$_GET = $params;
$_REQUEST = $params;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/') . '/' . $page;
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');

ob_start();
try {
    chdir(dirname($file));
    require $file;
} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    apiError('Report error: ' . $e->getMessage(), 500);
}
$html = ob_get_clean();
if (trim($html) === '') {
    header('Content-Type: application/json; charset=utf-8');
    apiError('Report produced no output', 500);
}

// -----------------------------------------------------------------------------
// Auto-detect Orientation (Landscape vs Portrait) & Paper Size (A4, A5, A3)
// -----------------------------------------------------------------------------
$isLandscape = false;
$paperSize = 'A4';

$landscapeFiles = [
    'cce/result.php', 'cce/tool_marksheet_subject.php', 'cce/marks_grade_register.php',
    'cce/subject_grade_summary.php', 'cce/gender_grade_table.php', 'cce/caste_grade_table.php',
    'cce/class_grade_summary.php', 'cce/annual.php', 'cce/annual_comprehensive_register.php',
    'cce/annual_combined_register.php', 'cce/grade_table.php', 'cce/annual_register.php',
    'cce/std5_8_annual_register.php', 'cce/nondvahi.php', 'cce/progress_card_term1.php',
    'cce/outcomes_report.php', 'modules/tachan/timetable_print.php', 'modules/tachan/monthly_print.php',
    'modules/mdm/reports/annual_cert.php', 'modules/mdm/reports/nodvahi2.php', 'modules/mdm/reports/annual_summary.php',
];

if (in_array($page, $landscapeFiles, true)) {
    $isLandscape = true;
}

if ($page === 'cce/pragati_pustak.php') {
    $isA5 = (($_GET['size'] ?? '') === 'a5') || (stripos($html, 'A5 landscape') !== false);
    $isLandscape = true;
    $paperSize = $isA5 ? 'A5' : 'A4';
} elseif ($page === 'modules/tachan/annual_print.php') {
    $isMatrix = !empty($_GET['matrix']);
    $m = (int)($_GET['month'] ?? 0);
    if ($isMatrix) {
        $isLandscape = true;
        $paperSize = 'A3';
    } elseif ($m === 0) {
        $isLandscape = true;
        $paperSize = 'A4';
    } else {
        $isLandscape = false;
        $paperSize = 'A4';
    }
} else {
    if (preg_match('/@page\s*\{[^}]*landscape/i', $html)) {
        $isLandscape = true;
    } elseif (preg_match('/@page\s*\{[^}]*portrait/i', $html)) {
        $isLandscape = false;
    }
    if (preg_match('/@page\s*\{[^}]*size\s*:\s*A5/i', $html) || (($_GET['size'] ?? '') === 'a5')) {
        $paperSize = 'A5';
    } elseif (preg_match('/@page\s*\{[^}]*size\s*:\s*A3/i', $html)) {
        $paperSize = 'A3';
    }
}

// Emit HTTP Headers for Flutter / Native client
header('X-Print-Orientation: ' . ($isLandscape ? 'landscape' : 'portrait'));
header('X-Print-Paper-Size: ' . $paperSize);

// Inject base tag, meta tags, and print CSS
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseHref = (defined('APP_URL') && APP_URL !== '')
    ? rtrim(APP_URL, '/') . '/'
    : ($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/') . '/');

$injectedHead = '<base href="' . htmlspecialchars($baseHref, ENT_QUOTES) . '">' . "\n"
    . '<meta name="print-orientation" content="' . ($isLandscape ? 'landscape' : 'portrait') . '">' . "\n"
    . '<meta name="print-paper-size" content="' . $paperSize . '">' . "\n"
    . '<style id="techguruji-print-framework">' . "\n"
    . '@page { size: ' . $paperSize . ' ' . ($isLandscape ? 'landscape' : 'portrait') . ' !important; }' . "\n"
    . '@media print {' . "\n"
    . '  .no-print, .toolbar, .tb, .bar, .pc-toolbar, .no-print-bar, .filters, .t-toolbar, .tw-toolbar, header.h-16, aside.app-sidebar, nav.no-print { display: none !important; }' . "\n"
    . '  body { background: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; margin: 0 !important; padding: 0 !important; }' . "\n"
    . '  .tachan-day, .tw { display: block !important; width: 100% !important; max-width: 100% !important; height: auto !important; max-height: none !important; margin: 0 !important; padding: 0 !important; zoom: 1 !important; transform: none !important; }' . "\n"
    . '}' . "\n"
    . '@media screen {' . "\n"
    . '  body { min-width: ' . ($isLandscape ? '1080px' : '750px') . ' !important; }' . "\n"
    . '}' . "\n"
    . '</style>';

if (stripos($html, '<base ') === false && stripos($html, '<head') !== false) {
    $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . "\n" . $injectedHead, $html, 1);
} elseif (stripos($html, '</head>') !== false) {
    $html = str_ireplace('</head>', $injectedHead . "\n" . '</head>', $html);
} else {
    $html = $injectedHead . "\n" . $html;
}

echo $html;
