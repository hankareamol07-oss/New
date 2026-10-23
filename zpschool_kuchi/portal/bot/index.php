<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * AI Study Bot & Scholarship Guide — Standalone Module
 * Works for All Classes (इ. १ ली ते ८ वी: १ ली, २ री, ३ री, ४ थी, ५ वी, ६ वी, ७ वी, ८ वी)
 * Includes Dedicated Scholarship Exam Prep for Class 4 & Class 7
 * Usable standalone, via hyperlink from Vijeta Portal, or embedded in Android App
 */

require_once __DIR__ . '/includes/knowledge_engine.php';

// Check if class lock is requested or user is a student
$isStudentSession = (isset($_SESSION['role']) && $_SESSION['role'] === 'student') || isset($_SESSION['std']);
$lockStd = false;

if (isset($_GET['lock_std']) && ($_GET['lock_std'] === '1' || $_GET['lock_std'] === 'true')) {
    $lockStd = true;
} elseif ($isStudentSession) {
    $lockStd = true;
}

$std = (int)($_GET['std'] ?? ($_SESSION['std'] ?? 6));
if ($isStudentSession && !empty($_SESSION['std'])) {
    $std = (int)$_SESSION['std'];
}
if ($std < 1 || $std > 8) $std = 6;
$stdLabel = formatMarathiStd($std);

$studentName = htmlspecialchars(trim((string)($_GET['name'] ?? ($_SESSION['name'] ?? 'विद्यार्थी'))));
$theme = trim((string)($_GET['theme'] ?? 'default'));
$isInApp = isset($_GET['in_app']) || isset($_GET['popup']);
$initialSubject = trim((string)($_GET['subject'] ?? ''));

