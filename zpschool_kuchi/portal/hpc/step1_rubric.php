<?php
$page_title = 'HPC — पायरी 1 · रुब्रिक निवड';
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
$csrf = hpc_rb_csrf();
$sid = (int) $student['id'];

require_once __DIR__ . '/../includes/header.php';
echo hpc_rb_css();
echo hpc_rb_header($student, 1, APP_URL . '/hpc/wizard.php');
?>
<div class="hpc-info mb-3">प्रत्येक विकास क्षेत्रासाठी सत्र 1 आणि सत्र 2 साठी एक-एक क्षमता (C-code) निवडा. निवड आपोआप जतन होते.</div>

<?php foreach ($domains as $did => $dom):
    $row = $assess[$did] ?? []; ?>
<div class="hpc-dom">
  <div class="hd"><span><?= $did ?>. <?= sanitize($dom['name_mr']) ?></span><span class="text-muted small"><?= count($dom['competencies']) ?> क्षमता</span></div>
  <div class="p-3 row g-3">
    <?php foreach ([1, 2] as $sem): $cur = $row ? hpc_rb_selected_code($row, $sem) : ''; ?>
    <div class="col-md-6">
      <div class="hpc-sem s<?= $sem ?>">
        <div class="lbl"><span><?= $sem === 1 ? '📘' : '📙' ?> सत्र <?= $sem ?></span>
          <span id="st_<?= $did ?>_<?= $sem ?>" class="<?= $cur !== '' ? 'hpc-ok' : 'hpc-pending' ?>"><?= $cur !== '' ? '✓ जतन' : '⏳ निवडा' ?></span></div>
        <select id="d<?= $did ?>_s<?= $sem ?>" class="form-select form-select-sm hpc-sel <?= $cur !== '' ? 'filled' : '' ?>" onchange="saveCode(<?= $did ?>,<?= $sem ?>)">
          <option value="">-- निवडा --</option>
          <?php foreach ($dom['competencies'] as $code => $txt): ?>
          <option value="<?= sanitize($code) ?>" title="<?= sanitize($txt) ?>" <?= $cur === $code ? 'selected' : '' ?>><?= sanitize($code) ?> : <?= sanitize(mb_strlen($txt) > 70 ? mb_substr($txt, 0, 70) . '…' : $txt) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="small text-muted mt-2" id="tx_<?= $did ?>_<?= $sem ?>"><?= $cur !== '' ? sanitize($cur . ' : ' . ($dom['competencies'][$cur] ?? '')) : '' ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="d-flex justify-content-between mt-3 mb-5">
  <a href="<?= APP_URL ?>/hpc/create.php?student_id=<?= $sid ?>" class="btn btn-outline-secondary">← विद्यार्थी माहिती</a>
  <button type="button" class="btn btn-primary" onclick="goNext()">पुढे: शिक्षक अभिप्राय →</button>
</div>
<div id="toast" class="hpc-toast">✓ जतन झाले</div>

<script>
const CSRF = <?= json_encode($csrf) ?>, SID = <?= $sid ?>;
const DOMAINS = <?= json_encode(array_map(fn($d) => $d['name_mr'], $domains), JSON_UNESCAPED_UNICODE) ?>;
function toast(msg, bad){const t=document.getElementById('toast');t.textContent=msg;t.style.background=bad?'#dc2626':'#16a34a';t.style.display='block';setTimeout(()=>t.style.display='none',1500);}
function saveCode(d, s){
  const sel=document.getElementById('d'+d+'_s'+s), code=sel.value, st=document.getElementById('st_'+d+'_'+s), tx=document.getElementById('tx_'+d+'_'+s);
  const opt=sel.options[sel.selectedIndex];
  tx.textContent = code ? code+' : '+opt.title : '';
  fetch('<?= APP_URL ?>/hpc/save_rubric.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({csrf:CSRF,student_id:SID,action:'code',domain_id:d,semester:s,capacity_code:code})})
  .then(r=>r.json()).then(j=>{
    if(j.success){sel.classList.toggle('filled',!!code);st.textContent=code?'✓ जतन':'⏳ निवडा';st.className=code?'hpc-ok':'hpc-pending';toast('✓ जतन झाले');}
    else toast('जतन झाले नाही',true);
  }).catch(()=>toast('जतन झाले नाही',true));
}
function goNext(){
  const missing=[];
  Object.keys(DOMAINS).forEach(d=>{[1,2].forEach(s=>{if(!document.getElementById('d'+d+'_s'+s).value)missing.push(d+'. '+DOMAINS[d]+' (सत्र '+s+')');});});
  if(missing.length){alert('खालील नोंदी अपूर्ण आहेत:\n'+missing.join('\n'));return;}
  location.href='<?= APP_URL ?>/hpc/step2_abhipray.php?student_id='+SID;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
