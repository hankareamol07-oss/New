<?php
$page_title = 'HPC — नोंदी (रुब्रिक पद्धत)';
require_once __DIR__ . '/rubric_lib.php';
require_once __DIR__ . '/../includes/hpc_curriculum.php';
requireLogin();

$db = getDB();
hpc_rb_ensure_schema($db);
$school_id = (int) $_SESSION['school_id'];
$school = getSchool();

// वर्ग/तुकडी यादी
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['classes'])) {
    $tStd = $tScope['std'];
    $st = $db->prepare("SELECT grade, COALESCE(section,'') AS section, COUNT(*) AS n FROM students WHERE school_id = ? AND std = ? AND is_active = 1 GROUP BY grade, section ORDER BY grade, section");
    $st->execute([$school_id, $tStd]);
    $classes = $st->fetchAll();
} else {
    $st = $db->prepare("SELECT grade, COALESCE(section,'') AS section, COUNT(*) AS n FROM students WHERE school_id = ? AND is_active = 1 GROUP BY grade, section ORDER BY grade, section");
    $st->execute([$school_id]);
    $classes = $st->fetchAll();
}

$grade = trim((string) ($_GET['grade'] ?? ''));
$section = trim((string) ($_GET['section'] ?? ''));
if ($grade === '' && $classes) { $grade = (string) $classes[0]['grade']; $section = (string) $classes[0]['section']; }

// If teacher, force grade to match teacher standard
if ($tScope && !empty($tScope['stds'])) {
    $check_std = hpc_std_from_grade($grade);
    if (!in_array($check_std, $tScope['stds'], true) && $classes) {
        $grade = (string) $classes[0]['grade'];
        $section = (string) $classes[0]['section'];
    }
}

$grade_std = $grade !== '' ? hpc_std_from_grade($grade) : 1;
$is_stage = ($grade_std >= 3);

$students = [];
if ($grade !== '') {
    $st = $db->prepare("SELECT s.*, h.id AS card_id, h.status FROM students s
        LEFT JOIN hpc_cards h ON h.student_id = s.id AND h.school_id = s.school_id AND h.academic_year = ?
        WHERE s.school_id = ? AND s.is_active = 1 AND s.grade = ? AND COALESCE(s.section,'') = ?
        ORDER BY CAST(s.roll_no AS UNSIGNED), s.name_mr");
    $st->execute([academic_year(), $school_id, $grade, $section]);
    $students = $st->fetchAll();
    $cardIds = array_values(array_filter(array_map(fn($s) => (int) $s['card_id'], $students)));
    $prog = [];
    if ($cardIds && !$is_stage) {
        $in = implode(',', array_fill(0, count($cardIds), '?'));
        $q = $db->prepare("SELECT hpc_card_id, domain_id, competencies, competencies_term2 FROM hpc_domain_assessments WHERE hpc_card_id IN ($in)");
        $q->execute($cardIds);
        $byCard = [];
        foreach ($q->fetchAll() as $r) $byCard[(int) $r['hpc_card_id']][(int) $r['domain_id']] = $r;
        foreach ($byCard as $cid => $rows) $prog[$cid] = hpc_rb_progress($rows);
    }
}

require_once __DIR__ . '/../includes/header.php';
echo hpc_rb_css();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="mb-0 text-primary"><i class="bi bi-card-checklist"></i> HPC — नोंदी</h2>
    <div class="text-muted small">समग्र प्रगती पत्रक · <?= sanitize((string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''))) ?> · <?= sanitize(academic_year()) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/hpc/index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-grid"></i> डॅशबोर्ड</a>
    <a href="<?= APP_URL ?>/cards/list.php" class="btn btn-outline-secondary btn-sm">HPC कार्ड यादी</a>
  </div>
</div>

