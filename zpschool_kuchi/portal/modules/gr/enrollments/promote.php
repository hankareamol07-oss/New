<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/app_layout.php';
require_login();
layout_start('वर्षांती बढती', 'gr');
?>
<div class="max-w-3xl mx-auto bg-white border rounded-2xl p-7"><h2 class="text-2xl font-bold text-navy">वर्षांती बढती</h2><p class="text-slate-600 mt-3">चालू वर्षातील सक्रिय विद्यार्थी पुढील शैक्षणिक वर्षात एक वर्ग पुढे करण्यासाठी वर्ग व्यवस्थापनातील वर्गोन्नती वापरा.</p><p class="mt-5 rounded-xl bg-amber-50 text-amber-800 p-4">सध्याचे विद्यार्थी आधीच पुढील वर्षात नोंदले असल्यास ते पुन्हा हलवले जाणार नाहीत.</p><a href="/modules/varg/index.php" class="inline-block mt-6 bg-teal text-white rounded-xl px-5 py-3 font-bold">वर्गोन्नती उघडा</a></div>
<?php layout_end(); ?>