$subjects = getSubjectsForStd($std);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AI अभ्यास मित्र</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #0f172a;
            --primary-light: #3b82f6;
            --gold: #d97706;
            --bg-body: #f8fafc;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Mukta', sans-serif;
            background-color: var(--bg-body);
            color: #1e293b;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .bot-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            color: white;
            padding: 12px 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            flex-shrink: 0;
        }
        .std-bar {
            background: white;
            padding: 8px 16px;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
            white-space: nowrap;
            flex-shrink: 0;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .std-btn {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            border: 1.5px solid #cbd5e1;
            background: white;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
        }
        .std-btn:hover, .std-btn.active {
            background: #1e3a8a;
            color: white;
            border-color: #1e3a8a;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.25);
        }
        .std-btn.scholarship-std {
            border-color: #f59e0b;
        }
        .std-btn.scholarship-std.active {
            background: linear-gradient(135deg, #d97706, #b45309);
            border-color: #b45309;
        }
        .subject-bar {
            background: #f1f5f9;
            padding: 8px 16px;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
            white-space: nowrap;
            flex-shrink: 0;
            display: flex;
            gap: 8px;
        }
        .subj-chip {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            background: white;
            color: #334155;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .subj-chip:hover, .subj-chip.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
        .subj-chip.chip-gold {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }
        .subj-chip.chip-gold.active {
            background: #d97706;
            color: white;
            border-color: #d97706;
        }
        .chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .msg-bubble {
            max-width: 85%;
            padding: 12px 18px;
            border-radius: 18px;
            font-size: 0.98rem;
            line-height: 1.55;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            animation: fadeIn 0.25s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .msg-user {
            align-self: flex-end;
            background: #2563eb;
            color: white;
            border-bottom-right-radius: 4px;
        }
        .msg-bot {
            align-self: flex-start;
            background: white;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 4px;
        }
        .chips-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .quick-chip {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 5px 13px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .quick-chip:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.15);
        }
        .quick-chip.chip-action {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }
        .quick-chip.chip-action:hover {
            background: #fde68a;
            border-color: #f59e0b;
        }
        .input-bar {
            background: white;
            padding: 12px 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .input-field {
            flex: 1;
            border-radius: 24px;
            padding: 10px 18px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.95rem;
            outline: none;
            transition: border 0.2s;
        }
        .input-field:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .btn-send {
            background: #1e3a8a;
            color: white;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            font-size: 1.1rem;
            transition: background 0.2s;
        }
        .btn-send:hover {
            background: #1d4ed8;
        }
        .btn-mic {
            background: #f1f5f9;
            color: #475569;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            font-size: 1.1rem;
            cursor: pointer;
        }
        .btn-mic.listening {
            background: #ef4444;
            color: white;
            animation: pulse 1.2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .quiz-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            margin-top: 10px;
        }
        .quiz-opt-btn {
            display: block;
            width: 100%;
            text-align: left;
            margin-bottom: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: white;
            font-size: 0.9rem;
            transition: all 0.15s;
        }
        .quiz-opt-btn:hover {
            background: #eff6ff;
            border-color: #93c5fd;
        }
        .quiz-opt-btn.correct {
            background: #d1fae5 !important;
            border-color: #34d399 !important;
            color: #065f46 !important;
            font-weight: bold;
        }
        .quiz-opt-btn.wrong {
            background: #fee2e2 !important;
            border-color: #f87171 !important;
            color: #991b1b !important;
        }
    </style>
</head>
<body>

    <!-- Bot Header -->
    <header class="bot-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-white rounded-circle p-1.5 shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <span class="fs-5">🤖</span>
            </div>
            <div>
                <h1 class="h6 mb-0 fw-bold">
                    AI अभ्यास मित्र
                </h1>
                <p class="small text-light mb-0 opacity-75" style="font-size: 0.78rem;">
                    सर्व विषय व शिष्यवृत्ती मार्गदर्शक
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-light btn-sm rounded-pill px-2.5 py-1" onclick="toggleAudio()" id="btnAudioToggle" title="आवाज चालू/बंद">
                <i class="bi bi-volume-up-fill" id="audioIcon"></i>
            </button>
            <button class="btn btn-outline-light btn-sm rounded-pill px-2.5 py-1" onclick="resetChat()" title="नवीन संभाषण">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </header>

    <?php if ($lockStd): ?>
    <!-- Standard Bar (Locked to Student's Class Only: १ ली ते ८ वी) -->
    <div class="std-bar locked-bar d-flex align-items-center justify-content-between px-3 py-2 bg-light border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary px-3 py-2 rounded-pill fs-6 d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-mortarboard-fill"></i>
                <span>इयत्ता <?= $stdLabel ?></span>
                <?= ($std === 4 || $std === 7) ? '<span class="badge bg-warning text-dark ms-1">🏆 शिष्यवृत्ती</span>' : '' ?>
            </span>
            <span class="text-muted small d-none d-sm-inline">
                (फक्त तुमच्या इयत्तेचा अधिकृत अभ्यासक्रम)
            </span>
        </div>
        <div class="text-end">
            <span class="badge bg-white text-dark border px-2.5 py-1.5 shadow-sm">
                <i class="bi bi-person-fill text-primary"></i> <?= $studentName ?>
            </span>
        </div>
    </div>
    <?php else: ?>
    <!-- Standard Selector Bar (Std 1 to 8: १ ली, २ री, ३ री, ४ थी, ५ वी, ६ वी, ७ वी, ८ वी) -->
    <div class="std-bar">
        <span class="small fw-bold text-muted me-1">इयत्ता:</span>
        <?php for ($i = 1; $i <= 8; $i++): ?>
            <?php 
                $isScholarship = ($i === 4 || $i === 7);
                $isActive = ($i === $std);
            ?>
            <button class="std-btn <?= $isScholarship ? 'scholarship-std' : '' ?> <?= $isActive ? 'active' : '' ?>" onclick="switchStd(<?= $i ?>)">
                <?= formatMarathiStd($i) ?> <?= $isScholarship ? '🏆' : '' ?>
            </button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <!-- Subjects Bar -->
    <div class="subject-bar" id="subjectBar">
        <div class="subj-chip active" data-subj="all" onclick="selectSubject('all')">
            <span>📚</span> सर्व विषय
        </div>
        <?php foreach ($subjects as $s): ?>
            <div class="subj-chip <?= !empty($s['is_scholarship']) ? 'chip-gold' : '' ?> <?= $initialSubject === $s['id'] ? 'active' : '' ?>" data-subj="<?= htmlspecialchars($s['id']) ?>" onclick="selectSubject('<?= htmlspecialchars($s['id']) ?>')">
                <span><?= $s['icon'] ?></span> <?= htmlspecialchars($s['name']) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Chat Messages Container -->
    <div class="chat-container" id="chatContainer">
        <!-- Welcome Bot Message -->
        <div class="msg-bubble msg-bot">
            👋 **नमस्कार <?= $studentName ?>!** मी तुमचा **AI अभ्यास मित्र** आहे.<br>
            मी **इयत्ता <span id="currentStdLabel"><?= $stdLabel ?></span>** च्या अधिकृत पाठ्यपुस्तकांवर, स्वाध्याय आणि शिष्यवृत्ती घटकांवर आधारित मदत करतो.<br><br>
            🎯 **तुम्ही काय करू शकता?**<br>
            • विज्ञानातील संकल्पना, स्वाध्याय किंवा आकृत्या समजावून घ्या 🔬<br>
            • इतिहास, भूगोल किंवा गणितातील प्रश्नांची उत्तरे मिळवा 📐<br>
            • 🏆 **इयत्ता ४ थी व ७ वी साठी शिष्यवृत्ती सराव प्रश्न व सूत्रे**<br>
            • 'सराव क्विझ' म्हणून प्रश्नमंजुषा सोडवा 🧠<br>

            <div class="chips-container">
                <span class="quick-chip" onclick="sendQuickPrompt('🔬 विज्ञानातील महत्त्वाचे स्वाध्याय प्रश्न दाखवा')">🔬 विज्ञान स्वाध्याय</span>
                <span class="quick-chip" onclick="sendQuickPrompt('🎯 ५ प्रश्नांची सराव क्विझ सुरू करा')">🎯 सराव क्विझ</span>
                <?php if ($std === 4 || $std === 7): ?>
                    <span class="quick-chip" onclick="sendQuickPrompt('🏆 शिष्यवृत्ती परीक्षा सराव व महत्त्वाची सूत्रे')">🏆 शिष्यवृत्ती तयारी</span>
                <?php endif; ?>
                <span class="quick-chip" onclick="sendQuickPrompt('📐 गणितातील महत्त्वाचे नियम व सूत्रे')">📐 गणित सूत्रे</span>
            </div>
        </div>
    </div>

    <!-- Input Bar -->
    <div class="input-bar">
        <button class="btn-mic" id="btnMic" onclick="toggleSpeech()" title="माईक द्वारे बोला (Voice Input)">
            <i class="bi bi-mic-fill" id="micIcon"></i>
        </button>
        <input type="text" class="input-field" id="txtQuery" placeholder="आपला प्रश्न येथे विचारा... (उदा. वाळवंटी वनस्पती, प्रकाश संश्लेषण, नफा तोटा)" onkeydown="if(event.key==='Enter') sendUserMessage()">
        <button class="btn-send" onclick="sendUserMessage()" title="प्रश्न पाठवा">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>

    <script>
        let isStdLocked = <?= $lockStd ? 'true' : 'false' ?>;
        let currentStd = <?= $std ?>;
        let currentSubject = '<?= $initialSubject ?: "all" ?>';
        let studentName = '<?= $studentName ?>';
        let soundEnabled = true;
        let isListening = false;
        let recognition = null;

        /**
         * Marathi ordinal class formatter:
         * १ ली, २ री, ३ री, ४ थी, ५ वी, ६ वी, ७ वी, ८ वी
         */
        function formatMarathiStd(std) {
            const map = {
                1: '१ ली',
                2: '२ री',
                3: '३ री',
                4: '४ थी',
                5: '५ वी',
                6: '६ वी',
                7: '७ वी',
                8: '८ वी'
            };
            return map[std] || (std + ' वी');
        }

        // Initialize Speech Recognition if supported
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRec();
            recognition.lang = 'mr-IN';
            recognition.continuous = false;
            recognition.interimResults = false;

            recognition.onresult = (e) => {
                const text = e.results[0][0].transcript;
                document.getElementById('txtQuery').value = text;
                toggleSpeech();
                sendUserMessage();
            };

            recognition.onerror = () => toggleSpeech(false);
            recognition.onend = () => toggleSpeech(false);
        } else {
            const micBtn = document.getElementById('btnMic');
            if (micBtn) micBtn.style.display = 'none';
        }

        function toggleSpeech(forceState) {
            if (!recognition) return;
            const btn = document.getElementById('btnMic');
            const icon = document.getElementById('micIcon');
            isListening = forceState !== undefined ? forceState : !isListening;

            if (isListening) {
                try {
                    recognition.start();
                    btn.classList.add('listening');
                    icon.className = 'bi bi-mic-mute-fill';
                } catch(e) {}
            } else {
                try {
                    recognition.stop();
                    btn.classList.remove('listening');
                    icon.className = 'bi bi-mic-fill';
                } catch(e) {}
            }
        }

        function toggleAudio() {
            soundEnabled = !soundEnabled;
            const icon = document.getElementById('audioIcon');
            icon.className = soundEnabled ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute-fill';
            if (!soundEnabled && window.speechSynthesis) {
                window.speechSynthesis.cancel();
            }
        }

        function speakText(text) {
            if (!soundEnabled || !('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            const clean = text.replace(/[*#_~`\[\]()]/g, '').replace(/https?:\/\/\S+/g, '');
            const utter = new SpeechSynthesisUtterance(clean.substring(0, 300));
            utter.lang = 'mr-IN';
            utter.rate = 1.0;
            window.speechSynthesis.speak(utter);
        }

        // Standard Switcher (Available only when not locked to student)
        async function switchStd(std) {
            if (isStdLocked) {
                console.warn("Class selection is locked to student's enrolled class.");
                return;
            }
            currentStd = std;
            document.querySelectorAll('.std-btn').forEach(btn => btn.classList.remove('active'));
            if (window.event && window.event.target) {
                window.event.target.classList.add('active');
            }
            document.getElementById('currentStdLabel').innerText = formatMarathiStd(std);

            // Reload subjects
            try {
                const res = await fetch(`api/chat.php?action=get_subjects&std=${std}`);
                const data = await res.json();
                if (data.success) {
                    const sb = document.getElementById('subjectBar');
                    sb.innerHTML = `<div class="subj-chip active" data-subj="all" onclick="selectSubject('all')"><span>📚</span> सर्व विषय</div>`;
                    data.subjects.forEach(s => {
                        const goldClass = s.is_scholarship ? 'chip-gold' : '';
                        sb.innerHTML += `<div class="subj-chip ${goldClass}" data-subj="${s.id}" onclick="selectSubject('${s.id}')"><span>${s.icon}</span> ${s.name}</div>`;
                    });
                }
            } catch(e) {}

            appendBotMessage(`🔄 इयत्ता बदलून **${formatMarathiStd(std)}** करण्यात आली आहे. आपण या इयत्तेच्या कोणत्याही विषयावर प्रश्न विचारू शकता!`, [
                '🔬 विज्ञान स्वाध्याय', 
                '🎯 सराव क्विझ', 
                ...(std === 4 || std === 7 ? ['🏆 शिष्यवृत्ती तयारी'] : []),
                '📐 गणित सूत्रे'
            ]);
        }

        async function selectSubject(subjId) {
            currentSubject = subjId;
            document.querySelectorAll('.subj-chip').forEach(c => c.classList.remove('active'));
            const activeChip = document.querySelector(`.subj-chip[data-subj="${subjId}"]`);
            if (activeChip) activeChip.classList.add('active');

            if (subjId === 'all') {
                appendBotMessage(`📚 **सर्व विषय निवडले आहेत.** आपण **इयत्ता ${formatMarathiStd(currentStd)}** मधील कोणत्याही विषयावर थेट प्रश्न विचारू शकता किंवा वरील पट्टीतून विशिष्ट विषय निवडून त्याचे घटक पाहू शकता.`, [
                    '🔬 विज्ञान स्वाध्याय',
                    '🎯 ५ प्रश्नांची सराव क्विझ',
                    ...(currentStd === 4 || currentStd === 7 ? ['🏆 शिष्यवृत्ती तयारी'] : []),
                    '📐 गणित सूत्रे'
                ]);
                return;
            }

            // Fetch and present topics in chat for this subject
            const typingId = appendTypingIndicator();

            try {
                const res = await fetch(`api/chat.php?action=get_topics&std=${currentStd}&subject=${encodeURIComponent(subjId)}`);
                const data = await res.json();
                removeTypingIndicator(typingId);

                if (data.success && data.topics && data.topics.length > 0) {
                    const cleanSubjName = activeChip ? activeChip.innerText.trim() : subjId;
                    
                    let topicPrompt = `📘 **${cleanSubjName} — घटक व पाठ निवडा (इयत्ता ${formatMarathiStd(currentStd)}):**\n`
                                    + `खालीलपैकी ज्या घटकाचा अभ्यास किंवा सराव करायचा आहे, **त्या घटकावर क्लिक करा:**`;

                    const topicChips = data.topics.map(t => t.display_title || t.title);
                    topicChips.push(`🎯 ${cleanSubjName} सराव क्विझ`);

                    appendBotMessage(topicPrompt, topicChips);
                } else {
                    const cleanSubjName = activeChip ? activeChip.innerText.trim() : subjId;
                    appendBotMessage(`📘 **${cleanSubjName}** निवडला आहे. आपण या विषयातील कोणताही प्रश्न विचारू शकता!`, [
                        '🎯 ५ प्रश्नांची सराव क्विझ',
                        '📐 महत्त्वाचे स्वाध्याय',
                        '📚 सर्व विषय'
                    ]);
                }
            } catch (err) {
                removeTypingIndicator(typingId);
                appendBotMessage(`⚠️ घटक लोड करताना त्रुटी आली. कृपया पुन्हा प्रयत्न करा.`);
            }
        }

        function sendQuickPrompt(prompt) {
            document.getElementById('txtQuery').value = prompt;
            sendUserMessage();
        }

        async function sendUserMessage() {
            const input = document.getElementById('txtQuery');
            const msg = input.value.trim();
            if (!msg) return;

            appendUserMessage(msg);
            input.value = '';

            // Show typing indicator
            const typingId = appendTypingIndicator();

            try {
                const fd = new FormData();
                fd.append('action', 'chat');
                fd.append('msg', msg);
                fd.append('std', currentStd);
                fd.append('subject', currentSubject);
                fd.append('name', studentName);

                const res = await fetch('api/chat.php', { method: 'POST', body: fd });
                const data = await res.json();

                removeTypingIndicator(typingId);

                if (data.success) {
                    appendBotMessage(data.reply, data.chips, data.quiz_data);
                    speakText(data.reply);
                } else {
                    appendBotMessage(`⚠️ ${data.error || 'उत्तर मिळवता आले नाही.'}`);
                }
            } catch (err) {
                removeTypingIndicator(typingId);
                appendBotMessage('⚠️ सर्व्हरशी संपर्क होऊ शकला नाही. कृपया पुन्हा प्रयत्न करा.');
            }
        }

        function appendUserMessage(text) {
            const container = document.getElementById('chatContainer');
            const div = document.createElement('div');
            div.className = 'msg-bubble msg-user';
            div.innerText = text;
            container.appendChild(div);
            scrollToBottom();
        }

        function appendBotMessage(markdown, chips = [], quizData = null) {
            const container = document.getElementById('chatContainer');
            const div = document.createElement('div');
            div.className = 'msg-bubble msg-bot';

            // Simple markdown parser
            let html = markdown
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n\n/g, '<br><br>')
                .replace(/\n/g, '<br>');

            div.innerHTML = html;

            // Render interactive quiz if present
            if (quizData && quizData.length > 0) {
                const qBox = document.createElement('div');
                qBox.className = 'quiz-card';
                qBox.innerHTML = `<strong>🎯 सराव प्रश्नमंजुषा (Interactive Quiz):</strong><br>`;

                quizData.forEach((q, idx) => {
                    const qItem = document.createElement('div');
                    qItem.style.marginBottom = '12px';
                    qItem.innerHTML = `<span class="fw-bold">${idx + 1}. ${q.question}</span>`;

                    const letters = ['A', 'B', 'C', 'D'];
                    q.options.forEach((opt, oi) => {
                        const optBtn = document.createElement('button');
                        optBtn.className = 'quiz-opt-btn';
                        optBtn.innerHTML = `<strong>(${letters[oi]})</strong> ${opt}`;
                        optBtn.onclick = () => {
                            if (opt === q.answer) {
                                optBtn.classList.add('correct');
                                optBtn.innerHTML += ' ✅ <em>बरोबर!</em>';
                            } else {
                                optBtn.classList.add('wrong');
                                optBtn.innerHTML += ` ❌ <em>चूक! योग्य: ${q.answer}</em>`;
                            }
                        };
                        qItem.appendChild(optBtn);
                    });
                    qBox.appendChild(qItem);
                });
                div.appendChild(qBox);
            }

            // Render chips
            if (chips && chips.length > 0) {
                const chipContainer = document.createElement('div');
                chipContainer.className = 'chips-container';
                chips.forEach(c => {
                    const span = document.createElement('span');
                    span.className = 'quick-chip';
                    if (c.includes('🎯') || c.includes('🏆')) {
                        span.classList.add('chip-action');
                    }
                    span.innerText = c;
                    span.onclick = () => sendQuickPrompt(c);
                    chipContainer.appendChild(span);
                });
                div.appendChild(chipContainer);
            }

            container.appendChild(div);
            scrollToBottom();
        }

        function appendTypingIndicator() {
            const container = document.getElementById('chatContainer');
            const id = 'typing_' + Date.now();
            const div = document.createElement('div');
            div.id = id;
            div.className = 'msg-bubble msg-bot text-muted';
            div.innerHTML = '🤖 <em>AI अभ्यास मित्र विचार करत आहे...</em>';
            container.appendChild(div);
            scrollToBottom();
            return id;
        }

        function removeTypingIndicator(id) {
            const el = document.getElementById(id);
            if (el) el.remove();
        }

        function scrollToBottom() {
            const container = document.getElementById('chatContainer');
            container.scrollTop = container.scrollHeight;
        }

        function resetChat() {
            const container = document.getElementById('chatContainer');
            container.innerHTML = `
                <div class="msg-bubble msg-bot">
                    🔄 <strong>नवीन संभाषण सुरू झाले आहे!</strong><br>
                    मी <strong>इयत्ता ${formatMarathiStd(currentStd)}</strong> च्या सर्व विषयांसाठी तयार आहे. आपला प्रश्न विचारा किंवा खालील पर्याय निवडा:
                    <div class="chips-container">
                        <span class="quick-chip" onclick="sendQuickPrompt('🔬 विज्ञानातील महत्त्वाचे स्वाध्याय प्रश्न दाखवा')">🔬 विज्ञान स्वाध्याय</span>
                        <span class="quick-chip" onclick="sendQuickPrompt('🎯 ५ प्रश्नांची सराव क्विझ सुरू करा')">🎯 सराव क्विझ</span>
                        ${(currentStd === 4 || currentStd === 7) ? '<span class="quick-chip" onclick="sendQuickPrompt(\'🏆 शिष्यवृत्ती परीक्षा सराव व महत्त्वाची सूत्रे\')">🏆 शिष्यवृत्ती तयारी</span>' : ''}
                        <span class="quick-chip" onclick="sendQuickPrompt('📐 गणितातील महत्त्वाचे नियम व सूत्रे')">📐 गणित सूत्रे</span>
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>
