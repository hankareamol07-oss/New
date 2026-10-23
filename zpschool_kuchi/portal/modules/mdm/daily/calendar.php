<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
$u=require_role(['admin','principal','clerk']);$sid=(int)$u['school_id'];$pdo=db();$month=(string)($_GET['month']??date('Y-m'));$start=DateTimeImmutable::createFromFormat('Y-m-d',$month.'-01')?:new DateTimeImmutable('first day of this month');$days=(int)$start->format('t');$q=$pdo->prepare('SELECT date,COUNT(*) total,SUM(cooked) cooked,SUM(holiday) holiday FROM mdm_daily WHERE school_id=? AND substr(date,1,7)=? GROUP BY date');$q->execute([$sid,$start->format('Y-m')]);$map=[];foreach($q as $r)$map[$r['date']]=$r;mdm_layout_start('दिनदर्शिका — पोषण आहार');
?>
<div class="bg-white border rounded-2xl p-6"><form method="get" class="mb-5"><input type="month" name="month" value="<?=e($start->format('Y-m'))?>"><button class="bg-teal text-white rounded-xl px-4">पहा</button></form><div class="grid grid-cols-7 gap-2"><?php for($d=1;$d<=$days;$d++):$date=$start->format('Y-m-').str_pad((string)$d,2,'0',STR_PAD_LEFT);$r=$map[$date]??[];$ok=(int)($r['total']??0)>=2;?><a href="/modules/mdm/daily/entry.php?date=<?=$date?>" class="border rounded-xl p-3 min-h-24 <?=$ok?'bg-green-50':'bg-amber-50'?>"><strong><?=$d?></strong><span class="block text-xs"><?=$ok?'पूर्ण':'अपूर्ण'?></span><span class="block text-xs">नोंदी <?=e((string)($r['total']??0))?></span></a><?php endfor;?></div></div>
<?php layout_end(); ?>
