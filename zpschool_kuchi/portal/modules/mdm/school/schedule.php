<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
$u=require_role(['admin','principal','clerk']);$sid=(int)$u['school_id'];$month=(string)($_GET['month']??date('Y-m'));$rows=mdm_report_rows(db(),$sid,$month.'-01',$month.'-31');mdm_layout_start('वेळापत्रक — पोषण आहार');
?>
<div class="bg-white border rounded-2xl p-6"><form method="get" class="mb-5"><input type="month" name="month" value="<?=e($month)?>"><button class="bg-teal text-white rounded-xl px-4">पहा</button></form><table class="w-full text-sm"><thead><tr><th>दिनांक</th><th>इयत्ता गट</th><th>मेनू</th><th>लाभार्थी</th><th>स्थिती</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=$r['date']?></td><td><?=mdm_groups()[$r['class_group']]?></td><td><?=e($r['menu_name']??'—')?></td><td><?=$r['beneficiaries']?></td><td><?=$r['holiday']?'सुट्टी':($r['cooked']?'शिजवले':'बाकी')?></td></tr><?php endforeach;?></tbody></table></div>
<?php layout_end(); ?>