<div class="hpc-info mb-3">
  <?php if ($is_stage): ?>
    <b>इयत्ता <?= $grade_std ?> (<?= $grade_std <= 5 ? 'पूर्वतयारी स्तर' : 'पूर्व-माध्यमिक स्तर' ?>)</b> — विषयनिहाय मूल्यमापन व ३-स्तरीय रुब्रिक (प्रारंभिक, प्रवीण, प्रगत).
  <?php else: ?>
    <b>इयत्ता १ व २ (पायाभूत स्तर)</b> — ६ विकास क्षेत्रे, ६९ अधिकृत क्षमता व ३ कामगिरी स्तर (निर्झर, पर्वत, आकाश).
  <?php endif; ?>
</div>

<div class="card mb-3"><div class="card-body">
  <div class="fw-semibold mb-2">वर्ग/तुकडी निवडा</div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($classes as $c): 
      $c_std = hpc_std_from_grade($c['grade']);
      $act = ((string) $c['grade'] === $grade && (string) $c['section'] === $section); 
    ?>
    <a class="btn btn-sm <?= $act ? 'btn-primary' : 'btn-outline-primary' ?>" href="?grade=<?= urlencode((string) $c['grade']) ?>&section=<?= urlencode((string) $c['section']) ?>">
      <?= sanitize(hpc_rb_grade_label((string) $c['grade'])) ?><?= $c['section'] !== '' ? ' — ' . sanitize((string) $c['section']) : '' ?> 
      <span class="badge <?= $c_std <= 2 ? 'bg-info text-dark' : 'bg-light text-dark' ?>"><?= (int) $c['n'] ?></span>
    </a>
    <?php endforeach; ?>
    <?php if (!$classes): ?><span class="text-muted">विद्यार्थी नाहीत — आधी विद्यार्थी जोडा.</span><?php endif; ?>
  </div>
</div></div>

<?php if ($grade !== ''): 
  $chk_wiz_access = can_access_hpc_class($grade_std, $school_id);
