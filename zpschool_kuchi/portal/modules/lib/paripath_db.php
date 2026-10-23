<?php
declare(strict_types=1);
/**
 * स्वतंत्र SQLite डेटाबेस — शालेय परिपाठ (School Assembly Module)
 * संपूर्ण परिपाठ डेटा (पंचांग, दिनविशेष, सुविचार, सुभाषित, प्रार्थना) साठी स्वतंत्र SQLite डेटाबेस.
 */

function getParipathDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir = dirname(__DIR__, 2) . '/data';
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }
    $dbFile = $dbDir . '/paripath.sqlite';

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 15,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');
    $pdo->exec('PRAGMA foreign_keys = ON;');

    initParipathSQLiteTables($pdo);

    return $pdo;
}

function initParipathSQLiteTables(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS panchang (
            date TEXT PRIMARY KEY,
            location TEXT DEFAULT "महाराष्ट्र",
            hindu_month TEXT,
            paksha TEXT,
            tithi TEXT,
            nakshatra TEXT,
            yoga TEXT,
            karana TEXT,
            var TEXT,
            ritu TEXT,
            sunrise TEXT,
            sunset TEXT,
            moonrise TEXT,
            moonset TEXT,
            rahu_kaal TEXT,
            festivals TEXT,
            vrat TEXT,
            is_festival INTEGER DEFAULT 0,
            raw_json TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS dinvishesh (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_date TEXT NOT NULL,
            event_year INTEGER,
            category TEXT NOT NULL DEFAULT "historical_event",
            title_mr TEXT NOT NULL,
            title_en TEXT,
            short_info_mr TEXT,
            description_mr TEXT,
            primary_image TEXT,
            month INTEGER NOT NULL,
            day INTEGER NOT NULL,
            is_featured INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_din_date ON dinvishesh (event_date);
        CREATE INDEX IF NOT EXISTS idx_din_md ON dinvishesh (month, day);
        CREATE INDEX IF NOT EXISTS idx_din_cat ON dinvishesh (category);

        CREATE TABLE IF NOT EXISTS suvichar (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            display_date TEXT NOT NULL UNIQUE,
            text_mr TEXT NOT NULL,
            text_en TEXT,
            author TEXT,
            meaning_mr TEXT,
            explanation_mr TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_suv_date ON suvichar (display_date);

        CREATE TABLE IF NOT EXISTS subhashit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            display_date TEXT NOT NULL UNIQUE,
            granth TEXT,
            chapter INTEGER,
            verse INTEGER,
            text_sa TEXT NOT NULL,
            text_mr TEXT,
            text_en TEXT,
            meaning_mr TEXT,
            explanation_mr TEXT,
            usage_mr TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_sub_date ON subhashit (display_date);

        CREATE TABLE IF NOT EXISTS prayers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,
            title_mr TEXT NOT NULL,
            subtitle_mr TEXT,
            author TEXT,
            content TEXT NOT NULL,
            meaning TEXT,
            audio_url TEXT,
            display_order INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS custom_notes (
            date TEXT PRIMARY KEY,
            announcement TEXT,
            word_of_day TEXT,
            word_meaning TEXT,
            gk_question TEXT,
            gk_answer TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ');

    // Ensure newly added columns exist in older SQLite files
    try {
        $pdo->exec('ALTER TABLE dinvishesh ADD COLUMN primary_image TEXT;');
    } catch (Throwable $e) {
        // column already exists
    }
}
