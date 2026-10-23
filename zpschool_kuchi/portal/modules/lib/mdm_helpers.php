<?php
declare(strict_types=1);

require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/app_layout.php';

function mdm_layout_start(string $title): void
{
    layout_start($title, 'mdm');
    echo '<nav class="no-print flex flex-wrap gap-2 mb-5">';
    foreach (mdm_nav() as [$label, $href]) {
        echo '<a class="bg-white border rounded-lg px-3 py-2 text-sm text-navy hover:bg-slate-50 transition" href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '<a class="bg-white border rounded-lg px-3 py-2 text-sm text-navy hover:bg-slate-50 transition" href="' . e(url('modules/mdm/school/setup.php')) . '">सेटअप</a>';
    echo '<a class="bg-white border rounded-lg px-3 py-2 text-sm text-navy hover:bg-slate-50 transition" href="' . e(url('modules/mdm/settings/grains.php')) . '">धान्य</a>';
    echo '</nav>';
}

function mdm_groups(): array
{
    return ['1-5' => 'इयत्ता १ ते ५', '6-8' => 'इयत्ता ६ ते ८'];
}

function mdm_nav(): array
{
    return [
        ['डॅशबोर्ड', url('modules/mdm/dashboard.php')],
        ['दैनिक नोंद', url('modules/mdm/daily/entry.php')],
        ['दिनदर्शिका', url('modules/mdm/daily/calendar.php')],
        ['साठा पावती', url('modules/mdm/balance/receipt.php')],
        ['मेनू यादी', url('modules/mdm/menus/list.php')],
        ['BMI नोंद', url('modules/mdm/bmi/entry.php')],
        ['विद्यार्थी आयात', url('modules/mdm/bmi/import.php')],
        ['अहवाल', url('modules/mdm/reports/index.php')],
        ['सेटिंग्ज', url('modules/mdm/school/settings.php')],
    ];
}

/**
 * २१ अधिकृत धान्यादी वस्तूंची सूची (Official 21 Commodities from PM POSHAN / Govt format)
 */
function mdm_official_commodities(): array
{
    return [
        1 => ['id' => 1, 'name_mr' => 'तांदूळ', 'name_en' => 'Rice', 'grain_type' => 'taandool', 'unit' => 'kg', 'norm_1to5' => 0.100, 'norm_6to8' => 0.150, 'is_pulse' => false],
        2 => ['id' => 2, 'name_mr' => 'मूगडाळ', 'name_en' => 'Moong Dal', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        3 => ['id' => 3, 'name_mr' => 'तूरडाळ', 'name_en' => 'Tur Dal', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        4 => ['id' => 4, 'name_mr' => 'मसूरडाळ', 'name_en' => 'Masoor Dal', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        5 => ['id' => 5, 'name_mr' => 'मटकी', 'name_en' => 'Matki', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        6 => ['id' => 6, 'name_mr' => 'अख्खा मूग', 'name_en' => 'Whole Moong', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        7 => ['id' => 7, 'name_mr' => 'चवळी', 'name_en' => 'Chawli', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        8 => ['id' => 8, 'name_mr' => 'हरभरा', 'name_en' => 'Harbhara', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        9 => ['id' => 9, 'name_mr' => 'वाटाणा', 'name_en' => 'Vatana', 'grain_type' => 'dal', 'unit' => 'kg', 'norm_1to5' => 0.020, 'norm_6to8' => 0.030, 'is_pulse' => true],
        10 => ['id' => 10, 'name_mr' => 'जिरे', 'name_en' => 'Cumin', 'grain_type' => 'spice', 'unit' => 'kg', 'norm_1to5' => 0.00005, 'norm_6to8' => 0.0001, 'is_pulse' => false],
        11 => ['id' => 11, 'name_mr' => 'मोहरी', 'name_en' => 'Mustard', 'grain_type' => 'spice', 'unit' => 'kg', 'norm_1to5' => 0.0001, 'norm_6to8' => 0.0001, 'is_pulse' => false],
        12 => ['id' => 12, 'name_mr' => 'हळद', 'name_en' => 'Turmeric', 'grain_type' => 'spice', 'unit' => 'kg', 'norm_1to5' => 0.0001, 'norm_6to8' => 0.0002, 'is_pulse' => false],
        13 => ['id' => 13, 'name_mr' => 'तिखट मसाला', 'name_en' => 'Chilli Masala', 'grain_type' => 'spice', 'unit' => 'kg', 'norm_1to5' => 0.0015, 'norm_6to8' => 0.0025, 'is_pulse' => false],
        14 => ['id' => 14, 'name_mr' => 'तेल', 'name_en' => 'Oil', 'grain_type' => 'oil', 'unit' => 'kg', 'norm_1to5' => 0.0050, 'norm_6to8' => 0.0075, 'is_pulse' => false],
        15 => ['id' => 15, 'name_mr' => 'मीठ', 'name_en' => 'Salt', 'grain_type' => 'salt_sugar', 'unit' => 'kg', 'norm_1to5' => 0.0010, 'norm_6to8' => 0.0015, 'is_pulse' => false],
        16 => ['id' => 16, 'name_mr' => 'मिरची पावडर', 'name_en' => 'Chilli Powder', 'grain_type' => 'spice', 'unit' => 'kg', 'norm_1to5' => 0.0005, 'norm_6to8' => 0.0010, 'is_pulse' => false],
        17 => ['id' => 17, 'name_mr' => 'गूळ / साखर', 'name_en' => 'Jaggery / Sugar', 'grain_type' => 'salt_sugar', 'unit' => 'kg', 'norm_1to5' => 0.0100, 'norm_6to8' => 0.0150, 'is_pulse' => false],
        18 => ['id' => 18, 'name_mr' => 'दूध पावडर', 'name_en' => 'Milk Powder', 'grain_type' => 'milk', 'unit' => 'kg', 'norm_1to5' => 0.0100, 'norm_6to8' => 0.0150, 'is_pulse' => false],
        19 => ['id' => 19, 'name_mr' => 'सोयाबीन वडी', 'name_en' => 'Soybean Vadi', 'grain_type' => 'other_dry', 'unit' => 'kg', 'norm_1to5' => 0.0200, 'norm_6to8' => 0.0300, 'is_pulse' => true],
        20 => ['id' => 20, 'name_mr' => 'नाचणी सत्त्व', 'name_en' => 'Nachni Satva', 'grain_type' => 'other_dry', 'unit' => 'kg', 'norm_1to5' => 0.0100, 'norm_6to8' => 0.0150, 'is_pulse' => false],
        21 => ['id' => 21, 'name_mr' => 'गरम मसाला', 'name_en' => 'Garam Masala', 'grain_type' => 'spice', 'unit' => 'kg', 'norm_1to5' => 0.0005, 'norm_6to8' => 0.0010, 'is_pulse' => false],
    ];
}

function mdm_marathi_months(): array
{
    return [
        1 => 'जानेवारी', 2 => 'फेब्रुवारी', 3 => 'मार्च', 4 => 'एप्रिल', 5 => 'मे', 6 => 'जून',
        7 => 'जुलै', 8 => 'ऑगस्ट', 9 => 'सप्टेंबर', 10 => 'ऑक्टोबर', 11 => 'नोव्हेंबर', 12 => 'डिसेंबर'
    ];
}

function mdm_marathi_day(string $date): string
{
    $w = (int)date('w', strtotime($date));
    $map = [0 => 'रवि', 1 => 'सोम', 2 => 'मंगळ', 3 => 'बुध', 4 => 'गुरू', 5 => 'शुक्र', 6 => 'शनि'];
    return $map[$w] ?? '';
}

function mdm_category(float $bmi): string
{
    if ($bmi <= 0) return '';
    if ($bmi < 18.5) return 'कमी वजन (Underweight)';
    if ($bmi <= 24.9) return 'निरोगी (Normal)';
    if ($bmi <= 29.9) return 'जास्त वजन (Overweight)';
    return 'लठ्ठपणा (Obese)';
}

function mdm_cost_rate(string $group, int $schoolId = 0): float
{
    static $rates = [];
    if (isset($rates[$group])) return $rates[$group];
    try {
        $pdo = db();
        $q = $pdo->prepare('SELECT rate_1to5, rate_6to8 FROM mdm_grant_settings WHERE school_id=? ORDER BY effective_from DESC LIMIT 1');
        $q->execute([$schoolId ?: school_id()]);
        $r = $q->fetch();
        if ($r) {
            $rates['1-5'] = (float)$r['rate_1to5'];
            $rates['6-8'] = (float)$r['rate_6to8'];
            return $rates[$group] ?? ($group === '1-5' ? 2.59 : 3.88);
        }
    } catch (Throwable $e) {}
    $rates['1-5'] = 2.59;
    $rates['6-8'] = 3.88;
    return $rates[$group] ?? ($group === '1-5' ? 2.59 : 3.88);
}

/**
 * Ensures standard 21 commodities and default menus are seeded for the school
 */
function mdm_ensure_seeded(PDO $pdo, int $schoolId): void
{
    static $seeded = [];
    if (!empty($seeded[$schoolId])) return;
    $seeded[$schoolId] = true;

    // 1. Seed 21 Commodities in mdm_grains
    $official = mdm_official_commodities();
    $existing = $pdo->prepare('SELECT name_mr, id FROM mdm_grains WHERE school_id=?');
    $existing->execute([$schoolId]);
    $existingMap = $existing->fetchAll(PDO::FETCH_KEY_PAIR);

    $insGrain = $pdo->prepare('INSERT INTO mdm_grains (id, school_id, name_mr, name_en, grain_type, unit, visible) VALUES (?,?,?,?,?,?,1)');
    foreach ($official as $id => $g) {
        if (!isset($existingMap[$g['name_mr']])) {
            try {
                $insGrain->execute([$id, $schoolId, $g['name_mr'], $g['name_en'], $g['grain_type'], $g['unit']]);
            } catch (Throwable $e) {
                // If ID conflict, insert without explicit ID
                $pdo->prepare('INSERT INTO mdm_grains (school_id, name_mr, name_en, grain_type, unit, visible) VALUES (?,?,?,?,?,1)')
                    ->execute([$schoolId, $g['name_mr'], $g['name_en'], $g['grain_type'], $g['unit']]);
            }
        }
    }

    // 2. Seed Default Weekly Menus if empty
    $menuCount = (int)$pdo->query("SELECT COUNT(*) FROM mdm_menus WHERE school_id = $schoolId")->fetchColumn();
    if ($menuCount === 0) {
        $defaultMenus = [
            1 => ['name' => 'हरभरा उसळ भात', 'weekday' => 1, 'pulse_id' => 8], // Mon
            2 => ['name' => 'मसूर उसळ भात', 'weekday' => 2, 'pulse_id' => 4],   // Tue
            3 => ['name' => 'मूगडाळ खिचडी', 'weekday' => 3, 'pulse_id' => 2],    // Wed
            4 => ['name' => 'मटकी उसळ भात', 'weekday' => 4, 'pulse_id' => 5],   // Thu
            5 => ['name' => 'चवळी उसळ भात', 'weekday' => 5, 'pulse_id' => 7],   // Fri
            6 => ['name' => 'वाटाणा पुलाव', 'weekday' => 6, 'pulse_id' => 9],    // Sat
        ];
        $insMenu = $pdo->prepare('INSERT INTO mdm_menus (id, school_id, name, weekday, active) VALUES (?,?,?,?,1)');
        $insItem = $pdo->prepare('INSERT INTO mdm_menu_items (menu_id, grain_id, qty_1to5, qty_6to8) VALUES (?,?,?,?)');
        foreach ($defaultMenus as $mid => $m) {
            try {
                $insMenu->execute([$mid, $schoolId, $m['name'], $m['weekday']]);
                // Link Rice (1)
                $insItem->execute([$mid, 1, 0.100, 0.150]);
                // Link Pulse
                $insItem->execute([$mid, $m['pulse_id'], 0.020, 0.030]);
                // Link Oil (14)
                $insItem->execute([$mid, 14, 0.005, 0.0075]);
                // Link Salt (15)
                $insItem->execute([$mid, 15, 0.001, 0.0015]);
                // Link Turmeric (12)
                $insItem->execute([$mid, 12, 0.0001, 0.0002]);
                // Link Chilli Masala (13)
                $insItem->execute([$mid, 13, 0.0015, 0.0025]);
                // Link Jeera (10)
                $insItem->execute([$mid, 10, 0.00005, 0.0001]);
                // Link Mohari (11)
                $insItem->execute([$mid, 11, 0.0001, 0.0001]);
            } catch (Throwable $e) {}
        }
    }
}

/**
 * Returns consumption breakdown for a menu
 */
function mdm_consumption(PDO $pdo, int $menuId, string $group, int $beneficiaries): array
{
    if ($menuId <= 0 || $beneficiaries <= 0) return [];
    $q = $pdo->prepare('SELECT g.id, g.name_mr, g.unit, mi.qty_1to5, mi.qty_6to8 
                        FROM mdm_menu_items mi 
                        JOIN mdm_grains g ON g.id=mi.grain_id 
                        WHERE mi.menu_id=? ORDER BY g.id');
    $q->execute([$menuId]);
    $out = [];
    foreach ($q as $row) {
        $perChild = (float)($group === '1-5' ? $row['qty_1to5'] : $row['qty_6to8']);
        $out[$row['name_mr']] = [
            'id' => (int)$row['id'],
            'qty' => round($perChild * $beneficiaries, 4),
            'unit' => $row['unit'],
        ];
    }
    return $out;
}

/**
 * Comprehensive data calculator for monthly registers (Part 1, Part 2, Prapatr B, Taste Register)
 */
function mdm_month_data(PDO $pdo, int $schoolId, int $year, int $month, string $group): array
{
    mdm_ensure_seeded($pdo, $schoolId);

    $from = sprintf('%04d-%02d-01', $year, $month);
    $daysInMonth = (int)date('t', strtotime($from));
    $to = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

    // Get school info
    $school = school_row($schoolId);

    // Get pat (enrolled count) for the class group from MDM pat settings first
    $pat = 0;
    try {
        // 1. Check specific month and year in mdm_pat_history
        $ph = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id=? AND class_group=? AND year=? AND month=?');
        $ph->execute([$schoolId, $group, $year, $month]);
        $pat = (int)($ph->fetchColumn() ?: 0);

        // 2. Check base/default pat (month=0) in mdm_pat_history
        if ($pat === 0) {
            $phBase = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id=? AND class_group=? AND month=0 ORDER BY year DESC LIMIT 1');
            $phBase->execute([$schoolId, $group]);
            $pat = (int)($phBase->fetchColumn() ?: 0);
        }

        // 3. Check latest recorded pat in mdm_pat_history
        if ($pat === 0) {
            $phLatest = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id=? AND class_group=? ORDER BY year DESC, month DESC LIMIT 1');
            $phLatest->execute([$schoolId, $group]);
            $pat = (int)($phLatest->fetchColumn() ?: 0);
        }

        // 4. Fallback to mdm_students count for this class group if pat not configured
        if ($pat === 0) {
            $minStd = $group === '1-5' ? 1 : 6;
            $maxStd = $group === '1-5' ? 5 : 8;
            $mCount = (int)$pdo->query("SELECT COUNT(*) FROM mdm_students WHERE school_id={$schoolId} AND CAST(std AS UNSIGNED) BETWEEN {$minStd} AND {$maxStd}")->fetchColumn();
            if ($mCount > 0) $pat = $mCount;
        }
    } catch (Throwable $e) {}

    // Get existing daily entries
    $dailyStmt = $pdo->prepare('SELECT d.*, m.name as menu_name, m.weekday as menu_weekday 
                                FROM mdm_daily d 
                                LEFT JOIN mdm_menus m ON m.id=d.menu_id 
                                WHERE d.school_id=? AND d.class_group=? AND d.date BETWEEN ? AND ?');
    $dailyStmt->execute([$schoolId, $group, $from, $to]);
    $dailyEntries = [];
    foreach ($dailyStmt->fetchAll() as $r) {
        $dailyEntries[$r['date']] = $r;
    }

    // Get default menus
    $menusStmt = $pdo->prepare('SELECT * FROM mdm_menus WHERE school_id=? AND active=1 ORDER BY weekday');
    $menusStmt->execute([$schoolId]);
    $menus = [];
    $menusByDay = [];
    foreach ($menusStmt->fetchAll() as $m) {
        $menus[$m['id']] = $m;
        $menusByDay[(int)$m['weekday']] = $m;
    }

    // Official commodities
    $commodities = mdm_official_commodities();

    // Opening, received, borrowed, returned stock for this month
    $stockStmt = $pdo->prepare('SELECT grain_id, opening, received, COALESCE(borrowed, 0) as borrowed, returned 
                                FROM mdm_stock 
                                WHERE school_id=? AND class_group=? AND year=? AND month=?');
    $stockStmt->execute([$schoolId, $group, $year, $month]);
    $stockRecords = [];
    foreach ($stockStmt->fetchAll() as $sr) {
        $stockRecords[(int)$sr['grain_id']] = [
            'opening' => (float)$sr['opening'],
            'received' => (float)$sr['received'],
            'borrowed' => (float)$sr['borrowed'],
            'returned' => (float)$sr['returned'],
        ];
    }

    // Norms
    $costRate = mdm_cost_rate($group, $schoolId);
    $riceNorm = $group === '1-5' ? 0.100 : 0.150;
    $pulseNorm = $group === '1-5' ? 0.020 : 0.030;
    $vegNorm = $group === '1-5' ? 0.050 : 0.075;
    $oilNorm = $group === '1-5' ? 0.0050 : 0.0075;
    $saltNorm = $group === '1-5' ? 0.0010 : 0.0015;

    // Daily rows array
    $days = [];
    $totalBeneficiaries = 0;
    $workingDays = 0;
    $cookedDays = 0;
    $totalCookedRice = 0.0;
    $itemTotals = array_fill(1, 21, 0.0);
    $totalVeg = 0.0;
    $totalCost = 0.0;

    // Running rice balance tracking for Part 1
    // Initial rice opening
    $riceOpening = $stockRecords[1]['opening'] ?? 100.0;
    $riceReceived = $stockRecords[1]['received'] ?? 0.0;
    $riceBorrowed = $stockRecords[1]['borrowed'] ?? 0.0;
    $riceReturned = $stockRecords[1]['returned'] ?? 0.0;

    $runningRiceBalance = $riceOpening;

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $w = (int)date('w', strtotime($dateStr));
        $dayName = mdm_marathi_day($dateStr);
        $isSunday = ($w === 0);

        $entry = $dailyEntries[$dateStr] ?? null;
        $beneficiaries = $entry ? (int)$entry['beneficiaries'] : 0;
        $cooked = $entry ? (bool)$entry['cooked'] : (!$isSunday && $beneficiaries > 0);
        $holiday = $entry ? (bool)$entry['holiday'] : $isSunday;

        if ($cooked && $beneficiaries > 0) {
            $cookedDays++;
        }
        if (!$holiday && !$isSunday) {
            $workingDays++;
        }

        $totalBeneficiaries += $beneficiaries;

        // Determine menu
        $menuId = $entry ? (int)$entry['menu_id'] : ($menusByDay[$w]['id'] ?? 1);
        $menuName = $entry && !empty($entry['menu_name']) ? $entry['menu_name'] : ($menus[$menuId]['name'] ?? ($menusByDay[$w]['name'] ?? 'खिचडी'));

        // Calculate commodities used on this day
        $dayRice = $cooked && $beneficiaries > 0 ? round($beneficiaries * $riceNorm, 4) : 0.0;
        $totalCookedRice += $dayRice;

        // Running balance for Day 1 adds received & borrowed if recorded on day 1
        $dayReceivedRice = ($d === 1) ? $riceReceived : 0.0;
        $dayBorrowedRice = ($d === 1) ? $riceBorrowed : 0.0;
        $dayReturnedRice = ($d === $daysInMonth) ? $riceReturned : 0.0;

        $dayTotalRiceAvailable = $runningRiceBalance + $dayReceivedRice + $dayBorrowedRice;
        $dayClosingRice = max(0.0, $dayTotalRiceAvailable - $dayRice - $dayReturnedRice);
        $dayOpeningRice = $runningRiceBalance;
        $runningRiceBalance = $dayClosingRice;

        // Daily item amounts for Part 2
        $dayItems = array_fill(1, 21, 0.0);
        $dayItems[1] = $dayRice;

        // Pulse item depending on weekday/menu:
        // Mon (1): Harbhara (8), Tue (2): Masoor (4), Wed (3): Moong Dal (2), Thu (4): Matki (5), Fri (5): Chawli (7), Sat (6): Vatana (9)
        $pulseGid = match ($w) {
            1 => 8, // Harbhara
            2 => 4, // Masoor
            3 => 2, // Moong dal
            4 => 5, // Matki
            5 => 7, // Chawli
            6 => 9, // Vatana
            default => 2
        };
        // Check if menu has a specific pulse
        if ($menuId && !empty($menus[$menuId])) {
            $mName = $menus[$menuId]['name'];
            if (str_contains($mName, 'हरभरा')) $pulseGid = 8;
            elseif (str_contains($mName, 'मटकी')) $pulseGid = 5;
            elseif (str_contains($mName, 'मसूर')) $pulseGid = 4;
            elseif (str_contains($mName, 'चवळी')) $pulseGid = 7;
            elseif (str_contains($mName, 'वाटाणा')) $pulseGid = 9;
            elseif (str_contains($mName, 'सोयाबीन')) $pulseGid = 19;
            elseif (str_contains($mName, 'तूर')) $pulseGid = 3;
            else $pulseGid = 2; // Moong dal
        }

        if ($cooked && $beneficiaries > 0) {
            $dayItems[$pulseGid] = round($beneficiaries * $pulseNorm, 4);
            $dayItems[14] = round($beneficiaries * $oilNorm, 4); // Oil
            $dayItems[15] = round($beneficiaries * $saltNorm, 4); // Salt
            $dayItems[10] = round($beneficiaries * ($group === '1-5' ? 0.00005 : 0.0001), 5); // Cumin
            $dayItems[11] = round($beneficiaries * 0.0001, 5); // Mustard
            $dayItems[12] = round($beneficiaries * ($group === '1-5' ? 0.0001 : 0.0002), 5); // Turmeric
            $dayItems[13] = round($beneficiaries * ($group === '1-5' ? 0.0015 : 0.0025), 4); // Chilli masala
        }

        // Aggregate totals
        foreach ($dayItems as $gid => $amt) {
            $itemTotals[$gid] += $amt;
        }

        $dayVeg = $cooked && $beneficiaries > 0 ? round($beneficiaries * $vegNorm, 4) : 0.0;
        $totalVeg += $dayVeg;

        $dayCost = $cooked && $beneficiaries > 0 ? round($beneficiaries * $costRate, 2) : 0.0;
        $totalCost += $dayCost;

        // Tasting info
        $tastingTime = $entry['tasting_time'] ?? ($isSunday || $holiday ? '' : '11:45');
        $distributionTime = $entry['distribution_time'] ?? ($isSunday || $holiday ? '' : '12:30');
        $tasterName = $entry['taster_name'] ?? ($isSunday || $holiday ? '' : ($school['name_mr'] ? 'मुख्याध्यापक' : 'शिक्षक'));
        $tasteRating = $entry['taste_rating'] ?? ($isSunday || $holiday ? '' : 'उत्तम');

        $days[] = [
            'day_no' => $d,
            'date' => $dateStr,
            'weekday' => $dayName,
            'is_sunday' => $isSunday,
            'is_holiday' => $holiday,
            'pat' => $pat,
            'beneficiaries' => $beneficiaries,
            'cooked' => $cooked,
            'menu_id' => $menuId,
            'menu_name' => $isSunday ? 'रविवार सुट्टी' : ($holiday ? 'सुट्टी' : $menuName),
            // Part 1 (Rice) columns
            'rice_opening' => $dayOpeningRice,
            'rice_received' => $dayReceivedRice,
            'rice_borrowed' => $dayBorrowedRice,
            'rice_total_available' => $dayTotalRiceAvailable,
            'rice_cooked' => $dayRice,
            'rice_returned' => $dayReturnedRice,
            'rice_closing' => $dayClosingRice,
            // Part 2 (All Commodities) columns
            'items' => $dayItems,
            'veg' => $dayVeg,
            'eggs' => 0,
            'supplement' => 0,
            'cost' => $dayCost,
            // Taste register columns
            'tasting_time' => $tastingTime,
            'distribution_time' => $distributionTime,
            'taster_name' => $tasterName,
            'taste_rating' => $tasteRating,
            'remarks' => $entry['remarks'] ?? '',
        ];
    }

    // Monthly commodity stock balancing
    $commoditiesSummary = [];
    foreach ($commodities as $gid => $c) {
        $open = $stockRecords[$gid]['opening'] ?? ($gid === 1 ? 100.0 : ($c['is_pulse'] ? 18.0 : ($gid === 14 ? 12.0 : ($gid === 15 ? 5.0 : 1.0))));
        $recv = $stockRecords[$gid]['received'] ?? 0.0;
        $borr = $stockRecords[$gid]['borrowed'] ?? 0.0;
        $used = round($itemTotals[$gid] ?? 0.0, 4);
        $retn = $stockRecords[$gid]['returned'] ?? 0.0;
        $tot = $open + $recv + $borr;
        $close = max(0.0, round($tot - $used - $retn, 4));

        // Demand forecast (for 20 days buffer stock as per Excel note)
        $avgDaily = $workingDays > 0 ? ($totalBeneficiaries / $workingDays) : $pat;
        $norm = $group === '1-5' ? $c['norm_1to5'] : $c['norm_6to8'];
        $demand20Days = round($avgDaily * 20 * $norm, 2);
        $demand = max(0.0, round($demand20Days - $close, 2));

        $commoditiesSummary[$gid] = [
            'id' => $gid,
            'name_mr' => $c['name_mr'],
            'name_en' => $c['name_en'],
            'unit' => $c['unit'],
            'opening' => $open,
            'received' => $recv,
            'borrowed' => $borr,
            'total_available' => $tot,
            'consumed' => $used,
            'returned' => $retn,
            'closing' => $close,
            'demand_next' => $demand,
            'remarks' => '',
        ];
    }

    // Financial breakdown
    // 1) ताटांची संख्या x दर = एकूण
    $calcCost = round($totalBeneficiaries * $costRate, 2);
    // 2) अनुदान विभाजन: भाजीपाला 38%, इंधन 34%, पूरक आहार 28%
    $grantVeg = round($calcCost * 0.38, 2);
    $grantFuel = round($calcCost * 0.34, 2);
    $grantSuppl = round($calcCost * 0.28, 2);

    // 3) स्वयंपाकी मानधन
    $cooksHonorarium = 0.0;
    try {
        $cooksStmt = $pdo->prepare('SELECT SUM(honorarium) FROM mdm_cooks WHERE school_id=? AND active=1');
        $cooksStmt->execute([$schoolId]);
        $cooksHonorarium = (float)($cooksStmt->fetchColumn() ?: 0.0);
    } catch (Throwable $e) {}

    return [
        'school' => $school,
        'year' => $year,
        'month' => $month,
        'month_name' => mdm_marathi_months()[$month] ?? (string)$month,
        'group' => $group,
        'group_label' => mdm_groups()[$group] ?? $group,
        'pat' => $pat,
        'days_in_month' => $daysInMonth,
        'working_days' => $workingDays,
        'cooked_days' => $cookedDays,
        'total_beneficiaries' => $totalBeneficiaries,
        'total_cooked_rice' => round($totalCookedRice, 4),
        'total_veg' => round($totalVeg, 4),
        'total_cost' => $calcCost,
        'grant_veg' => $grantVeg,
        'grant_fuel' => $grantFuel,
        'grant_suppl' => $grantSuppl,
        'cooks_honorarium' => $cooksHonorarium,
        'cost_rate' => $costRate,
        'rice_norm' => $riceNorm,
        'days' => $days,
        'commodities' => $commoditiesSummary,
    ];
}

function mdm_report_rows(PDO $pdo, int $sid, string $from, string $to, ?string $group = null): array
{
    $sql = 'SELECT d.*,m.name menu_name FROM mdm_daily d LEFT JOIN mdm_menus m ON m.id=d.menu_id AND m.school_id=d.school_id WHERE d.school_id=? AND d.date BETWEEN ? AND ?';
    $args = [$sid, $from, $to];
    if ($group !== null && isset(mdm_groups()[$group])) {
        $sql .= ' AND d.class_group=?';
        $args[] = $group;
    }
    $sql .= ' ORDER BY d.date,d.class_group';
    $q = $pdo->prepare($sql);
    $q->execute($args);
    return $q->fetchAll();
}

function mdm_report_csv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}
