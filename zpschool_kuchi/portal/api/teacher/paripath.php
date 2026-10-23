<?php
/**
 * GET /api/teacher/paripath.php
 * Daily Assembly & Daily Paripath
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin', 'student']);

$dbFile = DATA_DIR . '/paripath.sqlite';
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));

$data = [
    'date'             => $date,
    'school_name'      => SCHOOL_NAME_MR,
    'prarthana'        => 'खरा तो एकची धर्म जगाला प्रेम अर्पावे...',
    'pratidnya'        => 'भारत माझा देश आहे. सारे भारतीय माझे बांधव आहेत...',
    'suvichar'         => 'ज्ञानाने माणसाचे जीवन समृद्ध होते.',
    'dinvishesh'       => 'आजचा दिवस ऐतिहासिक व शैक्षणिक दृष्टिकोनातून महत्त्वाचा आहे.',
    'bodhkatha'        => 'एकता हेच सामर्थ्य असते.',
    'panchang'         => 'वार: ' . date('l') . ', दिनांक: ' . $date,
    'shikshak_sandesh' => 'नियमित उपस्थिती आणि स्वयंशिस्त ही यशाची गुरुकिल्ली आहे.',
];

if (file_exists($dbFile)) {
    try {
        $sqlite = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        // Search table for today's date if exists
        $tables = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('paripath_entries', $tables)) {
            $st = $sqlite->prepare("SELECT * FROM paripath_entries WHERE entry_date = ? LIMIT 1");
            $st->execute([$date]);
            $row = $st->fetch();
            if ($row) {
                $data = array_merge($data, $row);
            }
        }
    } catch (Throwable $e) {}
}

apiSuccess($data, 'दैनिक परिपाठ यशस्वीरित्या लोड झाला.');
