<?php
require_once __DIR__ . '/includes/functions.php';

$paper = ep_paper((int)($_GET['id'] ?? 0));
if (!$paper) {
    http_response_code(404);
    die('Paper not found');
}
if (in_array(($paper['paper_type'] ?? 'regular'), ['assessment', 'daily_study'], true)) {
    require __DIR__ . '/paper_view_assessment.php';
    exit;
}
require_once __DIR__ . '/includes/header_styles.php';

$currentSet = strtoupper(trim($_GET['set'] ?? 'A'));
if (!in_array($currentSet, ['A', 'B', 'C', 'D'], true)) {
    $currentSet = 'A';
}
$paper = ep_scramble_paper($paper, $currentSet);
$headerStyle = ep_header_style();
$sig = ep_paper_signature($paper);
$publicBase = ep_setting('public_base_url') ?: EP_BASE_URL;
$verifyUrl = rtrim($publicBase, '/') . '/verify.php?id=' . (int)$paper['paper_id'] . '&sig=' . $sig . '&set=' . $currentSet;

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
$format = $_GET['format'] ?? ($paper['meta']['format'] ?? ep_setting('default_paper_format', 'lines'));
if ($showAnswers && !isset($_GET['format'])) $format = 'standard';
$linesFormat = ($format === 'lines');
$qNo = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= h($paper['title']) ?> (Set <?= $currentSet ?>) - <?= h($stdLabel) ?> <?= h($subjectLabel) ?></title>
<link href="<?= EP_BASE_URL ?>/assets/paper_print.css" rel="stylesheet">
<!-- KaTeX Formula & Scientific Equation Rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}, {left: '\\(', right: '\\)', display: false}, {left: '\\[', right: '\\]', display: true}]});"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
.sheet.with-omr-bubbles .default-mcq-box { display: none !important; }
.sheet.with-omr-bubbles .mcq-omr-strip { display: inline-flex !important; }
.mcq-omr-strip {
  display: none;
  float: right;
  margin-left: 8px;
  align-items: center;
  gap: 3.5px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 1.5px 5px;
}
.q-omr-bubble {
  width: 17px;
  height: 17px;
  border: 1.2px solid #000;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 8pt;
  font-weight: 700;
  background: #fff;
  color: #000;
  user-select: none;
}
.q-omr-bubble.shaded {
  background: #000 !important;
  color: #fff !important;
  border-color: #000 !important;
}
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
  .mcq-omr-strip {
    background: transparent !important;
  }
  .q-omr-bubble {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
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
      <a href="paper_view.php?id=<?= (int)$paper['paper_id'] ?>&set=<?= $sBtn ?><?= $showAnswers ? '&answers=1' : '' ?><?= $linesFormat ? '&format=lines' : '' ?>&header_style=<?= $headerStyle ?>"
         class="set-pill-btn <?= $currentSet === $sBtn ? 'active' : '' ?>">
         <?= $sBtn ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Header Style Dropdown -->
  <select onchange="location.href=this.value;" style="background:#334155; color:#fff; border:1px solid #475569; border-radius:4px; font-size:12px; padding:3px 6px; font-weight:600;">
    <?php 
    $baseParam = "paper_view.php?id=" . (int)$paper['paper_id'] . "&set={$currentSet}" . ($showAnswers ? '&answers=1' : '') . ($linesFormat ? '&format=lines' : '');
    ?>
    <option value="<?= $baseParam ?>&header_style=board" <?= $headerStyle === 'board' ? 'selected' : '' ?>>📋 बोर्ड परीक्षा स्वरूप (Board Style)</option>
    <option value="<?= $baseParam ?>&header_style=center" <?= $headerStyle === 'center' ? 'selected' : '' ?>>🏛️ मध्यवर्ती मानचिन्ह (Centered Logo)</option>
    <option value="<?= $baseParam ?>&header_style=modern" <?= $headerStyle === 'modern' ? 'selected' : '' ?>>🏢 आधुनिक २-कॉलम (Modern Card)</option>
    <option value="<?= $baseParam ?>&header_style=compact" <?= $headerStyle === 'compact' ? 'selected' : '' ?>>📄 कॉम्पॅक्ट स्वरूप (Compact)</option>
  </select>

  <a href="paper_view.php?id=<?= (int)$paper['paper_id'] ?>&set=<?= $currentSet ?><?= $showAnswers ? '' : '&answers=1' ?><?= $linesFormat ? '&format=lines' : '' ?>&header_style=<?= $headerStyle ?>"><?= $showAnswers ? 'Question paper' : 'Answer key' ?></a>
  <a href="paper_view.php?id=<?= (int)$paper['paper_id'] ?>&set=<?= $currentSet ?>&format=<?= $linesFormat ? 'standard' : 'lines' ?><?= $showAnswers ? '&answers=1' : '' ?>&header_style=<?= $headerStyle ?>" style="background:#ffc107; color:#212529; font-weight:700; padding:4px 10px; border-radius:4px; text-decoration:none;">
    <?= $linesFormat ? '📄 केवळ प्रश्नपत्रिका (Compact - No Lines)' : '✍️ उत्तर-लेखन ओळींसह (With Student Answer Lines)' ?>
  </a>
  <a href="api.php?action=create_quiz_from_paper&paper_id=<?= (int)$paper['paper_id'] ?>&redirect=1" target="_blank" style="background:#198754; color:#fff; font-weight:700; padding:4px 10px; border-radius:4px; text-decoration:none;">
    &#128241; ऑनलाईन क्विझ
  </a>
  <a href="omr_view.php?id=<?= (int)$paper['paper_id'] ?>&set=<?= $currentSet ?>" target="_blank" style="background:#dc3545; color:#fff; font-weight:700; padding:4px 10px; border-radius:4px; text-decoration:none;">
    &#128196; OMR Sheet
  </a>
  <a href="omr_view.php?id=<?= (int)$paper['paper_id'] ?>&key=1&set=<?= $currentSet ?>" target="_blank" style="background:#fd7e14; color:#fff; font-weight:700; padding:4px 10px; border-radius:4px; text-decoration:none;">
    &#128273; OMR Key
  </a>
  <a href="<?= $competitive ? 'competitive_paper.php' : 'create_paper.php' ?>?edit=<?= (int)$paper['paper_id'] ?>">Edit</a>
  <a href="index.php">All papers</a>
  <label><input type="checkbox" id="twoCol"> Two columns</label>
  <label style="margin-left:8px; font-weight:600;"><input type="checkbox" id="omrBubblesToggle" <?= $competitive ? 'checked' : '' ?>> OMR पर्याय वर्तुळे</label>
  <label style="margin-left:8px; font-weight:600; cursor:pointer;"><input type="checkbox" id="toggleBorder" checked> फ्रेम बॉर्डर</label>
</div>

<div class="sheet with-border <?= $competitive ? 'competitive with-omr-bubbles' : '' ?> <?= $linesFormat ? 'lines-format' : '' ?>" id="sheet">
  <?php if ($competitive): ?>
    <div class="no-print" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:8px 14px; border-radius:6px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; font-size:10.5pt;">
      <div><strong><span style="font-size:14pt;">&#128196;</span> ओ.एम.आर. उत्तरपत्रिका व क्विझ (संच <?= $currentSet ?>):</strong> या संचाची A4 OMR शीट, उत्तरतालिका आणि क्विझ उपलब्ध आहे.</div>
      <div style="display:flex; gap:8px;">
        <a href="omr_view.php?id=<?= (int)$paper['paper_id'] ?>&set=<?= $currentSet ?>" target="_blank" style="background:#dc3545; color:#fff; padding:4px 12px; border-radius:4px; font-weight:bold; text-decoration:none; font-size:10pt;">
          &#128196; कोरी OMR शीट (संच <?= $currentSet ?>)
        </a>
        <a href="omr_view.php?id=<?= (int)$paper['paper_id'] ?>&key=1&set=<?= $currentSet ?>" target="_blank" style="background:#0d6efd; color:#fff; padding:4px 12px; border-radius:4px; font-weight:bold; text-decoration:none; font-size:10pt;">
          &#128273; OMR उत्तरतालिका (संच <?= $currentSet ?>)
        </a>
      </div>
    </div>
  <?php endif; ?>
  <?php $logoUrl = ep_logo_url(); ?>
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
      'std_label' => $stdLabel,
      'subject' => $subjectLabel,
      'total_marks' => $fmt($paper['total_marks']),
      'exam_date' => $paper['exam_date'] ? date('d/m/Y', strtotime($paper['exam_date'])) : '____________',
      'duration' => $paper['duration'],
      'set' => $currentSet,
      'show_answers' => $showAnswers,
      'english' => false,
  ]);
  ?>

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
      <?php foreach ($sec['question_ids'] as $qi => $qid): 
        if ($isComp) $qNo++; 
        if (!isset($questions[$qid])) continue; 
        $q = $questions[$qid]; 
        $isMCQ = ($sec['type'] === 'mcq') || (($q['question_type'] ?? '') === 'mcq') || $competitive || preg_match('/(?:[A-DА-Я1-4१-४]\s*[\)\.\-]|पर्याय|ऑप्शन)/u', $q['markup'] . ' ' . ($q['markup2'] ?? ''));
      ?>
        <li class="q">
          <?php if ($isComp && $perQ !== null): ?><span class="q-marks">[<?= $fmt($perQ) ?>]</span><?php endif; ?>
          <?php if ($isMCQ): 
            $mcqOpt = ep_parse_omr_option($q['answer_markup'] ?: ($q['answer_markup2'] ?? ''));
          ?>
            <span class="mcq-box default-mcq-box" style="float:right; border:1px solid #333; border-radius:3px; padding:1px 12px; font-weight:bold; font-size:10.5pt; margin-left:8px; background:#fafafa; <?= $showAnswers ? 'display:none;' : '' ?>">[ &nbsp;&nbsp;&nbsp; ]</span>
            <span class="mcq-omr-strip">
              <?php foreach (['A', 'B', 'C', 'D'] as $bIdx => $bLabel): 
                $isShaded = ($showAnswers && $mcqOpt === ($bIdx + 1));
              ?>
                <span class="q-omr-bubble <?= $isShaded ? 'shaded' : '' ?>"><?= $bLabel ?></span>
              <?php endforeach; ?>
            </span>
          <?php endif; ?>
          <div class="qtext">
            <?php 
            $pass = trim((string)($q['passage'] ?? ''));
            $isInstructionLike = preg_match('/^(?:Answer the following|Answer in one sentence|Write short note|Fill in the blanks|Choose the correct|State whether|Match the following|खालील|योग्य|रिकाम्या|जोड्या|चूक|एका वाक्यात|थोडक्यात|कारणे)/iu', $pass);
            if ($pass !== '' && !$isInstructionLike): ?><div class="passage"><?= ep_markup($pass) ?></div><?php endif; ?>
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
            <?php if ($linesFormat && !$showAnswers && !$isMCQ): 
              $qtype = $q['question_type'] ?? $sec['type'] ?? '';
              $std = (int)($paper['standard_id'] ?? 0);
              $space = ep_answer_space(['qtype' => $qtype, 'instruction' => $sec['title'] ?? ''], $std, $q['markup'] ?? '');
              echo ep_answer_space_html($space);
            endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
      </ol>
    </section>
  <?php endforeach; ?>
  </div>

  <div class="exam-signatures" style="margin-top: 25px; display: flex; justify-content: space-between; padding: 12px 10px; border-top: 1px dashed #aaa; font-size: 13px; font-weight: bold;">
    <div>प्राप्त गुण: ________ / <?= $fmt($paper['total_marks']) ?></div>
    <div>परीक्षक स्वाक्षरी: ____________________</div>
    <div>मुख्याध्यापक स्वाक्षरी व शिक्का</div>
  </div>

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
document.getElementById('twoCol').addEventListener('change', function () {
  document.getElementById('sheet').classList.toggle('two-col', this.checked);
});
const omrToggle = document.getElementById('omrBubblesToggle');
if (omrToggle) {
  omrToggle.addEventListener('change', function () {
    document.getElementById('sheet').classList.toggle('with-omr-bubbles', this.checked);
  });
}
const borderToggle = document.getElementById('toggleBorder');
if (borderToggle) {
  borderToggle.addEventListener('change', function () {
    document.getElementById('sheet').classList.toggle('with-border', this.checked);
  });
}
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
