<?php
/** Public student quiz page (opened from the homework PDF link / QR). No login. */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/quiz.php';

$quiz = ep_quiz_by_code(strtoupper(trim($_GET['c'] ?? '')));
$school = ep_setting('school_name');
$logo = ep_setting('school_logo');
$english = $quiz && $quiz['medium'] === 'English' && !preg_match('/\p{Devanagari}/u', $quiz['title']);
$L = $english
    ? ['name' => 'Your name', 'roll' => 'Roll no.', 'div' => 'Division', 'start' => 'Start quiz', 'submit' => 'Submit answers', 'answer' => 'Your answer', 'score' => 'Your score', 'correct' => 'Correct answer', 'time' => 'Time left', 'notfound' => 'Quiz not found. Check the link or code.', 'q' => 'questions', 'again' => 'Attempt again', 'unanswered' => 'unanswered — submit anyway?', 'timeup' => 'Time is up! Submitting your answers.', 'enter_code' => 'Enter quiz code']
    : ['name' => 'तुमचे नाव', 'roll' => 'हजेरी क्र.', 'div' => 'तुकडी', 'start' => 'प्रश्नमंजुषा सुरू करा', 'submit' => 'उत्तरे सबमिट करा', 'answer' => 'तुमचे उत्तर', 'score' => 'तुमचे गुण', 'correct' => 'योग्य उत्तर', 'time' => 'उरलेला वेळ', 'notfound' => 'प्रश्नमंजुषा सापडली नाही. लिंक / कोड तपासा.', 'q' => 'प्रश्न', 'again' => 'पुन्हा सोडवा', 'unanswered' => 'प्रश्न अनुत्तरित आहेत — तरीही सबमिट करायचे?', 'timeup' => 'वेळ संपली! उत्तरे सबमिट होत आहेत.', 'enter_code' => 'क्विझ कोड लिहा'];
