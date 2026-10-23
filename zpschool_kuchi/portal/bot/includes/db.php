<?php
require_once __DIR__ . '/config.php';

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dbDir = __DIR__ . '/../../../data';
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }
    $sqlitePath = $dbDir . '/bot.sqlite';

    $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_sessions (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        session_key  TEXT UNIQUE NOT NULL,
        student_id   INTEGER,
        mode         TEXT DEFAULT 'greeting',
        subject      TEXT,
        standard     TEXT,
        current_qid  INTEGER,
        score        INTEGER DEFAULT 0,
        total_asked  INTEGER DEFAULT 0,
        temp_name    TEXT,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        name           TEXT,
        standard       TEXT,
        login_streak   INTEGER DEFAULT 1,
        last_active_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    return $pdo;
}
