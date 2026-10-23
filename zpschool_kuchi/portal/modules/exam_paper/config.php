<?php
// Exam Paper Generator - SQLite configuration
// Uses a dedicated SQLite database at /data/exam_paper.sqlite for modularity and high performance.

$portalConfigFile = dirname(__DIR__, 2) . '/config/config.php';
if (file_exists($portalConfigFile)) {
    require_once $portalConfigFile;
}
$appDbFile = dirname(__DIR__, 2) . '/config/database.php';
if (file_exists($appDbFile)) {
    require_once $appDbFile;
}
$tenantDbFile = dirname(__DIR__, 2) . '/includes/tenant_db.php';
if (file_exists($tenantDbFile)) {
    require_once $tenantDbFile;
}
$accessFile = dirname(__DIR__, 2) . '/includes/module_access.php';
if (file_exists($accessFile)) {
    require_once $accessFile;
}

define('EP_ROOT', __DIR__);
define('EP_UPLOAD_DIR', EP_ROOT . '/uploads');       // school logo uploads
define('EP_IMAGE_DIR', EP_ROOT . '/data/images');    // question images (question_id.png)

// Base URL of this module relative to the web root, e.g. '/modules/exam_paper' or '/hpc/modules/exam_paper'
$sn = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
define('EP_BASE_URL', ($sn && $sn !== '/' && $sn !== '.') ? rtrim($sn, '/') : '/modules/exam_paper');

function ep_init_sqlite(PDO $pdo): void
{
    $schemaFile = __DIR__ . '/db/sqlite_schema.sql';
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }
}

function ep_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $candidate_paths = [
            dirname(__DIR__, 2) . '/data/exam_paper.sqlite',
            __DIR__ . '/data/exam_paper.sqlite',
            __DIR__ . '/exam_paper.sqlite',
        ];

        $dbFile = null;
        foreach ($candidate_paths as $cand) {
            if (file_exists($cand) && filesize($cand) > 0) {
                $dbFile = $cand;
                break;
            }
        }

        if (!$dbFile) {
            $dbDir = dirname(__DIR__, 2) . '/data';
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0777, true);
            }
            $dbFile = $dbDir . '/exam_paper.sqlite';
        }

        $isNew = !file_exists($dbFile) || filesize($dbFile) === 0;

        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 25,
        ]);

        try {
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA synchronous = NORMAL;');
            $pdo->exec('PRAGMA foreign_keys = ON;');
        } catch (Throwable $e) {}

        // Custom function to support MySQL RAND() in SQLite queries
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('RAND', function() {
                return mt_rand() / mt_getrandmax();
            });
        }

        if ($isNew) {
            ep_init_sqlite($pdo);
        }
    }
    return $pdo;
}
