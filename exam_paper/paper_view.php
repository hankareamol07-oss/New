<?php
require_once __DIR__ . '/includes/functions.php';

$paper = ep_paper((int)($_GET['id'] ?? 0));
if (!$paper) {
    http_response_code(404);
    die('Paper not found');
}
$showAnswers = !empty($_GET['answers']);
$allIds = [];
foreach ($paper['sections'] as $s) {
    $allIds = array_merge($allIds, $s['question_ids']);
}
$questions = [];
foreach (ep_questions_by_ids($allIds) as $q) {
    $questions[$q['question_id']] = $q;
}
$logo = ep_setting('school_logo');
$watermark = ep_setting('watermark_text');
$logoWatermark = $logo && ep_setting('watermark_logo') === '1';
$competitive = ($paper['paper_type'] ?? 'regular') === 'competitive';
$stdLabel = $competitive ? $paper['std_label'] : $paper['standard_name'];
$subjectLabel = $competitive ? $paper['exam_name'] : $paper['subject_name'];
$fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
// Competitive papers are pure MCQ: drop the "उकल : -----" / "Solution: ____" working-space lines from the source markup.
$qtext = fn(?string $s) => ep_markup($competitive ? preg_replace('/^\s*(उकल|Solution|Sol\.?|Ans\.?)\s*:?\s*[-_.\s]*$/miu', '', (string)$s) : $s);
$qNo = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= h($paper['title']) ?> - <?= h($stdLabel) ?> <?= h($subjectLabel) ?></title>
<link href="<?= EP_BASE_URL ?>/assets/paper_print.css" rel="stylesheet">
</head>
<body>
<div class="toolbar no-print">
  <button onclick="window.print()">&#128424; Print / Save as PDF</button>
  <a href="paper_view.php?id=<?= (int)$paper['paper_id'] ?><?= $showAnswers ? '' : '&answers=1' ?>"><?= $showAnswers ? 'Question paper' : 'Answer key' ?></a>
  <a href="<?= $competitive ? 'competitive_paper.php' : 'create_paper.php' ?>?edit=<?= (int)$paper['paper_id'] ?>">Edit</a>
  <a href="index.php">All papers</a>
  <label><input type="checkbox" id="twoCol"> Two columns</label>
</div>

