<?php
/**
 * 1-Click Server Extractor for exam_paper_data.zip
 * Can be run from browser (e.g. yourdomain.com/modules/exam_paper/extract_data_on_server.php)
 * or via CLI: php extract_data_on_server.php
 */
session_start();
// Security: Allow admin or CLI execution
$isCli = (php_sapi_name() === 'cli');
if (!$isCli && empty($_SESSION['admin_id']) && empty($_SESSION['user_id'])) {
    // If not logged in, ask for key or login
    if (($_GET['key'] ?? '') !== 'smartschool_update') {
        die('Access denied. Please login as Admin or append ?key=smartschool_update to the URL.');
    }
}

set_time_limit(1200);
ini_set('memory_limit', '1024M');

$candidates = [
    __DIR__ . '/../../data zip/exam_paper_data.zip',
    __DIR__ . '/../exam_paper_data.zip',
    __DIR__ . '/../../exam_paper_data.zip',
    __DIR__ . '/data/exam_paper_data.zip',
    __DIR__ . '/exam_paper_data.zip',
];

$zipPath = null;
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $zipPath = realpath($c);
        break;
    }
}

$targetBase = __DIR__;

header('Content-Type: text/plain; charset=utf-8');
echo "=== Smart School Hub: Exam Paper Data Extractor ===\n\n";

if (!$zipPath) {
    echo "ERROR: exam_paper_data.zip not found!\n";
    echo "Please upload 'exam_paper_data.zip' to one of the following locations:\n";
    foreach ($candidates as $c) {
        echo " - " . $c . "\n";
    }
    exit(1);
}

echo "Found zip file at: $zipPath (" . round(filesize($zipPath) / (1024 * 1024), 2) . " MB)\n";
echo "Target directory: $targetBase\n\n";

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    die("ERROR: Failed to open $zipPath\n");
}

$total = $zip->numFiles;
echo "Total entries in archive: $total\nExtracting files...\n";

$extracted = 0;
$skipped = 0;
$startTime = microtime(true);

for ($i = 0; $i < $total; $i++) {
    $entryName = $zip->getNameIndex($i);
    if (str_ends_with($entryName, '/')) continue;

    $dest = $targetBase . '/' . $entryName;
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $fpIn = $zip->getStream($entryName);
    if (!$fpIn) {
        $skipped++;
        continue;
    }

    $fpOut = fopen($dest, 'wb');
    if (!$fpOut) {
        fclose($fpIn);
        $skipped++;
        continue;
    }

    stream_copy_to_stream($fpIn, $fpOut);
    fclose($fpIn);
    fclose($fpOut);
    $extracted++;

    if ($extracted % 1000 === 0 || $extracted === $total) {
        $elapsed = round(microtime(true) - $startTime, 1);
        echo "Progress: $extracted / $total files ($elapsed sec)...\n";
        if (!$isCli) flush();
    }
}

$zip->close();
$totalTime = round(microtime(true) - $startTime, 2);
echo "\nEXTRACTION COMPLETED!\n";
echo "Files extracted: $extracted\n";
echo "Files skipped: $skipped\n";
echo "Total time: $totalTime seconds.\n";
echo "\nYou can now delete this file (extract_data_on_server.php) and exam_paper_data.zip from your server if desired.\n";
