<?php
/** Printable A4 daily homework sheet; ends with the topic quiz link + QR code. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/quiz.php';

$hw = ep_homework((int)($_GET['id'] ?? 0));
if (!$hw) {
    http_response_code(404);
    exit('Homework not found');
}
$logo = ep_setting('school_logo');
$watermark = ep_setting('watermark_text');
$logoWatermark = $logo && ep_setting('watermark_logo') === '1';
$english = $hw['medium'] === 'English' && !preg_match('/\p{Devanagari}/u', $hw['title']);
$digits = fn($n) => $english ? (string)$n : strtr((string)$n, ['0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४', '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९']);
$subjectNames = ['Marathi' => 'मराठी', 'Maths' => 'गणित', 'English' => 'इंग्रजी', 'EVS Part 1' => 'परिसर अभ्यास भाग १', 'EVS Part 2' => 'परिसर अभ्यास भाग २', 'EVS' => 'परिसर अभ्यास',
    'Science' => 'विज्ञान', 'Hindi' => 'हिंदी', 'Geography' => 'भूगोल', 'History & Civics' => 'इतिहास व नागरिकशास्त्र'];
$subject = $english ? $hw['subject'] : ($subjectNames[$hw['subject']] ?? $hw['subject']);
$L = $english
    ? ['std' => 'Std.', 'subject' => 'Subject', 'date' => 'Date', 'topic' => 'Chapter / Topic', 'teacher' => 'Teacher', 'name' => 'Name', 'roll' => 'Roll No.', 'hw' => 'Homework', 'note' => 'Note', 'quiz' => 'Online Quiz', 'sign' => 'Parent\'s sign',
        'quiz_hint' => 'Scan the QR code or open the link on a phone, enter your name and attempt the quiz.', 'qcount' => 'questions', 'min' => 'min']
    : ['std' => 'इयत्ता', 'subject' => 'विषय', 'date' => 'दिनांक', 'topic' => 'पाठ / घटक', 'teacher' => 'शिक्षक', 'name' => 'नाव', 'roll' => 'हजेरी क्र.', 'hw' => 'गृहपाठ', 'note' => 'सूचना', 'quiz' => 'ऑनलाइन प्रश्नमंजुषा', 'sign' => 'पालकांची सही',
        'quiz_hint' => 'QR कोड स्कॅन करा किंवा मोबाईलवर लिंक उघडा, नाव लिहा व प्रश्नमंजुषा सोडवा.', 'qcount' => 'प्रश्न', 'min' => 'मिनिटे'];
$quiz = $hw['quiz'];
$quizUrl = $quiz ? ep_quiz_url($quiz) : '';
$std = $hw['std_label'] ?: 'Std ' . $hw['standard'];
?>
<!DOCTYPE html>
<html lang="<?= $english ? 'en' : 'mr' ?>">
<head>
<meta charset="utf-8">
<title><?= h($hw['title']) ?> - <?= h($std) ?> <?= h($subject) ?> - <?= $digits(date('d/m/Y', strtotime($hw['hw_date']))) ?></title>
<link href="<?= EP_BASE_URL ?>/assets/paper_print.css" rel="stylesheet">
<style>
.sheet.homework .meta td { padding: 3px 0; }
.sheet.homework .hw-title { font-size: 15pt; font-weight: 700; margin: 10px 0 4px; border-bottom: 1px solid #000; padding-bottom: 2px; }
.sheet.homework ol.items { list-style: none; padding-left: 0; margin: 0; }
.sheet.homework ol.items > li { display: flex; gap: 8px; margin-bottom: 8px; font-size: 12.5pt; line-height: 1.6; }
.sheet.homework .item-no { flex: 0 0 26px; font-weight: 600; }
.sheet.homework .qtext { flex: 1; white-space: pre-wrap; }
.sheet.homework .page-img { display: block; max-width: 120mm; max-height: 90mm; border: 1px solid #ccc; margin-top: 4px; }
.sheet.homework .note { margin-top: 12px; padding: 6px 10px; border: 1px dashed #666; border-radius: 4px; white-space: pre-wrap; }
.sheet.homework .quiz-box { margin-top: 18px; border: 2px solid #000; border-radius: 6px; padding: 10px 12px; display: flex; gap: 14px; align-items: center; page-break-inside: avoid; }
.sheet.homework .quiz-box .qr { flex: 0 0 34mm; width: 34mm; height: 34mm; }
.sheet.homework .quiz-box .qr img, .sheet.homework .quiz-box .qr canvas { width: 34mm !important; height: 34mm !important; }
.sheet.homework .quiz-box h3 { margin: 0 0 4px; font-size: 13pt; }
.sheet.homework .quiz-box a { font-size: 13pt; font-weight: 700; word-break: break-all; color: #000; }
.sheet.homework .quiz-box .code { font-size: 11pt; margin-top: 2px; }
.sheet.homework .sign-line { margin-top: 26px; display: flex; justify-content: space-between; font-size: 11.5pt; }
.warn { background: #fff3cd; color: #664d03; padding: 4px 10px; font-size: 13px; }
</style>
</head>
<body>
<div class="toolbar no-print">
  <button onclick="window.print()">&#128424; Print / Save as PDF</button>
  <a href="homework_key.php?id=<?= (int)$hw['hw_id'] ?>">Answer key</a>
  <a href="homework_new.php?edit=<?= (int)$hw['hw_id'] ?>">Edit</a>
  <a href="homework.php">All homework</a>
  <?php if ($quiz): ?><a href="<?= h($quizUrl) ?>" target="_blank">Open quiz</a> <a href="quiz_results.php?id=<?= (int)$quiz['quiz_id'] ?>">Quiz results</a>
  <button onclick="shareLink()">&#128279; Copy quiz link</button><?php endif; ?>
</div>
<?php if ($quiz && ep_setting('public_base_url') === '' && preg_match('~^https?://(localhost|127\.|10\.|192\.168\.)~', $quizUrl)): ?>
  <div class="warn no-print">The quiz link uses this server's local address (<?= h($quizUrl) ?>). Set <b>Public URL of module</b> in School Settings so students' phones can open it.</div>
<?php endif; ?>

<div class="sheet homework" id="sheet">
  <?php if ($logoWatermark): ?><img class="watermark-logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt=""><?php endif; ?>
  <?php if ($watermark): ?><div class="watermark"><?= h($watermark) ?></div><?php endif; ?>

  <header class="paper-header">
    <?php if ($logo): ?><img class="logo" src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt="logo"><?php endif; ?>
    <div class="school">
      <h1><?= h(ep_setting('school_name')) ?></h1>
      <?php if (ep_setting('school_address')): ?><div class="addr"><?= h(ep_setting('school_address')) ?></div><?php endif; ?>
      <h2><?= h($hw['title']) ?></h2>
    </div>
    <?php if ($logo): ?><div class="logo-spacer"></div><?php endif; ?>
  </header>

  <table class="meta">
    <tr>
      <td><b><?= $L['std'] ?> :</b> <?= h($std) ?> <?= h($hw['division']) ?></td>
      <td class="c"><b><?= $L['subject'] ?> :</b> <?= h($subject) ?></td>
      <td class="r"><b><?= $L['date'] ?> :</b> <?= $digits(date('d/m/Y', strtotime($hw['hw_date']))) ?></td>
    </tr>
    <tr>
      <td colspan="2"><b><?= $L['topic'] ?> :</b> <?= h($hw['topic'] ?: ($hw['chapter_title'] ?? '')) ?></td>
      <td class="r"><?php if ($hw['teacher']): ?><b><?= $L['teacher'] ?> :</b> <?= h($hw['teacher']) ?><?php endif; ?></td>
    </tr>
  </table>
  <div class="student-line"><b><?= $L['name'] ?> :</b> ______________________________________ &nbsp; <b><?= $L['roll'] ?> :</b> ________</div>

  <?php if ($hw['items']): ?>
  <div class="hw-title"><?= $L['hw'] ?></div>
  <ol class="items <?= $english ? '' : 'devanagari' ?>">
    <?php foreach ($hw['items'] as $i => $it): ?>
      <li><span class="item-no"><?= $digits($i + 1) ?>.</span>
        <div class="qtext"><?= h($it['text']) ?><?php if (!empty($it['show_image']) && $it['page_image_url']): ?><img class="page-img" src="<?= h($it['page_image_url']) ?>" alt=""><?php endif; ?></div>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php if (trim((string)$hw['note']) !== ''): ?>
    <div class="note"><b><?= $L['note'] ?> :</b> <?= h($hw['note']) ?></div>
  <?php endif; ?>

  <?php if ($quiz): ?>
  <div class="quiz-box">
    <div class="qr" id="qr"></div>
    <div>
      <h3>&#9654; <?= $L['quiz'] ?> — <?= h($quiz['title']) ?> (<?= $digits(count($quiz['questions'])) ?> <?= $L['qcount'] ?><?= $quiz['time_limit'] ? ', ' . $digits($quiz['time_limit']) . ' ' . $L['min'] : '' ?>)</h3>
      <div><?= $L['quiz_hint'] ?></div>
      <a href="<?= h($quizUrl) ?>"><?= h($quizUrl) ?></a>
      <div class="code">Quiz code: <b><?= h($quiz['code']) ?></b></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="sign-line"><span><?= $L['teacher'] ?> : ____________________</span><span><?= $L['sign'] ?> : ____________________</span></div>
  <?php if (ep_setting('paper_footer')): ?><div class="paper-footer"><?= h(ep_setting("paper_footer")) ?></div><?php endif; ?>
</div>

<?php if ($quiz): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById('qr'), { text: <?= json_encode($quizUrl) ?>, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M });
  function shareLink() {
    const url = <?= json_encode($quizUrl) ?>;
    (navigator.share ? navigator.share({ title: <?= json_encode($quiz['title']) ?>, url }) : navigator.clipboard.writeText(url).then(() => alert('Quiz link copied:\n' + url)));
  }
</script>
<?php endif; ?>
</body>
</html>