$public = $quiz ? array_map(fn($q) => ['kind' => $q['kind'], 'text' => $q['text'], 'options' => $q['options'] ?? null, 'image_url' => $q['image_url'] ?? null], $quiz['questions']) : [];
?>
<!DOCTYPE html>
<html lang="<?= $english ? 'en' : 'mr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($quiz ? $quiz['title'] : 'Quiz') ?> - <?= h($school) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f4f6fa; }
.q-card { border-left: 4px solid #0d6efd; }
.q-card.ok { border-left-color: #198754; background: #f0fff4; }
.q-card.bad { border-left-color: #dc3545; background: #fff5f5; }
.opt { display: block; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px 12px; margin-bottom: 6px; cursor: pointer; background: #fff; }
.opt:has(input:checked) { border-color: #0d6efd; background: #e7f1ff; }
.opt.correct { border-color: #198754; background: #d1e7dd; }
.opt.wrong { border-color: #dc3545; background: #f8d7da; }
.score-big { font-size: 42px; font-weight: 800; }
#timer { position: sticky; top: 0; z-index: 5; }
</style>
</head>
<body>
<div class="container py-3" style="max-width:720px">
  <div class="d-flex align-items-center gap-2 mb-3">
    <?php if ($logo): ?><img src="<?= EP_BASE_URL ?>/uploads/<?= h($logo) ?>" alt="" style="height:44px"><?php endif; ?>
    <div><div class="fw-bold"><?= h($school) ?></div><?php if ($quiz): ?><div class="small text-muted"><?= h(($quiz['std_label'] ?? '') ?: 'Std ' . $quiz['standard']) ?> · <?= h($quiz['subject']) ?> · <?= h($quiz['topic']) ?></div><?php endif; ?></div>
  </div>

<?php if (!$quiz): ?>
  <div class="alert alert-warning"><?= $L['notfound'] ?></div>
  <form class="input-group" method="get"><input class="form-control" name="c" maxlength="8" placeholder="<?= $L['enter_code'] ?>" style="text-transform:uppercase"><button class="btn btn-primary">OK</button></form>
<?php else: ?>
  <div class="card shadow-sm mb-3"><div class="card-body">
    <h4 class="mb-1"><?= h($quiz['title']) ?></h4>
    <div class="text-muted small"><?= count($quiz['questions']) ?> <?= $L['q'] ?><?= $quiz['time_limit'] ? ' · ' . (int)$quiz['time_limit'] . ' min' : '' ?></div>
  </div></div>

  <div id="startBox" class="card shadow-sm"><div class="card-body">
    <div class="mb-2"><label class="form-label"><?= $L['name'] ?> *</label><input class="form-control form-control-lg" id="sName" autocomplete="name"></div>
    <div class="row g-2 mb-3">
      <div class="col-6"><label class="form-label"><?= $L['roll'] ?></label><input class="form-control" id="sRoll"></div>
      <div class="col-6"><label class="form-label"><?= $L['div'] ?></label><input class="form-control" id="sDiv"></div>
    </div>
    <button class="btn btn-primary btn-lg w-100" id="startBtn"><?= $L['start'] ?></button>
  </div></div>

  <div id="quizBox" class="d-none">
    <?php if ($quiz['time_limit']): ?><div id="timer" class="alert alert-info py-2 mb-3 d-flex justify-content-between"><span><?= $L['time'] ?></span><b id="timeLeft"></b></div><?php endif; ?>
    <div id="questions"></div>
    <button class="btn btn-success btn-lg w-100 mb-4" id="submitBtn"><?= $L['submit'] ?></button>
  </div>

  <div id="resultBox" class="d-none">
    <div class="card shadow-sm text-center mb-3"><div class="card-body">
      <div class="text-muted"><?= $L['score'] ?></div>
      <div class="score-big" id="scoreText"></div>
      <div id="scoreName" class="fw-semibold"></div>
      <button class="btn btn-outline-primary btn-sm mt-2" onclick="location.reload()"><?= $L['again'] ?></button>
    </div></div>
    <div id="review"></div>
  </div>
<?php endif; ?>
</div>

<?php if ($quiz): ?>
<script>
(function () {
  const Q = <?= json_encode($public, JSON_UNESCAPED_UNICODE) ?>;
  const CODE = <?= json_encode($quiz['code']) ?>;
  const LIMIT = <?= (int)$quiz['time_limit'] ?> * 60;
  const L = <?= json_encode($L, JSON_UNESCAPED_UNICODE) ?>;
  const OPT = ['A', 'B', 'C', 'D', 'E', 'F'];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const $ = s => document.querySelector(s);
  let timerId = null, submitted = false;

  $('#startBtn').onclick = () => {
    if (!$('#sName').value.trim()) { $('#sName').focus(); $('#sName').classList.add('is-invalid'); return; }
    $('#startBox').classList.add('d-none'); $('#quizBox').classList.remove('d-none');
    $('#questions').innerHTML = Q.map((q, i) => `
      <div class="card shadow-sm mb-3 q-card" id="q${i}"><div class="card-body">
        <div class="fw-semibold mb-2"><span class="badge bg-primary me-1">${i + 1}</span> ${esc(q.text)}</div>
        ${q.image_url ? `<img src="${esc(q.image_url)}" class="img-fluid mb-2" alt="">` : ''}
        ${q.kind === 'mcq'
          ? q.options.map((o, oi) => `<label class="opt"><input type="radio" name="a${i}" value="${oi}" class="form-check-input me-2">${OPT[oi]}) ${esc(o)}</label>`).join('')
          : `<input class="form-control" data-text="${i}" placeholder="${L.answer}">`}
      </div></div>`).join('');
    if (LIMIT > 0) {
      const end = Date.now() + LIMIT * 1000;
      const tick = () => {
        const s = Math.max(0, Math.round((end - Date.now()) / 1000));
        $('#timeLeft').textContent = `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
        if (s <= 0) { clearInterval(timerId); alert(L.timeup); submit(true); }
      };
      tick(); timerId = setInterval(tick, 1000);
    }
    window.scrollTo(0, 0);
  };

  function collect() {
    return Q.map((q, i) => {
      if (q.kind === 'mcq') { const r = document.querySelector(`input[name="a${i}"]:checked`); return r ? +r.value : null; }
      const v = document.querySelector(`[data-text="${i}"]`).value.trim(); return v === '' ? null : v;
    });
  }
  async function submit(force) {
    if (submitted) return;
    const answers = collect();
    const missing = answers.filter(a => a === null).length;
    if (!force && missing && !confirm(`${missing} ${L.unanswered}`)) return;
    submitted = true; clearInterval(timerId);
    $('#submitBtn').disabled = true;
    const r = await fetch('api.php?action=quiz_submit', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: CODE, student_name: $('#sName').value.trim(), roll_no: $('#sRoll').value, division: $('#sDiv').value, answers }) }).then(x => x.json());
    if (r.status !== 'success') { alert(r.message || 'Error'); submitted = false; $('#submitBtn').disabled = false; return; }
    $('#quizBox').classList.add('d-none'); $('#resultBox').classList.remove('d-none');
    $('#scoreText').textContent = `${r.score} / ${r.total}`;
    $('#scoreName').textContent = $('#sName').value.trim();
    $('#review').innerHTML = Q.map((q, i) => {
      const d = r.detail[i], correct = r.answers ? r.answers[i] : null;
      return `<div class="card shadow-sm mb-2 q-card ${d.correct ? 'ok' : 'bad'}"><div class="card-body py-2">
        <div class="fw-semibold"><span class="badge ${d.correct ? 'bg-success' : 'bg-danger'} me-1">${i + 1}</span> ${esc(q.text)}</div>
        ${q.kind === 'mcq'
          ? q.options.map((o, oi) => `<div class="opt py-1 ${correct === oi ? 'correct' : (d.given === oi && !d.correct ? 'wrong' : '')}">${OPT[oi]}) ${esc(o)}</div>`).join('')
          : `<div class="small mt-1">${L.answer}: <b>${esc(d.given ?? '—')}</b>${correct !== null && correct !== undefined ? ` &nbsp; ${L.correct}: <b class="text-success">${esc(correct)}</b>` : ''}</div>`}
      </div></div>`;
    }).join('');
    window.scrollTo(0, 0);
  }
  $('#submitBtn').onclick = () => submit(false);
})();
</script>
<?php endif; ?>
</body>
</html>
