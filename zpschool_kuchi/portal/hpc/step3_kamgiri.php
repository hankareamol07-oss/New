<?php
$page_title = 'HPC — पायरी 3 · कामगिरी';
require_once __DIR__ . '/rubric_lib.php';
requireLogin();

$db = getDB();
hpc_rb_ensure_schema($db);
$school_id = (int) $_SESSION['school_id'];
$student = hpc_rb_student($db, $school_id, (int) ($_GET['student_id'] ?? 0));
if (!$student) { flash('error', 'विद्यार्थी सापडला नाही.'); redirect(APP_URL . '/hpc/wizard.php'); }

$std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade'] ?? '');
if ($std >= 3) {
    redirect(APP_URL . '/hpc/stage_form.php?student_id=' . (int) $student['id']);
}

$card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
$kam = hpc_rb_kamgiri_rows($db, (int) $card['id']);
$domains = hpc_fs_domains();
$girl = hpc_rb_is_girl($student);
$csrf = hpc_rb_csrf();
$sid = (int) $student['id'];

require_once __DIR__ . '/../includes/header.php';
echo hpc_rb_css();
echo hpc_rb_header($student, 3, APP_URL . '/hpc/step2_abhipray.php?student_id=' . $sid);
?>
<div class="hpc-info mb-3">प्रत्येक विकास क्षेत्रासाठी विद्यार्थ्याची सामर्थ्ये आणि सुधारणा क्षेत्रे निवडा. हे संपूर्ण शैक्षणिक वर्षासाठी एकदाच भरायचे आहे (वर्षाच्या सारांश पानावर छापले जाते).</div>

<?php foreach ($domains as $did => $dom):
    $row = $kam[$did] ?? [];
    $selS = array_map('intval', hpc_rb_json_list($row['strengths'] ?? ''));
    $selI = array_map('intval', hpc_rb_json_list($row['improvements'] ?? ''));
    $optS = hpc_rb_kamgiri_options($db, $did, 'strength', $girl);
    $optI = hpc_rb_kamgiri_options($db, $did, 'improvement', $girl);
    $filled = $selS || $selI; ?>
<div class="hpc-dom">
  <div class="hd"><span><?= $did ?>. <?= sanitize($dom['name_mr']) ?></span>
    <span id="st_<?= $did ?>" class="<?= $filled ? 'hpc-ok' : 'hpc-pending' ?>"><?= $filled ? '✓ भरलेले (' . count($selS) . ' / ' . count($selI) . ')' : '⏳ न भरलेले' ?></span></div>
  <div class="p-3">
    <?php if (!$optS && !$optI): ?>
      <div class="small text-danger">कामगिरी बँक (cce_kamgiri) उपलब्ध नाही — update_tachan.php चालवा.</div>
    <?php else: ?>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="hpc-sem" style="background:#f0fdf4;border-color:#bbf7d0">
          <div class="lbl" style="color:#15803d"><span>💪 सामर्थ्य (Strength)</span></div>
          <div class="hpc-list" id="str_<?= $did ?>">
            <?php foreach ($optS as $id => $txt): ?>
            <label><input type="checkbox" value="<?= $id ?>" <?= in_array($id, $selS, true) ? 'checked' : '' ?>><span><?= sanitize($txt) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="hpc-sem s2">
          <div class="lbl"><span>📈 सुधारणा (Improvement)</span></div>
          <div class="hpc-list" id="imp_<?= $did ?>">
            <?php foreach ($optI as $id => $txt): ?>
            <label><input type="checkbox" value="<?= $id ?>" <?= in_array($id, $selI, true) ? 'checked' : '' ?>><span><?= sanitize($txt) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <button type="button" class="btn btn-sm btn-primary mt-3" onclick="saveKam(<?= $did ?>)">जतन करा</button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="d-flex justify-content-between mt-3 mb-5">
  <a href="<?= APP_URL ?>/hpc/step2_abhipray.php?student_id=<?= $sid ?>" class="btn btn-outline-secondary">← शिक्षक अभिप्राय</a>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/hpc/wizard.php" class="btn btn-outline-primary">विद्यार्थी यादी</a>
    <a href="<?= APP_URL ?>/cards/print_hpc_19.php?id=<?= (int) $card['id'] ?>&format=official19" target="_blank" class="btn btn-success">HPC PDF पहा</a>
  </div>
</div>
<div id="toast" class="hpc-toast">✓ जतन झाले</div>

<script>
const CSRF = <?= json_encode($csrf) ?>, SID = <?= $sid ?>;
function toast(msg, bad){const t=document.getElementById('toast');t.textContent=msg;t.style.background=bad?'#dc2626':'#16a34a';t.style.display='block';setTimeout(()=>t.style.display='none',1500);}
function saveKam(d){
  const pick=id=>[...document.querySelectorAll('#'+id+' input:checked')].map(i=>parseInt(i.value));
  fetch('<?= APP_URL ?>/hpc/save_rubric.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({csrf:CSRF,student_id:SID,action:'kamgiri',domain_id:d,strengths:pick('str_'+d),improvements:pick('imp_'+d)})})
  .then(r=>r.json()).then(j=>{
    const st=document.getElementById('st_'+d);
    if(j.success){const f=j.strengths||j.improvements;st.textContent=f?'✓ भरलेले ('+j.strengths+' / '+j.improvements+')':'⏳ न भरलेले';st.className=f?'hpc-ok':'hpc-pending';toast('✓ जतन झाले');}
    else toast('जतन झाले नाही',true);
  }).catch(()=>toast('जतन झाले नाही',true));
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
