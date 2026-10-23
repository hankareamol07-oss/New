<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_role(['admin', 'principal', 'clerk', 'teacher']);
$sid = (int) $u['school_id'];
$pdo = db();

mdm_ensure_seeded($pdo, $sid);

$month = (int) ($_GET['month'] ?? $_POST['month'] ?? date('m'));
$year = (int) ($_GET['year'] ?? $_POST['year'] ?? date('Y'));
$group = (string) ($_GET['class_group'] ?? $_POST['class_group'] ?? '1-5');
if (!isset(mdm_groups()[$group])) $group = '1-5';

$receipt_no = (string) ($_POST['receipt_no'] ?? $_GET['receipt_no'] ?? '');
$receipt_date = (string) ($_POST['receipt_date'] ?? $_GET['receipt_date'] ?? '');
$supplier_name = (string) ($_POST['supplier_name'] ?? $_GET['supplier_name'] ?? '');

// Previous month calculation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    $grain_ids = (array) ($_POST['grain_id'] ?? []);
    $openings = (array) ($_POST['opening'] ?? []);
    $receiveds = (array) ($_POST['received'] ?? []);
    $borroweds = (array) ($_POST['borrowed'] ?? []);
    $returneds = (array) ($_POST['returned'] ?? []);

    $stmt_save = $pdo->prepare('REPLACE INTO mdm_stock (school_id, grain_id, class_group, month, year, opening, received, borrowed, returned) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $saved = 0;
    foreach ($grain_ids as $i => $gid_raw) {
        $gid = (int) $gid_raw;
        $op = (float) ($openings[$i] ?? 0);
        $rc = (float) ($receiveds[$i] ?? 0);
        $br = (float) ($borroweds[$i] ?? 0);
        $rt = (float) ($returneds[$i] ?? 0);

        $stmt_save->execute([$sid, $gid, $group, $month, $year, $op, $rc, $br, $rt]);
        $saved++;
    }

    flash_set('success', "साठा पावती यशस्वीरित्या जतन केली ({$saved} वस्तू).");
    header('Location: ' . url("modules/mdm/balance/receipt.php?month={$month}&year={$year}&class_group=" . urlencode($group)));
    exit;
}

// Fetch 21 Commodities with their current month stock
$commodities = mdm_official_commodities();

$stock_stmt = $pdo->prepare('SELECT grain_id, opening, received, borrowed, returned FROM mdm_stock WHERE school_id = ? AND class_group = ? AND month = ? AND year = ?');
$stock_stmt->execute([$sid, $group, $month, $year]);
$cur_stocks = [];
foreach ($stock_stmt->fetchAll(PDO::FETCH_ASSOC) as $st) {
    $cur_stocks[(int)$st['grain_id']] = $st;
}

// Fetch previous month closing stock to auto-suggest opening if not set
$prev_stock_stmt = $pdo->prepare('SELECT grain_id, opening, received, borrowed, returned FROM mdm_stock WHERE school_id = ? AND class_group = ? AND month = ? AND year = ?');
$prev_stock_stmt->execute([$sid, $group, $prev_month, $prev_year]);
$prev_stocks = [];
foreach ($prev_stock_stmt->fetchAll(PDO::FETCH_ASSOC) as $pst) {
    $prev_stocks[(int)$pst['grain_id']] = $pst;
}

