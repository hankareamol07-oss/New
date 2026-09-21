<?php
/** Print view for संकलित / आकारिक मूल्यमापन चाचणी papers (included from paper_view.php; $paper is loaded). */
$meta = $paper['meta'];
$sections = $paper['sections'];
$logo = ep_setting('school_logo');
$watermark = ep_setting('watermark_text');
$logoWatermark = $logo && ep_setting('watermark_logo') === '1';
$english = ($meta['medium'] ?? '') === 'English' && !preg_match('/\p{Devanagari}/u', $paper['title'] . ($sections[0]['instruction'] ?? ''));
$fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
$digits = fn($n) => $english ? (string)$n : strtr((string)$n, ['0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४', '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९']);
$subjectNames = ['Marathi' => 'मराठी', 'Maths' => 'गणित', 'English' => 'इंग्रजी', 'EVS Part 1' => 'परिसर अभ्यास भाग १', 'EVS Part 2' => 'परिसर अभ्यास भाग २', 'EVS' => 'परिसर अभ्यास',
    'Science' => 'विज्ञान', 'Hindi' => 'हिंदी', 'Geography' => 'भूगोल', 'History & Civics' => 'इतिहास व नागरिकशास्त्र'];
$subject = $english ? ($meta['subject'] ?? '') : ($subjectNames[$meta['subject'] ?? ''] ?? ($meta['subject'] ?? ''));
$L = $english
    ? ['std' => 'Std.', 'subject' => 'Subject', 'marks' => 'Marks', 'time' => 'Time', 'date' => 'Date', 'name' => 'Name of the student', 'roll' => 'Roll No.', 'obtained' => 'Marks obtained', 'sign' => 'Teacher\'s sign', 'q' => 'Q.', 'inst' => 'Instructions']
    : ['std' => 'इयत्ता', 'subject' => 'विषय', 'marks' => 'गुण', 'time' => 'वेळ', 'date' => 'दिनांक', 'name' => 'विद्यार्थ्याचे नाव', 'roll' => 'हजेरी क्र.', 'obtained' => 'मिळालेले गुण', 'sign' => 'शिक्षकाची सही', 'q' => 'प्र.', 'inst' => 'सूचना'];
$editUrl = 'assessment_paper.php?edit=' . (int)$paper['paper_id'];
?>
<!DOCTYPE html>
<html lang="<?= $english ? 'en' : 'mr' ?>">
<head>
<meta charset="utf-8">
<title><?= h($paper['title']) ?> - <?= h($paper['std_label']) ?> <?= h($subject) ?></title>
<link href="<?= EP_BASE_URL ?>/assets/paper_print.css" rel="stylesheet">
</head>
<body>
<div class="toolbar no-print">
  <button onclick="window.print()">&#128424; Print / Save as PDF</button>
  <a href="<?= $editUrl ?>">Edit</a>
  <a href="index.php">All papers</a>
  <label><input type="checkbox" id="twoCol"> Two columns</label>
</div>

<div class="sheet assessment" id="sheet">
  <?php if ($logoWatermark): ?><img class="watermark-logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt=""><?php endif; ?>
  <?php if ($watermark): ?><div class="watermark"><?= h($watermark) ?></div><?php endif; ?>

  <header class="paper-header">
    <?php if ($logo): ?><img class="logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt="logo"><?php endif; ?>
    <div class="school">
      <h1><?= h(ep_setting('school_name')) ?></h1>
      <?php if (ep_setting('school_address')): ?><div class="addr"><?= h(ep_setting('school_address')) ?></div><?php endif; ?>
      <h2><?= h($paper['title']) ?></h2>
    </div>
    <?php if ($logo): ?><div class="logo-spacer"></div><?php endif; ?>
  </header>

  <table class="meta">
    <tr>
      <td><b><?= $L['std'] ?> :</b> <?= h($paper['std_label']) ?></td>
      <td class="c"><b><?= $L['subject'] ?> :</b> <?= h($subject) ?></td>
      <td class="r"><b><?= $L['marks'] ?> :</b> <?= $digits($fmt($paper['total_marks'])) ?></td>
    </tr>
    <tr>
      <td><b><?= $L['date'] ?> :</b> <?= $paper['exam_date'] ? $digits(date('d/m/Y', strtotime($paper['exam_date']))) : '____________' ?></td>
      <td class="c"><b><?= $L['time'] ?> :</b> <?= h($paper['duration'] ?: '____________') ?></td>
      <td class="r"><b><?= $L['obtained'] ?> :</b> ________</td>
    </tr>
  </table>
  <?php if (!empty($meta['student_fields'])): ?>
    <div class="student-line"><b><?= $L['name'] ?> :</b> ______________________________________ &nbsp; <b><?= $L['roll'] ?> :</b> ________</div>
  <?php else: ?>
    <div class="student-line"></div>
  <?php endif; ?>

  <?php if (trim((string)$paper['instructions'])): ?>
    <div class="instructions"><b><?= $L['inst'] ?> :</b>
      <ol><?php foreach (preg_split('/\r?\n/', trim($paper['instructions'])) as $line): if (trim($line) !== ''): ?><li><?= h($line) ?></li><?php endif; endforeach; ?></ol>
    </div>
  <?php endif; ?>

  <div class="body">
  <?php $prevQ = null; foreach ($sections as $sec): $label = $L['q'] . $digits($sec['q_no']) . ($sec['sub'] !== '' ? ' (' . h($sec['sub']) . ')' : ''); ?>
    <section class="qsection <?= $prevQ === $sec['q_no'] ? 'sub-section' : '' ?>">
      <div class="sec-title">
        <span><span class="q-label"><?= $label ?></span> <?= h($sec['instruction']) ?></span>
        <span class="sec-marks">(<?= $digits($fmt($sec['marks'])) ?> <?= $L['marks'] ?>)</span>
      </div>
      <?php if ($sec['items']): ?>
      <ol class="questions items <?= $english ? '' : 'devanagari' ?>">
        <?php foreach ($sec['items'] as $i => $it): ?>
          <li class="q">
            <span class="item-no"><?= $digits($i + 1) ?>)</span>
            <div class="qtext"><?= ep_markup($it['text']) ?>
              <?php if (!empty($it['show_image']) && !empty($it['page_image'])): ?>
                <div><img class="qimg page-img" src="<?= EP_BASE_URL ?>/book_page.php?f=<?= rawurlencode($it['page_image']) ?>" alt=""></div>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </section>
  <?php $prevQ = $sec['q_no']; endforeach; ?>
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
