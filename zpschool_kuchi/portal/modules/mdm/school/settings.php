<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_role(['admin', 'principal', 'clerk', 'teacher']);
$sid = (int) $u['school_id'];
$pdo = db();

$school = school_row($sid) ?: [];
$cur_month = (int) ($_GET['month'] ?? date('n'));
$cur_year = (int) ($_GET['year'] ?? date('Y'));

// Handle Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? 'save_pat');

    if ($action === 'save_pat') {
        $pat1to5 = max(0, (int) ($_POST['pat_1to5'] ?? 0));
        $pat6to8 = max(0, (int) ($_POST['pat_6to8'] ?? 0));
        $target_month = (int) ($_POST['month'] ?? $cur_month);
        $target_year = (int) ($_POST['year'] ?? $cur_year);
        $apply_all = !empty($_POST['apply_all_year']);

        $stmt = $pdo->prepare('REPLACE INTO mdm_pat_history (school_id, class_group, month, year, enrolled, present_days) VALUES (?, ?, ?, ?, ?, 0)');

        // Save for specified month
        $stmt->execute([$sid, '1-5', $target_month, $target_year, $pat1to5]);
        $stmt->execute([$sid, '6-8', $target_month, $target_year, $pat6to8]);

        // Always save as base/default pat (month = 0)
        $stmt->execute([$sid, '1-5', 0, $target_year, $pat1to5]);
        $stmt->execute([$sid, '6-8', 0, $target_year, $pat6to8]);

        // If apply to all 12 academic months (April to March)
        if ($apply_all) {
            $start_y = $target_month >= 4 ? $target_year : $target_year - 1;
            $end_y = $start_y + 1;
            // Months 4 to 12
            for ($m = 4; $m <= 12; $m++) {
                $stmt->execute([$sid, '1-5', $m, $start_y, $pat1to5]);
                $stmt->execute([$sid, '6-8', $m, $start_y, $pat6to8]);
            }
            // Months 1 to 3
            for ($m = 1; $m <= 3; $m++) {
                $stmt->execute([$sid, '1-5', $m, $end_y, $pat1to5]);
                $stmt->execute([$sid, '6-8', $m, $end_y, $pat6to8]);
            }
            flash_set('success', "इयत्ता १ ते ५ (पट: {$pat1to5}) व इयत्ता ६ ते ८ (पट: {$pat6to8}) संपूर्ण शैक्षणिक वर्षासाठी यशस्वीरित्या जतन केला.");
        } else {
            flash_set('success', "माहे " . mdm_marathi_months()[$target_month] . " {$target_year} साठी पटसंख्या (इ. १ ते ५: {$pat1to5}, इ. ६ ते ८: {$pat6to8}) जतन केली.");
        }
    } elseif ($action === 'save_school_info') {
        $p = $pdo->prepare('UPDATE schools SET name=?, name_en=?, udise=?, taluka=?, district=? WHERE id=?');
        $p->execute([
            trim((string)$_POST['name']),
            trim((string)($_POST['name_en'] ?? '')),
            trim((string)$_POST['udise']),
            trim((string)$_POST['taluka']),
            trim((string)$_POST['district']),
            $sid
        ]);
        flash_set('success', 'शाळा माहिती जतन झाली.');
        $school = school_row($sid) ?: [];
    }

    header('Location: ' . url("modules/mdm/school/settings.php?month={$cur_month}&year={$cur_year}"));
    exit;
}

// Fetch current pat for 1-5 and 6-8
$p1to5_stmt = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id = ? AND class_group = ? AND month = ? AND year = ?');
$p1to5_stmt->execute([$sid, '1-5', $cur_month, $cur_year]);
$cur_pat_1to5 = (int)($p1to5_stmt->fetchColumn() ?: 0);

$p6to8_stmt = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id = ? AND class_group = ? AND month = ? AND year = ?');
$p6to8_stmt->execute([$sid, '6-8', $cur_month, $cur_year]);
$cur_pat_6to8 = (int)($p6to8_stmt->fetchColumn() ?: 0);

// If current month is zero, check base pat (month = 0)
if ($cur_pat_1to5 === 0) {
    $cur_pat_1to5 = (int)$pdo->query("SELECT enrolled FROM mdm_pat_history WHERE school_id = {$sid} AND class_group = '1-5' ORDER BY year DESC, month DESC LIMIT 1")->fetchColumn();
}
if ($cur_pat_6to8 === 0) {
    $cur_pat_6to8 = (int)$pdo->query("SELECT enrolled FROM mdm_pat_history WHERE school_id = {$sid} AND class_group = '6-8' ORDER BY year DESC, month DESC LIMIT 1")->fetchColumn();
}

