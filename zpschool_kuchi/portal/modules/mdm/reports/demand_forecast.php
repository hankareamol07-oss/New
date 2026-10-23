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

$month = (int) ($_GET['month'] ?? date('m'));
$year = (int) ($_GET['year'] ?? date('Y'));

// Calculate average daily attendance/beneficiaries from mdm_daily in this month
$ym_str = sprintf('%04d-%02d', $year, $month);
$daily_stats = $pdo->prepare("SELECT COUNT(*) total_days, AVG(beneficiaries) avg_ben 
                              FROM mdm_daily 
                              WHERE school_id = ? AND class_group = ? AND substr(date, 1, 7) = ? AND cooked = 1");
$daily_stats->execute([$sid, $group, $ym_str]);
$stat = $daily_stats->fetch(PDO::FETCH_ASSOC);

$avg_ben = (float) ($stat['avg_ben'] ?? 0);
if ($avg_ben <= 0) {
    // Check mdm_pat_history
    $p_stmt = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id=? AND class_group=? AND month=? AND year=?');
    $p_stmt->execute([$sid, $group, $month, $year]);
    $avg_ben = (float)($p_stmt->fetchColumn() ?: 0);
    if ($avg_ben <= 0) {
        $avg_ben = (float)$pdo->query("SELECT enrolled FROM mdm_pat_history WHERE school_id={$sid} AND class_group='{$group}' ORDER BY year DESC, month DESC LIMIT 1")->fetchColumn();
    }
    if ($avg_ben <= 0) $avg_ben = 30; // sensible default
}
$avg_beneficiaries = (int) round($avg_ben);

// Buffer days norm is 20 days (१ महिन्याचा बफर साठा = २० कार्य दिवस)
$buffer_days = (int) ($_GET['days'] ?? 20);

// Fetch current stock from mdm_stock for all 21 commodities
$commodities = mdm_official_commodities();
$stock_stmt = $pdo->prepare('SELECT grain_id, opening, received, borrowed, returned FROM mdm_stock WHERE school_id = ? AND class_group = ? AND month = ? AND year = ?');
$stock_stmt->execute([$sid, $group, $month, $year]);
$cur_stocks = [];
foreach ($stock_stmt->fetchAll(PDO::FETCH_ASSOC) as $st) {
    $cur_stocks[(int)$st['grain_id']] = $st;
}

// Check CSV/Excel Export
if (isset($_GET['excel'])) {
    $filename = "MDM_Demand_Forecast_{$group}_{$month}_{$year}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['प्रधानमंत्री पोषण शक्ती निर्माण योजना - धान्य मागणी अंदाजपत्रक (२० दिवसांचा बफर साठा)']);
    fputcsv($out, ['शाळेचे नाव:', $school_name, 'UDISE:', $udise, 'इयत्ता गट:', mdm_groups()[$group], 'महिना/वर्ष:', mdm_marathi_months()[$month] . ' ' . $year]);
    fputcsv($out, ['सरासरी लाभार्थी संख्या:', $avg_beneficiaries, 'बफर दिवस:', $buffer_days]);
    fputcsv($out, ['अ.क्र.', 'धान्यादी वस्तू', 'दर विद्यार्थी प्रमाण (kg)', '२० दिवसांची एकूण गरज (kg)', 'उपलब्ध शिल्लक साठा (kg)', 'निव्वळ मागणी (kg)']);

    foreach ($commodities as $gid => $c) {
        $norm = $group === '6-8' ? $c['norm_6to8'] : $c['norm_1to5'];
        $need = round($avg_beneficiaries * $norm * $buffer_days, 3);
        $st = $cur_stocks[$gid] ?? [];
        $available = (float)($st['opening'] ?? 0) + (float)($st['received'] ?? 0) + (float)($st['borrowed'] ?? 0) - (float)($st['returned'] ?? 0);
        $demand = max(0.0, round($need - $available, 3));

        fputcsv($out, [$gid, $c['name_mr'], $norm, $need, $available, $demand]);
    }
    fclose($out);
    exit;
}

mdm_layout_start('मागणी अंदाजपत्रक (Buffer Stock) — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4 no-print">
    <div>
        <h2 class="text-2xl font-bold text-navy">धान्य मागणी अंदाजपत्रक (२० दिवसांचा बफर साठा)</h2>
        <p class="text-sm text-slate-500">शालेय पोषण आहार नियमानुसार पुढील महिन्यासाठी आवश्यक धान्याची आगाऊ मागणी पत्रक</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="?class_group=<?= urlencode($group) ?>&month=<?= $month ?>&year=<?= $year ?>&days=<?= $buffer_days ?>&excel=1" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2 font-semibold hover:bg-emerald-700 transition text-sm">
            <span>📥</span> Excel / CSV डाउनलोड
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-gold text-navy font-bold px-4 py-2 hover:brightness-105 transition text-sm">
            <span>🖨️</span> प्रिंट (A4)
        </button>
    </div>
</div>

<!-- Filters -->
<form method="get" class="bg-white border rounded-2xl p-5 mb-6 shadow-sm no-print">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">इयत्ता गट निवडा</label>
            <select name="class_group" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
                <?php foreach (mdm_groups() as $g_k => $g_v): ?>
                    <option value="<?= $g_k ?>" <?= $group === $g_k ? 'selected' : '' ?>><?= e($g_v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">महिना निवडा</label>
            <select name="month" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
                <?php foreach (mdm_marathi_months() as $m_num => $m_name): ?>
                    <option value="<?= $m_num ?>" <?= $month === $m_num ? 'selected' : '' ?>><?= e($m_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">वर्ष निवडा</label>
            <input type="number" name="year" value="<?= $year ?>" min="2020" max="2035" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">बफर स्टॉक दिवस (मानक: २०)</label>
            <input type="number" name="days" value="<?= $buffer_days ?>" min="1" max="45" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
        </div>
    </div>
</form>

<!-- Printable Demand Forecast -->
<div class="bg-white border rounded-2xl p-6 shadow-sm overflow-x-auto print:border-none print:p-0 print:shadow-none">
    
    <div class="text-center mb-6 pb-4 border-b">
        <h3 class="text-xl font-bold text-navy">प्रधानमंत्री पोषण शक्ती निर्माण योजना</h3>
        <h4 class="text-base font-semibold text-slate-700 mt-1">धान्य मागणी अंदाजपत्रक / बफर स्टॉक पत्रक (<?= e(mdm_groups()[$group]) ?>)</h4>
        <p class="text-xs text-slate-500 mt-1">कालावधी: माहे <?= e(mdm_marathi_months()[$month]) ?> <?= $year ?> (अपेक्षित कार्य दिवस: <?= $buffer_days ?>)</p>

        <div class="flex flex-wrap justify-between text-xs text-slate-600 mt-4 px-2">
            <span><strong>शाळेचे नाव:</strong> <?= e($school_name) ?></span>
            <span><strong>UDISE:</strong> <?= e($udise) ?></span>
            <span><strong>केंद्र:</strong> <?= e($kendra) ?></span>
            <span><strong>तालुका:</strong> <?= e($taluka) ?></span>
            <span><strong>सरासरी लाभार्थी:</strong> <?= $avg_beneficiaries ?> विद्यार्थी</span>
        </div>
    </div>

    <table class="w-full text-xs text-left border-collapse border border-slate-300">
        <thead>
            <tr class="bg-slate-100 text-slate-700 font-bold uppercase text-center">
                <th class="border border-slate-300 p-2.5 w-12">अ.क्र.</th>
                <th class="border border-slate-300 p-2.5 text-left min-w-[150px]">धान्यादी वस्तूचे नाव</th>
                <th class="border border-slate-300 p-2.5 w-16">एकक</th>
                <th class="border border-slate-300 p-2.5 w-32 bg-blue-50/70">प्रति विद्यार्थी प्रमाण</th>
                <th class="border border-slate-300 p-2.5 w-36 bg-amber-50/70"><?= $buffer_days ?> दिवसांची एकूण गरज</th>
                <th class="border border-slate-300 p-2.5 w-36 bg-slate-50">उपलब्ध शिल्लक साठा</th>
                <th class="border border-slate-300 p-2.5 w-36 bg-emerald-50/70 font-black text-navy">निव्वळ मागणी</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($commodities as $gid => $c): 
                $norm = $group === '6-8' ? $c['norm_6to8'] : $c['norm_1to5'];
                $total_need = round($avg_beneficiaries * $norm * $buffer_days, 3);
                $st = $cur_stocks[$gid] ?? [];
                $available = (float)($st['opening'] ?? 0) + (float)($st['received'] ?? 0) + (float)($st['borrowed'] ?? 0) - (float)($st['returned'] ?? 0);
                $demand = max(0.0, round($total_need - $available, 3));
            ?>
            <tr class="hover:bg-slate-50/80 transition">
                <td class="border border-slate-300 p-2 text-center font-bold text-slate-500"><?= $gid ?></td>
                <td class="border border-slate-300 p-2 font-semibold text-slate-800">
                    <?= e($c['name_mr']) ?>
                    <span class="text-[10px] text-slate-400 block"><?= e($c['name_en']) ?></span>
                </td>
                <td class="border border-slate-300 p-2 text-center text-slate-600">कि.ग्रॅ.</td>
                <td class="border border-slate-300 p-2 text-center font-medium bg-blue-50/30"><?= number_format($norm, 4) ?> kg</td>
                <td class="border border-slate-300 p-2 text-center font-bold text-slate-800 bg-amber-50/30"><?= number_format($total_need, 3) ?> kg</td>
                <td class="border border-slate-300 p-2 text-center text-slate-700 bg-slate-50"><?= number_format($available, 3) ?> kg</td>
                <td class="border border-slate-300 p-2 text-center font-black bg-emerald-50/40 <?= $demand > 0 ? 'text-navy' : 'text-slate-400' ?>">
                    <?= number_format($demand, 3) ?> kg
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-8 pt-6 flex justify-between text-xs font-bold text-slate-700 print:mt-12">
        <div class="text-center">
            <p class="h-10"></p>
            <p>शालेय पोषण आहार प्रभारी</p>
        </div>
        <div class="text-center">
            <p class="h-10"></p>
            <p>केंद्रप्रमुख / बीट विस्तार अधिकारी</p>
        </div>
        <div class="text-center">
            <p class="h-10"></p>
            <p>मुख्याध्यापक (स्वाक्षरी व शिक्का)</p>
        </div>
    </div>
</div>

<?php layout_end(); ?>
