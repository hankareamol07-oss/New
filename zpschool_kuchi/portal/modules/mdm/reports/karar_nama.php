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

// Fetch cooks from mdm_cooks
$cooks_stmt = $pdo->prepare('SELECT * FROM mdm_cooks WHERE school_id = ? ORDER BY id ASC');
$cooks_stmt->execute([$sid]);
$cooks = $cooks_stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_cook_id = (int) ($_GET['cook_id'] ?? ($cooks[0]['id'] ?? 0));
$current_cook = null;
foreach ($cooks as $ck) {
    if ((int)$ck['id'] === $selected_cook_id) {
        $current_cook = $ck;
        break;
    }
}
if (!$current_cook && !empty($cooks)) {
    $current_cook = $cooks[0];
}

$cook_name = $current_cook['name'] ?? 'श्रीमती / श्री ........................................';
$cook_phone = $current_cook['phone'] ?? '....................';
$cook_aadhaar = $current_cook['aadhaar'] ?? '........................';
$cook_role = ($current_cook['type'] ?? 'cook') === 'helper' ? 'मदतनीस' : 'मुख्य स्वयंपाकी';
$honorarium = (int) ($current_cook['monthly_pay'] ?? 2500);

$year_label = (date('m') >= 4 ? date('Y') : date('Y') - 1) . ' - ' . (date('m') >= 4 ? date('Y') + 1 : date('Y'));

