<?php
/** Teacher's answer key for a homework sheet: homework items with answers + quiz questions with correct answers. Not for students. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/quiz.php';

$hw = ep_homework((int)($_GET['id'] ?? 0));
if (!$hw) {
    http_response_code(404);
    exit('Homework not found');
}
$logo = ep_setting('school_logo');
$english = $hw['medium'] === 'English' && !preg_match('/\p{Devanagari}/u', $hw['title']);
$matchLang = $english ? 'en' : ($hw['medium'] === 'Hindi' || $hw['subject'] === 'Hindi' ? 'hi' : 'mr');
$digits = fn($n) => $english ? (string)$n : strtr((string)$n, ['0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४', '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९']);
$L = $english
    ? ['key' => 'Answer Key (Teacher copy)', 'std' => 'Std.', 'subject' => 'Subject', 'date' => 'Date', 'topic' => 'Chapter / Topic', 'teacher' => 'Teacher', 'hw' => 'Homework', 'quiz' => 'Quiz', 'ans' => 'Ans.', 'noans' => '— (no answer entered)', 'typed' => 'Expected answer']
    : ['key' => 'उत्तरसूची (शिक्षकांसाठी)', 'std' => 'इयत्ता', 'subject' => 'विषय', 'date' => 'दिनांक', 'topic' => 'पाठ / घटक', 'teacher' => 'शिक्षक', 'hw' => 'गृहपाठ', 'quiz' => 'प्रश्नमंजुषा', 'ans' => 'उत्तर :', 'noans' => '— (उत्तर नोंदवलेले नाही)', 'typed' => 'अपेक्षित उत्तर'];
$quiz = $hw['quiz'];
$std = $hw['std_label'] ?: 'Std ' . $hw['standard'];
$letters = ['A', 'B', 'C', 'D', 'E', 'F'];
?>
<!DOCTYPE html>
<html lang="<?= $english ? 'en' : 'mr' ?>">
<head>
<meta charset="utf-8">
<title><?= h($L['key']) ?> - <?= h($hw['title']) ?> - <?= h($std) ?></title>
<link href="<?= EP_BASE_URL ?>/assets/paper_print.css" rel="stylesheet">
<style>
.sheet.key .meta td { padding: 3px 0; }
.sheet.key .hw-title { font-size: 14pt; font-weight: 700; margin: 12px 0 4px; border-bottom: 1px solid #000; padding-bottom: 2px; }
.sheet.key ol.items { list-style: none; padding-left: 0; margin: 0; }
.sheet.key ol.items > li { display: flex; gap: 8px; margin-bottom: 7px; font-size: 12pt; line-height: 1.5; page-break-inside: avoid; }
.sheet.key .item-no { flex: 0 0 26px; font-weight: 600; }
.sheet.key .q { white-space: pre-wrap; }
.sheet.key .ans { margin-top: 2px; padding: 2px 8px; border-left: 3px solid #198754; background: #f2faf5; white-space: pre-wrap; }
.sheet.key .ans b { color: #146c43; }
.sheet.key .opts { margin: 2px 0 0 0; padding-left: 0; list-style: none; display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; font-size: 11.5pt; }
.sheet.key .opts li.ok { font-weight: 700; text-decoration: underline; }
.sheet.key .ribbon { display: inline-block; border: 2px solid #000; padding: 2px 10px; font-weight: 700; margin-top: 4px; }
.sheet.key .muted { color: #666; }
</style>
</head>
<body>
<div class="toolbar no-print">
  <button onclick="window.print()">&#128424; Print / Save as PDF</button>
  <a href="homework_view.php?id=<?= (int)$hw['hw_id'] ?>">Homework sheet</a>
  <a href="homework_new.php?edit=<?= (int)$hw['hw_id'] ?>">Edit</a>
  <a href="homework.php">All homework</a>
  <?php if ($quiz): ?><a href="quiz_results.php?id=<?= (int)$quiz['quiz_id'] ?>">Quiz results</a><?php endif; ?>
</div>

<div class="sheet key" id="sheet">
  <header class="paper-header">
    <?php if ($logo): ?><img class="logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt="logo"><?php endif; ?>
    <div class="school">
      <h1><?= h(ep_setting('school_name')) ?></h1>
      <h2><?= h($hw['title']) ?></h2>
      <div class="ribbon"><?= h($L['key']) ?></div>
    </div>
    <?php if ($logo): ?><div class="logo-spacer"></div><?php endif; ?>
  </header>

  <table class="meta">
    <tr>
      <td><b><?= $L['std'] ?> :</b> <?= h($std) ?> <?= h($hw['division']) ?></td>
      <td class="c"><b><?= $L['subject'] ?> :</b> <?= h($hw['subject']) ?></td>
      <td class="r"><b><?= $L['date'] ?> :</b> <?= $digits(date('d/m/Y', strtotime($hw['hw_date']))) ?></td>
    </tr>
    <tr>
      <td colspan="2"><b><?= $L['topic'] ?> :</b> <?= h($hw['topic'] ?: ($hw['chapter_title'] ?? '')) ?></td>
      <td class="r"><?php if ($hw['teacher']): ?><b><?= $L['teacher'] ?> :</b> <?= h($hw['teacher']) ?><?php endif; ?></td>
    </tr>
  </table>

  <?php if (!empty($hw['notes'])): ?>
  <div class="hw-title"><?= $english ? 'Short notes' : 'थोडक्यात टिपा' ?></div>
  <ul class="<?= $english ? '' : 'devanagari' ?>" style="padding-left:22px"><?php foreach ($hw['notes'] as $n): ?><li><?= h($n) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <?php if ($hw['items']): ?>
  <div class="hw-title"><?= $L['hw'] ?></div>
  <ol class="items <?= $english ? '' : 'devanagari' ?>">
    <?php foreach ($hw['items'] as $i => $it): ?>
      <li><span class="item-no"><?= $digits($i + 1) ?>.</span>
        <div style="flex:1">
          <?php if ($pairs = ep_item_pairs($it)): ?>
            <div class="q"><?= h(ep_match_stem($it['text'])) ?></div>
            <?= ep_match_table($pairs, $matchLang) ?>
            <div class="ans"><b><?= $L['ans'] ?></b> <?= h(ep_match_key_text($pairs, $matchLang)) ?></div>
          <?php else: ?>
            <div class="q"><?= h($it['text']) ?></div>
            <div class="ans"><b><?= $L['ans'] ?></b> <?= trim((string)($it['answer'] ?? '')) !== '' ? h($it['answer']) : '<span class="muted">' . $L['noans'] . '</span>' ?></div>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php if ($quiz): ?>
  <div class="hw-title"><?= $L['quiz'] ?> — <?= h($quiz['title']) ?> <span class="muted" style="font-weight:400;font-size:11pt">(<?= h($quiz['code']) ?>)</span></div>
  <ol class="items <?= $english ? '' : 'devanagari' ?>">
    <?php foreach ($quiz['questions'] as $i => $q): ?>
      <li><span class="item-no"><?= $digits($i + 1) ?>.</span>
        <div style="flex:1">
          <div class="q"><?= h($q['text']) ?></div>
          <?php if ($q['kind'] === 'mcq'): ?>
            <ul class="opts">
              <?php foreach ($q['options'] as $k => $o): ?>
                <li class="<?= $k === (int)$q['answer'] ? 'ok' : '' ?>"><?= $letters[$k] ?? $k + 1 ?>) <?= h($o) ?><?= $k === (int)$q['answer'] ? ' &#10004;' : '' ?></li>
              <?php endforeach; ?>
            </ul>
            <div class="ans"><b><?= $L['ans'] ?></b> <?= $letters[(int)$q['answer']] ?? (int)$q['answer'] + 1 ?>) <?= h($q['options'][(int)$q['answer']] ?? '') ?><?php if (!empty($q['explain'])): ?> <span class="muted">— <?= h($q['explain']) ?></span><?php endif; ?></div>
          <?php else: ?>
            <div class="ans"><b><?= $L['typed'] ?> :</b> <?= trim((string)$q['answer']) !== '' ? h($q['answer']) : '<span class="muted">' . $L['noans'] . '</span>' ?></div>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
  <?php if (ep_setting('paper_footer')): ?><div class="paper-footer"><?= h(ep_setting("paper_footer")) ?></div><?php endif; ?>
</div>
</body>
</html>
