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
    ? ['std' => 'Std.', 'subject' => 'Subject', 'marks' => 'Marks', 'time' => 'Time', 'date' => 'Date', 'name' => 'Name of the student', 'roll' => 'Roll No.', 'obtained' => 'Marks obtained', 'sign' => 'Teacher\'s sign', 'q' => 'Q.', 'inst' => 'Instructions',
       'school' => 'School', 'centre' => 'Centre', 'written' => 'Written', 'oral' => 'Oral', 'total' => 'Total', 'got' => 'Obtained', 'lo' => 'Based on learning outcomes', 'test' => 'Test No.', 'oral_sec' => 'Oral']
    : ['std' => 'इयत्ता', 'subject' => 'विषय', 'marks' => 'गुण', 'time' => 'वेळ', 'date' => 'दिनांक', 'name' => 'विद्यार्थ्याचे नाव', 'roll' => 'हजेरी क्र.', 'obtained' => 'मिळालेले गुण', 'sign' => 'शिक्षकाची सही', 'q' => 'प्र.', 'inst' => 'सूचना',
       'school' => 'शाळा', 'centre' => 'केंद्र', 'written' => 'लेखी', 'oral' => 'तोंडी', 'total' => 'एकूण गुण', 'got' => 'प्राप्त गुण', 'lo' => 'अध्ययन निष्पत्तीवर आधारित', 'test' => 'चाचणी क्रमांक', 'oral_sec' => 'तोंडी'];
$editUrl = 'assessment_paper.php?edit=' . (int)$paper['paper_id'];
$teacherKey = !empty($_GET['key']);   // teacher copy: correct pairing printed under each जोड्या लावा table
$matchLang = $english ? 'en' : (($meta['medium'] ?? '') === 'Hindi' || ($meta['subject'] ?? '') === 'Hindi' ? 'hi' : 'mr');
$viewUrl = 'paper_view.php?id=' . (int)$paper['paper_id'];

// Paper format: 'standard' (compact, no answer space) or 'lines' (minishala-style उत्तर-लेखन paper).
// ?format= overrides the saved choice; the saved choice falls back to the school-wide default setting.
$format = $_GET['format'] ?? ($meta['format'] ?? ep_setting('default_paper_format', 'standard'));
if ($teacherKey && !isset($_GET['format'])) $format = 'standard';   // teacher key needs no writing space
$linesFormat = $format === 'lines';
$std = (int)($meta['standard'] ?? 0);
$oralMarks = (float)($meta['oral_marks'] ?? 0);
$writtenMarks = (float)$paper['total_marks'] - $oralMarks;
$examWord = ($meta['exam_type'] ?? 'sankalit') === 'aakarik' ? ($english ? 'Formative Test' : 'आकारिक चाचणी') : ($english ? 'Summative Test' : 'संकलित चाचणी');
$oralSections = $linesFormat ? array_values(array_filter($sections, fn($s) => !empty($s['oral']))) : [];
$writtenSections = $linesFormat ? array_values(array_filter($sections, fn($s) => empty($s['oral']))) : $sections;
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
  <a href="<?= $viewUrl . ($teacherKey ? '' : '&key=1') ?><?= $linesFormat ? '&format=lines' : '' ?>"><?= $teacherKey ? 'Student paper' : 'Answer key (जोड्या)' ?></a>
  <a href="<?= $viewUrl ?>&format=<?= $linesFormat ? 'standard' : 'lines' ?><?= $teacherKey ? '&key=1' : '' ?>"><?= $linesFormat ? 'Compact (no answer lines)' : 'उत्तर-लेखन ओळींसह (answer lines)' ?></a>
  <a href="index.php">All papers</a>
  <?php if (!$linesFormat): ?><label><input type="checkbox" id="twoCol"> Two columns</label><?php endif; ?>
</div>

