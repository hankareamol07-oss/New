<?php
/** Print view for संकलित / आकारिक मूल्यमापन चाचणी papers (included from paper_view.php; $paper is loaded). */
require_once __DIR__ . '/includes/header_styles.php';

$currentSet = strtoupper(trim($_GET['set'] ?? 'A'));
if (!in_array($currentSet, ['A', 'B', 'C', 'D'], true)) {
    $currentSet = 'A';
}
$paper = ep_scramble_paper($paper, $currentSet);
$meta = $paper['meta'];
$sections = $paper['sections'];
$headerStyle = ep_header_style();
$sig = ep_paper_signature($paper);
$publicBase = ep_setting('public_base_url') ?: EP_BASE_URL;
$verifyUrl = rtrim($publicBase, '/') . '/verify.php?id=' . (int)$paper['paper_id'] . '&sig=' . $sig . '&set=' . $currentSet;

$logoUrl = ep_logo_url();
$watermark = ep_setting('watermark_text');
$logoWatermark = $logoUrl && ep_setting('watermark_logo') === '1';
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
$teacherKey = !empty($_GET['key']);   // teacher copy: correct pairing & answers printed under each question
$matchLang = $english ? 'en' : (($meta['medium'] ?? '') === 'Hindi' || ($meta['subject'] ?? '') === 'Hindi' ? 'hi' : 'mr');
$viewUrl = 'paper_view.php?id=' . (int)$paper['paper_id'];

