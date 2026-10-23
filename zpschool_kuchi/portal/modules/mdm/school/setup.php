<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
$u=require_role(['admin','principal','clerk']);$sid=(int)$u['school_id'];$pdo=db();
$studentCounts=[];foreach(['1-5','6-8'] as $g){$max=$g==='1-5'?5:8;$min=$g==='1-5'?1:6;$q=$pdo->prepare('SELECT COUNT(*) FROM students s JOIN classes c ON c.id=s.class_id WHERE s.school_id=? AND c.std BETWEEN ? AND ?');$q->execute([$sid,$min,$max]);$studentCounts[$g]=(int)$q->fetchColumn();}
if($_SERVER['REQUEST_METHOD']==='POST'&&valid_csrf($_POST['csrf']??null)){flash_set('success','पट माहिती अद्ययावत झाली.');header('Location: /modules/mdm/school/setup.php');exit;}
mdm_layout_start('शाळा सेटअप — पोषण आहार');
?>
<div class="bg-white border rounded-2xl p-6"><h2 class="text-2xl font-bold text-navy">शाळा सेटअप</h2><p class="text-slate-600 my-3">पट, स्वयंपाकी आणि अनुदान दर तपासून MDM नोंद सुरू करा.</p><div class="grid sm:grid-cols-2 gap-4"><div class="border rounded-xl p-4">इयत्ता १ ते ५ — पट <strong><?=$studentCounts['1-5']?></strong></div><div class="border rounded-xl p-4">इयत्ता ६ ते ८ — पट <strong><?=$studentCounts['6-8']?></strong></div></div><div class="flex flex-wrap gap-3 mt-6"><a class="bg-gold rounded-xl px-5 py-3 font-bold" href="/modules/mdm/school/settings.php">शाळा सेटिंग्ज</a><a class="bg-white border rounded-xl px-5 py-3" href="/modules/mdm/settings/grant_settings.php">अनुदान सेटिंग</a><a class="bg-white border rounded-xl px-5 py-3" href="/modules/mdm/settings/cooks.php">स्वयंपाकी</a></div></div>
<?php layout_end(); ?>
