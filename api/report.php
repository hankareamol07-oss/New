<?php
/**
 * Native Android API — report renderer.
 *
 * Renders one of the existing PHP report / print pages for the bearer-token authenticated school and returns
 * the exact HTML the web app prints (same CSS, same layout), so the Android app can hand it to the Android
 * print framework (PrintManager → PDF) without any interactive WebView screen.
 *
 *   GET/POST api/report.php?page=cce/result.php&std=5&semester=1
 *
 * Only whitelisted pages can be rendered; every page itself scopes data by $_SESSION['school_id'].
 */
require_once __DIR__ . '/native_bootstrap.php';

$ctx = native_boot();
$in = native_input();

$ALLOWED = [
    // CCE — निकालपत्रके
    'cce/result.php', 'cce/tool_marksheet_student.php', 'cce/tool_marksheet_subject.php', 'cce/marks_grade_register.php',
    'cce/grade_table_portrait.php', 'cce/subject_grade_summary.php', 'cce/gender_grade_table.php', 'cce/caste_grade_table.php',
    'cce/class_grade_summary.php', 'cce/annual.php', 'cce/annual_comprehensive_register.php', 'cce/annual_combined_register.php',
    'cce/annual_grade_table.php', 'cce/annual_marksheet.php', 'cce/std5_8_progress_card.php', 'cce/std5_8_annual_register.php',
    // CCE — नोंदवही / प्रगती पत्रक
    'cce/nondvahi.php', 'cce/register_cover.php', 'cce/register_index.php', 'cce/pragati_pustak.php', 'cce/progress_card_term1.php',
    'cce/narrative_report.php', 'cce/outcomes_report.php',
    // HPC
    'cards/print_hpc_19.php', 'cards/print_hpc_stage.php',
    // टाचण
    'modules/tachan/day_print.php', 'modules/tachan/weekly_print.php', 'modules/tachan/annual_print.php', 'modules/tachan/timetable_print.php',
];

$page = str_replace('\\', '/', native_str($in, 'page'));
if (!in_array($page, $ALLOWED, true)) {
    apiError('Report page not allowed', 403);
}
$file = realpath(__DIR__ . '/../' . $page);
if ($file === false || !is_file($file)) {
    apiError('Report page not found', 404);
}

// The pages read their parameters from $_GET / $_REQUEST.
$params = $in;
unset($params['page'], $params['action']);
if (isset($params['params']) && is_array($params['params'])) {
    $params = array_merge($params, $params['params']);
    unset($params['params']);
}
$_GET = $params;
$_REQUEST = $params;
$_SERVER['REQUEST_METHOD'] = 'GET';

// redirect()/exit inside a page must not leave us with a JSON header
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
echo $html;
