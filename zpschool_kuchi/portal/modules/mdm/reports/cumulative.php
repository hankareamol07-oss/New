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

$year = (int) ($_GET['year'] ?? date('Y'));
$up_to_month = (int) ($_GET['up_to_month'] ?? date('m'));

$rate_per_stu = mdm_cost_rate($group, $sid);
$rice_norm = $group === '6-8' ? 0.150 : 0.100;

// Progressive cumulative months from April (month 4) to up_to_month
$start_m = 4;
$cum_rows = [];
$run_days = 0;
$run_ben = 0;
$run_rice = 0.0;
$run_cost = 0.0;

for ($m = 4; $m <= 12; $m++) {
    $ym = sprintf('%04d-%02d', $year, $m);
    $st = $pdo->prepare('SELECT COUNT(*) served_days, SUM(beneficiaries) total_ben FROM mdm_daily WHERE school_id = ? AND class_group = ? AND substr(date, 1, 7) = ? AND cooked = 1');
    $st->execute([$sid, $group, $ym]);
    $dr = $st->fetch(PDO::FETCH_ASSOC);

    $days = (int)($dr['served_days'] ?? 0);
    $ben = (int)($dr['total_ben'] ?? 0);
    $rice = round($ben * $rice_norm, 3);
    $cost = round($ben * $rate_per_stu, 2);

    $run_days += $days;
    $run_ben += $ben;
    $run_rice += $rice;
    $run_cost += $cost;

    $cum_rows[] = [
        'name' => mdm_marathi_months()[$m],
        'month' => $m,
        'days' => $days,
        'ben' => $ben,
        'rice' => $rice,
        'cost' => $cost,
        'cum_days' => $run_days,
        'cum_ben' => $run_ben,
        'cum_rice' => $run_rice,
        'cum_cost' => $run_cost,
    ];
    if ($m === $up_to_month && $up_to_month >= 4) break;
}

if ($up_to_month < 4) {
    for ($m = 1; $m <= $up_to_month; $m++) {
        $ym = sprintf('%04d-%02d', $year + 1, $m);
        $st = $pdo->prepare('SELECT COUNT(*) served_days, SUM(beneficiaries) total_ben FROM mdm_daily WHERE school_id = ? AND class_group = ? AND substr(date, 1, 7) = ? AND cooked = 1');
        $st->execute([$sid, $group, $ym]);
        $dr = $st->fetch(PDO::FETCH_ASSOC);

        $days = (int)($dr['served_days'] ?? 0);
        $ben = (int)($dr['total_ben'] ?? 0);
        $rice = round($ben * $rice_norm, 3);
        $cost = round($ben * $rate_per_stu, 2);

        $run_days += $days;
        $run_ben += $ben;
        $run_rice += $rice;
        $run_cost += $cost;

        $cum_rows[] = [
            'name' => mdm_marathi_months()[$m],
            'month' => $m,
            'days' => $days,
            'ben' => $ben,
            'rice' => $rice,
            'cost' => $cost,
            'cum_days' => $run_days,
            'cum_ben' => $run_ben,
            'cum_rice' => $run_rice,
            'cum_cost' => $run_cost,
        ];
    }
}

// Check CSV/Excel Export
if (isset($_GET['excel'])) {
    $filename = "MDM_Cumulative_Report_{$group}_{$year}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['प्रधानमंत्री पोषण शक्ती निर्माण योजना - संचयी प्रगती अहवाल (Cumulative Report)']);
    fputcsv($out, ['शाळेचे नाव:', $school_name, 'UDISE:', $udise, 'इयत्ता गट:', mdm_groups()[$group]]);
    fputcsv($out, [
        'अ.क्र.', 'महिना', 'मासिक दिवस', 'मासिक लाभार्थी', 'मासिक तांदूळ (kg)', 'मासिक खर्च (₹)',
        'संचयी दिवस', 'संचयी लाभार्थी', 'संचयी तांदूळ (kg)', 'संचयी एकूण खर्च (₹)'
    ]);

    foreach ($cum_rows as $idx => $r) {
        fputcsv($out, [
            $idx + 1, $r['name'], $r['days'], $r['ben'], $r['rice'], $r['cost'],
            $r['cum_days'], $r['cum_ben'], $r['cum_rice'], $r['cum_cost']
        ]);
    }
    fclose($out);
    exit;
}