?>
<?php if (!$chk_wiz_access['allowed']): ?>
<div class="card border-warning shadow-sm my-3 text-center p-4 bg-white" style="border-radius:12px;">
  <div class="mb-2">
    <span class="rounded-circle bg-warning-subtle text-warning d-inline-flex p-3 fs-3">
      <i class="bi bi-shield-lock-fill text-danger"></i>
    </span>
  </div>
  <h4 class="fw-bold text-dark mb-1">इयत्ता <?= $grade_std ?> HPC (उच्च वर्ग) सशुल्क उपलब्ध आहे</h4>
  <p class="text-muted small mx-auto mb-3" style="max-width: 600px;">
    <?= sanitize($chk_wiz_access['message'] ?? 'मोफत योजनेमध्ये केवळ इयत्ता १ ली व २ री (पायाभूत स्तर) उपलब्ध आहे. इयत्ता ३ ते ८ च्या समग्र प्रगती पत्रकासाठी कृपया योजना अपग्रेड करा.') ?>
  </p>
  <div>
    <a href="<?= APP_URL ?>/subscription/plans.php" class="btn btn-warning fw-bold px-4 py-2 shadow-sm">
      <i class="bi bi-rocket-takeoff-fill me-1"></i> योजना अपग्रेड करा (सशुल्क सदस्य व्हा)
    </a>
  </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body p-0">
  <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
    <div class="fw-semibold">
      📋 <?= sanitize(hpc_rb_grade_label($grade)) ?><?= $section !== '' ? ' — ' . sanitize($section) : '' ?>
      <span class="badge <?= $is_stage ? 'bg-primary' : 'bg-info text-dark' ?> ms-2"><?= $is_stage ? ($grade_std <= 5 ? 'पूर्वतयारी' : 'पूर्व-माध्यमिक') : 'पायाभूत स्तर' ?></span>
    </div>
    <form class="d-flex gap-2" method="get" action="<?= $is_stage ? (APP_URL . '/hpc/stage_form.php') : (APP_URL . '/hpc/create.php') ?>">
      <select name="student_id" class="form-select form-select-sm" required>
        <option value="">-- विद्यार्थी निवडा --</option>
        <?php foreach ($students as $s): ?><option value="<?= (int) $s['id'] ?>"><?= sanitize((string) ($s['name_mr'] ?: $s['name'])) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-primary text-nowrap">HPC भरणे सुरू करा →</button>
    </form>
  </div>
  <table class="table table-hover mb-0 align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>विद्यार्थी</th>
        <?php if (!$is_stage): ?>
          <th class="text-center">सत्र 1</th>
          <th class="text-center">सत्र 2</th>
        <?php else: ?>
          <th class="text-center">स्थिती</th>
        <?php endif; ?>
        <th class="text-center">कृती</th>
        <th class="text-center">PDF</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($students as $i => $s): 
      $p = $prog[(int) $s['card_id']] ?? [1 => 0, 2 => 0]; 
      $sid = (int) $s['id']; 
      $cid = (int) $s['card_id'];
    ?>
      <tr>
        <td><?= sanitize((string) ($s['roll_no'] ?: $i + 1)) ?></td>
        <td>
          <a href="<?= $is_stage ? (APP_URL . '/hpc/stage_form.php?student_id=' . $sid) : (APP_URL . '/hpc/create.php?student_id=' . $sid) ?>" class="fw-semibold text-decoration-none">
            <?= sanitize((string) ($s['name_mr'] ?: $s['name'])) ?>
          </a>
          <span class="text-muted small ms-1"><?= sanitize((string) ($s['gender'] ?? '')) ?></span>
        </td>
        
        <?php if (!$is_stage): ?>
          <?php foreach ([1, 2] as $sem): ?>
          <td class="text-center"><span class="badge <?= $p[$sem] === 6 ? 'bg-success' : ($p[$sem] > 0 ? 'bg-warning text-dark' : 'bg-light text-muted') ?>"><?= $p[$sem] === 6 ? '✓ ' : '' ?><?= $p[$sem] ?>/6</span></td>
          <?php endforeach; ?>
        <?php else: ?>
          <td class="text-center">
            <?php if ($s['status'] === 'completed'): ?>
              <span class="badge bg-success">पूर्ण</span>
            <?php elseif ($s['status'] === 'draft'): ?>
              <span class="badge bg-warning text-dark">मसुदा</span>
            <?php else: ?>
              <span class="badge bg-light text-muted border">अपूर्ण</span>
            <?php endif; ?>
          </td>
        <?php endif; ?>

        <td class="text-center text-nowrap">
          <?php if ($is_stage): ?>
            <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/hpc/stage_form.php?student_id=<?= $sid ?>">
              <i class="bi bi-pencil-square"></i> विषय मूल्यांकन
            </a>
          <?php else: ?>
            <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/hpc/create.php?student_id=<?= $sid ?>" title="सविस्तर HPC फॉर्म">
              <i class="bi bi-pencil-square"></i> HPC फॉर्म
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= APP_URL ?>/hpc/step1_rubric.php?student_id=<?= $sid ?>" title="३-स्टेप रुब्रिक">१-३</a>
          <?php endif; ?>
        </td>

        <td class="text-center">
          <?php if ($cid): ?>
            <?php if ($is_stage): ?>
              <a class="btn btn-sm btn-success" target="_blank" href="<?= APP_URL ?>/cards/print_hpc_stage.php?id=<?= $cid ?>">PDF</a>
            <?php else: ?>
              <a class="btn btn-sm btn-success" target="_blank" href="<?= APP_URL ?>/cards/print_hpc_19.php?id=<?= $cid ?>&format=official19">PDF</a>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-muted small">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$students): ?><tr><td colspan="<?= $is_stage ? 5 : 6 ?>" class="text-center text-muted py-4">या वर्गात विद्यार्थी नाहीत.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
