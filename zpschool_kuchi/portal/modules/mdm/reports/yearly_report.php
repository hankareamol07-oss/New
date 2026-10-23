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

$start_year = (int) ($_GET['start_year'] ?? (date('m') >= 4 ? date('Y') : date('Y') - 1));
$end_year = $start_year + 1;

// Stats for 1-5 and 6-8
$stats = [];
foreach (['1-5', '6-8'] as $grp) {
    $date_start = "{$start_year}-04-01";
    $date_end = "{$end_year}-03-31";
    $stmt = $pdo->prepare('SELECT COUNT(*) served_days, SUM(beneficiaries) tot_ben 
                           FROM mdm_daily 
                           WHERE school_id = ? AND class_group = ? AND date BETWEEN ? AND ? AND cooked = 1');
    $stmt->execute([$sid, $grp, $date_start, $date_end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $ben = (int)($row['tot_ben'] ?? 0);
    $norm = $grp === '6-8' ? 0.150 : 0.100;
    $rate = mdm_cost_rate($grp, $sid);

    // Fetch pat from mdm_pat_history
    $p_stmt = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id=? AND class_group=? ORDER BY year DESC, month DESC LIMIT 1');
    $p_stmt->execute([$sid, $grp]);
    $pat_val = (int)($p_stmt->fetchColumn() ?: 0);

    $stats[$grp] = [
        'served_days' => (int)($row['served_days'] ?? 0),
        'beneficiaries' => $ben,
        'max_pat' => $pat_val,
        'avg_pat' => $pat_val,
        'rice_kg' => round($ben * $norm, 3),
        'grant_exp' => round($ben * $rate, 2),
    ];
}

// Cooks list
$cooks = $pdo->prepare('SELECT * FROM mdm_cooks WHERE school_id = ?');
$cooks->execute([$sid]);
$cooks_list = $cooks->fetchAll(PDO::FETCH_ASSOC);

mdm_layout_start('सर्वसमावेशक वार्षिक अहवाल — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4 no-print">
    <div>
        <h2 class="text-2xl font-bold text-navy">शालेय पोषण आहार वार्षिक परिपूर्ण अहवाल</h2>
        <p class="text-sm text-slate-500">शैक्षणिक वर्ष <?= $start_year ?>-<?= $end_year ?> सर्वंकष मूल्यमापन व प्रगती अहवाल</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= e(url('modules/mdm/reports/annual_cert.php')) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-teal text-white px-3.5 py-2 font-semibold hover:bg-teal/90 transition text-xs">
            <span>📜</span> उपयोगिता प्रमाणपत्र (UC)
        </a>
        <a href="<?= e(url('modules/mdm/reports/annual_summary.php')) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-navy text-white px-3.5 py-2 font-semibold hover:bg-navy/90 transition text-xs">
            <span>📊</span> १२ महिने सारांश
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl bg-gold text-navy font-bold px-4 py-2 hover:brightness-105 transition text-xs">
            <span>🖨️</span> प्रिंट (A4)
        </button>
    </div>
</div>

<div class="bg-white border rounded-2xl p-8 shadow-sm print:border-none print:p-0 print:shadow-none space-y-6">
    <div class="text-center pb-4 border-b">
        <h3 class="text-xl font-bold text-navy">प्रधानमंत्री पोषण शक्ती निर्माण योजना</h3>
        <h4 class="text-base font-semibold text-slate-700 mt-1">शाळा स्तरावरील वार्षिक परिपूर्ण अहवाल (Annual Report)</h4>
        <p class="text-xs text-slate-500 mt-1">शैक्षणिक वर्ष <?= $start_year ?> - <?= $end_year ?></p>
        <div class="flex flex-wrap justify-between text-xs text-slate-600 mt-3 px-2">
            <span><strong>शाळा:</strong> <?= e($school_name) ?></span>
            <span><strong>UDISE:</strong> <?= e($udise) ?></span>
            <span><strong>केंद्र:</strong> <?= e($kendra) ?></span>
            <span><strong>तालुका:</strong> <?= e($taluka) ?></span>
            <span><strong>जिल्हा:</strong> <?= e($district) ?></span>
        </div>
    </div>

    <!-- Section 1: Key Performance Indicators -->
    <div>
        <h4 class="text-sm font-bold text-navy border-b pb-2 mb-3">१. योजना अंमलबजावणी ठळक बाबी</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="border rounded-xl p-4 bg-slate-50">
                <h5 class="font-bold text-slate-800 text-xs mb-2">प्राथमिक विभाग (इयत्ता १ ली ते ५ वी)</h5>
                <ul class="text-xs space-y-1.5 text-slate-600">
                    <li>• आहार दिलेले दिवस: <strong class="text-slate-800"><?= $stats['1-5']['served_days'] ?> दिवस</strong></li>
                    <li>• एकूण लाभार्थी संख्या: <strong class="text-slate-800"><?= number_format($stats['1-5']['beneficiaries']) ?></strong></li>
                    <li>• सरासरी उपस्थिती: <strong class="text-slate-800"><?= $stats['1-5']['avg_pat'] ?> विद्यार्थी</strong></li>
                    <li>• एकूण शिजवलेला तांदूळ: <strong class="text-slate-800"><?= number_format($stats['1-5']['rice_kg'], 3) ?> kg</strong></li>
                    <li>• भाजीपाला/इंधन खर्च: <strong class="text-navy">₹ <?= number_format($stats['1-5']['grant_exp'], 2) ?></strong></li>
                </ul>
            </div>
            <div class="border rounded-xl p-4 bg-slate-50">
                <h5 class="font-bold text-slate-800 text-xs mb-2">उच्च प्राथमिक विभाग (इयत्ता ६ वी ते ८ वी)</h5>
                <ul class="text-xs space-y-1.5 text-slate-600">
                    <li>• आहार दिलेले दिवस: <strong class="text-slate-800"><?= $stats['6-8']['served_days'] ?> दिवस</strong></li>
                    <li>• एकूण लाभार्थी संख्या: <strong class="text-slate-800"><?= number_format($stats['6-8']['beneficiaries']) ?></strong></li>
                    <li>• सरासरी उपस्थिती: <strong class="text-slate-800"><?= $stats['6-8']['avg_pat'] ?> विद्यार्थी</strong></li>
                    <li>• एकूण शिजवलेला तांदूळ: <strong class="text-slate-800"><?= number_format($stats['6-8']['rice_kg'], 3) ?> kg</strong></li>
                    <li>• भाजीपाला/इंधन खर्च: <strong class="text-navy">₹ <?= number_format($stats['6-8']['grant_exp'], 2) ?></strong></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Section 2: Cooks -->
    <div>
        <h4 class="text-sm font-bold text-navy border-b pb-2 mb-3">२. स्वयंपाकी / मदतनीस तपशील</h4>
        <table class="w-full text-xs text-left border-collapse border border-slate-300">
            <thead>
                <tr class="bg-slate-100 font-bold text-slate-700">
                    <th class="border border-slate-300 p-2">अ.क्र.</th>
                    <th class="border border-slate-300 p-2">नाव</th>
                    <th class="border border-slate-300 p-2">पद</th>
                    <th class="border border-slate-300 p-2">मोबाईल क्र.</th>
                    <th class="border border-slate-300 p-2">आधार क्र.</th>
                    <th class="border border-slate-300 p-2">मासिक मानधन</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cooks_list)): ?>
                    <tr><td colspan="6" class="border border-slate-300 p-3 text-center text-slate-400">स्वयंपाकी नोंद आढळली नाही.</td></tr>
                <?php else: foreach ($cooks_list as $i => $ck): ?>
                    <tr>
                        <td class="border border-slate-300 p-2 text-center"><?= $i + 1 ?></td>
                        <td class="border border-slate-300 p-2 font-semibold"><?= e($ck['name']) ?></td>
                        <td class="border border-slate-300 p-2"><?= ($ck['type'] ?? '') === 'helper' ? 'मदतनीस' : 'स्वयंपाकी' ?></td>
                        <td class="border border-slate-300 p-2"><?= e($ck['phone'] ?? '-') ?></td>
                        <td class="border border-slate-300 p-2"><?= e($ck['aadhaar'] ?? '-') ?></td>
                        <td class="border border-slate-300 p-2 font-bold text-navy">₹ <?= number_format((float)($ck['monthly_pay'] ?? 2500)) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Section 3: Compliance & Health -->
    <div>
        <h4 class="text-sm font-bold text-navy border-b pb-2 mb-3">३. गुणवत्ता, सुरक्षितता व आरोग्य तपासणी</h4>
        <div class="text-xs space-y-2 text-slate-700">
            <p>✔ <strong>चव रजिस्टर:</strong> प्रतिदिन आहार वाटपापूर्वी अर्धा तास आधी मुख्याध्यापक / शिक्षक व पालकांकडून चव घेऊन नोंद ठेवण्यात आली आहे.</p>
            <p>✔ <strong>BMI तपासणी:</strong> विद्यार्थ्यांची जून, सप्टेंबर, डिसेंबर व मार्च अशा ४ त्रैमासिकांमध्ये नियमित उंची व वजन घेऊन BMI नोंद ठेवण्यात आली आहे.</p>
            <p>✔ <strong>पिण्याचे पाणी व स्वयंपाकघर स्वच्छता:</strong> स्वयंपाकगृह दररोज स्वच्छ केले जात असून निर्जंतुक पिण्याच्या पाण्याची व्यवस्था करण्यात आलेली आहे.</p>
            <p>✔ <strong>साठा हिशोब:</strong> पुरवठादार प्राप्त धान्य व लोकसहभाग साठा नोंदवही अद्ययावत ठेवलेली आहे.</p>
        </div>
    </div>

    <div class="mt-8 pt-8 flex justify-between text-xs font-bold text-slate-700 print:mt-16">
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
