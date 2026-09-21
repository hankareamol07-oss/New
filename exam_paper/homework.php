<?php
/** Daily homework list. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/quiz.php';

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : '';
$std = (int)($_GET['standard'] ?? 0);
$where = '1=1';
$params = [];
if ($date) { $where .= ' AND h.hw_date = ?'; $params[] = $date; }
if ($std) { $where .= ' AND h.standard = ?'; $params[] = $std; }
$st = ep_db()->prepare("SELECT h.*, q.code quiz_code, (SELECT COUNT(*) FROM ep_quiz_attempts a WHERE a.quiz_id = h.quiz_id) attempts,
                        (SELECT ROUND(AVG(a.score / NULLIF(a.total,0) * 100)) FROM ep_quiz_attempts a WHERE a.quiz_id = h.quiz_id) avg_pct
                        FROM ep_homework h LEFT JOIN ep_quizzes q ON q.quiz_id = h.quiz_id WHERE $where ORDER BY h.hw_date DESC, h.hw_id DESC LIMIT 300");
$st->execute($params);
$rows = $st->fetchAll();

$epPage = 'homework';
$epTitle = 'Daily Homework';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h3 class="mb-0">दैनिक गृहपाठ <small class="text-muted fs-6">Daily Homework</small></h3>
  <a class="btn btn-primary" href="homework_new.php"><i class="bi bi-plus-circle"></i> New homework</a>
</div>
<form class="row g-2 mb-3" method="get">
  <div class="col-auto"><input type="date" class="form-control" name="date" value="<?= h($date) ?>"></div>
  <div class="col-auto">
    <select class="form-select" name="standard"><option value="">All classes</option>
      <?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>" <?= $std === $i ? 'selected' : '' ?>>Std <?= $i ?></option><?php endfor; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button> <a class="btn btn-link" href="homework.php">Clear</a></div>
</form>
<?php if (!$rows): ?>
  <div class="alert alert-info">No homework yet. Click <b>New homework</b> — pick class, subject and today's chapter, add homework items (type or pick from the textbook exercise), attach a 10–15 question quiz and print / share the PDF.</div>
<?php else: ?>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th>Date</th><th>Class</th><th>Subject</th><th>Chapter / Topic</th><th>Items</th><th>Quiz</th><th>Teacher</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $n = count(json_decode($r['items_json'], true) ?: []); ?>
    <tr>
      <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['hw_date'])) ?></td>
      <td><?= h($r['std_label'] ?: 'Std ' . $r['standard']) ?> <?= h($r['division']) ?></td>
      <td><?= h($r['subject']) ?> <small class="text-muted"><?= h($r['medium']) ?></small></td>
      <td><?= h($r['topic']) ?></td>
      <td><?= $n ?></td>
      <td>
        <?php if ($r['quiz_code']): ?>
          <a href="quiz_results.php?id=<?= (int)$r['quiz_id'] ?>" class="text-decoration-none"><span class="badge bg-success"><?= (int)$r['attempts'] ?> attempts</span></a>
          <?php if ($r['avg_pct'] !== null): ?><small class="text-muted">avg <?= (int)$r['avg_pct'] ?>%</small><?php endif; ?>
        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
      </td>
      <td><?= h($r['teacher']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="homework_view.php?id=<?= (int)$r['hw_id'] ?>" target="_blank" title="Print / PDF"><i class="bi bi-printer"></i> PDF</a>
        <a class="btn btn-sm btn-outline-secondary" href="homework_new.php?edit=<?= (int)$r['hw_id'] ?>"><i class="bi bi-pencil"></i></a>
        <button class="btn btn-sm btn-outline-danger" onclick="delHw(<?= (int)$r['hw_id'] ?>)"><i class="bi bi-trash"></i></button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php endif; ?>
<script>
function delHw(id) {
  if (!confirm('Delete this homework (and its quiz results)?')) return;
  fetch('api.php?action=delete_homework', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ hw_id: id }) })
    .then(() => location.reload());
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