<div class="sheet <?= $competitive ? 'competitive' : '' ?>" id="sheet">
  <?php if ($logoWatermark): ?><img class="watermark-logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt=""><?php endif; ?>
  <?php if ($watermark): ?><div class="watermark"><?= h($watermark) ?></div><?php endif; ?>

  <header class="paper-header">
    <?php if ($logo): ?><img class="logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt="logo"><?php endif; ?>
    <div class="school">
      <h1><?= h(ep_setting('school_name')) ?></h1>
      <?php if (ep_setting('school_address')): ?><div class="addr"><?= h(ep_setting('school_address')) ?></div><?php endif; ?>
      <h2><?= h($paper['title']) ?><?= $showAnswers ? ' - ANSWER KEY' : '' ?></h2>
    </div>
    <?php if ($logo): ?><div class="logo-spacer"></div><?php endif; ?>
  </header>

  <table class="meta">
    <tr>
      <td><b>Std:</b> <?= h($stdLabel) ?> <?php if (!$competitive): ?><span class="muted">(<?= h($paper['medium']) ?>)</span><?php endif; ?></td>
      <td class="c"><b><?= $competitive ? 'Exam' : 'Subject' ?>:</b> <?= h($subjectLabel) ?></td>
      <td class="r"><b>Marks:</b> <?= $fmt($paper['total_marks']) ?></td>
    </tr>
    <tr>
      <td><b>Date:</b> <?= $paper['exam_date'] ? h(date('d/m/Y', strtotime($paper['exam_date']))) : '____________' ?></td>
      <td class="c"><b>Time:</b> <?= h($paper['duration'] ?: '____________') ?></td>
      <td class="r"><b>Roll No:</b> ____________</td>
    </tr>
  </table>
  <div class="student-line"><b>Student Name:</b> ________________________________________________</div>

  <?php if (trim($paper['instructions'])): ?>
    <div class="instructions"><b>Instructions:</b>
      <ol><?php foreach (preg_split('/\r?\n/', trim($paper['instructions'])) as $line): if (trim($line) !== ''): ?><li><?= h($line) ?></li><?php endif; endforeach; ?></ol>
    </div>
  <?php endif; ?>

  <?php if ($competitive && $showAnswers): ?>
    <table class="answer-grid">
      <?php $n = 0; $cells = []; foreach ($paper['sections'] as $sec) foreach ($sec['question_ids'] as $qid) { $n++; $cells[] = [$n, isset($questions[$qid]) ? $questions[$qid] : null]; } ?>
      <?php foreach (array_chunk($cells, 4) as $row): ?>
        <tr><?php foreach ($row as [$n, $q]): ?><th><?= $n ?></th><td><?= $q ? ep_markup(mb_strimwidth(trim((string)$q['answer_markup']), 0, 60, '…')) : '-' ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
    </table>
    <h3 class="answers-heading">Detailed answers</h3>
  <?php endif; ?>

  <div class="body">
  <?php foreach ($paper['sections'] as $si => $sec): $isComp = $competitive; $perQ = $sec['marks_per_q'] ?? null; $cnt = count($sec['question_ids']); ?>
    <section class="qsection">
      <?php if ($isComp): ?>
        <div class="sec-head">
          <div class="sec-name"><?= h($sec['title']) ?></div>
          <?php $marathi = preg_match('/\p{Devanagari}/u', $sec['title'] . $sec['subtitle']); ?>
          <div class="sec-range">( <?= $marathi ? 'प्रश्न क्रमांक' : 'Q. No.' ?> <?= $qNo + 1 ?> <?= $marathi ? 'ते' : 'to' ?> <?= $qNo + $cnt ?><?= $sec['subtitle'] !== '' ? ' - ' . h($sec['subtitle']) : '' ?> )</div>
        </div>
      <?php else: ?>
        <div class="sec-title"><span><?= h($sec['title']) ?></span><span class="sec-marks">[<?= $fmt($sec['marks']) ?>]</span></div>
      <?php endif; ?>
      <ol class="questions <?= $sec['type'] === 'mcq' ? 'mcq' : '' ?>" <?= $isComp ? 'start="' . ($qNo + 1) . '"' : '' ?>>
      <?php foreach ($sec['question_ids'] as $qi => $qid): if ($isComp) $qNo++; if (!isset($questions[$qid])) continue; $q = $questions[$qid]; ?>
        <li class="q">
          <?php if ($isComp && $perQ !== null): ?><span class="q-marks">[<?= $fmt($perQ) ?>]</span><?php endif; ?>
          <div class="qtext">
            <?php if ($q['passage']): ?><div class="passage"><?= ep_markup($q['passage']) ?></div><?php endif; ?>
            <?= $qtext($q['markup']) ?>
            <?php if ($q['image_url']): ?><div><img class="qimg" src="<?= h($q['image_url']) ?>" alt=""></div><?php endif; ?>
            <?php if ($q['markup2'] && !preg_match('/^\s*Ans\.?\s*:?\s*$/i', $q['markup2'])): ?><div><?= $qtext($q['markup2']) ?></div><?php endif; ?>
            <?php if ($q['image2_url']): ?><div><img class="qimg" src="<?= h($q['image2_url']) ?>" alt=""></div><?php endif; ?>
            <?php if ($q['markup3']): ?><div><?= $qtext($q['markup3']) ?></div><?php endif; ?>
            <?php if ($q['sub_questions']): ?>
              <ol class="subq"><?php foreach ($q['sub_questions'] as $sq): ?><li><?= ep_markup($sq['markup']) ?>
                <?php if (!empty($sq['image_url'])): ?><div><img class="qimg" src="<?= h($sq['image_url']) ?>" alt=""></div><?php endif; ?>
                <?php if ($showAnswers && ($sq['answer_markup'] || !empty($sq['answer_image_url']))): ?><div class="answer"><b>Ans:</b> <?= ep_markup($sq['answer_markup']) ?><?php if (!empty($sq['answer_image_url'])): ?><div><img class="qimg" src="<?= h($sq['answer_image_url']) ?>" alt=""></div><?php endif; ?></div><?php endif; ?></li><?php endforeach; ?></ol>
            <?php endif; ?>
            <?php if ($showAnswers && ($q['answer_markup'] || $q['answer_image_url'])): ?>
              <div class="answer"><b>Ans:</b> <?= ep_markup($q['answer_markup']) ?>
                <?php if ($q['answer_image_url']): ?><div><img class="qimg" src="<?= h($q['answer_image_url']) ?>" alt=""></div><?php endif; ?>
                <?php if ($q['answer_markup2']): ?><div><?= ep_markup($q['answer_markup2']) ?></div><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
      </ol>
    </section>
  <?php endforeach; ?>
  </div>

  <footer class="paper-footer"><?= h(ep_setting('paper_footer')) ?></footer>
</div>
<script>
document.getElementById('twoCol').addEventListener('change', function () {
  document.getElementById('sheet').classList.toggle('two-col', this.checked);
});
</script>
</body>
</html>
