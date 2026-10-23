<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
$u=require_role(['admin','principal','clerk']);$sid=(int)$u['school_id'];$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'&&valid_csrf($_POST['csrf']??null)){
    if(trim((string)($_POST['new_name_mr']??''))!==''){
        $pdo->prepare('INSERT IGNORE INTO mdm_grains(school_id,name_mr,name_en,grain_type,unit,visible) VALUES(?,?,?,?,?,1)')->execute([$sid,trim((string)$_POST['new_name_mr']),trim((string)($_POST['new_name_en']??'')),trim((string)($_POST['new_grain_type']??'other_dry')),trim((string)($_POST['new_unit']??'kg'))]);
    }
    $all=$pdo->prepare('SELECT id FROM mdm_grains WHERE school_id=?');$all->execute([$sid]);$visible=array_map('intval',(array)($_POST['visible']??[]));foreach($all as $r)$pdo->prepare('UPDATE mdm_grains SET visible=? WHERE id=? AND school_id=?')->execute([in_array((int)$r['id'],$visible,true)?1:0,(int)$r['id'],$sid]);flash_set('success','धान्य निवड जतन झाली.');
}
$q=$pdo->prepare('SELECT * FROM mdm_grains WHERE school_id=? ORDER BY id');$q->execute([$sid]);$grains=$q->fetchAll();mdm_layout_start('धान्य निवड — पोषण आहार');
?>
<div class="bg-white border rounded-2xl p-6"><p class="text-slate-600 mb-5">फक्त निवडलेले धान्य प्रपत्र ब, नोंदवही, साठा व इतर अहवालांमध्ये दिसेल.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><table class="w-full text-sm"><thead><tr><th>निवडा</th><th>नाव (मराठी)</th><th>English</th><th>प्रकार</th><th>एकक</th></tr></thead><tbody><?php foreach($grains as $g):?><tr><td><input type="checkbox" name="visible[]" value="<?=$g['id']?>" <?=$g['visible']?'checked':''?>></td><td><?=e($g['name_mr'])?></td><td><?=e($g['name_en'])?></td><td><?=e($g['grain_type'])?></td><td><?=e($g['unit'])?></td></tr><?php endforeach;?></tbody></table><div class="grid sm:grid-cols-4 gap-2 mt-5"><input name="new_name_mr" placeholder="नवीन धान्य (मराठी)"><input name="new_name_en" placeholder="English name"><input name="new_grain_type" placeholder="प्रकार"><input name="new_unit" value="kg" placeholder="एकक"></div><button class="mt-5 bg-gold rounded-xl px-5 py-3 font-bold">जतन करा</button></form></div>
<?php layout_end(); ?>
