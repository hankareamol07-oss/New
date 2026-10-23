<?php
/**
 * HPC stage-aware wizard (इयत्ता ३–८) — one page per student, tabs per subject, सत्र 1 / सत्र 2 side by side.
 * Drop into hpc/stage_form.php. wizard.php sends std 3–8 students here, std 1–2 keep step1_rubric.php.
 * Saves via hpc/save_stage.php (AJAX, JSON). Print via cards/print_hpc_stage.php?id=<card>.
 */
$page_title = 'HPC — विषयनिहाय मूल्यांकन (इयत्ता ३–८)';
require_once __DIR__ . '/rubric_lib.php';
require_once __DIR__ . '/../includes/hpc_curriculum.php';
requireLogin();

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$student = hpc_rb_student($db, $school_id, (int) ($_GET['student_id'] ?? 0));
if (!$student) { flash('error', 'विद्यार्थी सापडला नाही.'); redirect(APP_URL . '/hpc/wizard.php'); }

$std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade']);
if ($std < 3 || $std > 8) { redirect(APP_URL . '/hpc/create.php?student_id=' . (int) $student['id']); }

$chkAccess = can_access_hpc_class($std, $school_id);
if (!$chkAccess['allowed']) {
    flash('error', $chkAccess['message'] ?? 'उच्च वर्गांच्या (इ. ३ ते ८) HPC मूल्यमापनासाठी सशुल्क योजना आवश्यक आहे. मोफत योजना फक्त इ. १ ली व २ री साठी उपलब्ध आहे.');
    redirect(APP_URL . '/hpc/index.php?std=' . $std);
}
$stage = hpc_stage_for_std($std);
$mid = $stage === 'middle';
$card = hpc_rb_card($db, $school_id, (int) $student['id'], true);
if (!$card) {
    flash('error', 'आपल्या योजनेची कमाल विद्यार्थी मर्यादा पूर्ण झाली आहे. पुढील विद्यार्थ्यांचे नवीन कार्ड तयार करण्यासाठी कृपया योजना अपग्रेड करा.');
    redirect(APP_URL . '/hpc/index.php?std=' . $std);
}
$subjects = hpc_subjects_for_std($std);
$levels = hpc_rubric_levels($stage);                     // prarambhik / praveen / pragat
$abilities = hpc_rubric_abilities($stage);                     // awareness / sensitivity / creativity
$csrf = hpc_rb_csrf();
$sid = (int) $student['id'];

// Classmates for Sahadhyayi (सहाध्यायी) dropdown
$grade_mr = 'इयत्ता ' . $std;
$classmateStmt = $db->prepare("SELECT id, roll_no, name_mr, name FROM students WHERE school_id = ? AND (std = ? OR grade = ? OR grade = ?) AND id != ? AND is_active = 1 ORDER BY CAST(NULLIF(roll_no, '') AS UNSIGNED) ASC, roll_no ASC, name_mr ASC, name ASC");
$classmateStmt->execute([$school_id, $std, (string)$std, $grade_mr, $sid]);
$classmates = $classmateStmt->fetchAll(PDO::FETCH_ASSOC);

// Subject max credits mapping
$creditData = hpc_credit_std($std);
$subjectMaxCredits = [];
if ($creditData && !empty($creditData['subjects'])) {
    foreach ($creditData['subjects'] as $cs) {
        $subjectMaxCredits[$cs['subject_key']] = $cs['credit_points'];
    }
}

// existing data ----------------------------------------------------------------------------------------------------
$A = [];   // [subject_id][term] => row
$q = $db->prepare("SELECT * FROM hpc_subject_assessments WHERE hpc_card_id = ?");
$q->execute([(int) $card['id']]);
foreach ($q->fetchAll() as $r) $A[(int) $r['subject_id']][(int) $r['term']] = $r;
$S = [];
$q = $db->prepare("SELECT * FROM hpc_subject_summary WHERE hpc_card_id = ?");
$q->execute([(int) $card['id']]);
foreach ($q->fetchAll() as $r) $S[(int) $r['subject_id']] = $r;

$J = fn($v) => $v === null ? null : (is_string($v) ? json_decode($v, true) : $v);
$STR = hpc_statements($stage, 'strength');
$HUR = hpc_statements($stage, 'hurdle');
$SELF = hpc_statements($stage, 'self');
$PEER = $mid ? hpc_statements('middle', 'peer') : [];
$APPR = $mid ? hpc_statements('middle', 'approach') : [];
$SELFP = $mid ? hpc_statements('middle', 'self_progress') : [];

// curriculum per subject (goals + competencies) for the JS side
$CUR = [];
foreach ($subjects as $sub) {
    $CUR[$sub['subject_key']] = [
        'id' => (int) $sub['id'], 'name' => $sub['name_mr'],
        'goals' => array_values(array_map(fn($g) => ['code' => $g['goal_code'], 'text' => $g['text_mr'], 'prov' => $g['provenance']], hpc_goals($sub))),
        'comps' => array_values(array_map(fn($c) => ['code' => $c['comp_code'], 'goal' => $c['goal_code'] ?? hpc_goal_code_of($c['comp_code']), 'text' => $c['text_mr'], 'prov' => $c['provenance'], 'review' => (int) ($c['needs_review'] ?? 0)], hpc_competencies($sub))),
        'rubank' => hpc_rubric_bank($std, $sub['subject_key']),   // AI suggestions [comp][ability][level] => text
        'phrases' => hpc_phrase_bank($std, $sub['subject_key']),
        'activities' => hpc_activity_bank($std, $sub['subject_key']),
        'topics' => hpc_topics($std, $sub['subject_key']),
    ];
}
$DATA = [];
foreach ($subjects as $sub) {
    foreach ([1, 2] as $t) {
        $r = $A[(int) $sub['id']][$t] ?? [];
        foreach ($r as $k => $v) if (is_string($v) && $v !== '' && ($v[0] === '{' || $v[0] === '[')) $r[$k] = json_decode($v, true);
        $DATA[$sub['subject_key']][$t] = $r ?: new stdClass();
    }
    $DATA[$sub['subject_key']]['summary'] = $S[(int) $sub['id']] ?? new stdClass();
}

