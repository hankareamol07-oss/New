<?php
$page_title = 'HPC — पायरी 2 · शिक्षक अभिप्राय';
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
$assess = hpc_rb_assessments($db, (int) $card['id']);
$domains = hpc_fs_domains();
$girl = hpc_rb_is_girl($student);
$csrf = hpc_rb_csrf();
$sid = (int) $student['id'];

require_once __DIR__ . '/../includes/header.php';
echo hpc_rb_css();
echo hpc_rb_header($student, 2, APP_URL . '/hpc/step1_rubric.php?student_id=' . $sid);
?>
<div class="hpc-info mb-3">प्रत्येक विकास क्षेत्रासाठी निवडलेल्या क्षमतेनुसार सत्र 1 व सत्र 2 चे अभिप्राय निवडा (एकापेक्षा जास्त चालतील). "जतन करा" दाबा.</div>

<?php foreach ($domains as $did => $dom): $row = $assess[$did] ?? []; ?>
<div class="hpc-dom">
  <div class="hd"><span><?= $did ?>. <?= sanitize($dom['name_mr']) ?></span></div>
  <div class="p-3 row g-3">
    <?php foreach ([1, 2] as $sem):
        $code = $row ? hpc_rb_selected_code($row, $sem) : '';
        $sel = hpc_rb_json_list($row[$sem === 2 ? 'abhipray_ids_term2' : 'abhipray_ids'] ?? '');
        $sel = array_map('intval', $sel);
        $opts = hpc_rb_abhipray_options($db, $code, $girl); ?>
    <div class="col-md-6">
      <div class="hpc-sem s<?= $sem ?>">
        <div class="lbl"><span><?= $sem === 1 ? '📘' : '📙' ?> सत्र <?= $sem ?><?= $code !== '' ? ' — ' . sanitize($code) : '' ?></span>
          <span id="st_<?= $did ?>_<?= $sem ?>" class="<?= $sel ? 'hpc-ok' : 'hpc-pending' ?>"><?= $sel ? '✓ ' . count($sel) . ' निवडले' : '⏳ न भरलेले' ?></span></div>
        <?php if ($code === ''): ?>
          <div class="small text-danger">पायरी 1 मध्ये या क्षेत्रासाठी सत्र <?= $sem ?> ची क्षमता निवडा. <a href="<?= APP_URL ?>/hpc/step1_rubric.php?student_id=<?= $sid ?>">रुब्रिक निवड →</a></div>
        <?php elseif (!$opts): ?>
          <div class="small text-danger"><?= sanitize($code) ?> साठी अभिप्राय बँक (cce_abhipray2) उपलब्ध नाही — update_tachan.php चालवा.</div>
        <?php else: ?>
          <div class="small text-muted mb-2"><?= sanitize($dom['competencies'][$code] ?? '') ?></div>
          <div class="hpc-list" id="list_<?= $did ?>_<?= $sem ?>">
            <?php $n = 0; foreach ($opts as $id => $txt): $n++; ?>
            <label><input type="checkbox" value="<?= $id ?>" <?= in_array($id, $sel, true) ? 'checked' : '' ?>><span><?= $n ?>. <?= sanitize($txt) ?></span></label>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-sm btn-primary mt-2" onclick="saveAbhi(<?= $did ?>,<?= $sem ?>)">जतन करा</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="d-flex justify-content-between mt-3 mb-5">
  <a href="<?= APP_URL ?>/hpc/step1_rubric.php?student_id=<?= $sid ?>" class="btn btn-outline-secondary">← रुब्रिक निवड</a>
  <a href="<?= APP_URL ?>/hpc/step3_kamgiri.php?student_id=<?= $sid ?>" class="btn btn-primary">पुढे: कामगिरी →</a>
</div>
<div id="toast" class="hpc-toast">✓ जतन झाले</div>

<script>
const CSRF = <?= json_encode($csrf) ?>, SID = <?= $sid ?>;
function toast(msg, bad){const t=document.getElementById('toast');t.textContent=msg;t.style.background=bad?'#dc2626':'#16a34a';t.style.display='block';setTimeout(()=>t.style.display='none',1500);}
function saveAbhi(d, s){
  const ids=[...document.querySelectorAll('#list_'+d+'_'+s+' input:checked')].map(i=>parseInt(i.value));
  fetch('<?= APP_URL ?>/hpc/save_rubric.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({csrf:CSRF,student_id:SID,action:'abhipray',domain_id:d,semester:s,ids:ids})})
  .then(r=>r.json()).then(j=>{
    const st=document.getElementById('st_'+d+'_'+s);
    if(j.success){st.textContent=j.count?'✓ '+j.count+' निवडले':'⏳ न भरलेले';st.className=j.count?'hpc-ok':'hpc-pending';toast('✓ जतन झाले');}
    else toast('जतन झाले नाही',true);
  }).catch(()=>toast('जतन झाले नाही',true));
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