<div class="sheet assessment <?= $linesFormat ? 'lines-format' : '' ?> <?= $std >= 1 && $std <= 2 ? 'std12' : '' ?>" id="sheet">
  <?php if ($logoWatermark): ?><img class="watermark-logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt=""><?php endif; ?>
  <?php if ($watermark): ?><div class="watermark"><?= h($watermark) ?></div><?php endif; ?>

<?php if ($linesFormat): ?>
  <header class="paper-header ms-header">
    <?php if ($logo): ?><img class="logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt="logo"><?php endif; ?>
    <div class="school">
      <h1><?= h(ep_setting('school_name')) ?></h1>
      <?php if (ep_setting('school_address')): ?><div class="addr"><?= h(ep_setting('school_address')) ?></div><?php endif; ?>
    </div>
    <?php if ($logo): ?><div class="logo-spacer"></div><?php endif; ?>
  </header>
  <div class="ms-strip">
    <span class="ms-test"><?= h($examWord) ?> <?= $L['test'] ?> <?= $digits((int)($meta['test_no'] ?? 1)) ?></span>
    <span class="ms-std"><?= h($paper['std_label']) ?></span>
    <span class="ms-subject"><?= h($subject) ?></span>
    <span class="ms-lo"><?= $L['lo'] ?></span>
  </div>
  <div class="ms-student">
    <div class="ms-fields">
      <div class="ms-row"><b><?= $L['name'] ?> :</b><span class="dots"></span></div>
      <div class="ms-row"><b><?= $L['school'] ?> :</b><span class="dots"></span></div>
      <div class="ms-row"><b><?= $L['centre'] ?> :</b><span class="dots short"></span> <b class="ms-sign"><?= $L['sign'] ?></b></div>
      <div class="ms-row small"><b><?= $L['date'] ?> :</b> <?= $paper['exam_date'] ? $digits(date('d/m/Y', strtotime($paper['exam_date']))) : '' ?><span class="dots short"></span> <b><?= $L['time'] ?> :</b> <?= h($paper['duration']) ?></div>
    </div>
    <div class="ms-marksbox">
      <div class="ms-roll"><b><?= $L['roll'] ?></b> <span class="dots short"></span></div>
      <table class="ms-marks">
        <tr><td></td><th><?= $L['written'] ?></th><?php if ($oralMarks > 0): ?><th><?= $L['oral'] ?></th><?php endif; ?></tr>
        <tr><th><?= $L['got'] ?></th><td></td><?php if ($oralMarks > 0): ?><td></td><?php endif; ?></tr>
        <tr><th><?= $L['total'] ?></th><td><?= $digits($fmt($writtenMarks)) ?> <?= $L['marks'] ?></td><?php if ($oralMarks > 0): ?><td><?= $digits($fmt($oralMarks)) ?> <?= $L['marks'] ?></td><?php endif; ?></tr>
      </table>
    </div>
  </div>
  <div class="ms-rule"></div>
