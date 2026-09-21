<?php
// Exam Paper Generator - configuration.
// If your school management system already has a PDO/mysqli connection, you can
// replace ep_db() below to reuse it (it must return a PDO instance).

define('EP_DB_HOST', getenv('EP_DB_HOST') ?: 'localhost');
define('EP_DB_NAME', getenv('EP_DB_NAME') ?: 'school');
define('EP_DB_USER', getenv('EP_DB_USER') ?: 'school');
define('EP_DB_PASS', getenv('EP_DB_PASS') ?: '');

define('EP_ROOT', __DIR__);
define('EP_UPLOAD_DIR', EP_ROOT . '/uploads');       // school logo uploads
define('EP_IMAGE_DIR', EP_ROOT . '/data/images');    // question images (question_id.png)

// Base URL of this module relative to the web root, e.g. '/exam_paper'
define('EP_BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\'));

function ep_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . EP_DB_HOST . ';dbname=' . EP_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, EP_DB_USER, EP_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
