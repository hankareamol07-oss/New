<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
$user=require_role(['admin','principal','clerk','teacher']);
$sid=(int)$user['school_id'];$pdo=db();$classes=school_classes($sid);
$classId=(int)($_GET['division_id']??($classes[0]['id']??0));
$month=(string)($_GET['month']??date('Y-m'));
$start=DateTimeImmutable::createFromFormat('Y-m-d',$month.'-01')?:new DateTimeImmutable('first day of this month');
$days=(int)$start->format('t');
$q=$pdo->prepare('SELECT a.date,SUM(a.status="P") present,SUM(a.status="A") absent,SUM(a.status="L") `leave` FROM daily_attendance a JOIN students s ON s.id=a.student_id AND s.school_id=a.school_id WHERE a.school_id=? AND s.class_id=? AND substr(a.date,1,7)=? GROUP BY a.date');
$q->execute([$sid,$classId,$start->format('Y-m')]);$counts=[];foreach($q as $r)$counts[$r['date']]=$r;
layout_start('मासिक हजेरी कॅलेंडर','attendance');
?>
<div class="bg-white border rounded-2xl p-6"><form method="get" class="flex gap-3 mb-5"><input type="hidden" name="division_id" value="<?=$classId?>"><input type="month" name="month" value="<?=e($start->format('Y-m'))?>"><button class="bg-teal text-white rounded-xl px-4">पहा</button></form><h2 class="text-2xl font-bold text-navy mb-4">मासिक हजेरी कॅलेंडर</h2><div class="grid grid-cols-7 gap-2"><?php for($d=1;$d<=$days;$d++):$date=$start->format('Y-m-').str_pad((string)$d,2,'0',STR_PAD_LEFT);$r=$counts[$date]??[];?><a href="/modules/hajeri/index.php?class_id=<?=$classId?>&date=<?=$date?>" class="border rounded-xl p-3 min-h-20"><strong><?=$d?></strong><span class="block text-xs text-green-700">उपस्थित <?=e((string)($r['present']??0))?></span><span class="block text-xs text-red-700">अनुपस्थित <?=e((string)($r['absent']??0))?></span></a><?php endfor;?></div></div>
<?php layout_end(); ?>