require_once __DIR__ . '/../includes/header.php';
echo hpc_rb_css();
echo hpc_rb_header($student, 1, APP_URL . '/hpc/wizard.php');
?>
<style>
/* Modern Mobile-First Horizontally Scrollable Subject Pills */
.hs-tabs {
  display: flex !important;
  flex-wrap: nowrap !important;
  overflow-x: auto !important;
  -webkit-overflow-scrolling: touch;
  gap: 8px !important;
  padding: 4px 2px 10px 2px !important;
  margin-bottom: 1.25rem !important;
  border-bottom: 2px solid #fce7f3 !important;
  scrollbar-width: thin;
}
.hs-tabs::-webkit-scrollbar {
  height: 5px;
}
.hs-tabs::-webkit-scrollbar-thumb {
  background: #f472b6;
  border-radius: 999px;
}
.hs-tabs .nav-item {
  flex: 0 0 auto !important;
}
.hs-tabs .nav-link {
  white-space: nowrap !important;
  border-radius: 14px !important;
  font-weight: 700 !important;
  font-size: 0.88rem !important;
  padding: 8px 16px !important;
  border: 1.5px solid #fbcfe8 !important;
  background: #ffffff !important;
  color: #9d174d !important;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.hs-tabs .nav-link:hover {
  background: #fdf2f8 !important;
  border-color: #f472b6 !important;
  color: #831843 !important;
}
.hs-tabs .nav-link.active {
  background: linear-gradient(135deg, #e11d48 0%, #be185d 100%) !important;
  color: #ffffff !important;
  border-color: #be185d !important;
  box-shadow: 0 4px 14px rgba(190, 24, 93, 0.3) !important;
}

.hs-term{border:1px solid #f3c3dc;border-radius:14px;background:#fff8fc;padding:14px;margin-bottom:12px}
.hs-term h6{color:#e5007e;border-bottom:1px dashed #f3c3dc;padding-bottom:6px;font-weight:800}
.hs-code{display:inline-block;margin:2px 4px 2px 0;padding:3px 8px;border:1px solid #ddd;border-radius:6px;font-size:.8rem;background:#fff;cursor:pointer}
.hs-code input{margin-right:4px}
.hs-code.review{border-color:#f59e0b;background:#fffbeb}
.hs-rub td,.hs-rub th{font-size:.8rem;vertical-align:middle}
.hs-rub th.ab{background:#fdeef6;width:110px}
.hs-ok{color:#16a34a;font-size:.8rem}.hs-bad{color:#dc2626;font-size:.8rem}
.hs-small{font-size:.78rem;color:#666}
.hs-phrase-drop{border-color:#f3c3dc;background-color:#fff;color:#9d174d;font-weight:500;cursor:pointer}
.hs-phrase-drop:hover,.hs-phrase-drop:focus{border-color:#e5007e;box-shadow:0 0 0 2px rgba(229,0,126,.15);color:#831843}
.hs-phrase-drop option{color:#1f2937;font-weight:normal}
.hs-phrase-drop optgroup{color:#b91c1c;font-weight:700}
.hs-actsug span{background:#fdf2f8;color:#be185d;font-weight:600;border-color:#fbcfe8}
.hs-rubsug span{background:#eff6ff;color:#1d4ed8;font-weight:600;border-color:#bfdbfe}
</style>

<div class="hpc-info mb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2.5">
  <div>
    <span class="badge bg-primary text-white me-1.5 px-2.5 py-1 fs-6">
      <?= sanitize(hpc_stage_name_mr($stage)) ?>
    </span>
    <b>इयत्ता <?= sanitize($student['grade']) ?> (<?= sanitize(hpc_stage_std_range($stage)) ?>)</b>
    <div class="text-secondary small mt-1">प्रत्येक विषयासाठी सत्र १ व सत्र २ भरा. सर्व बदल आपोआप जतन होतात (✓).</div>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-1.5 mt-1 mt-md-0">
    <?php if ($mid): ?>
    <button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs" onclick="autoFillCurrentSubjectWheel()" title="चालू विषयाचे सत्र १ व २ चे प्रगतीचक्र स्वयंचलित भरा">
      ⚡ ऑटो-फिल प्रगतीचक्र
    </button>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-primary" href="<?= APP_URL ?>/students/edit.php?id=<?= $sid ?>&return=stage_form">
      <i class="bi bi-pencil-square"></i> माहिती / उंची-वजन
    </a>
    <a class="btn btn-sm btn-success" target="_blank" href="<?= APP_URL ?>/cards/print_hpc_stage.php?id=<?= (int) $card['id'] ?>">
      🖨 कार्ड छापा
    </a>
    <a class="btn btn-sm btn-outline-success" target="_blank" title="१ पान / विषय / सत्र" href="<?= APP_URL ?>/cards/print_hpc_stage.php?id=<?= (int) $card['id'] ?>&mode=compact">
      🖨 संक्षिप्त
    </a>
  </div>
</div>

<ul class="nav nav-tabs hs-tabs mb-3" role="tablist">
  <?php foreach ($subjects as $i => $sub): ?>
  <li class="nav-item">
    <button class="nav-link <?= $i === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#sub_<?= sanitize($sub['subject_key']) ?>">
      <?= sanitize($sub['name_mr']) ?>
    </button>
  </li>
  <?php endforeach; ?>
</ul>

<div class="tab-content">
<?php foreach ($subjects as $i => $sub): $k = $sub['subject_key']; ?>
<div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="sub_<?= sanitize($k) ?>">
  <div class="row g-3">
    <?php foreach ([1, 2] as $t): $p = "{$k}_{$t}"; ?>
    <div class="col-lg-6">
      <div class="hs-term" data-sub="<?= sanitize($k) ?>" data-term="<?= $t ?>">
        <h6><?= $t === 1 ? '📘 प्रथम सत्र' : '📙 द्वितीय सत्र' ?> <span class="float-end" id="st_<?= $p ?>"></span></h6>

        <label class="form-label mb-1"><b>अभ्यासक्रमाची ध्येये</b> <span class="hs-small">(एक किंवा अधिक)</span></label>
        <div id="goals_<?= $p ?>"></div>

        <label class="form-label mb-1 mt-2"><b>क्षमता</b> <span class="hs-small">(निवडलेल्या ध्येयांच्या; 🟠 = मजकूर तपासा)</span></label>
        <div id="comps_<?= $p ?>"></div>

        <div class="hs-topics" style="display:none">
          <label class="form-label mb-1 mt-2"><b>पाठ / घटक</b> <span class="hs-small">(2026-27 तचन वार्षिक नियोजनातील या सत्रात घेतलेले घटक निवडा — संबंधित अध्ययन निष्पत्ती आपोआप निवडल्या जातील)</span></label>
          <select class="form-select form-select-sm" data-topics multiple size="6"></select>
        </div>

        <?php if ($mid): ?>
        <label class="form-label mb-1 mt-2"><b>कृतीचा दृष्टिकोन</b></label>
        <div id="appr_<?= $p ?>">
          <?php foreach ($APPR as $s): ?><label class="hs-code"><input type="checkbox" value="<?= sanitize($s['option_set'] ?? $s['text_mr']) ?>" data-f="activity_approach"><?= sanitize($s['text_mr']) ?></label><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="input-group input-group-sm mb-1 mt-2 hs-actsug" style="display:none">
          <span class="input-group-text">💡 कृती व प्रश्न बँक</span>
          <select class="form-select" data-actsug><option value="">— क्षमता निवडा (वरील क्षमता यादीतून) —</option></select>
          <button type="button" class="btn btn-outline-primary" data-actfill>भरा</button>
        </div>
        <label class="form-label mb-1"><b>कृती / उपक्रम</b></label>
        <textarea class="form-control form-control-sm" rows="2" data-f="activity_mr" placeholder="कृती / उपक्रम... किंवा वरील बँकेतून भरा"></textarea>
        <label class="form-label mb-1 mt-1"><b>मूल्यांकन प्रश्न</b> <span class="hs-small">(प्रति ओळ एक)</span></label>
        <textarea class="form-control form-control-sm" rows="2" data-f="assessment_questions_mr" placeholder="मूल्यांकन प्रश्न... प्रति ओळ एक"></textarea>

        <label class="form-label mb-1 mt-2"><b>मूल्यांकन निकष (रुब्रिक) — कामगिरी स्तर</b></label>
        <div class="input-group input-group-sm mb-1 hs-rubsug">
          <span class="input-group-text">✨ AI सूचना</span>
          <select class="form-select" data-rubsug><option value="">— क्षमता निवडा (वरील क्षमता यादीतून) —</option></select>
          <button type="button" class="btn btn-outline-primary" data-rubfill>भरा</button>
        </div>
        <div class="hs-small mb-1">AI-निर्मित सूचना (अधिकृत HPC मजकूर नाही) — भरल्यानंतर कोणत्याही कक्षातील मजकूर संपादित करू शकता.</div>
        <table class="table table-bordered table-sm hs-rub mb-1">
          <thead><tr><th class="ab">क्षमता</th><?php foreach ($levels as $lv): ?><th class="text-center"><?= sanitize($lv['name_mr']) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($abilities as $ab): ?>
          <tr><th class="ab"><?= sanitize($ab['name_mr']) ?></th>
            <?php foreach ($levels as $lv): ?>
            <td class="text-center"><input type="radio" name="rub_<?= $p ?>_<?= sanitize($ab['ability_key']) ?>" value="<?= sanitize($lv['level_key']) ?>" data-rub="<?= sanitize($ab['ability_key']) ?>"></td>
            <?php endforeach; ?></tr>
          <tr class="hs-rubcells"><td></td>
            <?php foreach ($levels as $lv): ?>
            <td><textarea class="form-control form-control-sm" rows="3" placeholder="<?= sanitize($lv['name_mr']) ?> — निकष मजकूर" data-rubcell="<?= sanitize($ab['ability_key']) ?>" data-lv="<?= sanitize($lv['level_key']) ?>"></textarea></td>
            <?php endforeach; ?></tr>
          <tr><td colspan="<?= count($levels) + 1 ?>"><input class="form-control form-control-sm" placeholder="<?= sanitize($ab['name_mr']) ?> — निरीक्षण / निकष मजकूर" data-rubtext="<?= sanitize($ab['ability_key']) ?>"></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <div class="row g-2">
          <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label mb-0"><b>आव्हाने</b></label>
              <select class="form-select form-select-sm w-auto py-0 px-2 hs-phrase-drop" data-phrase="challenges" data-target="challenges_mr" style="font-size:0.78rem; max-width:65%;">
                <option value="">— निवडा (बँक) —</option>
              </select>
            </div>
            <textarea class="form-control form-control-sm" rows="2" data-f="challenges_mr" placeholder="आव्हाने... किंवा वरील बँक निवडा"></textarea>
          </div>
          <div class="col-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label mb-0"><b>मात / मदत</b></label>
              <select class="form-select form-select-sm w-auto py-0 px-2 hs-phrase-drop" data-phrase="support" data-target="support_mr" style="font-size:0.78rem; max-width:65%;">
                <option value="">— निवडा (बँक) —</option>
              </select>
            </div>
            <textarea class="form-control form-control-sm" rows="2" data-f="support_mr" placeholder="मात / मदत... किंवा वरील बँक निवडा"></textarea>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-1 mt-2">
          <label class="form-label mb-0"><b>शिक्षकांच्या निरीक्षणात्मक नोंदी</b></label>
          <select class="form-select form-select-sm w-auto py-0 px-2 hs-phrase-drop" data-phrase="teacher_notes" data-target="teacher_notes_mr" style="font-size:0.78rem; max-width:55%;">
            <option value="">— निवडा (बँक) —</option>
          </select>
        </div>
        <textarea class="form-control form-control-sm" rows="2" data-f="teacher_notes_mr" placeholder="शिक्षकांच्या निरीक्षणात्मक नोंदी... किंवा वरील बँक निवडा"></textarea>

        <details class="mt-2"><summary><b>विद्यार्थ्याचे आत्मचिंतन</b></summary>
          <?php foreach ($SELF as $n => $s): ?>
          <div class="d-flex align-items-center gap-2 my-1"><span class="hs-small flex-grow-1"><?= sanitize($s['text_mr']) ?></span>
            <select class="form-select form-select-sm w-auto" data-self="<?= $n + 1 ?>"><option value="">—</option><option value="yes">😊 हो</option><option value="some">😐 काही प्रमाणात</option><option value="no">☹ नाही</option><option value="unsure">❓ खात्री नाही</option></select></div>
          <?php endforeach; ?>
          <?php if ($mid): ?>
          <div class="hs-small mt-1">माझ्या प्रगतीचा आराखडा — गोल केलेल्या विधानांची संख्या (0–6):</div>
          <div class="d-flex gap-2">
            <?php foreach ($abilities as $ab): ?><label class="hs-small"><?= sanitize($ab['name_mr']) ?> <input type="number" min="0" max="6" class="form-control form-control-sm d-inline-block" style="width:60px" data-spc="<?= sanitize($ab['ability_key']) ?>"></label><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="row g-2 mt-2">
            <div class="col-md-4">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="hs-small fw-bold">सर्वात आवडले</span>
                <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="liked_most" data-target="liked_most_mr" style="font-size:0.74rem; max-width:60%"><option value="">— बँक —</option></select>
              </div>
              <input class="form-control form-control-sm" placeholder="सर्वात आवडले" data-f="liked_most_mr">
            </div>
            <div class="col-md-4">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="hs-small fw-bold">अधिक सराव</span>
                <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="need_practice" data-target="need_practice_mr" style="font-size:0.74rem; max-width:60%"><option value="">— बँक —</option></select>
              </div>
              <input class="form-control form-control-sm" placeholder="अधिक सराव" data-f="need_practice_mr">
            </div>
            <div class="col-md-4">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="hs-small fw-bold">मदत हवी</span>
                <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="need_help" data-target="need_help_mr" style="font-size:0.74rem; max-width:60%"><option value="">— बँक —</option></select>
              </div>
              <input class="form-control form-control-sm" placeholder="मदत हवी" data-f="need_help_mr">
            </div>
          </div>
          <div class="mt-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="hs-small fw-bold">माझे शिकणे</span>
              <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="my_learning" data-target="my_learning_mr" style="font-size:0.74rem; max-width:50%"><option value="">— बँक —</option></select>
            </div>
            <textarea class="form-control form-control-sm" rows="2" placeholder="माझे शिकणे" data-f="my_learning_mr"></textarea>
          </div>
        </details>

        <details class="mt-2"><summary><b>सहाध्यायी अभिप्राय</b></summary>
          <label class="form-label mb-1 hs-small mt-1"><b>सहाध्यायीचे नाव</b></label>
          <div class="row g-1 mb-2">
            <div class="col-sm-6">
              <select class="form-select form-select-sm" data-peer-select>
                <option value="">— वर्गातील सहाध्यायी निवडा —</option>
                <?php foreach ($classmates as $cm): 
                  $cname = trim($cm['name_mr'] ?: $cm['name']);
                  $croll = trim((string)($cm['roll_no'] ?? ''));
                ?>
                <option value="<?= sanitize($cname) ?>"><?= sanitize(($croll !== '' ? "($croll) " : '') . $cname) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <input class="form-control form-control-sm" placeholder="किंवा थेट नाव लिहा" data-f="peer_name">
            </div>
          </div>
          <?php if ($mid): ?>
          <?php foreach ($PEER as $n => $s): ?>
          <div class="d-flex align-items-center gap-2 my-1"><span class="hs-small flex-grow-1"><?= sanitize($s['text_mr']) ?></span>
            <select class="form-select form-select-sm w-auto" data-peer="<?= $n + 1 ?>"><option value="">—</option><option value="yes">😊 हो</option><option value="some">😐 काही प्रमाणात</option><option value="no">☹ नाही</option><option value="unsure">❓ खात्री नाही</option></select></div>
          <?php endforeach; ?>
          <div class="d-flex gap-2"><?php foreach ($abilities as $ab): ?><label class="hs-small"><?= sanitize($ab['name_mr']) ?> <input type="number" min="0" max="6" class="form-control form-control-sm d-inline-block" style="width:60px" data-ppc="<?= sanitize($ab['ability_key']) ?>"></label><?php endforeach; ?></div>
          <?php endif; ?>
          <div class="row g-2 mt-1">
            <div class="col-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="hs-small fw-bold">अधिक सराव</span>
                <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="peer_need_practice" data-target="peer_need_practice_mr" style="font-size:0.74rem; max-width:60%"><option value="">— बँक —</option></select>
              </div>
              <input class="form-control form-control-sm" placeholder="अधिक सराव (सहाध्यायीच्या मते)" data-f="peer_need_practice_mr">
            </div>
            <div class="col-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="hs-small fw-bold">मदत हवी</span>
                <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="peer_need_help" data-target="peer_need_help_mr" style="font-size:0.74rem; max-width:60%"><option value="">— बँक —</option></select>
              </div>
              <input class="form-control form-control-sm" placeholder="मदत हवी (सहाध्यायीच्या मते)" data-f="peer_need_help_mr">
            </div>
          </div>
        </details>

        <details class="mt-2" open><summary><b>शिक्षक अभिप्राय — बलस्थान / अडथळे</b></summary>
          <div class="row g-2">
            <div class="col-6"><div class="hs-small">बलस्थान क्षेत्र</div>
              <?php foreach ($STR as $s): if (trim($s['text_mr']) === 'इतर') continue; ?><label class="hs-code d-block"><input type="checkbox" value="<?= sanitize($s['option_set'] ?? $s['text_mr']) ?>" data-list="strengths"><?= sanitize($s['text_mr']) ?></label><?php endforeach; ?>
              <input class="form-control form-control-sm mt-1" placeholder="इतर" data-other="strengths"></div>
            <div class="col-6"><div class="hs-small">यशस्वी होण्यातील अडथळे</div>
              <?php foreach ($HUR as $s): if (trim($s['text_mr']) === 'इतर') continue; ?><label class="hs-code d-block"><input type="checkbox" value="<?= sanitize($s['option_set'] ?? $s['text_mr']) ?>" data-list="hurdles"><?= sanitize($s['text_mr']) ?></label><?php endforeach; ?>
              <input class="form-control form-control-sm mt-1" placeholder="इतर" data-other="hurdles"></div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-1 mt-2">
            <span class="hs-small fw-bold">विद्यार्थ्याच्या प्रगतीत वाढ होण्यासाठी मी मदत करू शकतो का?</span>
            <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="teacher_help" data-target="teacher_help_mr" style="font-size:0.74rem; max-width:45%"><option value="">— बँक —</option></select>
          </div>
          <textarea class="form-control form-control-sm" rows="2" placeholder="विद्यार्थ्याच्या प्रगतीत वाढ होण्यासाठी मी मदत करू शकतो का?" data-f="teacher_help_mr"></textarea>

          <div class="d-flex justify-content-between align-items-center mb-1 mt-2">
            <span class="hs-small fw-bold">शिक्षकांचे निरीक्षण आणि शिफारशी</span>
            <select class="form-select form-select-sm w-auto py-0 px-1 hs-phrase-drop" data-phrase="teacher_observation" data-target="teacher_observation_mr" style="font-size:0.74rem; max-width:45%"><option value="">— बँक —</option></select>
          </div>
          <textarea class="form-control form-control-sm" rows="2" placeholder="शिक्षकांचे निरीक्षण आणि शिफारशी" data-f="teacher_observation_mr"></textarea>
        </details>

        <?php if ($mid): ?>
        <!-- Interactive Progress Wheel (प्रगतीचक्र) -->
        <div class="card border-0 shadow-xs rounded-3 mt-2.5 p-2.5" style="background:#fff5f7;border:1.5px solid #fbcfe8 !important;">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold small" style="color:#be185d">
              <i class="bi bi-pie-chart-fill me-1"></i> विद्यार्थी प्रगतीचक्र (Evaluation Wheel)
            </span>
            <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 fw-bold" style="font-size:0.75rem" onclick="autoFillWheel('<?= sanitize($k) ?>', <?= $t ?>)" title="या सत्राचे प्रगतीचक्र स्वयंचलित भरा">
              ⚡ ऑटो-फिल चक्र
            </button>
          </div>
          <div class="d-flex flex-column flex-sm-row align-items-center gap-2" id="wheel_wrap_<?= $p ?>">
            <div id="wheel_svg_<?= $p ?>" style="width:140px;height:140px;flex-shrink:0;"></div>
            <div class="flex-grow-1 w-100">
              <table class="table table-bordered table-sm mb-0 text-center" style="font-size:0.72rem;background:#fff">
                <thead class="table-light">
                  <tr><th>क्षमता</th><th>शिक्षक</th><th>विद्यार्थी</th><th>सहाध्यायी</th></tr>
                </thead>
                <tbody id="wheel_tbl_<?= $p ?>">
                  <?php foreach ($abilities as $ab): $ak = $ab['ability_key']; ?>
                  <tr>
                    <td class="fw-bold text-start"><?= sanitize($ab['name_mr']) ?></td>
                    <td id="wt_<?= $p ?>_<?= $ak ?>">—</td>
                    <td id="ws_<?= $p ?>_<?= $ak ?>">—</td>
                    <td id="wp_<?= $p ?>_<?= $ak ?>">—</td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <div class="text-muted mt-1" style="font-size:0.68rem;line-height:1.2">
                रुब्रिक, आत्मचिंतन व सहाध्यायी गुणांकनानुसार प्रगतीचक्र स्वयंचलित भरले जाते.
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="hs-term mt-3" data-sub="<?= sanitize($k) ?>" data-summary="1">
    <h6>📗 वार्षिक सारांश (भाग – क) <span class="float-end" id="st_<?= sanitize($k) ?>_sum"></span></h6>
    <div class="row g-2 align-items-end">
      <?php foreach ($abilities as $ab): ?>
      <div class="col-md-3"><label class="form-label mb-1 hs-small"><?= sanitize($ab['name_mr']) ?> — अंतिम स्तर</label>
        <select class="form-select form-select-sm" data-sum="level_<?= sanitize($ab['ability_key']) ?>"><option value="">—</option><?php foreach ($levels as $lv): ?><option value="<?= sanitize($lv['level_key']) ?>"><?= sanitize($lv['name_mr']) ?></option><?php endforeach; ?></select></div>
      <?php endforeach; ?>
      <?php $maxCr = $subjectMaxCredits[$k] ?? null; ?>
      <div class="col-md-3">
        <label class="form-label mb-1 hs-small">मिळवलेले श्रेयांक गुण <?= $maxCr !== null ? '<span class="text-danger fw-bold">(कमाल ' . sanitize($maxCr) . ')</span>' : '' ?></label>
        <input type="number" step="0.01" min="0" <?= $maxCr !== null ? 'max="' . (float)$maxCr . '" placeholder="कमाल ' . sanitize($maxCr) . '"' : '' ?> class="form-control form-control-sm" data-sum="credit_points_earned">
      </div>
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label mb-0 hs-small"><b>विषय शेरा</b></label>
          <select class="form-select form-select-sm w-auto py-0 px-2 hs-phrase-drop" data-phrase="remark" data-target="remark_mr" style="font-size:0.78rem; max-width:60%">
            <option value="">— विषय शेरा निवडा (बँक) —</option>
          </select>
        </div>
        <input class="form-control form-control-sm" placeholder="विषय शेरा... किंवा वरील बँक निवडा" data-sum="remark_mr">
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<div class="d-flex justify-content-between mt-3 mb-5">
  <a href="<?= APP_URL ?>/hpc/wizard.php" class="btn btn-outline-secondary">← यादी</a>
  <div>
    <button type="button" class="btn btn-outline-primary" onclick="setStatus('draft')">मसुदा</button>
    <button type="button" class="btn btn-primary" onclick="setStatus('completed')">पूर्ण ✓</button>
    <a class="btn btn-success" target="_blank" href="<?= APP_URL ?>/cards/print_hpc_stage.php?id=<?= (int) $card['id'] ?>">🖨 कार्ड</a>
    <a class="btn btn-outline-success" target="_blank" title="१ पान / विषय / सत्र" href="<?= APP_URL ?>/cards/print_hpc_stage.php?id=<?= (int) $card['id'] ?>&mode=compact">🖨 संक्षिप्त</a>
  </div>
</div>
<div id="toast" class="hpc-toast">✓ जतन झाले</div>

<script>
const CSRF = <?= json_encode($csrf) ?>, SID = <?= $sid ?>, SAVE = <?= json_encode(APP_URL . '/hpc/save_stage.php') ?>;
const CUR = <?= json_encode($CUR, JSON_UNESCAPED_UNICODE) ?>;
const DATA = <?= json_encode($DATA, JSON_UNESCAPED_UNICODE) ?>;
const ABIL = <?= json_encode(array_column($abilities, 'ability_key')) ?>;
function toast(m, bad){const t=document.getElementById('toast');t.textContent=m;t.style.background=bad?'#dc2626':'#16a34a';t.style.display='block';setTimeout(()=>t.style.display='none',1800);}
async function post(body){ body.csrf=CSRF; body.student_id=SID; const r=await fetch(SAVE,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}); return r.json(); }
const timers={};
function debounce(key, fn){ clearTimeout(timers[key]); timers[key]=setTimeout(fn, 500); }

function box(sub, term){ return document.querySelector(`.hs-term[data-sub="${sub}"][data-term="${term}"]`); }
function selGoals(sub, term){ return [...box(sub,term).querySelectorAll('[data-goal]:checked')].map(i=>i.value); }
function selComps(sub, term){ return [...box(sub,term).querySelectorAll('[data-comp]:checked')].map(i=>i.value); }
function selTopics(sub, term){ const s=box(sub,term).querySelector('[data-topics]'); return s?[...s.selectedOptions].map(o=>o.value):[]; }
// 2026-27 तचन topics of this std/subject (hpc_topic_map); grouped by tachan subject (e.g. इतिहास + भूगोल under सामाजिक शास्त्रे)
function renderTopics(sub, term, chosen){
  const b=box(sub,term), sel=b.querySelector('[data-topics]'), wrap=b.querySelector('.hs-topics'), T=CUR[sub].topics||[];
  if(!sel) return; wrap.style.display=T.length?'':'none'; sel.innerHTML=''; let grp=null, cur=null;
  T.forEach(t=>{ if(t.tachan_subject!==cur){ grp=document.createElement('optgroup'); grp.label=t.tachan_subject; sel.appendChild(grp); cur=t.tachan_subject; }
    const o=document.createElement('option'); o.value=t.key; o.textContent=t.seq+'. '+t.topic+(t.comp_codes.length?'  ['+t.comp_codes.join(', ')+']':''); o.title=t.learning_outcome||''; o.selected=chosen.includes(t.key); grp.appendChild(o); });
}
// ticking a topic ticks its mapped competencies (and their goals) so the card follows the taught तचन plan
function applyTopicComps(sub, term){
  const b=box(sub,term), T=CUR[sub].topics||[], want=new Set();
  selTopics(sub,term).forEach(k=>{ const t=T.find(x=>x.key===k); (t?t.comp_codes:[]).forEach(c=>want.add(c)); });
  if(!want.size) return;
  const goals=new Set(selGoals(sub,term));
  want.forEach(c=>{ const cc=CUR[sub].comps.find(x=>x.code===c); if(cc&&cc.goal) goals.add(cc.goal); });
  b.querySelectorAll('[data-goal]').forEach(i=>{ if(goals.has(i.value)) i.checked=true; });
  const keep=new Set([...selComps(sub,term), ...want]); renderComps(sub,term,[...keep]);
}

function renderGoals(sub, term, chosen){
  const el=document.getElementById(`goals_${sub}_${term}`); el.innerHTML='';
  CUR[sub].goals.forEach(g=>{ const l=document.createElement('label'); l.className='hs-code'; l.title=g.text||'';
    l.innerHTML=`<input type="checkbox" data-goal value="${g.code}" ${chosen.includes(g.code)?'checked':''}>${g.code}${g.text?' : '+g.text.slice(0,60)+(g.text.length>60?'…':''):''}`; el.appendChild(l); });
}
function renderComps(sub, term, chosen){
  const el=document.getElementById(`comps_${sub}_${term}`); el.innerHTML=''; const goals=selGoals(sub,term);
  CUR[sub].comps.filter(c=>!goals.length||goals.includes(c.goal)).forEach(c=>{ const l=document.createElement('label'); l.className='hs-code'+(c.review||!c.text?' review':''); l.title=c.text||'(मजकूर उपलब्ध नाही — HPC कार्डावरील कोड)';
    l.innerHTML=`<input type="checkbox" data-comp value="${c.code}" ${chosen.includes(c.code)?'checked':''}>${c.code}${c.text?' : '+c.text.slice(0,70)+(c.text.length>70?'…':''):''}`; el.appendChild(l); });
  if(!el.children.length) el.innerHTML='<span class="hs-small">या विषयासाठी क्षमता-मजकूर अजून लोड झालेला नाही (CURRICULUM_GAPS.md पहा).</span>';
}
// AI rubric suggestions: dropdown lists the competencies ticked above; भरा copies the 9 cells (editable)
function renderRubSug(sub, term, keep){
  const b=box(sub,term), sel=b.querySelector('[data-rubsug]'); if(!sel) return;
  const bank=CUR[sub].rubank||{}, cur=keep||sel.value;
  sel.innerHTML='<option value="">— क्षमता निवडा (वरील क्षमता यादीतून) —</option>';
  selComps(sub,term).forEach(c=>{ if(bank[c]){ const o=document.createElement('option'); o.value=c; o.textContent=c; sel.appendChild(o);} });
  if(cur&&bank[cur]) sel.value=cur;
  const n=Object.keys(bank).length; b.querySelector('.hs-rubsug').style.display=n?'':'none';
}
function fillRubric(sub, term){
  const b=box(sub,term), c=b.querySelector('[data-rubsug]').value, bank=(CUR[sub].rubank||{})[c];
  if(!bank){ toast('प्रथम क्षमता निवडा', true); return; }
  const has=[...b.querySelectorAll('[data-rubcell]')].some(t=>t.value.trim());
  if(has && !confirm('असलेले रुब्रिक मजकूर बदलून AI सूचना भरायच्या?')) return;
  ABIL.forEach(a=>{ b.querySelectorAll(`[data-rubcell="${a}"]`).forEach(t=>t.value=(bank[a]||{})[t.dataset.lv]||'');
    const r=b.querySelector(`[data-rub="${a}"]:checked`); const tx=b.querySelector(`[data-rubtext="${a}"]`); if(r && !tx.value.trim()) tx.value=(bank[a]||{})[r.value]||''; });
  save(sub,term);
}

// Activity & assessment questions bank suggestions
function renderActSug(sub, term, keep){
  const b=box(sub,term), sel=b.querySelector('[data-actsug]'); if(!sel) return;
  const acts=CUR[sub].activities||{}, cur=keep||sel.value;
  sel.innerHTML='<option value="">— क्षमता निवडा (कृती व प्रश्न बँक) —</option>';
  selComps(sub,term).forEach(c=>{
    if(acts[c]){
      const o=document.createElement('option');
      o.value=c;
      o.textContent=c + (acts[c].title ? ' : ' + acts[c].title : '');
      sel.appendChild(o);
    }
  });
  if(cur && acts[cur]) sel.value=cur;
  const n=Object.keys(acts).length;
  const container=b.querySelector('.hs-actsug');
  if(container) container.style.display=n?'':'none';
}

function fillActivity(sub, term){
  const b=box(sub,term), sel=b.querySelector('[data-actsug]');
  if(!sel || !sel.value){ toast('प्रथम क्षमता निवडा', true); return; }
  const c=sel.value, act=(CUR[sub].activities||{})[c];
  if(!act){ toast('या क्षमतेसाठी कृती उपलब्ध नाही', true); return; }
  const actEl = b.querySelector('[data-f="activity_mr"]');
  const qEl = b.querySelector('[data-f="assessment_questions_mr"]');
  const has = (actEl && actEl.value.trim()) || (qEl && qEl.value.trim());
  if(has && !confirm('असलेली कृती व प्रश्न बदलून बँकेतील माहिती भरायची?')) return;
  if(actEl && act.activity_mr) actEl.value = act.activity_mr;
  if(qEl && act.assessment_questions && act.assessment_questions.length) {
    qEl.value = act.assessment_questions.join('\n');
  }
  if(act.approach) {
    const apprCb = b.querySelector(`[data-f="activity_approach"][value="${act.approach}"]`);
    if(apprCb) apprCb.checked = true;
  }
  save(sub, term);
  toast('✓ कृती व प्रश्न भरले');
}

// Phrase bank dropdowns population & selection
function renderPhraseDrops(container, sub){
  const phrases = (CUR[sub] && CUR[sub].phrases) || {};
  container.querySelectorAll('.hs-phrase-drop').forEach(sel => {
    const fKey = sel.dataset.phrase;
    const targetField = sel.dataset.target;
    sel.innerHTML = '';
    
    if (fKey === 'remark') {
      const defOpt = document.createElement('option');
      defOpt.value = '';
      defOpt.textContent = '— विषय शेरा निवडा (बँक) —';
      sel.appendChild(defOpt);
      const groups = [
        { label: '🌟 प्रगत स्तर शेरा', items: phrases['remark_pragat'] || [] },
        { label: '⭐ प्रवीण स्तर शेरा', items: phrases['remark_praveen'] || [] },
        { label: '🌱 प्रारंभिक स्तर शेरा', items: phrases['remark_prarambhik'] || [] },
      ];
      let total = 0;
      groups.forEach(g => {
        if (!g.items.length) return;
        total += g.items.length;
        const og = document.createElement('optgroup');
        og.label = g.label;
        g.items.forEach(txt => {
          const opt = document.createElement('option');
          opt.value = txt;
          opt.textContent = txt.length > 55 ? txt.slice(0, 55) + '…' : txt;
          opt.title = txt;
          og.appendChild(opt);
        });
        sel.appendChild(og);
      });
      sel.style.display = total ? '' : 'none';
    } else {
      const items = phrases[fKey] || [];
      const defOpt = document.createElement('option');
      defOpt.value = '';
      defOpt.textContent = sel.dataset.placeholder || '— बँक —';
      sel.appendChild(defOpt);
      items.forEach(txt => {
        const opt = document.createElement('option');
        opt.value = txt;
        opt.textContent = txt.length > 55 ? txt.slice(0, 55) + '…' : txt;
        opt.title = txt;
        sel.appendChild(opt);
      });
      sel.style.display = items.length ? '' : 'none';
    }

    if (!sel._bound) {
      sel._bound = true;
      sel.addEventListener('change', function(){
        const val = this.value;
        if (!val) return;
        const targetEl = container.querySelector(`[data-f="${targetField}"], [data-sum="${targetField}"]`);
        if (!targetEl) return;
        if (!targetEl.value.trim()) {
          targetEl.value = val;
        } else {
          targetEl.value = targetEl.value.trim() + "\n" + val;
        }
        this.value = '';
        targetEl.dispatchEvent(new Event('input', { bubbles: true }));
        toast('✓ मजकूर जोडला');
      });
    }
  });
}

function collect(sub, term){
  const b=box(sub,term), o={subject_key:sub, term, action:'subject'};
  o.goal_codes=selGoals(sub,term); o.comp_codes=selComps(sub,term); o.topic_keys=selTopics(sub,term);
  b.querySelectorAll('[data-f]').forEach(i=>o[i.dataset.f]=i.value);
  const appr=[...b.querySelectorAll('[data-f="activity_approach"]:checked')].map(i=>i.value); if(b.querySelector('#appr_'+sub+'_'+term)) o.activity_approach=appr;
  o.rubric_json={}; ABIL.forEach(a=>{ const r=b.querySelector(`[data-rub="${a}"]:checked`); const rj={level:r?r.value:null, text:b.querySelector(`[data-rubtext="${a}"]`).value};
    b.querySelectorAll(`[data-rubcell="${a}"]`).forEach(t=>{ if(t.value.trim()) rj[t.dataset.lv]=t.value.trim(); });
    const sg=b.querySelector('[data-rubsug]'); if(sg&&sg.value) rj.src=sg.value; o.rubric_json[a]=rj; });
  const sr={}; b.querySelectorAll('[data-self]').forEach(s=>{ if(s.value) sr[s.dataset.self]=s.value; }); o.self_reflection=sr;
  const pr={}; b.querySelectorAll('[data-peer]').forEach(s=>{ if(s.value) pr[s.dataset.peer]=s.value; }); if(b.querySelector('[data-peer]')) o.peer_reflection=pr;
  const spc={}; b.querySelectorAll('[data-spc]').forEach(s=>{ if(s.value!=='') spc[s.dataset.spc]=+s.value; }); if(b.querySelector('[data-spc]')) o.self_progress_count=spc;
  const ppc={}; b.querySelectorAll('[data-ppc]').forEach(s=>{ if(s.value!=='') ppc[s.dataset.ppc]=+s.value; }); if(b.querySelector('[data-ppc]')) o.peer_progress_count=ppc;
  ['strengths','hurdles'].forEach(L=>{ const v=[...b.querySelectorAll(`[data-list="${L}"]:checked`)].map(i=>i.value); const ot=b.querySelector(`[data-other="${L}"]`).value.trim(); if(ot) v.push('other:'+ot); o[L]=v; });
  // wheel = teacher rubric level + self/peer progress counts (0-2 प्रारंभिक, 3-4 प्रवीण, 5-6 प्रगत)
  const lv=n=>(n===undefined||n===null||n==='')?null:(+n<=2?'prarambhik':(+n<=4?'praveen':'pragat'));
  o.wheel_json={}; 
  ABIL.forEach(a=>{
    const tLev = o.rubric_json[a]?.level || 'pragat';
    const sLev = lv(spc[a]) || tLev;
    const pLev = lv(ppc[a]) || tLev;
    o.wheel_json[a] = {teacher: tLev, student: sLev, peer: pLev};
  });
  return o;
}

// --- Interactive Progress Wheel (प्रगतीचक्र) Auto-Fill & Rendering ---
function generateWheelSVG(w, uid, size = 140) {
  const cx = size / 2, cy = size / 2;
  const scale = size / 300;
  const rCore = 30 * scale, rB = 55 * scale, rP = 82 * scale, rA = 108 * scale, rEval = 124 * scale, rOuter = 146 * scale;
  const rings = [
    {r1: rCore, r2: rB, key: 'B', depth: 0},
    {r1: rB, r2: rP, key: 'P', depth: 1},
    {r1: rP, r2: rA, key: 'A', depth: 2}
  ];
  const doms = {
    awareness: {name: 'जाणीवजागृती', bg: '#fb7185', fill: '#fda4af', soft: '#fff1f2', start: 150, end: 270},
    sensitivity: {name: 'संवेदनशीलता', bg: '#fde047', fill: '#fef08a', soft: '#fefce8', start: 270, end: 390},
    creativity: {name: 'सर्जनशीलता', bg: '#38bdf8', fill: '#7dd3fc', soft: '#f0f9ff', start: 30, end: 150}
  };
  const evals = ['teacher', 'student', 'peer'];
  const depthMap = {'prarambhik': 0, '1': 0, 'b': 0, 'praveen': 1, '2': 1, 'p': 1, 'pragat': 2, '3': 2, 'a': 2};

  function pt(r, deg) {
    const rad = deg * Math.PI / 180;
    return [cx + r * Math.cos(rad), cy + r * Math.sin(rad)];
  }

  function arcPath(r1, r2, a1, a2) {
    const [x1, y1] = pt(r2, a1);
    const [x2, y2] = pt(r2, a2);
    const [x3, y3] = pt(r1, a2);
    const [x4, y4] = pt(r1, a1);
    const large = (a2 - a1) > 180 ? 1 : 0;
    return `M ${x1.toFixed(1)} ${y1.toFixed(1)} A ${r2.toFixed(1)} ${r2.toFixed(1)} 0 ${large} 1 ${x2.toFixed(1)} ${y2.toFixed(1)} L ${x3.toFixed(1)} ${y3.toFixed(1)} A ${r1.toFixed(1)} ${r1.toFixed(1)} 0 ${large} 0 ${x4.toFixed(1)} ${y4.toFixed(1)} Z`;
  }

  let paths = '';
  Object.keys(doms).forEach(dk => {
    const d = doms[dk];
    const span = (d.end - d.start) / 3;
    paths += `<path d="${arcPath(rEval, rOuter, d.start, d.end)}" fill="${d.bg}" stroke="#ffffff" stroke-width="1.2"/>`;

    evals.forEach((ek, ei) => {
      const eStart = d.start + ei * span;
      const eEnd = eStart + span;
      const curLv = String(w[dk]?.[ek] || 'pragat').toLowerCase();
      const maxD = depthMap[curLv] !== undefined ? depthMap[curLv] : 2;

      paths += `<path d="${arcPath(rA, rEval, eStart, eEnd)}" fill="${d.soft}" stroke="#ffffff" stroke-width="0.8"/>`;

      rings.forEach(rg => {
        const isFilled = rg.depth <= maxD;
        const col = isFilled ? d.fill : '#ffffff';
        paths += `<path d="${arcPath(rg.r1, rg.r2, eStart, eEnd)}" fill="${col}" stroke="#94a3b8" stroke-width="0.6"/>`;
      });
    });
  });

  paths += `<circle cx="${cx}" cy="${cy}" r="${rCore}" fill="#ffffff" stroke="#0284c7" stroke-width="2"/>`;
  paths += `<text x="${cx}" y="${cy + 3}" font-size="${(8.5*scale).toFixed(1)}" font-weight="800" fill="#0f172a" text-anchor="middle">प्रगतीचक्र</text>`;

  return `<svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:auto;">${paths}</svg>`;
}

function updateLiveWheel(sub, term) {
  const b = box(sub, term);
  if (!b) return;
  const wrap = b.querySelector('#wheel_svg_' + sub + '_' + term);
  if (!wrap) return;

  const lvName = {'prarambhik': 'प्रारंभिक', 'praveen': 'प्रवीण', 'pragat': 'प्रगत'};
  const lv = n => (n === undefined || n === null || n === '') ? null : (+n <= 2 ? 'prarambhik' : (+n <= 4 ? 'praveen' : 'pragat'));

  const wData = {};
  ABIL.forEach(a => {
    const r = b.querySelector(`[data-rub="${a}"]:checked`);
    const tLev = r ? r.value : 'pragat'; // Default auto-fill to pragat
    const spcInp = b.querySelector(`[data-spc="${a}"]`);
    const ppcInp = b.querySelector(`[data-ppc="${a}"]`);
    const sLev = (spcInp && spcInp.value !== '') ? (lv(spcInp.value) || tLev) : tLev;
    const pLev = (ppcInp && ppcInp.value !== '') ? (lv(ppcInp.value) || tLev) : tLev;

    wData[a] = {teacher: tLev, student: sLev, peer: pLev};

    const tdT = b.querySelector(`#wt_${sub}_${term}_${a}`);
    const tdS = b.querySelector(`#ws_${sub}_${term}_${a}`);
    const tdP = b.querySelector(`#wp_${sub}_${term}_${a}`);
    if (tdT) tdT.innerHTML = `<span class="badge ${tLev==='pragat'?'bg-success':(tLev==='praveen'?'bg-warning text-dark':'bg-secondary')}">${lvName[tLev]||tLev}</span>`;
    if (tdS) tdS.innerHTML = `<span class="badge ${sLev==='pragat'?'bg-success':(sLev==='praveen'?'bg-warning text-dark':'bg-secondary')}">${lvName[sLev]||sLev}</span>`;
    if (tdP) tdP.innerHTML = `<span class="badge ${pLev==='pragat'?'bg-success':(pLev==='praveen'?'bg-warning text-dark':'bg-secondary')}">${lvName[pLev]||pLev}</span>`;
  });

  wrap.innerHTML = generateWheelSVG(wData, sub + '_' + term, 140);
}

function autoFillWheel(sub, term) {
  const b = box(sub, term);
  if (!b) return;

  // 1. Fill all 3 rubric abilities as 'pragat' (or keep selected level)
  ABIL.forEach(a => {
    let r = b.querySelector(`[data-rub="${a}"]:checked`);
    if (!r) {
      const pr = b.querySelector(`[data-rub="${a}"][value="pragat"]`);
      if (pr) {
        pr.checked = true;
        r = pr;
      }
    }
    const curVal = r ? r.value : 'pragat';
    const tx = b.querySelector(`[data-rubtext="${a}"]`);
    const cell = b.querySelector(`[data-rubcell="${a}"][data-lv="${curVal}"]`);
    if (tx && (!tx.value || !tx.value.trim()) && cell && cell.value.trim()) {
      tx.value = cell.value.trim();
    }

    // Set SPC and PPC to 6 (Pragat)
    const spcInp = b.querySelector(`[data-spc="${a}"]`);
    if (spcInp) spcInp.value = (curVal === 'pragat' ? 6 : (curVal === 'praveen' ? 4 : 2));
    const ppcInp = b.querySelector(`[data-ppc="${a}"]`);
    if (ppcInp) ppcInp.value = (curVal === 'pragat' ? 6 : (curVal === 'praveen' ? 4 : 2));
  });

  // 2. Self reflection: set all to 'yes' (😊 हो)
  b.querySelectorAll('[data-self]').forEach(s => {
    if (!s.value) s.value = 'yes';
  });

  // 3. Peer reflection: set all to 'yes' (😊 हो)
  b.querySelectorAll('[data-peer]').forEach(s => {
    if (!s.value) s.value = 'yes';
  });

  // 4. Select a peer student if empty
  const psel = b.querySelector('[data-peer-select]');
  const pinp = b.querySelector('[data-f="peer_name"]');
  if (psel && pinp && !pinp.value.trim() && psel.options.length > 1) {
    for (let i = 1; i < psel.options.length; i++) {
      if (psel.options[i].value) {
        psel.selectedIndex = i;
        pinp.value = psel.options[i].value;
        break;
      }
    }
  }

  // 5. If strengths are empty, check first 2
  const strChecked = b.querySelectorAll('[data-list="strengths"]:checked');
  if (strChecked.length === 0) {
    const allStr = b.querySelectorAll('[data-list="strengths"]');
    if (allStr.length > 0) allStr[0].checked = true;
    if (allStr.length > 1) allStr[1].checked = true;
  }

  // 6. Update live wheel & save
  updateLiveWheel(sub, term);
  save(sub, term);
  toast('✓ प्रगतीचक्र व सर्व मूल्यांकन स्वयंचलित भरले गेले!');
}

function autoFillCurrentSubjectWheel() {
  const activeTab = document.querySelector('.hs-tabs .nav-link.active');
  let target = activeTab ? activeTab.getAttribute('data-bs-target') : '';
  let sub = target ? target.replace('#sub_', '') : (Object.keys(CUR)[0] || '');
  if (sub) {
    autoFillWheel(sub, 1);
    autoFillWheel(sub, 2);
    toast('✓ ' + (CUR[sub] ? CUR[sub].name_mr : sub) + ' विषयाचे दोन्ही सत्रांचे प्रगतीचक्र स्वयंचलित भरले!');
  }
}

async function save(sub, term){
  const st=document.getElementById(`st_${sub}_${term}`); st.innerHTML='<span class="hs-small">⏳</span>';
  const r=await post(collect(sub,term));
  st.innerHTML=r.success?'<span class="hs-ok">✓ जतन</span>':`<span class="hs-bad">✗ ${r.error||''}</span>`;
  if(!r.success) toast('जतन झाले नाही: '+(r.error||''), true);
}
function fill(sub, term){
  const d=DATA[sub][term]||{}, b=box(sub,term);
  renderGoals(sub,term,d.goal_codes||[]); renderComps(sub,term,d.comp_codes||[]); renderTopics(sub,term,d.topic_keys||[]);
  b.querySelectorAll('[data-f]').forEach(i=>{ if(i.type==='checkbox') i.checked=(d.activity_approach||[]).includes(i.value); else i.value=d[i.dataset.f]||''; });

  // Peer student dropdown sync
  const psel = b.querySelector('[data-peer-select]');
  const pinp = b.querySelector('[data-f="peer_name"]');
  if (psel && pinp) {
    if (d.peer_name) {
      pinp.value = d.peer_name;
      psel.value = d.peer_name;
    }
    if (!psel._bound) {
      psel._bound = true;
      psel.addEventListener('change', function(){
        pinp.value = this.value;
        pinp.dispatchEvent(new Event('input', { bubbles: true }));
      });
      pinp.addEventListener('input', function(){
        psel.value = this.value;
      });
    }
  }

  renderPhraseDrops(b, sub);

  ABIL.forEach(a=>{ const rj=(d.rubric_json||{})[a]||{}; const lvl=rj.level||d['level_'+a]; if(lvl){const r=b.querySelector(`[data-rub="${a}"][value="${lvl}"]`); if(r) r.checked=true;} b.querySelector(`[data-rubtext="${a}"]`).value=rj.text||'';
    b.querySelectorAll(`[data-rubcell="${a}"]`).forEach(t=>t.value=rj[t.dataset.lv]||''); });
  renderRubSug(sub,term,((d.rubric_json||{})[ABIL[0]]||{}).src||'');
  renderActSug(sub,term,'');
  b.querySelector('[data-rubfill]').addEventListener('click',()=>fillRubric(sub,term));
  b.querySelector('[data-actfill]')?.addEventListener('click',()=>fillActivity(sub,term));
  b.addEventListener('input', e=>{ if(e.target.matches('[data-rubcell]')){ const a=e.target.dataset.rubcell, r=b.querySelector(`[data-rub="${a}"]:checked`); if(r && r.value===e.target.dataset.lv) b.querySelector(`[data-rubtext="${a}"]`).value=e.target.value; debounce(sub+term, ()=>save(sub,term)); } });
  b.querySelectorAll('[data-self]').forEach(s=>s.value=(d.self_reflection||{})[s.dataset.self]||'');
  b.querySelectorAll('[data-peer]').forEach(s=>s.value=(d.peer_reflection||{})[s.dataset.peer]||'');
  b.querySelectorAll('[data-spc]').forEach(s=>s.value=(d.self_progress_count||{})[s.dataset.spc]??'');
  b.querySelectorAll('[data-ppc]').forEach(s=>s.value=(d.peer_progress_count||{})[s.dataset.ppc]??'');
  ['strengths','hurdles'].forEach(L=>{ const v=d[L]||[]; b.querySelectorAll(`[data-list="${L}"]`).forEach(i=>i.checked=v.includes(i.value)); const ot=v.find(x=>String(x).startsWith('other:')); b.querySelector(`[data-other="${L}"]`).value=ot?ot.slice(6):''; });
  
  // Render live wheel on load
  updateLiveWheel(sub, term);

  b.addEventListener('change', e=>{ 
    if(e.target.matches('[data-goal]')) renderComps(sub,term,selComps(sub,term));
    if(e.target.matches('[data-topics]')) applyTopicComps(sub,term);
    if(e.target.matches('[data-goal],[data-comp],[data-topics]')) {
      renderRubSug(sub,term,'');
      renderActSug(sub,term,'');
    }
    if(e.target.matches('[data-rub]')){ 
      const a=e.target.dataset.rub, val=e.target.value;
      const tx=b.querySelector(`[data-rubtext="${a}"]`), cell=b.querySelector(`[data-rubcell="${a}"][data-lv="${val}"]`); 
      if(cell && cell.value.trim()) tx.value=cell.value.trim(); 

      // Auto-fill student and peer count if empty
      const spcInp = b.querySelector(`[data-spc="${a}"]`);
      const ppcInp = b.querySelector(`[data-ppc="${a}"]`);
      const defCount = (val === 'pragat' ? 6 : (val === 'praveen' ? 4 : 2));
      if (spcInp && (!spcInp.value || spcInp.value === '0')) spcInp.value = defCount;
      if (ppcInp && (!ppcInp.value || ppcInp.value === '0')) ppcInp.value = defCount;

      updateLiveWheel(sub, term);
    }
    if(e.target.matches('[data-spc],[data-ppc],[data-self],[data-peer]')){
      updateLiveWheel(sub, term);
    }
    debounce(sub+term, ()=>save(sub,term)); 
  });
  b.addEventListener('input', e=>{ 
    if(e.target.matches('[data-spc],[data-ppc]')) updateLiveWheel(sub, term);
    if(e.target.matches('textarea,input[type=text],input:not([type]),input[type=number]')) debounce(sub+term, ()=>save(sub,term)); 
  });
}
async function saveSummary(sub){
  const b=document.querySelector(`.hs-term[data-sub="${sub}"][data-summary]`), o={action:'summary', subject_key:sub};
  b.querySelectorAll('[data-sum]').forEach(i=>o[i.dataset.sum]=i.value);
  const crInp = b.querySelector('[data-sum="credit_points_earned"]');
  if (crInp && crInp.value !== '' && crInp.max) {
    const val = parseFloat(crInp.value);
    const maxVal = parseFloat(crInp.max);
    if (!isNaN(val) && !isNaN(maxVal) && val > maxVal) {
      toast(`⚠️ मिळवलेले श्रेयांक कमाल मर्यादेपेक्षा (${maxVal}) जास्त असू शकत नाहीत`, true);
      crInp.classList.add('is-invalid');
    } else {
      crInp.classList.remove('is-invalid');
    }
  }
  const r=await post(o); document.getElementById(`st_${sub}_sum`).innerHTML=r.success?'<span class="hs-ok">✓ जतन</span>':'<span class="hs-bad">✗</span>';
}
Object.keys(CUR).forEach(sub=>{
  [1,2].forEach(t=>fill(sub,t));
  const s=DATA[sub].summary||{}, b=document.querySelector(`.hs-term[data-sub="${sub}"][data-summary]`);
  if (b) {
    b.querySelectorAll('[data-sum]').forEach(i=>i.value=s[i.dataset.sum]??'');
    renderPhraseDrops(b, sub);
    b.addEventListener('change', ()=>debounce(sub+'sum', ()=>saveSummary(sub)));
    b.addEventListener('input', ()=>debounce(sub+'sum', ()=>saveSummary(sub)));
  }
});
async function setStatus(s){ const r=await post({action:'status', status:s}); toast(r.success?(s==='completed'?'✓ कार्ड पूर्ण':'✓ मसुदा'):'त्रुटी', !r.success); }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
