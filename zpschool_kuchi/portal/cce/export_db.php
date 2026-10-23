<?php
/**
 * Export / Download School Tenant SQLite Database
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/tenant_db.php';
requireLogin();

// Normal users should only export CSV/Excel, not raw SQLite database files
if (!isAdmin()) {
    redirect(APP_URL . '/cce/export_csv.php');
}
$school_id = (int)$_SESSION['school_id'];
$udise = getSchoolUdise($school_id);
$sqlite_path = __DIR__ . "/../data/schools/$udise.sqlite";

// Ensure the tenant database exists and is initialized
getTenantDB($school_id, $udise);

if (!file_exists($sqlite_path)) {
    flash('error', 'डेटाबेस फाईल सापडली नाही.');
    redirect(APP_URL . '/cce/index.php');
}

$filename = "hpc_{$udise}_" . date('Y-m-d') . ".sqlite";

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.sqlite3');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($sqlite_path));
readfile($sqlite_path);
exit;
