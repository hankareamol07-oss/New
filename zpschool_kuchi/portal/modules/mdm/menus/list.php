<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_role(['admin', 'principal', 'clerk', 'teacher']);
$sid = (int) $u['school_id'];
$pdo = db();

mdm_ensure_seeded($pdo, $sid);

$editId = (int) ($_GET['edit'] ?? 0);

// Handle Default Seed Button
if (isset($_POST['seed_default']) && valid_csrf($_POST['csrf'] ?? null)) {
    // Delete existing and re-seed
    $defaultMenus = [
        1 => ['name' => 'व्हेजीटेबल पुलाव', 'weekday' => 1, 'pulse_id' => 2], // Mon
        2 => ['name' => 'मसाले भात', 'weekday' => 2, 'pulse_id' => 3],       // Tue
        3 => ['name' => 'मटार पुलाव', 'weekday' => 3, 'pulse_id' => 9],        // Wed
        4 => ['name' => 'मुगडाळ खिचडी', 'weekday' => 4, 'pulse_id' => 2],      // Thu
        5 => ['name' => 'चवळी उसळ भात', 'weekday' => 5, 'pulse_id' => 7],      // Fri
        6 => ['name' => 'हरभरा पुलाव', 'weekday' => 6, 'pulse_id' => 8],       // Sat
    ];
    $insMenu = $pdo->prepare('INSERT INTO mdm_menus (school_id, name, weekday, active) VALUES (?,?,?,1)');
    $insItem = $pdo->prepare('INSERT INTO mdm_menu_items (menu_id, grain_id, qty_1to5, qty_6to8) VALUES (?,?,?,?)');

    foreach ($defaultMenus as $m) {
        $chk = $pdo->prepare('SELECT id FROM mdm_menus WHERE school_id=? AND weekday=? LIMIT 1');
        $chk->execute([$sid, $m['weekday']]);
        $existing_mid = (int) $chk->fetchColumn();

        if (!$existing_mid) {
            $insMenu->execute([$sid, $m['name'], $m['weekday']]);
            $mid = (int)$pdo->lastInsertId();
            $insItem->execute([$mid, 1, 0.100, 0.150]); // Rice
            $insItem->execute([$mid, $m['pulse_id'], 0.020, 0.030]); // Pulse
            $insItem->execute([$mid, 14, 0.005, 0.0075]); // Oil
            $insItem->execute([$mid, 15, 0.001, 0.0015]); // Salt
            $insItem->execute([$mid, 12, 0.0001, 0.0002]); // Haldi
            $insItem->execute([$mid, 13, 0.0015, 0.0025]); // Masala
            $insItem->execute([$mid, 10, 0.00005, 0.0001]); // Jeera
            $insItem->execute([$mid, 11, 0.0001, 0.0001]); // Mohari
        }
    }
    flash_set('success', 'शासकीय मानक साप्ताहिक मेनू यशस्वीरित्या लोड केले.');
    header('Location: ' . url('modules/mdm/menus/list.php'));
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null) && !isset($_POST['seed_default'])) {
    $menuId = (int) ($_POST['menu_id'] ?? 0);
    $name = trim((string) $_POST['name']);
    $weekday = (int) $_POST['weekday'];
    $active = isset($_POST['active']) ? 1 : 0;

    if ($menuId <= 0) {
        $pdo->prepare('INSERT INTO mdm_menus (school_id, name, weekday, active) VALUES (?, ?, ?, ?)')
            ->execute([$sid, $name, $weekday, $active]);
        $menuId = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare('UPDATE mdm_menus SET name=?, weekday=?, active=? WHERE id=? AND school_id=?')
            ->execute([$name, $weekday, $active, $menuId, $sid]);
        $pdo->prepare('DELETE FROM mdm_menu_items WHERE menu_id=?')->execute([$menuId]);
    }

    $p = $pdo->prepare('INSERT INTO mdm_menu_items (menu_id, grain_id, qty_1to5, qty_6to8) VALUES (?, ?, ?, ?)');
    foreach ((array) ($_POST['grain_id'] ?? []) as $i => $grain) {
        $q1 = (float) ($_POST['qty_1to5'][$i] ?? 0);
        $q2 = (float) ($_POST['qty_6to8'][$i] ?? 0);
        if ($q1 > 0 || $q2 > 0) {
            $p->execute([$menuId, (int)$grain, $q1, $q2]);
        }
    }

    flash_set('success', "मेनू '{$name}' यशस्वीरित्या जतन झाला.");
    header('Location: ' . url('modules/mdm/menus/list.php'));
    exit;
}

