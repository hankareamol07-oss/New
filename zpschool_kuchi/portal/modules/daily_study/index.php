<?php
declare(strict_types=1);

$page_title = 'दैनिक अभ्यास (Daily Homework)';
require_once __DIR__ . '/../../includes/cce_functions.php';
require_once __DIR__ . '/../../includes/module_access.php';
requireLogin();

$school = getSchool();
$schoolId = (int)($school['id'] ?? SCHOOL_ID);
$db = getMainDB();
$user = $_SESSION['user'] ?? $_SESSION;

$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
$allowedStds = ($tScope && !empty($tScope['stds'])) ? $tScope['stds'] : range(1, 8);

$std = (int)($_GET['std'] ?? ($tScope['std'] ?? $allowedStds[0] ?? 1));
if (!in_array($std, $allowedStds)) {
    $std = $allowedStds[0];
}

$date = trim($_GET['date'] ?? date('Y-m-d'));

// Handle form submit (Publish)
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_study'])) {
    $pStd = (int)($_POST['std'] ?? $std);
    $pSubject = trim($_POST['subject'] ?? '');
    $pTopic = trim($_POST['topic'] ?? '');
    $pContent = trim($_POST['content'] ?? '');
    $pInstructions = trim($_POST['instructions'] ?? '');
    $pTachanId = !empty($_POST['tachan_id']) ? (int)$_POST['tachan_id'] : null;
    $pYoutube = trim($_POST['youtube_url'] ?? '');

    if (!$pStd || !$pSubject || !$pTopic || !$pContent) {
        $err = 'कृपया इयत्ता, विषय, घटक आणि स्वाध्याय सर्व आवश्यक माहिती भरा.';
    } else {
        try {
            $tId = $user['teacher_id'] ?? $user['id'] ?? null;
            $tName = $user['name_mr'] ?? $user['name'] ?? 'शिक्षक';
            $st = $db->prepare("
                INSERT INTO daily_study (school_id, std, section, study_date, subject, topic, tachan_id, content, instructions, youtube_url, teacher_id, teacher_name, is_published)
                VALUES (?, ?, 'A', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $st->execute([$schoolId, $pStd, $date, $pSubject, $pTopic, $pTachanId, $pContent, $pInstructions, $pYoutube, $tId, $tName]);
            $msg = 'दैनिक अभ्यास यशस्वीरीत्या प्रकाशित केला! विद्यार्थ्यांच्या लॉगिनवर तात्काळ दिसेल.';
        } catch (Throwable $e) {
            $err = 'त्रुटी: ' . $e->getMessage();
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    try {
        $st = $db->prepare("DELETE FROM daily_study WHERE id = ? AND school_id = ?");
        $st->execute([$delId, $schoolId]);
        $msg = 'दैनिक अभ्यास हटवला.';
    } catch (Throwable $e) {
        $err = 'हटवता आले नाही: ' . $e->getMessage();
    }
}

// Fetch subjects for this standard and teacher
$subjects = [];
if ($tScope && !empty($tScope['teacher_id'])) {
    $stSub = $db->prepare("
        SELECT DISTINCT s.id, s.name_mr, s.short_name, s.sort_order 
        FROM cce_subjects s
        JOIN cce_subject_teachers st ON st.subject_id = s.id
        WHERE st.school_id = ? AND st.std = ? AND st.teacher_id = ?
        ORDER BY s.sort_order ASC, s.id ASC
    ");
    $stSub->execute([$schoolId, $std, $tScope['teacher_id']]);
    $subjects = $stSub->fetchAll(PDO::FETCH_ASSOC);
}

// Fallback to all standard subjects if no specific subject mapped
if (empty($subjects)) {
    $stSub = $db->prepare("SELECT id, name_mr, short_name, sort_order FROM cce_subjects WHERE school_id = ? AND std = ? ORDER BY sort_order ASC, id ASC");
    $stSub->execute([$schoolId, $std]);
    $subjects = $stSub->fetchAll(PDO::FETCH_ASSOC);
}

// If still empty, standard Marathi medium subjects
if (empty($subjects)) {
    $defaultSubjects = ['मराठी', 'गणित', 'इंग्रजी', 'सामान्य विज्ञान', 'सामाजिक शास्त्रे', 'हिंदी', 'कला', 'कार्यानुभव', 'शारीरिक शिक्षण'];
    foreach ($defaultSubjects as $idx => $dSub) {
        $subjects[] = ['id' => $idx + 1, 'name_mr' => $dSub, 'name_en' => $dSub];
    }
}

// Fetch published daily study entries for this std and date
$stList = $db->prepare("
    SELECT ds.*, 
           (SELECT COUNT(*) FROM student_study_submissions sub WHERE sub.study_id = ds.id) as completed_count,
           (SELECT COUNT(*) FROM students s WHERE s.school_id = ds.school_id AND s.std = ds.std AND s.is_active = 1) as total_students
    FROM daily_study ds
    WHERE ds.school_id = ? AND ds.std = ? AND ds.study_date = ?
    ORDER BY ds.id DESC
");
$stList->execute([$schoolId, $std, $date]);
$publishedList = $stList->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header Card -->
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-800 rounded-3xl p-6 md:p-8 text-white shadow-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="inline-flex items-center gap-2 bg-white/20 px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase mb-2">
                <i class="bi bi-journal-bookmark-fill"></i> NEP 2020 टाचण लिंक
            </div>
            <h1 class="text-2xl md:text-3xl font-black">दैनिक अभ्यास व्यवस्थापन (Daily Homework)</h1>
            <p class="text-blue-100 text-sm mt-1">टाचण (Lesson Plan) मधील स्वाध्याय निवडून विद्यार्थ्यांच्या लॉगिनवर प्रकाशित करा.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= APP_URL ?>/modules/tachan/index.php" class="bg-white/10 hover:bg-white/20 text-white font-bold px-4 py-2.5 rounded-xl text-sm transition flex items-center gap-1.5 border border-white/20">
                <i class="bi bi-journal-text"></i> टाचण बँक पहा
            </a>
            <a href="<?= APP_URL ?>/index.php" class="bg-white text-blue-900 font-extrabold px-4 py-2.5 rounded-xl text-sm shadow hover:bg-blue-50 transition flex items-center gap-1.5">
                <i class="bi bi-house"></i> डॅशबोर्ड
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($msg): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl flex items-center gap-3 font-semibold shadow-sm">
            <i class="bi bi-check-circle-fill text-emerald-600 text-xl"></i>
            <div><?= htmlspecialchars($msg) ?></div>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl flex items-center gap-3 font-semibold shadow-sm">
            <i class="bi bi-exclamation-triangle-fill text-rose-600 text-xl"></i>
            <div><?= htmlspecialchars($err) ?></div>
        </div>
    <?php endif; ?>

    <!-- Scope & Filter Bar -->
    <div class="bg-white rounded-2xl p-4 md:p-5 border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <!-- Class Standard Pills -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider me-1">
                <i class="bi bi-funnel-fill"></i> इयत्ता:
            </span>
            <?php foreach ($allowedStds as $s_num): ?>
                <a href="?std=<?= $s_num ?>&date=<?= urlencode($date) ?>" 
                   class="px-4 py-2 rounded-xl text-sm font-bold transition <?= $std === $s_num ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    इ. <?= $s_num ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Date Selector -->
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="std" value="<?= $std ?>">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">तारीख:</label>
            <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="border border-slate-300 rounded-xl px-3 py-1.5 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
            <button type="submit" class="bg-slate-800 text-white px-3 py-1.5 rounded-xl text-sm font-bold hover:bg-slate-900">
                पहा
            </button>
        </form>
    </div>

    <!-- Main Grid: Create/Publish Form & Published List -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Form: Publish Daily Homework (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-black">
                        <i class="bi bi-pencil-fill text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-900">नवीन दैनिक अभ्यास प्रकाशित करा</h2>
                        <p class="text-xs text-slate-500">इयत्ता <?= $std ?> · दिनांक: <?= date('d M Y', strtotime($date)) ?></p>
                    </div>
                </div>
                <span class="text-xs font-bold bg-amber-100 text-amber-800 px-3 py-1 rounded-full">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> थेट स्टुडंट ॲपमध्ये
                </span>
            </div>

            <form method="POST" class="space-y-4" id="dailyStudyForm">
                <input type="hidden" name="publish_study" value="1">
                <input type="hidden" name="std" value="<?= $std ?>">
                <input type="hidden" name="tachan_id" id="tachan_id" value="">

                <!-- Subject Selection -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">
                        १. विषय निवडा <span class="text-rose-500">*</span>
                    </label>
                    <select name="subject" id="subjectSelect" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 bg-slate-50" required onchange="loadTachanTopics()">
                        <option value="">-- विषय निवडा --</option>
                        <?php foreach ($subjects as $sb): ?>
                            <option value="<?= htmlspecialchars($sb['name_mr']) ?>">
                                <?= htmlspecialchars($sb['name_mr']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tachan Quick Select Card -->
                <div class="p-4 bg-indigo-50/70 border border-indigo-100 rounded-2xl space-y-2" id="tachanSelectBox">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-indigo-900 flex items-center gap-1.5">
                            <i class="bi bi-lightbulb-fill text-amber-500"></i> टाचण बँकेतून पाठ/घटक निवडा (११,८५१+ टाचण):
                        </span>
                        <span id="topicLoading" class="text-xs font-bold text-indigo-600 hidden">
                            <i class="bi bi-arrow-repeat spin"></i> लोड होत आहे...
                        </span>
                    </div>
                    <select id="tachanTopicSelect" class="w-full border border-indigo-200 rounded-xl px-3 py-2 text-sm text-indigo-950 font-medium bg-white focus:ring-2 focus:ring-indigo-500" onchange="applyTachanData()">
                        <option value="">-- प्रथम वरील विषय निवडा --</option>
                    </select>
                    <p class="text-[11px] text-indigo-700">टाचण निवडल्यावर घटक आणि स्वाध्याय प्रश्न आपोआप खाली भरले जातील!</p>
                </div>

                <!-- Topic Input -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">
                        २. पाठ / घटकाचे नाव (Topic) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="topic" id="topicInput" placeholder="उदा. सजीव सृष्टी आणि सूक्ष्मजीवांचे वर्गीकरण" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-blue-500" required>
                </div>

                <!-- Homework / Study Content -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">
                        ३. स्वाध्याय / गृहपाठ प्रश्न व कृती (Questions / Study Tasks) <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="content" id="contentInput" rows="5" placeholder="विद्यार्थ्यांनी घरी करावयाचा अभ्यास, प्रश्नोत्तरे, स्वाध्याय किंवा वाचन कृती इथे लिहा किंवा टाचणमधून निवडा..." class="w-full border border-slate-300 rounded-xl p-4 text-sm font-medium text-slate-900 focus:ring-2 focus:ring-blue-500" required></textarea>
                </div>

                <!-- Learning Outcome / Special Instructions -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">
                        ४. अध्ययन निष्पत्ती किंवा पालकांसाठी सूचना (ऐच्छिक)
                    </label>
                    <input type="text" name="instructions" id="instructionsInput" placeholder="उदा. पालकांनी विद्यार्थ्यांचे पाठांतर तपासावे." class="w-full border border-slate-300 rounded-xl px-4 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- YouTube Video Link for Extra Study -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5 flex items-center gap-1.5">
                        <i class="bi bi-youtube text-rose-600 text-sm"></i> ५. जादा अभ्यासासाठी YouTube व्हिडिओ लिंक (Extra Study Video - ऐच्छिक)
                    </label>
                    <input type="url" name="youtube_url" id="youtubeUrlInput" placeholder="उदा. https://www.youtube.com/watch?v=... किंवा https://youtu.be/..." class="w-full border border-slate-300 rounded-xl px-4 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-rose-500">
                    <p class="text-[11px] text-slate-500 mt-1">विद्यार्थ्यांना संकल्पना स्पष्ट होण्यासाठी उपयुक्त शैक्षणिक व्हिडिओ लिंक येथे देऊ शकता.</p>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 px-6 rounded-xl shadow-lg transition flex items-center justify-center gap-2 text-base">
                    <i class="bi bi-send-check-fill text-lg"></i> दैनिक अभ्यास प्रकाशित करा (Publish to Student Login)
                </button>
            </form>
        </div>

        <!-- List: Published Homework for Selected Date (5 cols) -->
        <div class="lg:col-span-5 space-y-4">
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h3 class="font-black text-slate-900 text-base flex items-center gap-2">
                        <i class="bi bi-calendar2-check-fill text-emerald-600"></i> आजचा प्रकाशित अभ्यास
                    </h3>
                    <span class="text-xs font-black bg-slate-100 text-slate-700 px-2.5 py-1 rounded-full">
                        <?= count($publishedList) ?> विषय
                    </span>
                </div>

                <?php if (empty($publishedList)): ?>
                    <div class="text-center py-8 text-slate-400">
                        <i class="bi bi-journal-x text-4xl"></i>
                        <p class="text-xs font-bold mt-2">या तारखेसाठी अद्याप दैनिक अभ्यास प्रकाशित केलेला नाही.</p>
                        <p class="text-[11px] text-slate-400">डावीकडील फॉर्म वापरून अभ्यास प्रकाशित करा.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($publishedList as $item): ?>
                            <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/70 hover:bg-white transition space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="inline-block px-2.5 py-0.5 rounded-lg text-xs font-extrabold bg-blue-100 text-blue-800 mb-1">
                                            <?= htmlspecialchars($item['subject']) ?>
                                        </span>
                                        <h4 class="font-bold text-slate-900 text-sm leading-snug">
                                            <?= htmlspecialchars($item['topic']) ?>
                                        </h4>
                                    </div>
                                    <a href="?std=<?= $std ?>&date=<?= urlencode($date) ?>&delete_id=<?= $item['id'] ?>" 
                                       onclick="return confirm('हा दैनिक अभ्यास हटवायचा आहे का?')"
                                       class="text-rose-500 hover:text-rose-700 text-sm p-1" title="हटवा">
                                        <i class="bi bi-trash3"></i>
                                    </a>
                                </div>

                                <div class="text-xs text-slate-600 whitespace-pre-line bg-white p-2.5 rounded-xl border border-slate-100 font-medium">
                                    <?= nl2br(htmlspecialchars($item['content'])) ?>
                                </div>

                                <?php if (!empty($item['instructions'])): ?>
                                    <div class="text-[11px] text-amber-700 font-semibold flex items-center gap-1">
                                        <i class="bi bi-info-circle"></i> <?= htmlspecialchars($item['instructions']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item['youtube_url'])): ?>
                                    <div class="pt-1">
                                        <a href="<?= htmlspecialchars($item['youtube_url']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-bold rounded-lg border border-red-200 transition">
                                            <i class="bi bi-youtube text-red-600"></i> शैक्षणिक व्हिडिओ पहा
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-100">
                                    <span>शिक्षक: <strong><?= htmlspecialchars($item['teacher_name'] ?: 'शिक्षक') ?></strong></span>
                                    <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                        <i class="bi bi-people-fill"></i> <?= (int)$item['completed_count'] ?> / <?= (int)$item['total_students'] ?> पूर्ण
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let currentTopics = [];

async function loadTachanTopics() {
    const std = <?= (int)$std ?>;
    const subject = document.getElementById('subjectSelect').value;
    const select = document.getElementById('tachanTopicSelect');
    const loading = document.getElementById('topicLoading');

    if (!subject) {
        select.innerHTML = '<option value="">-- प्रथम वरील विषय निवडा --</option>';
        return;
    }

    loading.classList.remove('hidden');
    select.innerHTML = '<option value="">लोड होत आहे...</option>';

    try {
        const resp = await fetch('<?= APP_URL ?>/api/daily_study.php?action=tachan_topics&std=' + std + '&subject=' + encodeURIComponent(subject));
        const res = await resp.json();
        loading.classList.add('hidden');

        if (res.success && res.topics && res.topics.length > 0) {
            currentTopics = res.topics;
            let html = '<option value="">-- टाचण घटकातून निवडा (' + res.topics.length + ' टाचण उपलब्ध) --</option>';
            res.topics.forEach((t, i) => {
                html += '<option value="' + i + '">' + (i+1) + '. ' + t.topic + '</option>';
            });
            select.innerHTML = html;
        } else {
            select.innerHTML = '<option value="">या विषयासाठी टाचण उपलब्ध नाही (स्वतः प्रश्न लिहा)</option>';
        }
    } catch (e) {
        loading.classList.add('hidden');
        select.innerHTML = '<option value="">लोड करताना त्रुटी आली</option>';
    }
}

function applyTachanData() {
    const idx = document.getElementById('tachanTopicSelect').value;
    if (idx === '' || !currentTopics[idx]) return;

    const t = currentTopics[idx];
    document.getElementById('tachan_id').value = t.id || '';
    document.getElementById('topicInput').value = t.topic || '';
    
    // Auto-fill homework / study tasks from tachan
    let hw = t.homework || '';
    if (!hw && t.tl_activity) {
        hw = t.tl_activity;
    }
    document.getElementById('contentInput').value = hw;

    // Auto-fill learning outcome
    if (t.learning_outcome) {
        document.getElementById('instructionsInput').value = 'अध्ययन निष्पत्ती: ' + t.learning_outcome;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
