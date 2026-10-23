<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/paripath_engine.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$assembly = paripath_get_full_assembly($date);
$panchang = $assembly['panchang'];
$dinvishesh = $assembly['dinvishesh'];
$suvichar = $assembly['suvichar'];
$subhashit = $assembly['subhashit'];
$notes = $assembly['custom_notes'];

$school = isLoggedIn() ? getSchool() : null;
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? '') ?: 'महाराष्ट्र राज्य प्राथमिक/माध्यमिक शाळा');
$theme = $_GET['theme'] ?? 'green'; // green, black, navy
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechGuruji डिजिटल परिपाठ फळा — <?= sanitize($assembly['marathi_date']) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="192x192" href="<?= APP_URL ?>/assets/images/techguruji_logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/images/favicon.png">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/images/favicon.svg">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/images/techguruji_logo.png">
    <link rel="shortcut icon" href="<?= APP_URL ?>/favicon.ico">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800;900&family=Yatra+One&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- html2canvas for High-Quality JPG Export and WhatsApp Sharing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Mukta', sans-serif;
            background-color: #0c1a14;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            user-select: none;
        }

        /* Chalkboard themes */
        body.theme-green {
            background: radial-gradient(circle at center, #1b4332 0%, #081c15 100%);
            color: #f1faee;
        }
        body.theme-black {
            background: radial-gradient(circle at center, #262626 0%, #0a0a0a 100%);
            color: #fafafa;
        }
        body.theme-navy {
            background: radial-gradient(circle at center, #1e3a5f 0%, #0a1118 100%);
            color: #f0f9ff;
        }

        /* Wooden Frame */
        .board-frame {
            border: 14px solid #8b5a2b;
            border-image: linear-gradient(to bottom right, #a06535, #5c3412, #8b5a2b, #3d1f05) 16;
            box-shadow: inset 0 0 40px rgba(0,0,0,0.85), 0 10px 30px rgba(0,0,0,0.7);
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 24px 32px;
            position: relative;
        }

        /* Chalk dust overlay texture */
        .board-frame::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 0);
            background-size: 24px 24px;
            pointer-events: none;
            opacity: 0.7;
        }

        /* Top control bar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px dashed rgba(255,255,255,0.25);
            position: relative;
            z-index: 10;
        }
        .btn-ctrl {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            transition: all 0.2s;
        }
        .btn-ctrl:hover {
            background: rgba(255,255,255,0.25);
            color: #ffd166;
        }

        /* Blackboard Header */
        .chalk-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .school-title {
            font-size: 2rem;
            font-weight: 900;
            letter-spacing: 0.02em;
            color: #ffd166;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.6);
            margin-bottom: 6px;
        }
        .meta-strip {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            font-size: 1.25rem;
            font-weight: 700;
            color: #e2e8f0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        .meta-pill {
            background: rgba(255,255,255,0.1);
            padding: 4px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Chalkboard Main Grid */
        .board-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 28px;
            flex: 1;
        }
        @media (max-width: 992px) {
            .board-grid {
                grid-template-columns: 1fr;
            }
            .school-title {
                font-size: 1.5rem;
            }
            .board-frame {
                padding: 16px;
                border-width: 8px;
            }
        }

        /* Chalk Sections */
        .chalk-box {
            background: rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255,255,255,0.18);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.3);
            position: relative;
        }
        .chalk-box-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #ffd166;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px dashed rgba(255,255,255,0.2);
            padding-bottom: 6px;
        }
        .chalk-suvichar {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1.6;
            color: #ffffff;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        .chalk-author {
            text-align: right;
            font-size: 1.1rem;
            color: #a7f3d0;
            margin-top: 10px;
            font-weight: 700;
        }
        .chalk-shloka {
            font-size: 1.35rem;
            line-height: 1.7;
            color: #fef08a;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
        }
        .chalk-meaning {
            font-size: 1.05rem;
            color: #e2e8f0;
            line-height: 1.5;
        }

        .event-chalk-item {
            border-bottom: 1px dashed rgba(255,255,255,0.15);
            padding: 10px 0;
        }
        .event-chalk-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .event-chalk-badge {
            display: inline-block;
            background: #ffd166;
            color: #1e293b;
            font-weight: 800;
            font-size: 0.82rem;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 8px;
        }
        .event-chalk-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
        }

        .sun-moon-strip {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed rgba(255,255,255,0.2);
            font-size: 1rem;
            color: #f1faee;
        }

        /* Chalk tray at bottom */
        .chalk-tray {
            background: linear-gradient(to bottom, #4a2810, #2d1606);
            height: 16px;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.7);
            position: relative;
        }
    </style>