// Fetch all recorded pat history
$history_stmt = $pdo->prepare('SELECT month, year, 
                               MAX(CASE WHEN class_group="1-5" THEN enrolled ELSE 0 END) as pat_1to5,
                               MAX(CASE WHEN class_group="6-8" THEN enrolled ELSE 0 END) as pat_6to8
                               FROM mdm_pat_history 
                               WHERE school_id = ? AND month > 0
                               GROUP BY year, month 
                               ORDER BY year DESC, month DESC');
$history_stmt->execute([$sid]);
$pat_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

mdm_layout_start('शाळा पोषण आहार सेटिंग्ज (पटसंख्या) — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-navy">शाळा पोषण आहार सेटिंग्ज (MDM Settings)</h2>
        <p class="text-sm text-slate-500">इयत्ता १ ते ५ आणि ६ ते ८ ची अधिकृत पटसंख्या (केवळ पोषण आहार मॉड्युलसाठी)</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= e(url('modules/mdm/dashboard.php')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 text-slate-700 px-4 py-2 font-semibold hover:bg-slate-200 transition text-sm">
            ← डॅशबोर्डकडे जा
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- Left Column: MDM Pat Settings (7 cols) -->
    <div class="lg:col-span-7 space-y-6">

        <!-- Card: MDM Pat Form -->
        <div class="bg-white border rounded-2xl p-6 shadow-sm border-l-4 border-l-gold">
            <div class="flex items-center justify-between pb-3 border-b mb-5">
                <div>
                    <h3 class="font-bold text-navy text-lg flex items-center gap-2">
                        <span>👥</span> पोषण आहार नियमित पटसंख्या नोंदवा
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">ही पटसंख्या प्रपत्र ब, तांदूळ नोंदवही व धान्य हिशोबात वापरली जाईल</p>
                </div>
                <span class="px-3 py-1 rounded-full bg-gold/20 text-navy font-black text-xs">
                    MDM Module Only
                </span>
            </div>

            <form method="post" class="space-y-5">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_pat">

                <!-- Month & Year Selector -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">महिना निवडा</label>
                        <select name="month" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold bg-white">
                            <?php foreach (mdm_marathi_months() as $m_num => $m_name): ?>
                                <option value="<?= $m_num ?>" <?= $cur_month === $m_num ? 'selected' : '' ?>><?= e($m_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">वर्ष निवडा</label>
                        <input type="number" name="year" value="<?= $cur_year ?>" min="2020" max="2035" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold bg-white">
                    </div>
                </div>

                <!-- 1 to 5 and 6 to 8 Inputs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    
                    <!-- 1 to 5 -->
                    <div class="border-2 border-blue-200 bg-blue-50/40 rounded-2xl p-5 text-center">
                        <span class="inline-block px-3 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-bold mb-2">
                            प्राथमिक विभाग
                        </span>
                        <h4 class="font-bold text-navy text-base mb-1">इयत्ता १ ली ते ५ वी पटसंख्या</h4>
                        <p class="text-[11px] text-slate-500 mb-3">दर विद्यार्थी तांदूळ: १००g | दर: ₹ २.५९</p>
                        
                        <div class="flex items-center justify-center gap-2">
                            <input type="number" min="0" max="5000" name="pat_1to5" value="<?= $cur_pat_1to5 ?>" 
                                   class="w-32 text-center rounded-xl border-2 border-blue-300 px-3 py-2.5 text-2xl font-black text-navy focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <span class="text-sm font-bold text-slate-600">विद्यार्थी</span>
                        </div>
                    </div>

                    <!-- 6 to 8 -->
                    <div class="border-2 border-purple-200 bg-purple-50/40 rounded-2xl p-5 text-center">
                        <span class="inline-block px-3 py-1 rounded-full bg-purple-100 text-purple-800 text-xs font-bold mb-2">
                            उच्च प्राथमिक विभाग
                        </span>
                        <h4 class="font-bold text-navy text-base mb-1">इयत्ता ६ वी ते ८ वी पटसंख्या</h4>
                        <p class="text-[11px] text-slate-500 mb-3">दर विद्यार्थी तांदूळ: १५०g | दर: ₹ ३.८८</p>
                        
                        <div class="flex items-center justify-center gap-2">
                            <input type="number" min="0" max="5000" name="pat_6to8" value="<?= $cur_pat_6to8 ?>" 
                                   class="w-32 text-center rounded-xl border-2 border-purple-300 px-3 py-2.5 text-2xl font-black text-navy focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                            <span class="text-sm font-bold text-slate-600">विद्यार्थी</span>
                        </div>
                    </div>

                </div>

                <!-- Checkbox: Apply to all months -->
                <div class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 flex items-start gap-3">
                    <input type="checkbox" name="apply_all_year" id="apply_all_year" value="1" checked class="mt-0.5 rounded border-amber-400 text-gold focus:ring-gold">
                    <label for="apply_all_year" class="text-xs text-amber-900 font-semibold cursor-pointer">
                        <strong>हेच पट संपूर्ण शैक्षणिक वर्षासाठी (सर्व १२ महिने) लागू करा</strong>
                        <span class="block text-[11px] text-amber-700 font-normal mt-0.5">
                            हे निवडल्यास एप्रिल ते मार्च या सर्व महिन्यांचे प्रपत्र ब व धान्य हिशोब आपोआप या पटसंख्येनुसार तयार होतील.
                        </span>
                    </label>
                </div>

                <!-- Save Button -->
                <button type="submit" class="w-full rounded-xl bg-gold text-navy font-bold py-3 text-base hover:brightness-105 transition shadow-sm flex items-center justify-center gap-2">
                    <span>💾</span> पटसंख्या जतन करा
                </button>
            </form>
        </div>

        <!-- History Table of Pat -->
        <div class="bg-white border rounded-2xl p-6 shadow-sm">
            <h3 class="font-bold text-navy text-base flex items-center justify-between pb-3 border-b mb-4">
                <span>📋 महिनानिहाय नोंदवलेली पटसंख्या इतिहास</span>
                <span class="text-xs text-slate-500"><?= count($pat_history) ?> महिने</span>
            </h3>

            <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-bold border-b">
                            <th class="p-2.5">महिना / वर्ष</th>
                            <th class="p-2.5 text-center bg-blue-50/50">इ. १ ते ५ पट</th>
                            <th class="p-2.5 text-center bg-purple-50/50">इ. ६ ते ८ पट</th>
                            <th class="p-2.5 text-center">एकूण पट</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($pat_history)): ?>
                            <tr><td colspan="4" class="p-4 text-center text-slate-400">अद्याप पटसंख्या नोंदवलेली नाही.</td></tr>
                        <?php else: foreach ($pat_history as $ph): 
                            $tot = (int)$ph['pat_1to5'] + (int)$ph['pat_6to8'];
                        ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-2.5 font-semibold text-slate-800">
                                    <?= e(mdm_marathi_months()[(int)$ph['month']] ?? '') ?> <?= $ph['year'] ?>
                                </td>
                                <td class="p-2.5 text-center font-bold text-blue-700 bg-blue-50/20"><?= $ph['pat_1to5'] ?></td>
                                <td class="p-2.5 text-center font-bold text-purple-700 bg-purple-50/20"><?= $ph['pat_6to8'] ?></td>
                                <td class="p-2.5 text-center font-black text-navy"><?= $tot ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Right Column: School Info for MDM Reports (5 cols) -->
    <div class="lg:col-span-5 space-y-6">

        <div class="bg-white border rounded-2xl p-6 shadow-sm">
            <h3 class="font-bold text-navy text-base pb-3 border-b mb-4 flex items-center gap-2">
                <span>🏫</span> पोषण आहार अहवालासाठी शाळा तपशील
            </h3>

            <form method="post" class="space-y-4 text-xs">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_school_info">

                <div>
                    <label class="block font-bold text-slate-600 mb-1">शाळेचे नाव (मराठी)</label>
                    <input type="text" name="name" value="<?= e((string)($school['name_mr'] ?: ($school['name'] ?? ''))) ?>" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold bg-white">
                </div>

                <div>
                    <label class="block font-bold text-slate-600 mb-1">UDISE क्रमांक</label>
                    <input type="text" name="udise" value="<?= e((string)($school['udise_code'] ?? ($school['udise'] ?? ''))) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">तालुका</label>
                        <input type="text" name="taluka" value="<?= e((string)($school['taluka'] ?? 'खंडाळा')) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">जिल्हा</label>
                        <input type="text" name="district" value="<?= e((string)($school['district'] ?? 'सातारा')) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-white">
                    </div>
                </div>

                <button type="submit" class="w-full rounded-xl bg-navy text-white font-bold py-2.5 text-xs hover:bg-navy/90 transition shadow-sm">
                    शाळा माहिती जतन करा
                </button>
            </form>
        </div>

        <div class="bg-blue-50/70 border border-blue-200 rounded-2xl p-5 text-xs text-blue-900 leading-relaxed">
            <h4 class="font-bold text-sm mb-2 flex items-center gap-1.5">
                <span>ℹ️</span> सूचना व प्रमाणके
            </h4>
            <p class="mb-2">• <strong>इयत्ता १ ते ५:</strong> तांदूळ 100g, डाळी 20g, तेल 5g, प्रति लाभार्थी अनुदान ₹ 2.59</p>
            <p class="mb-2">• <strong>इयत्ता ६ ते ८:</strong> तांदूळ 150g, डाळी 30g, तेल 7.5g, प्रति लाभार्थी अनुदान ₹ 3.88</p>
            <p>• ही पटसंख्या बदलल्यास प्रपत्र ब व सर्व शासकीय अहवाल आपोआप नवीन पटसंख्येनुसार तयार होतात.</p>
        </div>

    </div>

</div>

<?php layout_end(); ?>
