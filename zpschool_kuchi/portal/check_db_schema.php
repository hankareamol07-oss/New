<?php
$configPath = file_exists(__DIR__ . '/config/config.php') ? __DIR__ . '/config/config.php' : '/var/www/html/zpschool2kuchi/config/config.php';
require_once $configPath;
$pdo = getDB();
try {
    $cols = $pdo->query("DESCRIBE daily_study")->fetchAll(PDO::FETCH_ASSOC);
    echo "daily_study columns:\n";
    foreach ($cols as $c) {
        echo "- {$c['Field']} ({$c['Type']})\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
