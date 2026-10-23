<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/quiz_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$quizId = (int)($_GET['id'] ?? 0);
if ($quizId <= 0) {
    header("Location: ../../index.php");
    exit;
}

$quiz = getQuizWithQuestions($quizId);
if (!$quiz) {
    die("प्रश्नमंजुषा सापडली नाही. <a href='../../index.php'>मागे जा</a>");
}

$studentName = $_SESSION['name_mr'] ?? $_SESSION['name'] ?? 'विद्यार्थी';
$std = (int)($_SESSION['std'] ?? $quiz['std']);
$division = $_SESSION['division'] ?? 'A';
$page_title = $quiz['title'] . ' - ऑनलाइन क्विझ';
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - जि. प. शाळा नं. २ कुची</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">

    <!-- LPK Web Quiz Styles -->
    <link href="assets/css/animate.css" rel="stylesheet">
    <link href="assets/css/quiz.css" rel="stylesheet">

    <style>
        :root {
            --primary: #1e3a8a;
            --accent: #f59e0b;
        }
        body {
            font-family: 'Mukta', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #1e293b;
        }
        .header-bar {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            color: white;
            padding: 0.85rem 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .game-status-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
        }
        .stat-val {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }
        .lifeline-btn {
            background: white;
            border: 1.5px solid #cbd5e1;
            border-radius: 30px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 0.88rem;
            color: #334155;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.04);
        }
        .lifeline-btn:hover:not(:disabled) {
            border-color: #2563eb;
            color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(37,99,235,0.15);
        }
        .lifeline-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        .fancy-q-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 12px 30px -8px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        .fancy-options-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        @media (max-width: 768px) {
            .fancy-options-grid {
                grid-template-columns: 1fr;
            }
        }
        .fancy-opt {
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 14px;
            user-select: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        .fancy-opt:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .fancy-opt.opt-a { background: linear-gradient(135deg, #1e3a8a, #2563eb); }
        .fancy-opt.opt-b { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .fancy-opt.opt-c { background: linear-gradient(135deg, #7c3aed, #9333ea); }
        .fancy-opt.opt-d { background: linear-gradient(135deg, #0d9488, #06b6d4); }

        .fancy-opt.state-correct {
            background: linear-gradient(135deg, #059669, #10b981) !important;
            animation: pulseCorrect 0.6s ease;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.5) !important;
        }
        .fancy-opt.state-wrong {
            background: linear-gradient(135deg, #b91c1c, #ef4444) !important;
            animation: shakeWrong 0.5s ease;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.5) !important;
        }
        .fancy-opt.state-disabled {
            opacity: 0.3;
            pointer-events: none;
            filter: grayscale(80%);
        }
        .opt-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        @keyframes pulseCorrect {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        @keyframes shakeWrong {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }
        /* Circular Timer Override */
        .base-timer {
            width: 76px;
            height: 76px;
            margin: auto;
        }
        .base-timer__label {
            width: 76px;
            height: 76px;
            font-family: 'Outfit', sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
        }
        .result-trophy-img {
            width: 110px;
            height: 110px;
            object-fit: contain;
            margin-bottom: 1rem;
            animation: bounceIn 0.8s ease;
        }
    </style>
</head>
<body>

    <!-- Audio Elements for Fancy Sound Effects -->
    <audio id="sndCorrect" preload="auto" src="assets/sound/true.wav"></audio>
    <audio id="sndWrong" preload="auto" src="assets/sound/false.wav"></audio>
    <audio id="sndWin" preload="auto" src="assets/sound/quiz.mp3"></audio>

    <!-- Top Navigation Header -->
    <header class="header-bar sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="../../index.php" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold">
                    <i class="bi bi-arrow-left me-1"></i>मागे (डॅशबोर्ड)
                </a>
                <div>
                    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($quiz['title']) ?></h5>
                    <small class="opacity-75">इयत्ता <?= (int)$quiz['std'] ?> वी · <?= htmlspecialchars($quiz['subject_name']) ?> · विद्यार्थी: <?= htmlspecialchars($studentName) ?></small>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-light rounded-circle" id="btnSoundToggle" onclick="toggleSound()" title="आवाज बंद/चालू">
                <i class="bi bi-volume-up-fill" id="soundIcon"></i>
            </button>
        </div>
    </header>

    <main class="container py-3" style="max-width: 900px;">

        <!-- Active Quiz Player Container -->
        <div id="quizPlayerContainer">
            
            <!-- Game Top Status Bar (Coins, SVG Timer, Score) -->
            <div class="game-status-card">
                <div class="d-flex justify-content-between align-items-center">
                    <!-- Left: Coins -->
                    <div class="stat-box">
                        <img src="assets/images/coins.png" alt="Coins" class="stat-icon animated swing infinite">
                        <div>
                            <div class="stat-val text-warning" id="dispCoins">0</div>
                            <div class="stat-label">नाणी (Coins)</div>
                        </div>
                    </div>

                    <!-- Center: Circular SVG Countdown Timer -->
                    <div class="text-center">
                        <div id="app"></div>
                    </div>

                    <!-- Right: Score -->
                    <div class="stat-box text-end">
                        <div>
                            <div class="stat-val text-primary" id="dispScore">0</div>
                            <div class="stat-label">गुण (Score)</div>
                        </div>
                        <img src="assets/images/score-icon.png" alt="Score" class="stat-icon">
                    </div>
                </div>

                <!-- Lifelines Bar -->
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-3 pt-3 border-top">
                    <button class="lifeline-btn" id="btnLifeFifty" onclick="useFiftyFifty()">
                        ✂️ ५०:५० (दोन पर्याय हटवा)
                    </button>
                    <button class="lifeline-btn" id="btnLifeTime" onclick="useExtraTime()">
                        ⏳ वेळ वाढवा (+१५ सेकंद)
                    </button>
                    <button class="lifeline-btn" id="btnLifeSkip" onclick="skipQuestion()">
                        ⏭️ प्रश्न वगळा (Skip)
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="d-flex justify-content-between align-items-center mb-1 text-muted small fw-bold">
                <span>प्रगती (Progress)</span>
                <span id="dispProgressText">प्रश्न १ / <?= count($quiz['questions']) ?></span>
            </div>
            <div class="progress mb-4" style="height: 10px; border-radius: 10px;">
                <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" id="dispProgressBar" style="width: 5%;"></div>
            </div>

            <!-- Question Card -->
            <div class="fancy-q-card text-center" id="qCard">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-bold mb-3 d-inline-block" id="qBadge">
                    प्रश्न १
                </span>
                <h4 class="fw-bold text-dark mb-0 lh-base" id="qText" style="min-height: 60px;">
                    प्रश्न लोड होत आहे...
                </h4>
            </div>

            <!-- 4 Fancy Option Buttons -->
            <div class="fancy-options-grid mb-4" id="optionsGrid">
                <div class="fancy-opt opt-a" id="optA" onclick="handleOptionSelect('A')">
                    <span class="opt-badge">A</span>
                    <span class="opt-text" id="optTextA">-</span>
                </div>
                <div class="fancy-opt opt-b" id="optB" onclick="handleOptionSelect('B')">
                    <span class="opt-badge">B</span>
                    <span class="opt-text" id="optTextB">-</span>
                </div>
                <div class="fancy-opt opt-c" id="optC" onclick="handleOptionSelect('C')">
                    <span class="opt-badge">C</span>
                    <span class="opt-text" id="optTextC">-</span>
                </div>
                <div class="fancy-opt opt-d" id="optD" onclick="handleOptionSelect('D')">
                    <span class="opt-badge">D</span>
                    <span class="opt-text" id="optTextD">-</span>
                </div>
            </div>

        </div>

        <!-- Result / Celebration View (Hidden initially) -->
        <div id="quizResultContainer" class="d-none">
            <div class="card border-0 shadow-lg p-4 p-md-5 rounded-4 text-center bg-white mb-4">
                <img src="assets/images/leaderboard/trophy.png" alt="Trophy" class="result-trophy-img mx-auto">
                <h2 class="h3 fw-bold text-primary mb-1" id="resTitle">अभिनंदन! उत्कृष्ट कामगिरी! 🌟</h2>
                <p class="text-muted mb-4" id="resSubtitle">तुम्ही ही प्रश्नमंजुषा यशस्वीरीत्या पूर्ण केली आहे.</p>

                <div class="row g-3 justify-content-center mb-4">
                    <div class="col-sm-4 col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="display-6 fw-bold text-primary" id="finalScore">0 / 0</div>
                            <div class="text-muted small fw-bold">एकूण गुण</div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="display-6 fw-bold text-success" id="finalPercent">0%</div>
                            <div class="text-muted small fw-bold">टक्केवारी</div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="display-6 fw-bold text-warning" id="finalCoins">+0</div>
                            <div class="text-muted small fw-bold">मिळालेली नाणी 🪙</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="play.php?id=<?= $quizId ?>" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-xs">
                        <i class="bi bi-arrow-repeat me-1"></i>पुन्हा खेळा (Play Again)
                    </a>
                    <a href="../../index.php" class="btn btn-outline-secondary fw-bold px-4 py-2 rounded-pill">
                        <i class="bi bi-house-door me-1"></i>डॅशबोर्डवर जा
                    </a>
                </div>
            </div>

            <!-- Detailed Question Review Accordion -->
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                <h4 class="h5 fw-bold text-primary mb-3">
                    <i class="bi bi-journal-check me-2"></i>प्रश्नोत्तरे व अचूक उत्तराचे स्पष्टीकरण (Review)
                </h4>
                <div id="reviewContainer">
                    <!-- Dynamic review items -->
                </div>
            </div>
        </div>

    </main>

    <!-- Client-side Fancy Quiz Engine -->
    <script>
        const quizData = <?= json_encode($quiz, JSON_UNESCAPED_UNICODE) ?>;
        const questions = quizData.questions || [];
        const totalQ = questions.length;

        let currentIdx = 0;
        let score = 0;
        let coins = 0;
        let soundEnabled = true;
        let isAnsweringLocked = false;
        let userAnswers = {};
        let startTime = Date.now();

        // Timer Variables
        const TIME_LIMIT = 20; // 20 seconds per question
        let timeLeft = TIME_LIMIT;
        let timerInterval = null;

        // Sounds
        const sndCorrect = document.getElementById('sndCorrect');
        const sndWrong = document.getElementById('sndWrong');
        const sndWin = document.getElementById('sndWin');

        function toggleSound() {
            soundEnabled = !soundEnabled;
            document.getElementById('soundIcon').className = soundEnabled ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute-fill';
        }

        function playSound(audioEl) {
            if (soundEnabled && audioEl) {
                audioEl.currentTime = 0;
                audioEl.play().catch(e => console.log('Audio autoplay prevented:', e));
            }
        }

        // --- CIRCULAR SVG COUNTDOWN TIMER ---
        function renderTimer() {
            const container = document.getElementById('app');
            container.innerHTML = `
                <div class="base-timer">
                    <svg class="base-timer__svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <g class="base-timer__circle">
                            <circle class="base-timer__path-elapsed" cx="50" cy="50" r="45"></circle>
                            <path id="base-timer-path-remaining" stroke-dasharray="283" class="base-timer__path-remaining green" d="M 50, 50 m -45, 0 a 45,45 0 1,0 90,0 a 45,45 0 1,0 -90,0"></path>
                        </g>
                    </svg>
                    <span id="base-timer-label" class="base-timer__label">${timeLeft}</span>
                </div>
            `;
        }

        function startTimer() {
            clearInterval(timerInterval);
            timeLeft = TIME_LIMIT;
            updateTimerDisplay();

            timerInterval = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();

                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    handleTimeUp();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const label = document.getElementById('base-timer-label');
            if (label) label.innerText = timeLeft;

            const path = document.getElementById('base-timer-path-remaining');
            if (path) {
                const fraction = timeLeft / TIME_LIMIT;
                const dashOffset = 283 * (1 - fraction);
                path.style.strokeDashoffset = dashOffset;

                path.classList.remove('green', 'orange', 'red');
                if (timeLeft > 10) path.classList.add('green');
                else if (timeLeft > 5) path.classList.add('orange');
                else path.classList.add('red');
            }
        }

        // --- QUESTION RENDERING ---
        function loadQuestion(idx) {
            if (idx >= totalQ) {
                finishQuiz();
                return;
            }

            isAnsweringLocked = false;
            const q = questions[idx];

            // Update Progress
            const pct = Math.round(((idx + 1) / totalQ) * 100);
            document.getElementById('dispProgressBar').style.width = pct + '%';
            document.getElementById('dispProgressText').innerText = `प्रश्न ${idx + 1} / ${totalQ}`;

            // Update Question
            const card = document.getElementById('qCard');
            card.classList.remove('zoomIn');
            void card.offsetWidth; // trigger reflow
            card.classList.add('zoomIn', 'animated');

            document.getElementById('qBadge').innerText = `प्रश्न क्र. ${idx + 1}`;
            document.getElementById('qText').innerText = q.question_text;

            // Reset Options
            ['A', 'B', 'C', 'D'].forEach(opt => {
                const el = document.getElementById('opt' + opt);
                el.className = `fancy-opt opt-${opt.toLowerCase()}`;
                const key = 'option_' + opt.toLowerCase();
                document.getElementById('optText' + opt).innerText = q[key] || '-';
            });

            // Start Timer
            startTimer();
        }

        function handleOptionSelect(selectedOpt) {
            if (isAnsweringLocked) return;
            isAnsweringLocked = true;
            clearInterval(timerInterval);

            const q = questions[currentIdx];
            const correctOpt = (q.correct_option || 'A').toUpperCase();
            const isCorrect = (selectedOpt === correctOpt);

            userAnswers[q.id] = selectedOpt;

            const selectedEl = document.getElementById('opt' + selectedOpt);
            const correctEl = document.getElementById('opt' + correctOpt);

            if (isCorrect) {
                playSound(sndCorrect);
                selectedEl.classList.add('state-correct');
                score++;
                coins += 10;
                document.getElementById('dispScore').innerText = score;
                document.getElementById('dispCoins').innerText = coins;
            } else {
                playSound(sndWrong);
                selectedEl.classList.add('state-wrong');
                if (correctEl) correctEl.classList.add('state-correct');
            }

            // Next question after brief celebration delay
            setTimeout(() => {
                currentIdx++;
                loadQuestion(currentIdx);
            }, 1200);
        }

        function handleTimeUp() {
            if (isAnsweringLocked) return;
            isAnsweringLocked = true;

            const q = questions[currentIdx];
            userAnswers[q.id] = ''; // blank answer on timeout
            playSound(sndWrong);

            const correctOpt = (q.correct_option || 'A').toUpperCase();
            const correctEl = document.getElementById('opt' + correctOpt);
            if (correctEl) correctEl.classList.add('state-correct');

            setTimeout(() => {
                currentIdx++;
                loadQuestion(currentIdx);
            }, 1300);
        }

        // --- LIFELINES ---
        function useFiftyFifty() {
            if (isAnsweringLocked) return;
            const q = questions[currentIdx];
            const correctOpt = (q.correct_option || 'A').toUpperCase();

            const wrongOpts = ['A', 'B', 'C', 'D'].filter(o => o !== correctOpt);
            // Pick two wrong options to disable
            wrongOpts.sort(() => Math.random() - 0.5);
            const toHide = wrongOpts.slice(0, 2);

            toHide.forEach(opt => {
                document.getElementById('opt' + opt).classList.add('state-disabled');
            });

            document.getElementById('btnLifeFifty').disabled = true;
        }

        function useExtraTime() {
            timeLeft += 15;
            updateTimerDisplay();
            document.getElementById('btnLifeTime').disabled = true;
        }

        function skipQuestion() {
            if (isAnsweringLocked) return;
            clearInterval(timerInterval);
            currentIdx++;
            document.getElementById('btnLifeSkip').disabled = true;
            loadQuestion(currentIdx);
        }

        // --- FINISH QUIZ & SUBMIT RESULT ---
        async function finishQuiz() {
            clearInterval(timerInterval);
            playSound(sndWin);

            document.getElementById('quizPlayerContainer').classList.add('d-none');
            document.getElementById('quizResultContainer').classList.remove('d-none');
            window.scrollTo({ top: 0, behavior: 'smooth' });

            const timeSpent = Math.round((Date.now() - startTime) / 1000);
            const pct = Math.round((score / totalQ) * 100);

            document.getElementById('finalScore').innerText = `${score} / ${totalQ}`;
            document.getElementById('finalPercent').innerText = `${pct}%`;
            document.getElementById('finalCoins').innerText = `+${coins}`;

            if (pct >= 75) {
                document.getElementById('resTitle').innerText = 'अति उत्कृष्ट! सुवर्ण पदक! 🌟🏆';
                document.getElementById('resSubtitle').innerText = 'तुम्ही उत्कृष्ट गुणांसह ही प्रश्नमंजुषा उत्तीर्ण केली आहे!';
            } else if (pct >= 40) {
                document.getElementById('resTitle').innerText = 'अभिनंदन! तुम्ही उत्तीर्ण झालात! 👏🎉';
                document.getElementById('resSubtitle').innerText = 'छान प्रयत्न! आणखी सराव करून १००% गुण मिळवण्याचा प्रयत्न करा.';
            } else {
                document.getElementById('resTitle').innerText = 'छान प्रयत्न! आणखी सराव करा! 📚💪';
                document.getElementById('resSubtitle').innerText = 'पुन्हा एकदा घटक काळजीपूर्वक वाचा आणि पुन्हा टेस्ट सोडवा.';
            }

            // Post result to API
            try {
                const res = await fetch('../../api/quiz.php?action=submit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        quiz_id: <?= $quizId ?>,
                        answers: userAnswers,
                        time_spent: timeSpent
                    })
                });
                const data = await res.json();
                if (data.success && data.result && data.result.review) {
                    renderReview(data.result.review);
                }
            } catch (e) {
                console.error("Submit error:", e);
            }
        }

        function renderReview(reviewList) {
            const cont = document.getElementById('reviewContainer');
            cont.innerHTML = reviewList.map((item, idx) => {
                const isCorrect = item.is_correct;
                return `
                    <div class="p-3 mb-3 rounded-3 border ${isCorrect ? 'border-success-subtle bg-success-subtle/10' : 'border-danger-subtle bg-danger-subtle/10'}" style="border-left: 5px solid ${isCorrect ? '#10b981' : '#ef4444'} !important;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge ${isCorrect ? 'bg-success' : 'bg-danger'} px-2.5 py-1 fw-bold">
                                ${isCorrect ? '✓ बरोबर उत्तर' : '✗ चुकीचे उत्तर'}
                            </span>
                            <small class="text-muted">प्रश्न क्र. ${idx + 1}</small>
                        </div>
                        <h6 class="fw-bold text-dark mb-2">${escapeHtml(item.question_text)}</h6>
                        <div class="small mb-1">
                            तुमचे उत्तर: <strong class="${isCorrect ? 'text-success' : 'text-danger'}">${item.user_answer ? '(' + item.user_answer + ') ' + (item['option_' + item.user_answer.toLowerCase()] || '') : 'वेळ संपली / सोडवले नाही'}</strong>
                        </div>
                        <div class="small text-success mb-2">
                            योग्य उत्तर: <strong>(${item.correct_answer}) ${escapeHtml(item['option_' + item.correct_answer.toLowerCase()] || '')}</strong>
                        </div>
                        ${item.explanation ? `<div class="p-2 bg-light rounded text-secondary small">💡 <strong>स्पष्टीकरण:</strong> ${escapeHtml(item.explanation)}</div>` : ''}
                    </div>
                `;
            }).join('');
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        // Initialize Game
        document.addEventListener('DOMContentLoaded', () => {
            renderTimer();
            loadQuestion(0);
        });
    </script>
</body>
</html>
