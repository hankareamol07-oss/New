<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
$u=require_role(['admin','principal','clerk']);$sid=(int)$u['school_id'];$month=(int)($_GET['month']??date('m'));$year=(int)($_GET['year']??date('Y'));$group=(string)($_GET['class_group']??'1-5');$pdo=db();$q=$pdo->prepare('SELECT g.name_mr,s.opening,s.received,s.returned FROM mdm_stock s JOIN mdm_grains g ON g.id=s.grain_id WHERE s.school_id=? AND s.month=? AND s.year=? AND s.class_group=? ORDER BY g.id');$q->execute([$sid,$month,$year,$group]);$rows=$q->fetchAll();mdm_layout_start('साठा तक्ता — पोषण आहार');
?>
<div class="bg-white border rounded-2xl p-6"><table class="w-full text-sm"><thead><tr><th>धान्य</th><th>प्रारंभिक</th><th>प्राप्त</th><th>उपभोग अंदाज</th><th>शिल्लक</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['name_mr'])?></td><td><?=$r['opening']?></td><td><?=$r['received']?></td><td><?=$r['returned']?></td><td><?=number_format((float)$r['opening']+(float)$r['received']-(float)$r['returned'],3)?></td></tr><?php endforeach;?></tbody></table></div>
<?php layout_end(); ?>
