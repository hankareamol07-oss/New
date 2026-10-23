<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
$u=require_role(['admin','principal','clerk']);$sid=(int)$u['school_id'];$pdo=db();$month=(int)($_GET['month']??date('m'));$year=(int)($_GET['year']??date('Y'));
if($_SERVER['REQUEST_METHOD']==='POST'&&valid_csrf($_POST['csrf']??null)){$p=$pdo->prepare('UPDATE mdm_daily SET locked=1 WHERE school_id=? AND substr(date,1,7)=?');$p->execute([$sid,sprintf('%04d-%02d',$year,$month)]);flash_set('success','महिन्याच्या नोंदी लॉक झाल्या.');}
mdm_layout_start('मासिक नोंद लॉक');
?>
<div class="bg-white border rounded-2xl p-6"><p>लॉक केल्यानंतर निवडलेल्या महिन्यातील नोंदी बदलता येणार नाहीत.</p><form method="post" class="mt-5"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="bg-gold rounded-xl px-5 py-3" onclick="return confirm('महिन्याच्या नोंदी लॉक करायच्या आहेत का?')">महिना लॉक करा</button></form></div>
<?php layout_end(); ?>