// Fetch menus
$menus = $pdo->prepare('SELECT * FROM mdm_menus WHERE school_id=? ORDER BY weekday, id');
$menus->execute([$sid]);
$menus = $menus->fetchAll(PDO::FETCH_ASSOC);

// Fetch grains (21 commodities)
$grains = mdm_official_commodities();

$selected = [];
$menu = null;
if ($editId) {
    $q = $pdo->prepare('SELECT * FROM mdm_menus WHERE id=? AND school_id=?');
    $q->execute([$editId, $sid]);
    $menu = $q->fetch(PDO::FETCH_ASSOC);

    $q2 = $pdo->prepare('SELECT * FROM mdm_menu_items WHERE menu_id=?');
    $q2->execute([$editId]);
    foreach ($q2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $selected[(int)$r['grain_id']] = $r;
    }
}

$weekday_map = [
    1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार', 7 => 'रविवार'
];

mdm_layout_start('आहार मेनू व्यवस्थापन — पोषण आहार');
?>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-xl sm:text-2xl font-bold text-navy">शालेय पोषण आहार मेनू व्यवस्थापन</h2>
        <p class="text-xs sm:text-sm text-slate-500">वारनिहाय मेनू व प्रति विद्यार्थी धान्यादी प्रमाणके (kg)</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="post" class="inline" onsubmit="return confirm('शासनाचे प्रमाणित ६ साप्ताहिक मेनू लोड करायचे आहेत का?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="seed_default" value="1">
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-teal text-white px-3.5 py-2 font-semibold hover:bg-teal/90 transition text-xs sm:text-sm shadow-sm">
                <span>🔄</span> मानक मेनू लोड करा
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- Menus List (5 cols) -->
    <div class="lg:col-span-5 space-y-4">
        <div class="bg-white border rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b mb-4">
                <h3 class="font-bold text-navy text-base flex items-center gap-2">
                    <span>📋</span> चालू मेनू यादी
                </h3>
                <a href="<?= e(url('modules/mdm/menus/list.php')) ?>" class="text-xs text-teal font-bold hover:underline">
                    + नवीन मेनू जोडा
                </a>
            </div>

            <div class="space-y-3">
                <?php if (empty($menus)): ?>
                    <p class="text-xs text-slate-400 py-4 text-center">कोणतेही मेनू आढळले नाहीत. वरील 'मानक मेनू लोड करा' बटनावर क्लिक करा.</p>
                <?php else: foreach ($menus as $m): 
                    $wday_name = $weekday_map[(int)$m['weekday']] ?? 'वार ' . $m['weekday'];
                    $is_editing = (int)($menu['id'] ?? 0) === (int)$m['id'];
                ?>
                <div class="p-3.5 rounded-xl border <?= $is_editing ? 'border-teal bg-teal-50/40 ring-1 ring-teal' : 'border-slate-200 bg-white hover:border-slate-300' ?> flex items-center justify-between gap-3 transition">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-navy text-white">
                                <?= e($wday_name) ?>
                            </span>
                            <span class="font-bold text-navy text-sm"><?= e($m['name']) ?></span>
                        </div>
                        <span class="text-[11px] <?= $m['active'] ? 'text-emerald-600' : 'text-slate-400' ?> font-semibold mt-1 block">
                            ● <?= $m['active'] ? 'सक्रिय मेनू' : 'बंद' ?>
                        </span>
                    </div>
                    <a href="?edit=<?= $m['id'] ?>" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-navy font-bold text-xs px-3 py-1.5 transition">
                        ✏️ बदला
                    </a>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Menu Editor Form (7 cols) -->
    <div class="lg:col-span-7">
        <form method="post" class="bg-white border rounded-2xl p-6 shadow-sm space-y-4">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="menu_id" value="<?= e((string)($menu['id'] ?? 0)) ?>">

            <div class="pb-3 border-b flex items-center justify-between">
                <h3 class="font-bold text-navy text-base flex items-center gap-2">
                    <span><?= $menu ? '✏️ मेनू बदला / साहित्य तपशील' : '➕ नवीन मेनू तयार करा' ?></span>
                </h3>
                <?php if ($menu): ?>
                    <a href="<?= e(url('modules/mdm/menus/list.php')) ?>" class="text-xs text-slate-500 hover:underline">रद्द करा</a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">मेनूचे नाव</label>
                    <input type="text" name="name" required value="<?= e($menu['name'] ?? '') ?>" placeholder="उदा. व्हेजीटेबल पुलाव" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">वार निवडा</label>
                    <select name="weekday" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-white">
                        <?php foreach ($weekday_map as $w_num => $w_name): ?>
                            <option value="<?= $w_num ?>" <?= (int)($menu['weekday'] ?? 1) === $w_num ? 'selected' : '' ?>><?= e($w_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="active" id="menu_active" <?= !$menu || !empty($menu['active']) ? 'checked' : '' ?> class="rounded text-teal focus:ring-teal">
                <label for="menu_active" class="text-xs font-bold text-slate-700 cursor-pointer">हा मेनू चालू ठेवा (Active)</label>
            </div>

            <div class="pt-3 border-t">
                <h4 class="font-bold text-navy text-xs uppercase tracking-wider mb-2">प्रति विद्यार्थी साहित्य प्रमाणके (kg मध्ये)</h4>
                <p class="text-[11px] text-slate-500 mb-3">या मेनूसाठी वापरल्या जाणाऱ्या वस्तूंचे प्रमाण भरा (इतर वस्तू शून्य राहू द्या)</p>

                <div class="max-h-[340px] overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100">
                    <div class="bg-slate-100 grid grid-cols-12 p-2.5 text-[11px] font-bold text-slate-700 uppercase sticky top-0">
                        <div class="col-span-6">धान्यादी वस्तू</div>
                        <div class="col-span-3 text-center">इ. १ ते ५ (kg)</div>
                        <div class="col-span-3 text-center">इ. ६ ते ८ (kg)</div>
                    </div>
                    <?php foreach ($grains as $gid => $g): 
                        $sel_row = $selected[$gid] ?? [];
                        $q1 = isset($sel_row['qty_1to5']) ? (float)$sel_row['qty_1to5'] : 0.0;
                        $q2 = isset($sel_row['qty_6to8']) ? (float)$sel_row['qty_6to8'] : 0.0;
                        
                        // Default suggestion for Rice and major pulses
                        if (!$menu && $gid === 1) { $q1 = 0.100; $q2 = 0.150; }
                    ?>
                    <div class="grid grid-cols-12 p-2 items-center text-xs hover:bg-slate-50">
                        <input type="hidden" name="grain_id[]" value="<?= $gid ?>">
                        <div class="col-span-6 font-semibold text-slate-800">
                            <?= e($g['name_mr']) ?>
                            <span class="text-[10px] text-slate-400 block"><?= e($g['name_en']) ?></span>
                        </div>
                        <div class="col-span-3 px-1 text-center">
                            <input type="number" step="0.00001" min="0" name="qty_1to5[]" value="<?= $q1 > 0 ? $q1 : '' ?>" placeholder="0" 
                                   class="w-full text-center rounded border border-slate-300 px-1 py-1 text-xs focus:ring-1 focus:ring-teal">
                        </div>
                        <div class="col-span-3 px-1 text-center">
                            <input type="number" step="0.00001" min="0" name="qty_6to8[]" value="<?= $q2 > 0 ? $q2 : '' ?>" placeholder="0" 
                                   class="w-full text-center rounded border border-slate-300 px-1 py-1 text-xs focus:ring-1 focus:ring-teal">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-3 border-t flex justify-end">
                <button type="submit" class="rounded-xl bg-gold text-navy font-bold px-8 py-2.5 hover:brightness-105 transition shadow text-sm flex items-center gap-1.5">
                    <span>💾</span> मेनू तपशील जतन करा
                </button>
            </div>
        </form>
    </div>

</div>

<?php layout_end(); ?>