mdm_layout_start('स्वयंपाकी / मदतनीस करारनामा — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4 no-print">
    <div>
        <h2 class="text-2xl font-bold text-navy">स्वयंपाकी / मदतनीस नियुक्ती करारनामा</h2>
        <p class="text-sm text-slate-500">प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना अंतर्गत शाळा व्यवस्थापन समिती व स्वयंपाकी करार</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-gold text-navy font-bold px-5 py-2.5 hover:brightness-105 transition shadow text-sm">
            <span>🖨️</span> करारनामा प्रिंट करा (A4)
        </button>
    </div>
</div>

<!-- Selector Form -->
<?php if (!empty($cooks)): ?>
<form method="get" class="bg-white border rounded-2xl p-4 mb-6 shadow-sm no-print flex items-center gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">स्वयंपाकी / मदतनीस निवडा</label>
        <select name="cook_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
            <?php foreach ($cooks as $ck): ?>
                <option value="<?= $ck['id'] ?>" <?= (int)$ck['id'] === (int)$current_cook['id'] ? 'selected' : '' ?>>
                    <?= e($ck['name']) ?> (<?= ($ck['type'] ?? '') === 'helper' ? 'मदतनीस' : 'स्वयंपाकी' ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>
<?php endif; ?>

<!-- Agreement Document (Printable A4) -->
<div class="bg-white border rounded-2xl p-8 sm:p-12 shadow-sm max-w-4xl mx-auto print:border-none print:p-0 print:shadow-none text-slate-800 leading-relaxed text-sm">
    
    <div class="text-center pb-4 border-b-2 border-slate-800 mb-6">
        <h3 class="text-xl font-bold text-navy uppercase tracking-wide">शाळा व्यवस्थापन समिती व स्वयंपाकी / मदतनीस करारनामा</h3>
        <h4 class="text-sm font-semibold text-slate-700 mt-1">प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना - शैक्षणिक वर्ष <?= e($year_label) ?></h4>
        <p class="text-xs text-slate-600 mt-1">शाळेचे नाव: <strong><?= e($school_name) ?></strong> | UDISE क्र: <strong><?= e($udise) ?></strong> | केंद्र: <strong><?= e($kendra) ?></strong> | ता. <strong><?= e($taluka) ?></strong>, जि. <strong><?= e($district) ?></strong></p>
    </div>

    <div class="space-y-4 text-justify">
        <p>
            आज दिनांक ................. रोजी <strong>शाळा व्यवस्थापन समिती, <?= e($school_name) ?></strong> (प्रथम पक्षकार) आणि 
            <strong><?= e($cook_name) ?></strong>, पद: <strong><?= e($cook_role) ?></strong>, आधार क्रमांक: <?= e($cook_aadhaar) ?>, 
            मोबाईल: <?= e($cook_phone) ?> (द्वितीय पक्षकार) यांच्यामध्ये खालील अटी व शर्तींनुसार हा करारनामा करण्यात येत आहे.
        </p>

        <h4 class="font-bold text-slate-900 mt-4 text-sm underline">करारनाम्यातील अटी व शर्ती:</h4>

        <ol class="list-decimal list-inside space-y-2 text-xs sm:text-sm pl-2">
            <li><strong>कामाचे स्वरूप व वेळ:</strong> द्वितीय पक्षकार शाळेच्या कामकाजाच्या प्रत्येक दिवशी सकाळी वेळेत उपस्थित राहून शासनाने ठरवून दिलेल्या मेनू व प्रमाणकानुसार विद्यार्थ्यांना वेळेवर ताजा, गरम व सकस पोषण आहार तयार करून देतील.</li>
            <li><strong>स्वच्छता व आरोग्य:</strong> स्वयंपाकगृह, भांडी, धान्य व पिण्याचे पाणी स्वच्छ ठेवण्याची जबाबदारी द्वितीय पक्षकाराची राहील. नखे कापणे, स्वच्छ कपडे घालणे व हात धुवूनच काम करणे बंधनकारक राहील.</li>
            <li><strong>चव रजिस्टर नोंद:</strong> आहार विद्यार्थ्यांना वाटप करण्यापूर्वी अर्धा तास आधी मुख्याध्यापक / शिक्षक किंवा शाळा व्यवस्थापन समिती सदस्यास आहाराची चव देणे व चव नोंदवहीत नोंद घेणे आवश्यक राहील.</li>
            <li><strong>धान्य साठवणूक:</strong> शाळेत उपलब्ध धान्याची नासाडी होणार नाही तसेच किडे, उंदीर यांपासून धान्याचे रक्षण होईल याची दक्षता घ्यावी.</li>
            <li><strong>मानधन:</strong> द्वितीय पक्षकारास शासनाने वेळोवेळी निर्धारित केल्यानुसार दरमहा <strong>₹ <?= number_format($honorarium) ?>/-</strong> (अक्षरी रुपये <?= $honorarium === 2500 ? 'दोन हजार पाचशे' : ($honorarium === 3000 ? 'तीन हजार' : number_format($honorarium)) ?> फक्त) मानधन थेट बँक खात्यात शासनाकडून अनुदान प्राप्त होताच अदा केले जाईल.</li>
            <li><strong>हजेरी व गैरहजेरी:</strong> अपरिहार्य कारणास्तव गैरहजर राहावयाचे असल्यास मुख्याध्यापकांची पूर्वपरवानगी घेऊन पर्यायी व्यवस्था करणे आवश्यक राहील. विनापरवानगी गैरहजर राहिल्यास कामावरून कमी करण्याचा अधिकार समितीस राहील.</li>
            <li><strong>कालावधी:</strong> हा करारनामा केवळ शैक्षणिक वर्ष <?= e($year_label) ?> पुरता मर्यादित राहील. या नियुक्तीमुळे नियमित सेवेचा किंवा शासकीय सेवेचा कोणताही हक्क प्राप्त होणार नाही.</li>
        </ol>

        <p class="pt-2">
            वरील सर्व अटी व शर्ती दोन्ही पक्षकारांनी काळजीपूर्वक वाचून व समजून घेऊन कोणत्याही दबावाशिवाय स्वखुशीने स्वाक्षरी केली आहे.
        </p>
    </div>

    <!-- Signatures -->
    <div class="mt-12 pt-8 grid grid-cols-2 gap-8 text-xs font-bold text-slate-800">
        <div class="space-y-12">
            <div>
                <p class="h-10"></p>
                <p>स्वाक्षरी: ........................................</p>
                <p>द्वितीय पक्षकार (स्वयंपाकी / मदतनीस)</p>
                <p>नाव: <?= e($cook_name) ?></p>
            </div>
            <div>
                <p class="h-10"></p>
                <p>साक्षीदार १: ........................................</p>
                <p>नाव व पत्ता: ........................................</p>
            </div>
        </div>

        <div class="space-y-12 text-right">
            <div>
                <p class="h-10"></p>
                <p>स्वाक्षरी: ........................................</p>
                <p>प्रथम पक्षकार (अध्यक्ष / सचिव, SMC)</p>
                <p><?= e($school_name) ?></p>
            </div>
            <div>
                <p class="h-10"></p>
                <p>साक्षीदार २: ........................................</p>
                <p>नाव व पत्ता: ........................................</p>
            </div>
        </div>
    </div>

</div>

<?php layout_end(); ?>
