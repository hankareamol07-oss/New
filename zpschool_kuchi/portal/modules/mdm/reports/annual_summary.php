<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
require_once __DIR__ . '/../../lib/print_layout.php';

$u = require_login();
$sid = (int) $u['school_id'];
$pdo = db();

$school = school_row($sid) ?: [];
$school_name = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? 'प्राथमिक शाळा'));
$udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));
$taluka = (string) ($school['taluka'] ?? 'खंडाळा');
$district = (string) ($school['district'] ?? 'सातारा');
$kendra = (string) ($school['kendra'] ?? 'केंद्र शाळा');

$group = (string) ($_GET['class_group'] ?? '1-5');
if (!isset(mdm_groups()[$group])) $group = '1-5';

$start_year = (int) ($_GET['start_year'] ?? (date('m') >= 4 ? date('Y') : date('Y') - 1));
$end_year = $start_year + 1;

// 12 Academic Months: April to March
$academic_months = [
    ['month' => 4, 'year' => $start_year, 'name' => 'एप्रिल'],
    ['month' => 5, 'year' => $start_year, 'name' => 'मे (उन्हाळी सुट्टी)'],
    ['month' => 6, 'year' => $start_year, 'name' => 'जून'],
    ['month' => 7, 'year' => $start_year, 'name' => 'जुलै'],
    ['month' => 8, 'year' => $start_year, 'name' => 'ऑगस्ट'],
    ['month' => 9, 'year' => $start_year, 'name' => 'सप्टेंबर'],
    ['month' => 10, 'year' => $start_year, 'name' => 'ऑक्टोबर'],
    ['month' => 11, 'year' => $start_year, 'name' => 'नोव्हेंबर'],
    ['month' => 12, 'year' => $start_year, 'name' => 'डिसेंबर'],
    ['month' => 1, 'year' => $end_year, 'name' => 'जानेवारी'],
    ['month' => 2, 'year' => $end_year, 'name' => 'फेब्रुवारी'],
    ['month' => 3, 'year' => $end_year, 'name' => 'मार्च'],
];

$rate_per_stu = mdm_cost_rate($group, $sid); // 2.59 for 1-5, 3.88 for 6-8
$rice_norm = $group === '6-8' ? 0.150 : 0.100;

// Monthly summaries array
$summaries = [];
$tot_days = 0;
$tot_beneficiaries = 0;
$tot_rice_kg = 0.0;
$tot_pulses_kg = 0.0;
$tot_oil_spice_kg = 0.0;
$tot_veg_amt = 0.0;
$tot_fuel_amt = 0.0;
$tot_supp_amt = 0.0;
$tot_cook_amt = 0.0;
$tot_grant_amt = 0.0;