// If teacher key requested, pre-fetch any answers from textbook question bank that aren't stored in item
$dbAnswers = [];
if ($teacherKey) {
    $bqIds = [];
    foreach ($sections as $s) {
        foreach ($s['items'] as $it) {
            if (!empty($it['bq_id'])) $bqIds[] = (int)$it['bq_id'];
        }
    }
    if ($bqIds) {
        $bqIds = array_values(array_unique(array_filter($bqIds)));
        $placeholders = implode(',', array_fill(0, count($bqIds), '?'));
        $st = ep_db()->prepare("SELECT bq_id, answer FROM ep_book_questions WHERE bq_id IN ($placeholders) AND answer IS NOT NULL AND answer != ''");
        $st->execute($bqIds);
        $dbAnswers = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

// Paper format: 'standard' (compact, no answer space) or 'lines' (उत्तर-लेखन paper).
// ?format= overrides the saved choice; the saved choice falls back to the school-wide default setting.
$format = $_GET['format'] ?? ($meta['format'] ?? ep_setting('default_paper_format', 'lines'));
if ($teacherKey && !isset($_GET['format'])) $format = 'standard';   // teacher key needs no writing space
$linesFormat = $format === 'lines';
$std = (int)($meta['standard'] ?? 0);
$oralMarks = (float)($meta['oral_marks'] ?? 0);
$writtenMarks = (float)$paper['total_marks'] - $oralMarks;
$examType = $meta['exam_type'] ?? 'sankalit';
if ($examType === 'daily_study') {
    $examWord = $english ? 'Daily Study Worksheet' : 'दैनंदिन स्वाध्याय पत्रक';
} elseif ($examType === 'aakarik') {
    $examWord = $english ? 'Formative Test' : 'आकारिक चाचणी';
} else {
    $examWord = $english ? 'Summative Test' : 'संकलित चाचणी';
}
$oralSections = $linesFormat ? array_values(array_filter($sections, fn($s) => !empty($s['oral']))) : [];
$writtenSections = $linesFormat ? array_values(array_filter($sections, fn($s) => empty($s['oral']))) : $sections;
?>
<!DOCTYPE html>
<html lang="<?= $english ? 'en' : 'mr' ?>">
<head>
<meta charset="utf-8">
<title><?= h($paper['title']) ?> (Set <?= $currentSet ?>)<?= $teacherKey ? ' - उत्तरसूची' : '' ?> - <?= h($paper['std_label']) ?> <?= h($subject) ?></title>
<link href="<?= EP_BASE_URL ?>/assets/paper_print.css" rel="stylesheet">
<!-- KaTeX Formula & Scientific Equation Rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}, {left: '\\(', right: '\\)', display: false}, {left: '\\[', right: '\\]', display: true}]});"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
.set-pill-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 9px;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  background: #475569;
  border: 1px solid #64748b;
  border-radius: 4px;
  text-decoration: none;
  transition: all 0.15s ease;
}
.set-pill-btn:hover { background: #334155; color: #fff; }
.set-pill-btn.active { background: #0d6efd; border-color: #0d6efd; color: #fff; box-shadow: 0 0 0 2px rgba(13,110,253,0.35); }
.sec-stamp-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px dashed #64748b;
  border-radius: 4px;
  padding: 4px 10px;
  margin-top: 14px;
  font-size: 8.5pt;
  background: #f8fafc;
  color: #334155;
}
@media print {
  .sec-stamp-box {
    background: transparent !important;
    border-color: #444 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
</head>
<body>
<div class="toolbar no-print">
  <button onclick="window.print()">&#128424; Print / Save as PDF</button>

  <!-- Multi-Set Scrambler Studio Selector -->
  <div style="display:inline-flex; align-items:center; gap:3px; background:#1e293b; padding:2px 6px; border-radius:5px; border:1px solid #334155;">
    <span style="font-size:11px; font-weight:700; color:#94a3b8; margin-right:3px;">संच (SET):</span>
    <?php foreach (['A', 'B', 'C', 'D'] as $sBtn): ?>
      <a href="<?= $viewUrl ?>&set=<?= $sBtn ?><?= $linesFormat ? '&format=lines' : '' ?><?= $teacherKey ? '&key=1' : '' ?>&header_style=<?= $headerStyle ?>"
         class="set-pill-btn <?= $currentSet === $sBtn ? 'active' : '' ?>">
         <?= $sBtn ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Header Style Dropdown -->
  <select onchange="location.href=this.value;" style="background:#334155; color:#fff; border:1px solid #475569; border-radius:4px; font-size:12px; padding:3px 6px; font-weight:600;">
    <?php 
    $baseParam = "{$viewUrl}&set={$currentSet}" . ($linesFormat ? '&format=lines' : '') . ($teacherKey ? '&key=1' : '');
    ?>
    <option value="<?= $baseParam ?>&header_style=board" <?= $headerStyle === 'board' ? 'selected' : '' ?>>📋 बोर्ड परीक्षा स्वरूप (Board Style)</option>
    <option value="<?= $baseParam ?>&header_style=center" <?= $headerStyle === 'center' ? 'selected' : '' ?>>🏛️ मध्यवर्ती मानचिन्ह (Centered Logo)</option>
    <option value="<?= $baseParam ?>&header_style=modern" <?= $headerStyle === 'modern' ? 'selected' : '' ?>>🏢 आधुनिक २-कॉलम (Modern Card)</option>
    <option value="<?= $baseParam ?>&header_style=compact" <?= $headerStyle === 'compact' ? 'selected' : '' ?>>📄 कॉम्पॅक्ट स्वरूप (Compact)</option>
  </select>

  <!-- Premium Word (.docx) Export Button -->
  <a href="export_docx.php?id=<?= (int)$paper['paper_id'] ?>&set=<?= $currentSet ?>" style="background:#0d6efd; color:#fff; font-weight:700; padding:4px 10px; border-radius:4px; text-decoration:none;">
    <i class="bi bi-file-earmark-word"></i> 📥 Word (.docx) [Premium]
  </a>

  <a href="<?= $editUrl ?>">Edit</a>
  <a href="<?= $viewUrl . ($teacherKey ? '' : '&key=1') ?>&set=<?= $currentSet ?><?= $linesFormat ? '&format=lines' : '' ?>&header_style=<?= $headerStyle ?>"><?= $teacherKey ? 'Student paper' : 'Answer key (उत्तरसूची)' ?></a>
  <a href="<?= $viewUrl ?>&set=<?= $currentSet ?>&format=<?= $linesFormat ? 'standard' : 'lines' ?><?= $teacherKey ? '&key=1' : '' ?>&header_style=<?= $headerStyle ?>" style="background:#ffc107; color:#212529; font-weight:700; padding:4px 10px; border-radius:4px; text-decoration:none;">
    <?= $linesFormat ? '📄 केवळ प्रश्नपत्रिका (Compact - No Lines)' : '✍️ उत्तर-लेखन ओळींसह (With Student Answer Lines)' ?>
  </a>
  <a href="api.php?action=create_quiz_from_paper&paper_id=<?= (int)$paper['paper_id'] ?>&redirect=1" target="_blank" style="background:#198754; color:#fff; font-weight:700; padding:4px 10px; border-radius:4px; text-decoration:none;">
    &#128241; ऑनलाईन क्विझ
  </a>
  <?php if (!$linesFormat): ?><label><input type="checkbox" id="twoCol"> Two columns</label><?php endif; ?>
  <label style="margin-left:14px; font-weight:600; cursor:pointer;"><input type="checkbox" id="toggleBorder" checked> फ्रेम बॉर्डर</label>
</div>

<div class="sheet assessment with-border <?= $linesFormat ? 'lines-format' : '' ?> <?= $std >= 1 && $std <= 2 ? 'std12' : '' ?>" id="sheet">
  <?php if ($logoWatermark && $logoUrl): ?><img class="watermark-logo" src="<?= h($logoUrl) ?>" alt=""><?php endif; ?>
  <?php if ($watermark): ?><div class="watermark"><?= h($watermark) ?></div><?php endif; ?>

  <?php
  // Render Dynamic School Header using chosen Style Template
  ep_render_header([
      'style' => $headerStyle,
      'school_name' => ep_setting('school_name'),
      'school_address' => ep_setting('school_address'),
      'logo_url' => $logoUrl,
      'title' => $paper['title'],
      'std_label' => $paper['std_label'],
      'subject' => $subject,
      'total_marks' => $digits($fmt($paper['total_marks'])),
      'exam_date' => $paper['exam_date'] ? date('d/m/Y', strtotime($paper['exam_date'])) : '____________',
      'duration' => $paper['duration'],
      'set' => $currentSet,
      'show_answers' => $teacherKey,
      'english' => $english,
  ]);
  ?>
  <?php if ($linesFormat): ?>
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
  <?php elseif (!empty($meta['student_fields'])): ?>
    <div class="student-line" style="margin-top: 8px;"><b><?= $L['name'] ?> :</b> ______________________________________ &nbsp; <b><?= $L['roll'] ?> :</b> ________</div>
  <?php endif; ?>

  <?php if (trim((string)$paper['instructions'])): ?>
    <div class="instructions"><b><?= $L['inst'] ?> :</b>
      <ol><?php foreach (preg_split('/\r?\n/', trim($paper['instructions'])) as $line): if (trim($line) !== ''): ?><li><?= h($line) ?></li><?php endif; endforeach; ?></ol>
    </div>
  <?php endif; ?>

  <?php
  // Renders one question section. In the lines format each item gets its answer space (ep_answer_space).
  $renderSection = function (array $sec, ?int $prevQ) use ($L, $digits, $fmt, $english, $matchLang, $teacherKey, $linesFormat, $std, $dbAnswers): void {
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
            $itemMode = $pairs ? 'none' : $itemSpace['mode'];
            $itAnswer = trim((string)($it['answer'] ?? ($dbAnswers[$it['bq_id'] ?? 0] ?? ''))); ?>
          <li class="q">
            <span class="item-no"><?= $digits($i + 1) ?>)</span>
            <div class="qtext">
              <?php 
              $renderedText = ep_markup($it['text']);
              $hasInlineBlank = str_contains($renderedText, 'ans-inline-blank');
              ?>
              <?php if ($pairs): ?>
                <?= ep_markup(ep_match_stem($it['text'])) ?>
                <?= ep_match_table($pairs, $matchLang, $teacherKey) ?>
              <?php elseif ($itemMode === 'inline'): ?>
                <?= $renderedText ?> <?= !$hasInlineBlank ? ep_answer_space_html(['mode' => 'tail']) : '' ?>
              <?php elseif (in_array($itemMode, ['short', 'grid2', 'tail'], true)): ?>
                <div class="q-inline"><span><?= $renderedText ?></span><?= ep_answer_space_html($itemSpace) ?></div>
              <?php else: ?>
                <?= $renderedText ?>
              <?php endif; ?>
              <?php if (!empty($it['show_image']) && !empty($it['page_image'])): ?>
                <div><img class="qimg page-img" src="<?= EP_BASE_URL ?>/book_page.php?f=<?= rawurlencode($it['page_image']) ?>" alt=""></div>
              <?php endif; ?>
              <?php if ($teacherKey && !$pairs && $itAnswer !== ''): ?>
                <div class="match-key ans-key-box"><b><?= $english ? 'Ans:' : 'उत्तर:' ?></b> <?= ep_markup($itAnswer) ?></div>
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

  <?php if (!$linesFormat): ?>
  <div class="exam-signatures" style="margin-top: 25px; display: flex; justify-content: space-between; padding: 12px 10px; border-top: 1px dashed #aaa; font-size: 13px; font-weight: bold;">
    <div>प्राप्त गुण: ________ / <?= $digits($fmt($paper['total_marks'])) ?></div>
    <div>परीक्षक स्वाक्षरी: ____________________</div>
    <div>मुख्याध्यापक स्वाक्षरी व शिक्का</div>
  </div>
  <?php endif; ?>

  <div class="sec-stamp-box">
    <div style="display:flex; align-items:center; gap:8px;">
      <div id="paperQr" style="width:38px; height:38px; background:#fff; padding:2px; border:1px solid #ccc; flex:0 0 38px;"></div>
      <div>
        <div><b>🔒 डिजिटल सुरक्षा पडताळणी कोड (Digital Security Token):</b> <code style="font-weight:700; color:#0f172a;"><?= h($sig) ?></code></div>
        <div style="font-size:8pt; color:#64748b;">HMAC-SHA256 प्रमाणित अधिकृत परीक्षा प्रत &bull; संच: <b><?= $currentSet ?></b> &bull; QR कोड स्कॅन करून सत्यप्रत पडताळा.</div>
      </div>
    </div>
    <div style="text-align:right; font-size:8pt; color:#64748b;">
      प्रणाली पडताळणी: <b><?= date('d/m/Y') ?></b>
    </div>
  </div>

  <footer class="paper-footer"><?= h(ep_setting('paper_footer')) ?></footer>
</div>
<script>
var tc = document.getElementById('twoCol');
if (tc) tc.addEventListener('change', function () {
  document.getElementById('sheet').classList.toggle('two-col', this.checked);
});
var tb = document.getElementById('toggleBorder');
if (tb) tb.addEventListener('change', function () {
  document.getElementById('sheet').classList.toggle('with-border', this.checked);
});
if (typeof QRCode !== 'undefined' && document.getElementById('paperQr')) {
  new QRCode(document.getElementById('paperQr'), {
    text: <?= json_encode($verifyUrl) ?>,
    width: 36,
    height: 36,
    correctLevel: QRCode.CorrectLevel.M
  });
}
</script>
</body>
</html>