// Calculate monthly consumption for each grain in current month
$ym_str = sprintf('%04d-%02d', $year, $month);
$cons_stmt = $pdo->prepare('SELECT d.beneficiaries, mi.grain_id, mi.qty_1to5, mi.qty_6to8 
                            FROM mdm_daily d 
                            JOIN mdm_menu_items mi ON mi.menu_id = d.menu_id 
                            WHERE d.school_id = ? AND d.class_group = ? AND substr(d.date, 1, 7) = ? AND d.cooked = 1');
$cons_stmt->execute([$sid, $group, $ym_str]);
$grain_consumptions = [];
foreach ($cons_stmt->fetchAll(PDO::FETCH_ASSOC) as $crow) {
    $gid = (int)$crow['grain_id'];
    $ben = (int)$crow['beneficiaries'];
    $per_stu = $group === '6-8' ? (float)$crow['qty_6to8'] : (float)$crow['qty_1to5'];
    $grain_consumptions[$gid] = ($grain_consumptions[$gid] ?? 0.0) + ($ben * $per_stu);
}

// Check if Rice consumption is in mdm_daily without menu items
if (!isset($grain_consumptions[1])) {
    $rice_norm = $group === '6-8' ? 0.150 : 0.100;
    $sum_ben = (int)$pdo->query("SELECT SUM(beneficiaries) FROM mdm_daily WHERE school_id = {$sid} AND class_group = '{$group}' AND substr(date, 1, 7) = '{$ym_str}' AND cooked = 1")->fetchColumn();
    $grain_consumptions[1] = $sum_ben * $rice_norm;
}

// If no existing stock in current month, check if user clicked "auto_prev"
$auto_fill_prev = isset($_GET['auto_prev']) && $_GET['auto_prev'] === '1';

mdm_layout_start('साठा पावती व धान्य हिशोब — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-navy">धान्य साठा पावती व शिल्लक हिशोब</h2>
        <p class="text-sm text-slate-500">पुरवठादार प्राप्त धान्य (PRAPT) व लोकसहभाग / उसनवार (Lok) नोंदवही</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= e(url("modules/mdm/reports/prapatr_b.php?month={$month}&year={$year}&class_group=" . urlencode($group))) ?>" class="inline-flex items-center gap-2 rounded-xl bg-teal text-white px-4 py-2 font-semibold hover:bg-teal/90 transition text-sm">
            <span>📄</span> प्रपत्र ब अहवाल →
        </a>
    </div>
</div>

<!-- Filters Form -->
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
            <button type="submit" class="w-full rounded-xl bg-navy text-white px-4 py-2 text-sm font-bold hover:bg-navy/90 transition">
                माहिती पहा
            </button>
        </div>
    </div>
</form>

<!-- Main Stock Form -->
<form method="post" id="stockForm" class="bg-white border rounded-2xl p-6 shadow-sm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="class_group" value="<?= e($group) ?>">
    <input type="hidden" name="month" value="<?= $month ?>">
    <input type="hidden" name="year" value="<?= $year ?>">

    <!-- Challan Details Bar -->
    <div class="bg-blue-50/70 border border-blue-100 rounded-xl p-4 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
            <h4 class="text-sm font-bold text-navy flex items-center gap-2">
                <span>📋</span> चलन / पावती माहिती (माहे <?= e(mdm_marathi_months()[$month] ?? '') ?> <?= $year ?>)
            </h4>
            <a href="?month=<?= $month ?>&year=<?= $year ?>&class_group=<?= urlencode($group) ?>&auto_prev=1" class="text-xs font-bold text-teal hover:underline flex items-center gap-1">
                <span>🔄</span> मागील महिन्याची अखेर शिल्लक ओढा
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">पावती / चलन क्रमांक</label>
                <input type="text" name="receipt_no" value="<?= e($receipt_no) ?>" placeholder="उदा. CH-2026/04" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm bg-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">पावती दिनांक</label>
                <input type="date" name="receipt_date" value="<?= e($receipt_date ?: sprintf('%04d-%02d-05', $year, $month)) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm bg-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">पुरवठादार / एजन्सी नाव</label>
                <input type="text" name="supplier_name" value="<?= e($supplier_name ?: 'महाराष्ट्र राज्य वखार महामंडळ / पुरवठादार') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm bg-white">
            </div>
        </div>
    </div>

    <!-- 21 Commodities Stock Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-xs text-left border-collapse border border-slate-300">
            <thead>
                <tr class="bg-slate-100 text-slate-700 font-bold uppercase text-center">
                    <th class="border border-slate-300 p-2.5 w-10">अ.क्र.</th>
                    <th class="border border-slate-300 p-2.5 text-left min-w-[140px]">धान्यादी वस्तूचे नाव</th>
                    <th class="border border-slate-300 p-2.5 w-14">एकक</th>
                    <th class="border border-slate-300 p-2.5 w-28 bg-amber-50/70">१) मागील शिल्लक</th>
                    <th class="border border-slate-300 p-2.5 w-28 bg-blue-50/70">२) पुरवठादार प्राप्त</th>
                    <th class="border border-slate-300 p-2.5 w-28 bg-emerald-50/70">३) लोकसहभाग/उसनवार</th>
                    <th class="border border-slate-300 p-2.5 w-28 bg-red-50/70">४) उसनवार परत/नासाडी</th>
                    <th class="border border-slate-300 p-2.5 w-28 bg-slate-50">५) चालू उपभोग</th>
                    <th class="border border-slate-300 p-2.5 w-32 bg-teal-50/70 font-black text-navy">६) अखेर शिल्लक</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commodities as $gid => $c): 
                    $cur = $cur_stocks[$gid] ?? [];
                    $prev = $prev_stocks[$gid] ?? [];

                    // Calculate default opening
                    $opening_val = isset($cur['opening']) ? (float)$cur['opening'] : 0.0;
                    if ($auto_fill_prev || (!isset($cur['opening']) && !empty($prev))) {
                        // Previous closing = prev opening + received + borrowed - returned - prev_cons
                        $opening_val = (float)($prev['opening'] ?? 0) + (float)($prev['received'] ?? 0) + (float)($prev['borrowed'] ?? 0) - (float)($prev['returned'] ?? 0);
                    }

                    $received_val = isset($cur['received']) ? (float)$cur['received'] : 0.0;
                    $borrowed_val = isset($cur['borrowed']) ? (float)$cur['borrowed'] : 0.0;
                    $returned_val = isset($cur['returned']) ? (float)$cur['returned'] : 0.0;
                    $consumption_val = (float)($grain_consumptions[$gid] ?? 0.0);

                    $closing_val = $opening_val + $received_val + $borrowed_val - $returned_val - $consumption_val;
                ?>
                <tr class="hover:bg-slate-50/80 transition" data-gid="<?= $gid ?>">
                    <td class="border border-slate-300 p-2 text-center font-bold text-slate-500"><?= $gid ?></td>
                    <td class="border border-slate-300 p-2 font-semibold text-slate-800">
                        <input type="hidden" name="grain_id[]" value="<?= $gid ?>">
                        <?= e($c['name_mr']) ?>
                        <span class="text-[10px] text-slate-400 block"><?= e($c['name_en']) ?></span>
                    </td>
                    <td class="border border-slate-300 p-2 text-center font-semibold text-slate-600">कि.ग्रॅ.</td>
                    
                    <!-- 1) Opening -->
                    <td class="border border-slate-300 p-1.5 text-center bg-amber-50/30">
                        <input type="number" step="0.001" min="0" name="opening[]" value="<?= $opening_val > 0 ? number_format($opening_val, 3, '.', '') : '0.000' ?>" 
                               class="val-open w-24 text-center rounded border border-slate-300 px-2 py-1 text-xs focus:ring-1 focus:ring-amber-500"
                               oninput="calcStock(<?= $gid ?>)">
                    </td>

                    <!-- 2) Received from Supplier -->
                    <td class="border border-slate-300 p-1.5 text-center bg-blue-50/30">
                        <input type="number" step="0.001" min="0" name="received[]" value="<?= $received_val > 0 ? number_format($received_val, 3, '.', '') : '0.000' ?>" 
                               class="val-rec w-24 text-center rounded border border-slate-300 px-2 py-1 text-xs focus:ring-1 focus:ring-blue-500 font-semibold text-blue-700"
                               oninput="calcStock(<?= $gid ?>)">
                    </td>

                    <!-- 3) Borrowed / Public Contribution -->
                    <td class="border border-slate-300 p-1.5 text-center bg-emerald-50/30">
                        <input type="number" step="0.001" min="0" name="borrowed[]" value="<?= $borrowed_val > 0 ? number_format($borrowed_val, 3, '.', '') : '0.000' ?>" 
                               class="val-bor w-24 text-center rounded border border-slate-300 px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500 font-semibold text-emerald-700"
                               oninput="calcStock(<?= $gid ?>)">
                    </td>

                    <!-- 4) Returned / Spoiled -->
                    <td class="border border-slate-300 p-1.5 text-center bg-red-50/30">
                        <input type="number" step="0.001" min="0" name="returned[]" value="<?= $returned_val > 0 ? number_format($returned_val, 3, '.', '') : '0.000' ?>" 
                               class="val-ret w-24 text-center rounded border border-slate-300 px-2 py-1 text-xs focus:ring-1 focus:ring-red-500 text-red-600"
                               oninput="calcStock(<?= $gid ?>)">
                    </td>

                    <!-- 5) Consumed -->
                    <td class="border border-slate-300 p-2 text-center font-bold text-slate-700 bg-slate-50">
                        <span class="val-cons"><?= number_format($consumption_val, 3) ?></span>
                    </td>

                    <!-- 6) Closing -->
                    <td class="border border-slate-300 p-2 text-center bg-teal-50/50 font-bold text-navy">
                        <span class="val-close text-xs"><?= number_format($closing_val, 3) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-4 pt-4 border-t">
        <p class="text-xs text-slate-500">
            * <strong>अखेर शिल्लक</strong> = (मागील शिल्लक + पुरवठादार प्राप्त + लोकसहभाग/उसनवार) - उसनवार परत - चालू उपभोग.
        </p>
        <button type="submit" class="rounded-xl bg-gold text-navy font-bold px-8 py-3 hover:brightness-105 transition shadow text-base flex items-center gap-2">
            <span>💾</span> साठा माहिती जतन करा
        </button>
    </div>
</form>

<script>
function calcStock(gid) {
    const row = document.querySelector(`tr[data-gid="${gid}"]`);
    if (!row) return;

    const op = parseFloat(row.querySelector('.val-open').value) || 0;
    const rec = parseFloat(row.querySelector('.val-rec').value) || 0;
    const bor = parseFloat(row.querySelector('.val-bor').value) || 0;
    const ret = parseFloat(row.querySelector('.val-ret').value) || 0;
    const cons = parseFloat(row.querySelector('.val-cons').textContent) || 0;

    const closing = (op + rec + bor) - ret - cons;
    const closeSpan = row.querySelector('.val-close');
    closeSpan.textContent = closing.toFixed(3);

    if (closing < 0) {
        closeSpan.className = 'val-close text-xs font-bold text-red-600';
    } else {
        closeSpan.className = 'val-close text-xs font-bold text-navy';
    }
}
</script>

<?php layout_end(); ?>
