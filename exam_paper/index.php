<?php
require_once __DIR__ . '/includes/functions.php';

if (isset($_POST['delete_id'])) {
    ep_db()->prepare('DELETE FROM ep_papers WHERE paper_id = ?')->execute([(int)$_POST['delete_id']]);
    header('Location: index.php');
    exit;
}

$papers = ep_db()->query('SELECT p.paper_id, p.title, p.paper_type, p.exam_name, p.std_label, p.exam_date, p.total_marks, p.created_at, s.name AS standard_name, s.medium, sub.name AS subject_name
    FROM ep_papers p LEFT JOIN ep_standards s ON s.standard_id=p.standard_id LEFT JOIN ep_subjects sub ON sub.subject_id=p.subject_id
    ORDER BY p.created_at DESC')->fetchAll();
$stats = ep_db()->query('SELECT (SELECT COUNT(*) FROM ep_standards) std, (SELECT COUNT(*) FROM ep_subjects) sub, (SELECT COUNT(*) FROM ep_chapters) ch, (SELECT COUNT(*) FROM ep_questions) q')->fetch();

$epPage = 'papers';
$epTitle = 'My Papers';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Question Papers</h3>
  <div>
    <a href="create_paper.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create New Paper</a>
    <a href="competitive_paper.php" class="btn btn-outline-primary"><i class="bi bi-trophy"></i> Scholarship / Navodaya Paper</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php foreach (['Classes' => $stats['std'], 'Subjects' => $stats['sub'], 'Chapters' => $stats['ch'], 'Questions' => $stats['q']] as $label => $n): ?>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm"><div class="card-body py-2">
      <div class="text-muted small"><?= $label ?> in bank</div>
      <div class="fs-4 fw-semibold"><?= number_format((int)$n) ?></div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (!$papers): ?>
  <div class="alert alert-info">No papers created yet. Click <b>Create New Paper</b> to generate your first exam paper.</div>
<?php else: ?>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th>#</th><th>Title</th><th>Class</th><th>Subject</th><th>Exam Date</th><th>Marks</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($papers as $p): ?>
    <tr>
      <td><?= (int)$p['paper_id'] ?></td>
      <td><?= h($p['title']) ?></td>
      <?php if ($p['paper_type'] === 'competitive'): ?>
        <td><?= h($p['std_label']) ?> <span class="badge bg-warning text-dark">Competitive</span></td>
        <td><?= h($p['exam_name']) ?></td>
      <?php else: ?>
        <td><?= h($p['standard_name']) ?> <small class="text-muted">(<?= h($p['medium']) ?>)</small></td>
        <td><?= h($p['subject_name']) ?></td>
      <?php endif; ?>
      <td><?= $p['exam_date'] ? h(date('d-m-Y', strtotime($p['exam_date']))) : '-' ?></td>
      <td><?= rtrim(rtrim(number_format((float)$p['total_marks'], 2, '.', ''), '0'), '.') ?></td>
      <td><small><?= h(date('d-m-Y H:i', strtotime($p['created_at']))) ?></small></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>" target="_blank"><i class="bi bi-printer"></i> Print</a>
        <a class="btn btn-sm btn-outline-success" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&answers=1" target="_blank"><i class="bi bi-key"></i> Answer Key</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $p['paper_type'] === 'competitive' ? 'competitive_paper.php' : 'create_paper.php' ?>?edit=<?= (int)$p['paper_id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this paper?')">
          <input type="hidden" name="delete_id" value="<?= (int)$p['paper_id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
