<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/docx_export.php';

$access = require_module_access('que');
$paperId = (int)($_GET['id'] ?? 0);
$set = strtoupper(trim($_GET['set'] ?? 'A'));

if ($paperId <= 0) {
    http_response_code(400);
    die('अवैध पेपर आयडी (Invalid Paper ID)');
}

ep_export_paper_docx($paperId, $set);
