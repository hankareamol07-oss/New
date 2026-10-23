<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/module_helpers.php';
require_login();
$tiers = [['१ – ४९ विद्यार्थी','₹49'],['५० – १९९ विद्यार्थी','₹99'],['२०० – ४९९ विद्यार्थी','₹199'],['५०० – ९९९ विद्यार्थी','₹399'],['१,०००+ विद्यार्थी','₹599']];
layout_start('वार्षिक सदस्यता', 'gr');
?>
<div class="max-w-4xl mx-auto"><div class="bg-white border rounded-2xl p-7"><h2 class="text-2xl font-bold text-navy">वार्षिक सदस्यता</h2><p class="text-slate-600 mt-2">विजेता अकॅडमी — जनरल रजिस्टरसाठी स्थिर शुल्क माहिती.</p><div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-7"><?php foreach ($tiers as [$students,$price]): ?><div class="border rounded-xl p-4"><p class="text-sm text-slate-500"><?= e($students) ?></p><strong class="text-2xl text-navy block mt-2"><?= e($price) ?></strong><span class="text-xs text-teal">वार्षिक</span></div><?php endforeach; ?></div><p class="mt-7 rounded-xl bg-teal/10 text-teal p-4">या आवृत्तीत पेमेंट प्रक्रिया जोडलेली नाही. सदस्यता माहिती फक्त संदर्भासाठी आहे.</p></div></div>
<?php layout_end(); ?>