<?php else: ?>
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
<?php endif; ?>

  <?php if (trim((string)$paper['instructions'])): ?>
    <div class="instructions"><b><?= $L['inst'] ?> :</b>
      <ol><?php foreach (preg_split('/\r?\n/', trim($paper['instructions'])) as $line): if (trim($line) !== ''): ?><li><?= h($line) ?></li><?php endif; endforeach; ?></ol>
    </div>
  <?php endif; ?>

  <?php
  // Renders one question section. In the lines format each item gets its answer space (ep_answer_space).
  $renderSection = function (array $sec, ?int $prevQ) use ($L, $digits, $fmt, $english, $matchLang, $teacherKey, $linesFormat, $std): void {
      $label = $L['q'] . $digits($sec['q_no']) . ($sec['sub'] !== '' ? ' (' . h($sec['sub']) . ')' : '');
      $space = $linesFormat ? ep_answer_space($sec, $std) : ['mode' => 'none'];
      $mode = $space['mode'];
      // a textbook question of another type than the section (e.g. fallback fill) keeps its own answer space
      $itemSpaceOf = fn(array $it) => ($linesFormat && !empty($it['qtype']) && $it['qtype'] !== ($sec['qtype'] ?? '') && $space['source'] !== 'teacher') ? ep_answer_space(['qtype' => $it['qtype']], $std) : $space;
      $grid2 = $mode === 'grid2';
      foreach ($sec['items'] as $it) {
          if ($grid2 && (ep_item_pairs($it) || !in_array($itemSpaceOf($it)['mode'], ['short', 'grid2'], true))) $grid2 = false;
      }
      ?>
    <section class="qsection <?= $prevQ === $sec['q_no'] ? 'sub-section' : '' ?> ans-mode-<?= h($mode) ?>">
      <div class="sec-title">
        <span><span class="q-label"><?= $label ?></span> <?= h($sec['instruction']) ?></span>
        <span class="sec-marks">(<?= $digits($fmt($sec['marks'])) ?> <?= $L['marks'] ?>)</span>
      </div>
      <?php if ($sec['items']): ?>
      <ol class="questions items <?= $english ? '' : 'devanagari' ?> <?= $grid2 ? 'grid2' : '' ?>">
        <?php foreach ($sec['items'] as $i => $it):
            $pairs = ep_item_pairs($it);
            $itemSpace = $itemSpaceOf($it);
            $itemMode = $pairs ? 'none' : $itemSpace['mode']; ?>
          <li class="q">
            <span class="item-no"><?= $digits($i + 1) ?>)</span>
            <div class="qtext">
              <?php if ($pairs): ?>
                <?= ep_markup(ep_match_stem($it['text'])) ?>
                <?= ep_match_table($pairs, $matchLang, $teacherKey) ?>
              <?php elseif ($itemMode === 'inline' && !str_contains($it['text'], '___')): ?>
                <?= ep_markup($it['text']) ?> <?= ep_answer_space_html(['mode' => 'tail']) ?>
              <?php elseif (in_array($itemMode, ['short', 'grid2', 'tail'], true)): ?>
                <div class="q-inline"><span><?= ep_markup($it['text']) ?></span><?= ep_answer_space_html($itemSpace) ?></div>
              <?php else: ?>
                <?= ep_markup($it['text']) ?>
              <?php endif; ?>
              <?php if (!empty($it['show_image']) && !empty($it['page_image'])): ?>
                <div><img class="qimg page-img" src="<?= EP_BASE_URL ?>/book_page.php?f=<?= rawurlencode($it['page_image']) ?>" alt=""></div>
              <?php endif; ?>
              <?php if (in_array($itemMode, ['lines', 'blank', 'box'], true)): ?>
                <?= ep_answer_space_html($itemSpace) ?>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php elseif ($linesFormat && in_array($mode, ['lines', 'blank', 'box'], true)): ?>
        <?= ep_answer_space_html($space) ?>
      <?php endif; ?>
    </section>
  <?php };
  ?>

  <div class="body">
  <?php $prevQ = null; foreach ($writtenSections as $sec): $renderSection($sec, $prevQ); $prevQ = $sec['q_no']; endforeach; ?>
  </div>

  <?php if ($oralSections): ?>
  <div class="ms-oral">
    <div class="ms-oral-title"><?= $L['oral_sec'] ?> <?php if ($oralMarks > 0): ?>(<?= $L['marks'] ?> <?= $digits($fmt($oralMarks)) ?>)<?php endif; ?></div>
    <?php $prevQ = null; foreach ($oralSections as $sec): $renderSection($sec, $prevQ); $prevQ = $sec['q_no']; endforeach; ?>
  </div>
  <?php endif; ?>

  <footer class="paper-footer"><?= h(ep_setting('paper_footer')) ?></footer>
</div>
<script>
var tc = document.getElementById('twoCol');
if (tc) tc.addEventListener('change', function () {
  document.getElementById('sheet').classList.toggle('two-col', this.checked);
});
</script>
</body>
</html>
