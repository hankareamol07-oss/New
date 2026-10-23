<?php
require_once __DIR__ . '/includes/db.php';
$db = getDb();

/* ── Stats ── */
$stats = [
    'students'  => $db->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'questions' => $db->query('SELECT COUNT(*) FROM quiz_questions')->fetchColumn(),
    'attempts'  => $db->query('SELECT COUNT(*) FROM performance_logs')->fetchColumn(),
    'accuracy'  => $db->query("SELECT ROUND(AVG(is_correct::int)*100) FROM performance_logs")->fetchColumn() ?? 0,
];

/* ── Students ── */
$students = $db->query("SELECT s.id, s.name, s.standard, s.login_streak,
    COUNT(p.id) AS attempts,
    ROUND(AVG(p.is_correct::int)*100) AS accuracy
  FROM students s
  LEFT JOIN performance_logs p ON p.student_id = s.id
  GROUP BY s.id ORDER BY s.created_at DESC LIMIT 50")->fetchAll();

/* ── Recent activity ── */
$activity = $db->query("SELECT s.name, p.subject, p.is_correct, p.time_to_answer_sec,
    to_char(p.created_at,'DD Mon, HH12:MI AM') AS time_str
  FROM performance_logs p
  JOIN students s ON s.id = p.student_id
  ORDER BY p.created_at DESC LIMIT 30")->fetchAll();

/* ── Quiz questions ── */
$questions = $db->query("SELECT id, subject, standard, question, correct_answer FROM quiz_questions ORDER BY id DESC LIMIT 50")->fetchAll();

/* ── Subject stats ── */
$subjectStats = $db->query("SELECT subject,
    COUNT(*) AS total,
    SUM(is_correct::int) AS correct,
    ROUND(AVG(is_correct::int)*100) AS accuracy,
    ROUND(AVG(time_to_answer_sec)) AS avg_speed
  FROM performance_logs GROUP BY subject")->fetchAll();

/* ── URLs ── */
$domains  = getenv('REPLIT_DOMAINS') ?: '';
$domain   = explode(',', $domains)[0] ?? 'YOUR_REPLIT_DOMAIN';
$waUrl    = "https://{$domain}/webhook.php";
$apiUrl   = "https://{$domain}/api/chat.php";
$verifyToken = 'techedu_verify_2026';

/* ── Handle actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['delete_student'])) {
        $db->prepare('DELETE FROM students WHERE id = ?')->execute([(int)$_POST['delete_student']]);
    }
    if (!empty($_POST['delete_question'])) {
        $db->prepare('DELETE FROM quiz_questions WHERE id = ?')->execute([(int)$_POST['delete_question']]);
    }
    if (!empty($_POST['add_question'])) {
        $parts = [];
        foreach (['opt_a','opt_b','opt_c','opt_d'] as $k) {
            $v = str_replace(['\\', '"'], ['\\\\', '\\"'], trim($_POST[$k] ?? ''));
            $parts[] = '"' . $v . '"';
        }
        $opts = '{' . implode(',', $parts) . '}';
        $db->prepare('INSERT INTO quiz_questions (subject, standard, question, options, correct_answer, explanation) VALUES (?,?,?,?,?,?)')
           ->execute([
               trim($_POST['subject']),
               trim($_POST['standard']),
               trim($_POST['question']),
               $opts,
               trim($_POST['correct_answer']),
               trim($_POST['explanation'] ?? ''),
           ]);
    }
    header('Location: /admin.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TechEdu Admin Panel</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body style="background:#f1f5f9;min-height:100vh;">
<div class="admin-shell">

  <div class="admin-header">
    <div style="font-size:2rem">🎓</div>
    <h1>TechEdu Admin Panel</h1>
    <a href="/" class="admin-back" style="margin-left:auto">← Chat Bot</a>
  </div>

  <!-- Stats -->
  <div class="admin-grid">
    <div class="stat-card">
      <div class="stat-val"><?= $stats['students'] ?></div>
      <div class="stat-label">Students</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['questions'] ?></div>
      <div class="stat-label">Questions</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['attempts'] ?></div>
      <div class="stat-label">Quiz Attempts</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['accuracy'] ?>%</div>
      <div class="stat-label">Avg Accuracy</div>
    </div>
  </div>

  <!-- WhatsApp via n8n (FREE) -->
  <div class="wh-box">
    <h2>📱 WhatsApp Bot via n8n + Meta (Free — No Twilio needed)</h2>

    <div class="n8n-tabs">
      <div class="n8n-tab-header">
        <button class="tab-btn active" onclick="showTab('n8n')">n8n Setup</button>
        <button class="tab-btn" onclick="showTab('meta')">Meta WhatsApp API</button>
        <button class="tab-btn" onclick="showTab('direct')">Direct Webhook</button>
      </div>

      <!-- n8n tab -->
      <div id="tab-n8n" class="tab-content">
        <p class="step-intro">Your n8n on Railway calls our bot API → bot replies → n8n sends WhatsApp reply. 100% free!</p>
        <div class="steps">
          <div class="step">
            <span class="step-num">1</span>
            <div>
              <b>In n8n: Create a new Workflow</b><br>
              Add a <em>WhatsApp Trigger</em> node — it listens for incoming messages.
            </div>
          </div>
          <div class="step">
            <span class="step-num">2</span>
            <div>
              <b>Add HTTP Request node</b> — POST to your bot API:
              <div class="wh-url" style="margin-top:6px"><?= htmlspecialchars($apiUrl) ?></div>
              <div style="margin-top:6px;font-size:.82rem;color:#475569">
                Body (JSON):<br>
                <code>{ "from": "{{ $json.from }}", "message": "{{ $json.message }}" }</code>
              </div>
            </div>
          </div>
          <div class="step">
            <span class="step-num">3</span>
            <div>
              <b>Add WhatsApp node</b> — Send reply<br>
              Set <em>To</em>: <code>{{ $json.to }}</code> &nbsp;|&nbsp; <em>Message</em>: <code>{{ $json.reply }}</code>
            </div>
          </div>
          <div class="step">
            <span class="step-num">4</span>
            <div>
              <b>(Optional) Secure the API</b><br>
              Set <code>WEBHOOK_SECRET</code> env var in Replit Secrets, then add header in n8n:<br>
              <code>X-Webhook-Secret: your-secret</code>
            </div>
          </div>
        </div>
      </div>

      <!-- Meta tab -->
      <div id="tab-meta" class="tab-content hidden">
        <p class="step-intro">Use Meta's free WhatsApp Cloud API (1,000 free conversations/month) directly with our webhook.</p>
        <div class="steps">
          <div class="step"><span class="step-num">1</span><div>Go to <b>developers.facebook.com</b> → Create App → Business → Add WhatsApp product</div></div>
          <div class="step"><span class="step-num">2</span><div>
            In WhatsApp → Configuration → Webhook URL:<br>
            <div class="wh-url" style="margin-top:4px"><?= htmlspecialchars($waUrl) ?></div>
            Verify Token: <div class="wh-url" style="margin-top:4px"><?= $verifyToken ?></div>
          </div></div>
          <div class="step"><span class="step-num">3</span><div>
            Set these Replit Secrets:<br>
            <code>WHATSAPP_ACCESS_TOKEN</code> — your Meta user access token<br>
            <code>WHATSAPP_PHONE_NUMBER_ID</code> — your phone number ID<br>
            <code>WHATSAPP_VERIFY_TOKEN</code> — <em><?= $verifyToken ?></em>
          </div></div>
          <div class="step"><span class="step-num">4</span><div>Subscribe to <b>messages</b> webhook field. Done! ✅</div></div>
        </div>
      </div>

      <!-- Direct tab -->
      <div id="tab-direct" class="tab-content hidden">
        <p class="step-intro">Call the bot API directly from any platform (n8n, Zapier, Make, your own server).</p>
        <table style="width:100%;font-size:.85rem;border-collapse:collapse">
          <tr><th style="text-align:left;padding:6px 10px;background:#f1f5f9">Field</th><th style="text-align:left;padding:6px 10px;background:#f1f5f9">Value</th></tr>
          <tr><td style="padding:8px 10px;border-top:1px solid #e2e8f0"><b>URL</b></td><td style="padding:8px 10px;border-top:1px solid #e2e8f0"><code><?= htmlspecialchars($apiUrl) ?></code></td></tr>
          <tr><td style="padding:8px 10px;border-top:1px solid #e2e8f0"><b>Method</b></td><td style="padding:8px 10px;border-top:1px solid #e2e8f0">POST</td></tr>
          <tr><td style="padding:8px 10px;border-top:1px solid #e2e8f0"><b>Body</b></td><td style="padding:8px 10px;border-top:1px solid #e2e8f0"><code>{"from":"whatsapp:+91XXXXXXXXXX","message":"Hello"}</code></td></tr>
          <tr><td style="padding:8px 10px;border-top:1px solid #e2e8f0"><b>Response</b></td><td style="padding:8px 10px;border-top:1px solid #e2e8f0"><code>{"reply":"Bot response...","to":"whatsapp:+91...","ok":true}</code></td></tr>
        </table>
      </div>
    </div>
  </div>

  <script>
  function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.remove('hidden');
    event.target.classList.add('active');
  }
  </script>

  <!-- Subject Stats -->
  <?php if ($subjectStats): ?>
  <div class="table-card">
    <h2>📊 Subject Performance</h2>
    <table>
      <tr><th>Subject</th><th>Attempts</th><th>Correct</th><th>Accuracy</th><th>Avg Speed</th></tr>
      <?php foreach ($subjectStats as $s): ?>
      <tr>
        <td><b><?= htmlspecialchars($s['subject']) ?></b></td>
        <td><?= $s['total'] ?></td>
        <td><?= $s['correct'] ?></td>
        <td><?= $s['accuracy'] ?>%</td>
        <td><?= $s['avg_speed'] ?>s</td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <!-- Students -->
  <div class="table-card">
    <h2>👨‍🎓 Students (<?= count($students) ?>)</h2>
    <table>
      <tr><th>#</th><th>Name</th><th>Standard</th><th>Streak</th><th>Attempts</th><th>Accuracy</th><th></th></tr>
      <?php foreach ($students as $s): ?>
      <tr>
        <td><?= $s['id'] ?></td>
        <td><b><?= htmlspecialchars($s['name']) ?></b></td>
        <td><span class="pill pill-<?= match($s['standard']) { '4th'=>'4','5th'=>'5','7th'=>'7',default=>'8' } ?>"><?= $s['standard'] ?></span></td>
        <td>🔥 <?= $s['login_streak'] ?></td>
        <td><?= $s['attempts'] ?></td>
        <td><?= $s['accuracy'] ?? '—' ?><?= $s['accuracy'] !== null ? '%' : '' ?></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars($s['name']) ?>?')">
            <input type="hidden" name="delete_student" value="<?= $s['id'] ?>">
            <button type="submit" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:.85rem;">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?><tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:24px">No students yet</td></tr><?php endif; ?>
    </table>
  </div>

  <!-- Recent Activity -->
  <?php if ($activity): ?>
  <div class="table-card">
    <h2>⚡ Recent Activity</h2>
    <table>
      <tr><th>Student</th><th>Subject</th><th>Result</th><th>Speed</th><th>Time</th></tr>
      <?php foreach ($activity as $a): ?>
      <tr>
        <td><?= htmlspecialchars($a['name']) ?></td>
        <td><?= htmlspecialchars($a['subject']) ?></td>
        <td><span class="pill <?= $a['is_correct'] ? 'pill-correct' : 'pill-wrong' ?>"><?= $a['is_correct'] ? '✓ Correct' : '✗ Wrong' ?></span></td>
        <td><?= round($a['time_to_answer_sec'], 1) ?>s</td>
        <td style="color:#94a3b8;font-size:.82rem"><?= $a['time_str'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <!-- Syllabus Seeders -->
  <div class="table-card" style="border-left:4px solid #f97316">
    <h2>🌱 Seed Questions from Syllabus</h2>
    <p style="font-size:.85rem;color:#64748b;margin-bottom:14px">
      Generate AI quiz questions from official MSCEPUPPSS PSE syllabus PDFs using Gemini.
      Safe to re-run — duplicate questions are skipped automatically.
    </p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="/seed_4th.php?secret=seed2026" target="_blank"
         style="background:#f97316;color:#fff;padding:9px 20px;border-radius:7px;font-size:.88rem;font-weight:700;text-decoration:none">
        ▶ Seed 4th Std Questions
      </a>
    </div>
    <p style="font-size:.78rem;color:#94a3b8;margin-top:10px">
      Opens in new tab — shows live progress. Takes ~2-3 minutes (4 subjects × 8 topics × Gemini calls).
    </p>
  </div>

  <!-- Add Question Form -->
  <div class="table-card">
    <h2>➕ Add Quiz Question</h2>
    <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:10px 16px">
      <div style="grid-column:1/-1">
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Question</label>
        <input type="text" name="question" required placeholder="प्रश्न लिहा..." style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;box-sizing:border-box;margin-top:3px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Subject</label>
        <select name="subject" required style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;margin-top:3px">
          <option value="Math">Math</option>
          <option value="Marathi">Marathi</option>
          <option value="English">English</option>
          <option value="Intelligence">Intelligence</option>
        </select>
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Standard</label>
        <select name="standard" required style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;margin-top:3px">
          <option value="5th">5th (5वी)</option>
          <option value="8th">8th (8वी)</option>
          <option value="4th">4th (4थी)</option>
          <option value="7th">7th (7वी)</option>
        </select>
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Option A</label>
        <input type="text" name="opt_a" required placeholder="पर्याय A" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;box-sizing:border-box;margin-top:3px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Option B</label>
        <input type="text" name="opt_b" required placeholder="पर्याय B" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;box-sizing:border-box;margin-top:3px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Option C</label>
        <input type="text" name="opt_c" required placeholder="पर्याय C" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;box-sizing:border-box;margin-top:3px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Option D</label>
        <input type="text" name="opt_d" required placeholder="पर्याय D" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;box-sizing:border-box;margin-top:3px">
      </div>
      <div>
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Correct Answer (exact text of the option)</label>
        <input type="text" name="correct_answer" required placeholder="बरोबर उत्तर" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;box-sizing:border-box;margin-top:3px">
      </div>
      <div style="grid-column:1/-1">
        <label style="font-size:.8rem;font-weight:600;color:#64748b">Explanation (optional)</label>
        <input type="text" name="explanation" placeholder="स्पष्टीकरण (optional)" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;box-sizing:border-box;margin-top:3px">
      </div>
      <div style="grid-column:1/-1">
        <button type="submit" name="add_question" value="1" style="background:var(--primary);color:#fff;border:none;padding:9px 24px;border-radius:7px;font-size:.88rem;font-weight:700;cursor:pointer">+ Add Question</button>
      </div>
    </form>
  </div>

  <!-- Quiz Questions -->
  <div class="table-card">
    <h2>📚 Quiz Bank (<?= count($questions) ?>)</h2>
    <table>
      <tr><th>#</th><th>Subject</th><th>Std</th><th>Question</th><th>Answer</th><th></th></tr>
      <?php foreach ($questions as $q): ?>
      <tr>
        <td><?= $q['id'] ?></td>
        <td><?= htmlspecialchars($q['subject']) ?></td>
        <td><span class="pill pill-<?= match($q['standard']) { '4th'=>'4','5th'=>'5','7th'=>'7',default=>'8' } ?>"><?= $q['standard'] ?></span></td>
        <td style="max-width:340px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($q['question']) ?></td>
        <td><b style="color:#15803d"><?= htmlspecialchars($q['correct_answer']) ?></b></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this question?')">
            <input type="hidden" name="delete_question" value="<?= $q['id'] ?>">
            <button type="submit" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:.85rem;">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$questions): ?><tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:24px">No questions yet</td></tr><?php endif; ?>
    </table>
  </div>

  <p style="text-align:center;color:#94a3b8;font-size:.8rem;margin-top:24px">TechEdu.in – Free Student Service by Amol Hankare</p>
</div>
</body>
</html>
