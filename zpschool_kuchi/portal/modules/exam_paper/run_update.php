<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/import_hindi_update.php';

$sqlFile = 'c:/xampp/htdocs/hpc/new/QP data/update_hindi_2026.sql';
echo "Starting import from: $sqlFile\n";
$res = run_import($sqlFile);
echo "Import Result:\n";
print_r($res);