mdm_layout_start('संचयी अहवाल (Cumulative) — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4 no-print">
    <div>
        <h2 class="text-2xl font-bold text-navy">संचयी पोषण आहार प्रगती अहवाल (Cumulative)</h2>
        <p class="text-sm text-slate-500">महिन्यानिहाय व प्रगतीपथावरील एकूण संचयी (Progressive Total) आकडेवारी</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="?class_group=<?= urlencode($group) ?>&year=<?= $year ?>&up_to_month=<?= $up_to_month ?>&excel=1" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2 font-semibold hover:bg-emerald-700 transition text-sm">
            <span>📥</span> Excel / CSV डाउनलोड
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-gold text-navy font-bold px-4 py-2 hover:brightness-105 transition text-sm">
            <span>🖨️</span> प्रिंट करा (A4)
        </button>
    </div>
</div>

<form method="get" class="bg-white border rounded-2xl p-5 mb-6 shadow-sm no-print flex flex-wrap gap-4 items-end">
    <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">इयत्ता गट निवडा</label>
        <select name="class_group" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
            <?php foreach (mdm_groups() as $g_k => $g_v): ?>
                <option value="<?= $g_k ?>" <?= $group === $g_k ? 'selected' : '' ?>><?= e($g_v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">वर्ष निवडा</label>
        <input type="number" name="year" value="<?= $year ?>" min="2020" max="2035" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">या महिन्यापर्यंत संचयी</label>
        <select name="up_to_month" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
            <?php foreach (mdm_marathi_months() as $m_num => $m_name): ?>
                <option value="<?= $m_num ?>" <?= $up_to_month === $m_num ? 'selected' : '' ?>><?= e($m_name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="bg-white border rounded-2xl p-6 shadow-sm overflow-x-auto print:border-none print:p-0 print:shadow-none">
    <div class="text-center mb-6 pb-4 border-b">
        <h3 class="text-xl font-bold text-navy">प्रधानमंत्री पोषण शक्ती निर्माण योजना</h3>
        <h4 class="text-base font-semibold text-slate-700 mt-1">मासिक व संचयी (Progressive) प्रगती अहवाल</h4>
        <div class="flex flex-wrap justify-between text-xs text-slate-600 mt-3 px-2">
            <span><strong>शाळा:</strong> <?= e($school_name) ?></span>
            <span><strong>UDISE:</strong> <?= e($udise) ?></span>
            <span><strong>इयत्ता गट:</strong> <?= e(mdm_groups()[$group]) ?></span>
            <span><strong>केंद्र:</strong> <?= e($kendra) ?></span>
        </div>
    </div>

    <table class="w-full text-xs text-center border-collapse border border-slate-400">
        <thead>
            <tr class="bg-slate-200 font-bold text-slate-800">
                <th rowspan="2" class="border border-slate-400 p-2 w-10">अ.क्र.</th>
                <th rowspan="2" class="border border-slate-400 p-2 text-left min-w-[110px]">महिना</th>
                <th colspan="4" class="border border-slate-400 p-1 bg-blue-50">चालू महिन्यातील आकडेवारी</th>
                <th colspan="4" class="border border-slate-400 p-1 bg-emerald-50">प्रगतीपथावरील संचयी एकूण (Cumulative)</th>
            </tr>
            <tr class="bg-slate-100 font-semibold text-slate-700">
                <th class="border border-slate-400 p-1">दिवस</th>
                <th class="border border-slate-400 p-1">लाभार्थी</th>
                <th class="border border-slate-400 p-1">तांदूळ (kg)</th>
                <th class="border border-slate-400 p-1">खर्च (₹)</th>
                <th class="border border-slate-400 p-1 font-bold text-emerald-900">एकूण दिवस</th>
                <th class="border border-slate-400 p-1 font-bold text-emerald-900">एकूण लाभार्थी</th>
                <th class="border border-slate-400 p-1 font-bold text-emerald-900">एकूण तांदूळ (kg)</th>
                <th class="border border-slate-400 p-1 font-bold text-navy bg-gold/20">एकूण खर्च (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cum_rows as $idx => $r): ?>
            <tr class="hover:bg-slate-50">
                <td class="border border-slate-400 p-1.5 font-semibold text-slate-600"><?= $idx + 1 ?></td>
                <td class="border border-slate-400 p-1.5 text-left font-semibold text-slate-800"><?= e($r['name']) ?></td>
                <td class="border border-slate-400 p-1.5"><?= $r['days'] ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($r['ben']) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($r['rice'], 3) ?></td>
                <td class="border border-slate-400 p-1.5"><?= number_format($r['cost'], 2) ?></td>
                <td class="border border-slate-400 p-1.5 font-bold text-emerald-800 bg-emerald-50/20"><?= $r['cum_days'] ?></td>
                <td class="border border-slate-400 p-1.5 font-bold text-emerald-800 bg-emerald-50/20"><?= number_format($r['cum_ben']) ?></td>
                <td class="border border-slate-400 p-1.5 font-bold text-emerald-800 bg-emerald-50/20"><?= number_format($r['cum_rice'], 3) ?></td>
                <td class="border border-slate-400 p-1.5 font-black text-navy bg-gold/10">₹ <?= number_format($r['cum_cost'], 2) ?></td>
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
            <p>मुख्याध्यापक (स्वाक्षरी व शिक्का)</p>
        </div>
    </div>
</div>

<?php layout_end(); ?>