foreach ($academic_months as $am) {
    $m = $am['month'];
    $y = $am['year'];
    $ym = sprintf('%04d-%02d', $y, $m);

    // Daily records for this month
    $stmt = $pdo->prepare('SELECT COUNT(*) served_days, SUM(beneficiaries) total_ben 
                           FROM mdm_daily 
                           WHERE school_id = ? AND class_group = ? AND substr(date, 1, 7) = ? AND cooked = 1');
    $stmt->execute([$sid, $group, $ym]);
    $drow = $stmt->fetch(PDO::FETCH_ASSOC);

    $days = (int)($drow['served_days'] ?? 0);
    $ben = (int)($drow['total_ben'] ?? 0);

    // Fetch pat from mdm_pat_history
    $p_stmt = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id=? AND class_group=? AND month=? AND year=?');
    $p_stmt->execute([$sid, $group, $m, $y]);
    $pat = (int)($p_stmt->fetchColumn() ?: 0);
    if ($pat === 0) {
        $pat = (int)$pdo->query("SELECT enrolled FROM mdm_pat_history WHERE school_id={$sid} AND class_group='{$group}' ORDER BY year DESC, month DESC LIMIT 1")->fetchColumn();
    }

    // Rice used
    $rice_kg = round($ben * $rice_norm, 3);
    
    // Grains & pulses used
    $pulse_norm = $group === '6-8' ? 0.030 : 0.020;
    $oil_norm = $group === '6-8' ? 0.0075 : 0.0050;
    $pulses_kg = round($ben * $pulse_norm, 3);
    $oil_spice_kg = round($ben * ($oil_norm + 0.003), 3);

    // Financial breakdown (38% Veg, 34% Fuel, 28% Supplementary)
    $material_grant = round($ben * $rate_per_stu, 2);
    $veg_grant = round($material_grant * 0.38, 2);
    $fuel_grant = round($material_grant * 0.34, 2);
    $supp_grant = round($material_grant * 0.28, 2);

    // Cook honorarium (approx ₹ 2,500 / ₹ 3,000 per month if meals served)
    $cook_grant = $days > 0 ? 2500.0 : 0.0;

    $total_grant = $material_grant + $cook_grant;

    $summaries[] = [
        'name' => $am['name'],
        'month' => $m,
        'year' => $y,
        'pat' => $pat,
        'days' => $days,
        'ben' => $ben,
        'rice_kg' => $rice_kg,
        'pulses_kg' => $pulses_kg,
        'oil_spice_kg' => $oil_spice_kg,
        'veg_grant' => $veg_grant,
        'fuel_grant' => $fuel_grant,
        'supp_grant' => $supp_grant,
        'cook_grant' => $cook_grant,
        'total_grant' => $total_grant,
    ];

    $tot_days += $days;
    $tot_beneficiaries += $ben;
    $tot_rice_kg += $rice_kg;
    $tot_pulses_kg += $pulses_kg;
    $tot_oil_spice_kg += $oil_spice_kg;
    $tot_veg_amt += $veg_grant;
    $tot_fuel_amt += $fuel_grant;
    $tot_supp_amt += $supp_grant;
    $tot_cook_amt += $cook_grant;
    $tot_grant_amt += $total_grant;
}

// Check CSV/Excel Export
if (isset($_GET['excel'])) {
    $filename = "MDM_Annual_Summary_{$group}_{$start_year}_{$end_year}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['प्रधानमंत्री पोषण शक्ती निर्माण योजना - १२ महिन्यांचा वार्षिक सारांश अहवाल']);
    fputcsv($out, ['शाळेचे नाव:', $school_name, 'UDISE:', $udise, 'इयत्ता गट:', mdm_groups()[$group], 'शैक्षणिक वर्ष:', "{$start_year}-{$end_year}"]);
    fputcsv($out, [
        'अ.क्र.', 'महिना', 'आहार दिवस', 'एकूण लाभार्थी', 'तांदूळ (kg)', 'डाळी (kg)', 'इतर वस्तू (kg)',
        'भाजीपाला (₹)', 'इंधन खर्च (₹)', 'पूरक आहार (₹)', 'मानधन (₹)', 'एकूण खर्च रक्कम (₹)'
    ]);

    foreach ($summaries as $idx => $s) {
        fputcsv($out, [
            $idx + 1, $s['name'], $s['days'], $s['ben'], $s['rice_kg'], $s['pulses_kg'], $s['oil_spice_kg'],
            $s['veg_grant'], $s['fuel_grant'], $s['supp_grant'], $s['cook_grant'], $s['total_grant']
        ]);
    }
    fputcsv($out, [
        'एकूण', '', $tot_days, $tot_beneficiaries, $tot_rice_kg, $tot_pulses_kg, $tot_oil_spice_kg,
        $tot_veg_amt, $tot_fuel_amt, $tot_supp_amt, $tot_cook_amt, $tot_grant_amt
    ]);
    fclose($out);
    exit;
}

mdm_layout_start('वार्षिक पोषण आहार सारांश — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4 no-print">
    <div>
        <h2 class="text-2xl font-bold text-navy">वार्षिक पोषण आहार सारांश अहवाल (१२ महिने)</h2>
        <p class="text-sm text-slate-500">शैक्षणिक वर्ष <?= $start_year ?>-<?= $end_year ?> (एप्रिल ते मार्च) मासिक हिशोब गोषवारा</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="?class_group=<?= urlencode($group) ?>&start_year=<?= $start_year ?>&excel=1" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2 font-semibold hover:bg-emerald-700 transition text-sm">
            <span>📥</span> Excel / CSV डाउनलोड
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-gold text-navy font-bold px-4 py-2 hover:brightness-105 transition text-sm">
            <span>🖨️</span> प्रिंट (A4 आडवे)
        </button>
    </div>
</div>

<!-- Filters -->
<form method="get" class="bg-white border rounded-2xl p-5 mb-6 shadow-sm no-print">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">इयत्ता गट निवडा</label>
            <select name="class_group" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
                <?php foreach (mdm_groups() as $g_k => $g_v): ?>
                    <option value="<?= $g_k ?>" <?= $group === $g_k ? 'selected' : '' ?>><?= e($g_v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">शैक्षणिक वर्ष</label>
            <select name="start_year" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
                <?php for ($y = date('Y') + 1; $y >= 2022; $y--): ?>
                    <option value="<?= $y ?>" <?= $start_year === $y ? 'selected' : '' ?>><?= $y ?> - <?= $y + 1 ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="w-full rounded-xl bg-navy text-white px-4 py-2 text-sm font-bold hover:bg-navy/90 transition">
                सारांश दाखवा
            </button>
        </div>
    </div>
</form>

<!-- Printable Annual Summary Matrix -->
<div class="bg-white border rounded-2xl p-6 shadow-sm overflow-x-auto print:border-none print:p-0 print:shadow-none">
    
    <div class="text-center mb-6 pb-4 border-b">
        <h3 class="text-xl font-bold text-navy">प्रधानमंत्री पोषण शक्ती निर्माण योजना</h3>
        <h4 class="text-base font-semibold text-slate-700 mt-1">१२ महिन्यांचा एकत्रित वार्षिक खर्च व धान्य गोषवारा (<?= e(mdm_groups()[$group]) ?>)</h4>
        <p class="text-xs text-slate-500 mt-1">शैक्षणिक वर्ष: <?= $start_year ?>-<?= $end_year ?> (एप्रिल <?= $start_year ?> ते मार्च <?= $end_year ?>)</p>

        <div class="flex flex-wrap justify-between text-xs text-slate-600 mt-4 px-2">
            <span><strong>शाळेचे नाव:</strong> <?= e($school_name) ?></span>
            <span><strong>UDISE:</strong> <?= e($udise) ?></span>
            <span><strong>केंद्र:</strong> <?= e($kendra) ?></span>
            <span><strong>तालुका:</strong> <?= e($taluka) ?></span>
            <span><strong>जिल्हा:</strong> <?= e($district) ?></span>
        </div>
    </div>

    <table class="w-full text-xs text-center border-collapse border border-slate-400">
        <thead>
            <tr class="bg-slate-200 font-bold text-slate-800">
                <th rowspan="2" class="border border-slate-400 p-2 w-10">अ.क्र.</th>
                <th rowspan="2" class="border border-slate-400 p-2 text-left min-w-[120px]">महिना</th>
                <th rowspan="2" class="border border-slate-400 p-2 w-16">आहार दिवस</th>
                <th rowspan="2" class="border border-slate-400 p-2 w-20">एकूण लाभार्थी</th>
                <th colspan="3" class="border border-slate-400 p-1 bg-blue-50">धान्य उपभोग (कि.ग्रॅ.)</th>
                <th colspan="5" class="border border-slate-400 p-1 bg-amber-50">अनुदान खर्च (रुपये ₹)</th>
            </tr>
            <tr class="bg-slate-100 font-semibold text-slate-700">
                <th class="border border-slate-400 p-1 w-20">तांदूळ</th>
                <th class="border border-slate-400 p-1 w-20">डाळी</th>
                <th class="border border-slate-400 p-1 w-20">इतर वस्तू</th>
                <th class="border border-slate-400 p-1 w-20">भाजीपाला (३८%)</th>
                <th class="border border-slate-400 p-1 w-20">इंधन (३४%)</th>
                <th class="border border-slate-400 p-1 w-20">पूरक आहार (२८%)</th>
                <th class="border border-slate-400 p-1 w-20">मानधन</th>
                <th class="border border-slate-400 p-1 w-24 font-bold text-navy">एकूण खर्च ₹</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($summaries as $idx => $s): ?>
            <tr class="hover:bg-slate-50 <?= $s['days'] === 0 ? 'bg-slate-50/50 text-slate-400' : '' ?>">
                <td class="border border-slate-400 p-1.5 font-semibold"><?= $idx + 1 ?></td>
                <td class="border border-slate-400 p-1.5 text-left font-semibold text-slate-800"><?= e($s['name']) ?></td>
                <td class="border border-slate-400 p-1.5"><?= $s['days'] ?></td>
                <td class="border border-slate-400 p-1.5 font-bold"><?= number_format($s['ben']) ?></td>
                <td class="border border-slate-400 p-1.5 font-medium"><?= number_format($s['rice_kg'], 3) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($s['pulses_kg'], 3) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($s['oil_spice_kg'], 3) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($s['veg_grant'], 2) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($s['fuel_grant'], 2) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($s['supp_grant'], 2) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($s['cook_grant'], 2) ?></td>
                <td class="border border-slate-400 p-1.5 font-bold text-navy bg-amber-50/30"><?= number_format($s['total_grant'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="bg-slate-200 font-bold text-slate-900 border-t-2 border-slate-600">
                <th colspan="2" class="border border-slate-400 p-2 text-center">वार्षिक एकूण बेरीज</th>
                <th class="border border-slate-400 p-2"><?= $tot_days ?></th>
                <th class="border border-slate-400 p-2 text-blue-900"><?= number_format($tot_beneficiaries) ?></th>
                <th class="border border-slate-400 p-2"><?= number_format($tot_rice_kg, 3) ?></th>
                <th class="border border-slate-400 p-2"><?= number_format($tot_pulses_kg, 3) ?></th>
                <th class="border border-slate-400 p-2"><?= number_format($tot_oil_spice_kg, 3) ?></th>
                <th class="border border-slate-400 p-2"><?= number_format($tot_veg_amt, 2) ?></th>
                <th class="border border-slate-400 p-2"><?= number_format($tot_fuel_amt, 2) ?></th>
                <th class="border border-slate-400 p-2"><?= number_format($tot_supp_amt, 2) ?></th>
                <th class="border border-slate-400 p-2"><?= number_format($tot_cook_amt, 2) ?></th>
                <th class="border border-slate-400 p-2 text-navy text-sm font-black bg-gold/30">₹ <?= number_format($tot_grant_amt, 2) ?></th>
            </tr>
        </tfoot>
    </table>

    <div class="mt-8 pt-6 flex justify-between text-xs font-bold text-slate-700 print:mt-12">
        <div class="text-center">
            <p class="h-10"></p>
            <p>शालेय पोषण आहार प्रभारी</p>
        </div>
        <div class="text-center">
            <p class="h-10"></p>
            <p>शाळा व्यवस्थापन समिती अध्यक्ष</p>
        </div>
        <div class="text-center">
            <p class="h-10"></p>
            <p>मुख्याध्यापक (स्वाक्षरी व शिक्का)</p>
        </div>
    </div>
</div>

<?php layout_end(); ?>
