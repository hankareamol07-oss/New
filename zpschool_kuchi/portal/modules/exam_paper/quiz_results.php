<?php
/** Teacher view: attempts + per-question analysis for one quiz. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/quiz.php';

$quiz = ep_quiz((int)($_GET['id'] ?? 0));
if (!$quiz) {
    http_response_code(404);
    exit('Quiz not found');
}
$st = ep_db()->prepare('SELECT * FROM ep_quiz_attempts WHERE quiz_id = ? ORDER BY submitted_at DESC');
$st->execute([(int)$quiz['quiz_id']]);
$attempts = $st->fetchAll();
$hw = ep_db()->prepare('SELECT hw_id FROM ep_homework WHERE quiz_id = ? LIMIT 1');
$hw->execute([(int)$quiz['quiz_id']]);
$hwId = (int)$hw->fetchColumn();
$url = ep_quiz_url($quiz);

$nQ = count($quiz['questions']);
$correctPer = array_fill(0, $nQ, 0);
foreach ($attempts as $a) {
    [, , $detail] = ep_quiz_score($quiz, json_decode($a['answers_json'], true) ?: []);
    foreach ($detail as $i => $d) {
        if ($d['correct']) $correctPer[$i]++;
    }
}
$avg = $attempts ? array_sum(array_map(fn($a) => $a['total'] ? $a['score'] / $a['total'] * 100 : 0, $attempts)) / count($attempts) : 0;

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quiz_' . $quiz['code'] . '_results.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Roll', 'Division', 'Score', 'Total', 'Percent', 'Submitted']);
    foreach ($attempts as $a) {
        fputcsv($out, [$a['student_name'], $a['roll_no'], $a['division'], $a['score'], $a['total'], $a['total'] ? round($a['score'] / $a['total'] * 100) : 0, $a['submitted_at']]);
    }
    exit;
}

$epPage = 'homework';
$epTitle = 'Quiz results';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h3 class="mb-0"><?= h($quiz['title']) ?></h3>
    <div class="text-muted small">Std <?= (int)$quiz['standard'] ?> · <?= h($quiz['subject']) ?> · <?= h($quiz['topic']) ?> · <?= $nQ ?> questions · code <b><?= h($quiz['code']) ?></b></div>
  </div>
  <div class="btn-group">
    <a class="btn btn-outline-primary" href="<?= h($url) ?>" target="_blank"><i class="bi bi-phone"></i> Open quiz</a>
    <?php if ($hwId): ?><a class="btn btn-outline-secondary" href="homework_view.php?id=<?= $hwId ?>" target="_blank"><i class="bi bi-printer"></i> Homework PDF</a><?php endif; ?>
    <a class="btn btn-outline-secondary" href="?id=<?= (int)$quiz['quiz_id'] ?>&export=csv"><i class="bi bi-download"></i> CSV</a>
    <a class="btn btn-outline-secondary" href="homework.php">Back</a>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-sm-4"><div class="card shadow-sm text-center"><div class="card-body"><div class="fs-2 fw-bold"><?= count($attempts) ?></div><div class="text-muted small">attempts</div></div></div></div>
  <div class="col-sm-4"><div class="card shadow-sm text-center"><div class="card-body"><div class="fs-2 fw-bold"><?= round($avg) ?>%</div><div class="text-muted small">average score</div></div></div></div>
  <div class="col-sm-4"><div class="card shadow-sm text-center"><div class="card-body"><div class="fs-6 fw-bold text-break"><a href="<?= h($url) ?>"><?= h($url) ?></a></div><div class="text-muted small">quiz link</div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-header fw-semibold">Students</div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>#</th><th>Name</th><th>Roll</th><th>Div</th><th>Score</th><th>%</th><th>Submitted</th></tr></thead>
      <tbody>
      <?php if (!$attempts): ?><tr><td colspan="7" class="text-muted text-center py-3">No attempts yet.</td></tr><?php endif; ?>
      <?php foreach ($attempts as $i => $a): $pct = $a['total'] ? round($a['score'] / $a['total'] * 100) : 0; ?>
        <tr><td><?= $i + 1 ?></td><td><?= h($a['student_name']) ?></td><td><?= h($a['roll_no']) ?></td><td><?= h($a['division']) ?></td>
          <td><?= (int)$a['score'] ?> / <?= (int)$a['total'] ?></td>
          <td><span class="badge <?= $pct >= 60 ? 'bg-success' : ($pct >= 35 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= $pct ?>%</span></td>
          <td class="text-nowrap small"><?= date('d/m H:i', strtotime($a['submitted_at'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header fw-semibold">Question-wise correct %</div>
    <ol class="list-group list-group-numbered list-group-flush">
      <?php foreach ($quiz['questions'] as $i => $q): $p = $attempts ? round($correctPer[$i] / count($attempts) * 100) : 0; ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between gap-2"><span class="small"><?= h(mb_strimwidth($q['text'], 0, 90, '…')) ?></span><b class="text-nowrap"><?= $p ?>%</b></div>
          <div class="progress" style="height:5px"><div class="progress-bar <?= $p >= 60 ? 'bg-success' : 'bg-danger' ?>" style="width:<?= $p ?>%"></div></div>
          <div class="small text-muted"><?= $q['kind'] === 'mcq' ? 'Ans: ' . h($q['options'][$q['answer']] ?? '') : 'Ans: ' . h($q['answer']) ?></div>
        </li>
      <?php endforeach; ?>
    </ol></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
