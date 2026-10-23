<?php
declare(strict_types=1);

/**
 * list.php is retired — permanently redirect to day-wise backdate.php
 */
require_once __DIR__ . '/../lib/tachan_ui.php';

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . APP_URL . '/modules/tachan/backdate.php' . $qs, true, 301);
exit;