</head>
<body class="theme-<?= htmlspecialchars($theme) ?>">

    <div class="board-frame">
        <!-- Controls Bar (Ignored in html2canvas capture) -->
        <div class="toolbar" data-html2canvas-ignore="true">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <a href="index.php?date=<?= $date ?>" class="btn-ctrl" title="मुख्य परिपाठ डॅशबोर्ड">
                    <i class="bi bi-arrow-left"></i> परत
                </a>
                <a href="blackboard.php?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>&theme=<?= $theme ?>" class="btn-ctrl">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <span class="btn-ctrl" style="cursor:default; font-weight:700;">
                    <?= sanitize($assembly['marathi_date']) ?>
                </span>
                <a href="blackboard.php?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>&theme=<?= $theme ?>" class="btn-ctrl">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <!-- Theme Switcher -->
                <a href="blackboard.php?date=<?= $date ?>&theme=green" class="btn-ctrl <?= $theme==='green'?'style=\"border-color:#ffd166;\"':'' ?>">
                    🟢 हिरवा
                </a>
                <a href="blackboard.php?date=<?= $date ?>&theme=black" class="btn-ctrl <?= $theme==='black'?'style=\"border-color:#ffd166;\"':'' ?>">
                    ⚫ काळा
                </a>
                <a href="blackboard.php?date=<?= $date ?>&theme=navy" class="btn-ctrl <?= $theme==='navy'?'style=\"border-color:#ffd166;\"':'' ?>">
                    🔵 निळा
                </a>

                <!-- JPG Export & WhatsApp Share -->
                <button type="button" onclick="downloadBlackboardJPG()" class="btn-ctrl" style="background:#0284c7; border-color:#38bdf8; font-weight:700;" title="JPG इमेज डाउनलोड करा">
                    <i class="bi bi-file-earmark-arrow-down-fill"></i> JPG डाउनलोड
                </button>
                <button type="button" onclick="shareBlackboardWhatsApp()" class="btn-ctrl" style="background:#16a34a; border-color:#4ade80; font-weight:700; color:#fff;" title="WhatsApp वर थेट पाठवा">
                    <i class="bi bi-whatsapp"></i> WhatsApp शेअर
                </button>

                <button onclick="toggleFullScreen()" class="btn-ctrl" title="पूर्ण स्क्रीन (Full Screen)">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
                <a href="print.php?date=<?= $date ?>" target="_blank" class="btn-ctrl">
                    <i class="bi bi-printer"></i> प्रिंट
                </a>
            </div>
        </div>

        <!-- School Header -->
        <div class="chalk-header">
            <h1 class="school-title">
                <?= sanitize($schoolName) ?>
            </h1>
            <div class="meta-strip">
                <span class="meta-pill">📅 <?= sanitize($assembly['marathi_date']) ?></span>
                <span class="meta-pill" style="color:#ffd166;">✨ वार: <?= sanitize($assembly['marathi_day']) ?></span>
                <span class="meta-pill">🌙 तिथी: <?= sanitize($panchang['tithi'] ?? 'शुक्ल दशमी') ?></span>
                <span class="meta-pill">⭐ नक्षत्र: <?= sanitize($panchang['nakshatra'] ?? '') ?></span>
            </div>
        </div>

        <!-- Main Content 2 Columns -->
        <div class="board-grid">
            
            <!-- Left Side: Suvichar & Subhashit -->
            <div>
                <!-- Today's Suvichar -->
                <div class="chalk-box">
                    <div class="chalk-box-title">
                        <i class="bi bi-quote"></i> आजचा सुविचार
                    </div>
                    <div class="chalk-suvichar">
                        "<?= sanitize($suvichar['text_mr'] ?? '') ?>"
                    </div>
                    <?php if (!empty($suvichar['author'])): ?>
                    <div class="chalk-author">
                        — <?= sanitize($suvichar['author']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($suvichar['meaning_mr'])): ?>
                    <div class="chalk-meaning mt-2" style="border-top:1px dashed rgba(255,255,255,0.15); padding-top:8px; font-size:1.05rem; color:#cbd5e1;">
                        <strong>भावार्थ:</strong> <?= sanitize($suvichar['meaning_mr']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sanskrit Subhashit / Ovi -->
                <div class="chalk-box">
                    <div class="chalk-box-title">
                        <i class="bi bi-book-half"></i> संस्कृत सुभाषित / ज्ञानेश्वरी ओवी (<?= sanitize($subhashit['granth'] ?? 'सुभाषित') ?>)
                    </div>
                    <div class="chalk-shloka">
                        <?= nl2br(sanitize($subhashit['text_sa'] ?? '')) ?>
                    </div>
                    <div class="chalk-meaning">
                        <strong>मराठी अर्थ:</strong> <?= sanitize($subhashit['meaning_mr'] ?? '') ?>
                    </div>
                    <?php if (!empty($subhashit['usage_mr'])): ?>
                    <div class="chalk-meaning mt-2" style="color:#6ee7b7; font-weight:600;">
                        💡 <strong>विद्यार्थ्यांसाठी बोध:</strong> <?= sanitize($subhashit['usage_mr']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Word of the Day & GK -->
                <div class="chalk-box" style="margin-bottom:0;">
                    <div class="chalk-box-title">
                        <i class="bi bi-pencil-square"></i> आजचा शब्द व सामान्य ज्ञान
                    </div>
                    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <span style="color:#ffd166; font-weight:800;">📖 आजचा शब्द:</span>
                            <div style="font-size:1.15rem; font-weight:700; color:#fff;">
                                <?= sanitize($notes['word_of_day'] ?: 'परिश्रम (Hard Work)') ?>
                            </div>
                            <div style="font-size:0.95rem; color:#cbd5e1;">
                                <?= sanitize($notes['word_meaning'] ?: 'कार्यात यश मिळवण्यासाठी केलेले सततचे प्रयत्न.') ?>
                            </div>
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <span style="color:#ffd166; font-weight:800;">🧠 सामान्य ज्ञान:</span>
                            <div style="font-size:1.05rem; font-weight:700; color:#fff;">
                                <?= sanitize($notes['gk_question'] ?: 'भारताचे पहिले अंतराळवीर कोण होते?') ?>
                            </div>
                            <div style="font-size:0.95rem; color:#6ee7b7; font-weight:700;">
                                उत्तर: <?= sanitize($notes['gk_answer'] ?: 'विंग कमांडर राकेश शर्मा') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Dinvishesh & Panchang Highlights -->
            <div>
                <!-- Dinvishesh -->
                <div class="chalk-box">
                    <div class="chalk-box-title">
                        <i class="bi bi-clock-history"></i> आजचे दिनविशेष
                    </div>
                    <?php if (empty($dinvishesh)): ?>
                    <div style="text-align:center; padding:20px; color:#cbd5e1;">
                        आजचे दिनविशेष उपलब्ध आहेत.
                    </div>
                    <?php else: ?>
                        <?php 
                        // Show top 5 significant events on blackboard
                        $chalkEvents = array_slice($dinvishesh, 0, 5);
                        foreach ($chalkEvents as $ev): 
                            $badgeText = match($ev['category']) {
                                'international_day' => 'विशेष दिन',
                                'birth_anniversary' => 'जयंती',
                                'death_anniversary' => 'पुण्यतिथी',
                                default => 'घटना'
                            };
                        ?>
                        <div class="event-chalk-item">
                            <div>
                                <span class="event-chalk-badge"><?= $badgeText ?></span>
                                <?php if (!empty($ev['event_year'])): ?>
                                <span style="color:#94a3b8; font-weight:700; font-size:0.9rem; margin-right:6px;">
                                    (सन <?= paripath_marathi_digits($ev['event_year']) ?>)
                                </span>
                                <?php endif; ?>
                                <span class="event-chalk-title"><?= sanitize($ev['title_mr']) ?></span>
                            </div>
                            <?php if (!empty($ev['short_info_mr'])): ?>
                            <div style="font-size:0.95rem; color:#cbd5e1; margin-top:4px; padding-left:4px;">
                                <?= sanitize($ev['short_info_mr']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Panchang & Sun/Moon -->
                <div class="chalk-box" style="margin-bottom:0;">
                    <div class="chalk-box-title">
                        <i class="bi bi-brightness-high"></i> दैनिक पंचांग व सूर्य-चंद्र वेळ
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:1.05rem;">
                        <div><strong style="color:#94a3b8;">महिना / पक्ष:</strong> <?= sanitize($panchang['hindu_month'] ?? 'भाद्रपद') ?> (<?= sanitize($panchang['paksha'] ?? '') ?>)</div>
                        <div><strong style="color:#94a3b8;">ऋतू:</strong> <?= sanitize($panchang['ritu'] ?? 'शरद') ?></div>
                        <div><strong style="color:#94a3b8;">योग:</strong> <?= sanitize($panchang['yoga'] ?? '-') ?></div>
                        <div><strong style="color:#94a3b8;">करण:</strong> <?= sanitize($panchang['karana'] ?? '-') ?></div>
                    </div>

                    <div class="sun-moon-strip">
                        <div>
                            <span style="color:#ffd166; font-size:1.1rem; display:block;"><i class="bi bi-sunrise-fill"></i> सूर्योदय</span>
                            <strong><?= sanitize($panchang['sunrise'] ?? '06:24 AM') ?></strong>
                        </div>
                        <div>
                            <span style="color:#8CCDB2; font-size:1.1rem; display:block;"><i class="bi bi-sunset-fill"></i> सूर्यास्त</span>
                            <strong><?= sanitize($panchang['sunset'] ?? '06:33 PM') ?></strong>
                        </div>
                        <div>
                            <span style="color:#f87171; font-size:1.1rem; display:block;"><i class="bi bi-exclamation-octagon"></i> राहू काळ</span>
                            <strong><?= sanitize($panchang['rahu_kaal'] ?? '-') ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($notes['announcement'])): ?>
                    <div style="margin-top:14px; padding-top:10px; border-top:1px dashed rgba(255,255,255,0.2);">
                        <strong style="color:#ffd166;"><i class="bi bi-megaphone-fill"></i> आजची सूचना:</strong>
                        <div style="font-size:1.05rem; color:#fff; margin-top:2px;">
                            <?= nl2br(sanitize($notes['announcement'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

    <!-- Wooden Chalk Tray -->
    <div class="chalk-tray"></div>

    <!-- Toast notification overlay -->
    <div id="boardToast" style="display:none; position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:#0f172a; color:#fff; padding:12px 24px; border-radius:30px; box-shadow:0 10px 30px rgba(0,0,0,0.5); z-index:9999; font-size:1rem; font-weight:700; border:1px solid #38bdf8; text-align:center;">
        <span id="boardToastMsg">इमेज तयार होत आहे...</span>
    </div>

    <script>
        const currentDate = '<?= $date ?>';
        const schoolName = '<?= addslashes(sanitize($schoolName)) ?>';
        const marathiDate = '<?= addslashes(sanitize($assembly['marathi_date'])) ?>';
        const marathiDay = '<?= addslashes(sanitize($assembly['marathi_day'])) ?>';
        const suvicharText = '<?= addslashes(sanitize($suvichar['text_mr'] ?? '')) ?>';

        function showBoardToast(msg, duration = 3000) {
            const toast = document.getElementById('boardToast');
            const toastMsg = document.getElementById('boardToastMsg');
            if (!toast || !toastMsg) return;
            toastMsg.innerText = msg;
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, duration);
        }

        async function getBlackboardBlob() {
            const board = document.querySelector('.board-frame');
            if (!board) throw new Error('Board element not found');

            // Render crisp 2x scale canvas
            const canvas = await html2canvas(board, {
                scale: 2,
                useCORS: true,
                backgroundColor: null,
                logging: false,
                scrollX: 0,
                scrollY: -window.scrollY
            });

            return new Promise((resolve, reject) => {
                canvas.toBlob((blob) => {
                    if (blob) resolve(blob);
                    else reject(new Error('Canvas blob generation failed'));
                }, 'image/jpeg', 0.95);
            });
        }

        async function downloadBlackboardJPG() {
            try {
                showBoardToast('📸 JPG इमेज तयार होत आहे, कृपया थांबा...', 4000);
                const blob = await getBlackboardBlob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `TechGuruji_Paripath_${currentDate}.jpg`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showBoardToast('✅ दैनिक परिपाठ JPG इमेज यशस्वीरित्या सेव्ह झाली!');
            } catch (err) {
                console.error(err);
                alert('इमेज तयार करताना त्रुटी आली: ' + err.message);
            }
        }

        async function shareBlackboardWhatsApp() {
            try {
                showBoardToast('📲 WhatsApp साठी इमेज तयार होत आहे...', 5000);
                const blob = await getBlackboardBlob();
                const fileName = `TechGuruji_Paripath_${currentDate}.jpg`;
                const file = new File([blob], fileName, { type: 'image/jpeg' });

                const caption = `*शालेय दैनिक परिपाठ फलक लेखन*\n🏫 *${schoolName}*\n📅 *तारीख:* ${marathiDate} (${marathiDay})\n\n💬 *आजचा सुविचार:* "${suvicharText}"\n\n✨ _TechGuruji डिजिटल परिपाठ पोर्टलद्वारे_`;

                // Check if browser supports Web Share API with files (Android, iOS Safari, Mobile Chrome)
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    try {
                        await navigator.share({
                            files: [file],
                            title: `दैनिक परिपाठ — ${marathiDate}`,
                            text: caption
                        });
                        showBoardToast('✅ WhatsApp वर शेअर केले!');
                        return;
                    } catch (shareErr) {
                        if (shareErr.name === 'AbortError') {
                            showBoardToast('शेअर रद्द केले');
                            return;
                        }
                    }
                }

                // Fallback: Download JPG + Open WhatsApp Web
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                const waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(caption + '\n\n(कृपया डाउनलोड झालेली फळा इमेज सोबत पाठवा)');
                window.open(waUrl, '_blank');
                showBoardToast('✅ JPG इमेज डाउनलोड झाली व WhatsApp उघडले!');
            } catch (err) {
                console.error(err);
                alert('WhatsApp शेअर त्रुटी: ' + err.message);
            }
        }

        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    alert(`पूर्ण स्क्रीन एरर: ${err.message}`);
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }
    </script>
</body>
</html>
