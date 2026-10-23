<?php
declare(strict_types=1);

/**
 * Paripath CLI Scraper & Cache Warmer
 * Usage:
 *   php modules/lib/paripath_sync_cli.php --all-year
 *   php modules/lib/paripath_sync_cli.php --year=2026
 *   php modules/lib/paripath_sync_cli.php --academic
 *   php modules/lib/paripath_sync_cli.php --today
 *   php modules/lib/paripath_sync_cli.php --month=2026-09
 *   php modules/lib/paripath_sync_cli.php --start=2026-06-01 --end=2027-05-31
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once __DIR__ . '/paripath_engine.php';
require_once __DIR__ . '/paripath_year_scraper.php';

$options = getopt('', ['today', 'days:', 'month:', 'start:', 'end:', 'all-year', 'year:', 'academic']);

$db = getParipathDB();

echo "=======================================================\n";
echo "       शालेय परिपाठ डेटा सिंक व कॅश वॉर्मर (SQLite)\n";
echo "=======================================================\n";

if (isset($options['all-year']) || isset($options['year']) || isset($options['academic'])) {
    $y = isset($options['year']) ? (int)$options['year'] : 2026;
    $start = isset($options['academic']) ? "{$y}-06-01" : "{$y}-01-01";
    $end = isset($options['academic']) ? ($y + 1) . "-05-31" : ($y + 1) . "-05-31"; // ensure full academic cycle

    echo "पूर्ण वर्षाचा डेटा सिंक करत आहे: {$start} ते {$end}\n";
    echo "पायरी: दिनविशेष (सर्व ३६५ दिवस), पंचांग, सुविचार, सुभाषित व फलक ज्ञान.\n\n";

    $scraper = new ParipathYearScraper($db);
    $stats = $scraper->syncWholeYear($start, $end, function ($msg) {
        echo " >> " . $msg . "\n";
    });

    echo "\n-------------------------------------------------------\n";
    echo "वर्ष सिंक यशस्वीरित्या पूर्ण झाले!\n";
    echo " - दिनविशेष दिवस: {$stats['dinvishesh_days']} (एकूण घटना: {$stats['dinvishesh_events']})\n";
    echo " - पंचांग नोंदी: थेट API: {$stats['panchang_remote']}, कॅलेंडर गणना: {$stats['panchang_calculated']}\n";
    echo " - सुविचार नोंदी: थेट API: {$stats['suvichar_remote']}, बँक सिडेड: {$stats['suvichar_seeded']}\n";
    echo " - सुभाषित नोंदी: थेट API: {$stats['subhashit_remote']}, बँक सिडेड: {$stats['subhashit_seeded']}\n";
    echo " - परिपाठ फलक नोंदी: {$stats['custom_notes_seeded']}\n";
} else {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');

    if (isset($options['today'])) {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
    } elseif (isset($options['days'])) {
        $num = max(1, (int)$options['days']);
        $startDate = date('Y-m-d', strtotime('-3 days'));
        $endDate = date('Y-m-d', strtotime('+' . ($num - 4) . ' days'));
    } elseif (isset($options['month'])) {
        $m = $options['month']; // YYYY-MM
        $startDate = date('Y-m-01', strtotime($m . '-01'));
        $endDate = date('Y-m-t', strtotime($m . '-01'));
    } elseif (isset($options['start']) && isset($options['end'])) {
        $startDate = $options['start'];
        $endDate = $options['end'];
    } else {
        $startDate = date('Y-m-d', strtotime('-2 days'));
        $endDate = date('Y-m-d', strtotime('+14 days'));
    }

    echo "Range: {$startDate} ते {$endDate}\n";
    $res = paripath_scrape_range($startDate, $endDate);
    echo "पूर्ण झाले! एकूण दिवस: {$res['total']} (यशस्वी: {$res['success']}, अयशस्वी: {$res['failed']})\n";
}

echo "\nवर्तमान SQLite डेटाबेस सांख्यिकी (data/paripath.sqlite):\n";
echo " - पंचांग (Panchang): " . $db->query("SELECT COUNT(*) FROM panchang")->fetchColumn() . " दिवस\n";
echo " - दिनविशेष (Dinvishesh): " . $db->query("SELECT COUNT(*) FROM dinvishesh")->fetchColumn() . " ऐतिहासिक घटना\n";
echo " - सुविचार (Suvichar): " . $db->query("SELECT COUNT(*) FROM suvichar")->fetchColumn() . " दिवस\n";
echo " - सुभाषित (Subhashit): " . $db->query("SELECT COUNT(*) FROM subhashit")->fetchColumn() . " दिवस\n";
echo " - परिपाठ फलक (Custom Notes): " . $db->query("SELECT COUNT(*) FROM custom_notes")->fetchColumn() . " दिवस\n";
echo " - प्रार्थना व प्रतिज्ञा (Prayers): " . $db->query("SELECT COUNT(*) FROM prayers")->fetchColumn() . " स्तोत्रे/गाणी\n";
echo "=======================================================\n";
