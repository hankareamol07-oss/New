<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/tg_shell.php';

if (!function_exists('tg_module_error_page')) {
    function tg_module_error_page(Throwable $e): void
    {
        error_log('Module error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
        }
        $b = defined('APP_URL') ? APP_URL : '';
        echo '<!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>पान उपलब्ध नाही</title>';
        tg_head_assets($b);
        echo '</head><body class="tg-app"><div class="container py-5" style="max-width:560px"><div class="tg-card p-4 text-center">'
            . '<i class="bi bi-tools fs-1" style="color:var(--tg-600)"></i>'
            . '<h1 class="h4 fw-bold mt-3">हे पान सध्या उपलब्ध नाही</h1>'
            . '<p class="text-muted mb-4">या विभागासाठी आवश्यक माहिती तुमच्या शाळेच्या खात्यात अजून तयार नाही. कृपया नंतर प्रयत्न करा किंवा मदतीसाठी संपर्क करा.</p>'
            . '<a class="btn btn-success px-4" href="' . htmlspecialchars($b . '/dashboard.php', ENT_QUOTES) . '">डॅशबोर्डवर जा</a>'
            . '</div></div></body></html>';
    }
    set_exception_handler('tg_module_error_page');
}
