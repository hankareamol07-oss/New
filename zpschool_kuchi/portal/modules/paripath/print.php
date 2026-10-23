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
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? '') ?: 'महाराष्ट्र राज्य प्राथमिक व माध्यमिक शाळा');
$udise = $school['udise_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>शालेय परिपाठ — <?= sanitize($assembly['marathi_date']) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Mukta', sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 20px;
        }
        .a4-sheet {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 16mm 16mm;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            border: 2px solid #0f172a;
        }
        
        .no-print-bar {
            max-width: 210mm;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-print {
            background: #0284c7;
            color: #fff;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        .btn-back {
            background: #e2e8f0;
            color: #1e293b;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-family: inherit;
        }

        .header-box {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .school-name {
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: -0.01em;
            color: #0f172a;
        }
        .udise-line {
            font-size: 0.85rem;
            color: #475569;
            margin-top: 2px;
        }
        .paripath-tag {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            padding: 2px 14px;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 700;
            margin-top: 6px;
        }

        .date-strip {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 14px;
        }

        .section-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
            background: #fff;
        }
        .section-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }

        .suvichar-text {
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.5;
            color: #0f172a;
            text-align: center;
            padding: 4px 0;
        }
        .suvichar-author {
            text-align: right;
            font-size: 0.9rem;
            color: #475569;
            font-weight: 600;
        }

        .subhashit-text {
            font-size: 1.05rem;
            font-weight: 700;
            text-align: center;
            line-height: 1.6;
            color: #1e293b;
        }

        .panchang-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            font-size: 0.9rem;
        }
        .panchang-cell {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .panchang-cell small {
            display: block;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .panchang-cell strong {
            font-size: 0.92rem;
            color: #0f172a;
        }

        .din-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .din-list li {
            font-size: 0.9rem;
            padding: 4px 0;
            border-bottom: 1px dotted #e2e8f0;
            line-height: 1.4;
        }
        .din-list li:last-child {
            border-bottom: none;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px solid #94a3b8;
            font-size: 0.9rem;
            font-weight: 700;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .a4-sheet {
                box-shadow: none;
                padding: 10mm 12mm;
                border: 1.5px solid #000;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <a href="index.php?date=<?= $date ?>" class="btn-back">
            ← परिपाठ डॅशबोर्ड
        </a>
        <button onclick="window.print()" class="btn-print">
            🖨️ A4 प्रिंट काढा
        </button>
    </div>

    <div class="a4-sheet">

        <!-- Header -->
        <div class="header-box">
            <div class="school-name"><?= sanitize($schoolName) ?></div>
            <?php if (!empty($udise)): ?>
            <div class="udise-line">युडायस क्रमांक (UDISE): <?= sanitize($udise) ?></div>
            <?php endif; ?>
            <div class="paripath-tag">शालेय दैनिक परिपाठ फलक</div>
        </div>

        <!-- Date & Day Strip -->
        <div class="date-strip">
            <span>📅 दिनांक: <?= sanitize($assembly['marathi_date']) ?></span>
            <span>✨ वार: <?= sanitize($assembly['marathi_day']) ?></span>
            <span>🌙 तिथी: <?= sanitize($panchang['tithi'] ?? 'शुक्ल दशमी') ?></span>
        </div>

        <!-- 1. Suvichar -->
        <div class="section-box">
            <div class="section-title">
                <span>📌 आजचा सुविचार</span>
                <span style="font-size:0.8rem; color:#64748b;">Thought of the Day</span>
            </div>
            <div class="suvichar-text">
                "<?= sanitize($suvichar['text_mr'] ?? '') ?>"
            </div>
            <?php if (!empty($suvichar['author'])): ?>
            <div class="suvichar-author">— <?= sanitize($suvichar['author']) ?></div>
            <?php endif; ?>
            <?php if (!empty($suvichar['meaning_mr'])): ?>
            <div style="font-size:0.85rem; color:#475569; margin-top:4px; border-top:1px dotted #e2e8f0; padding-top:4px;">
                <strong>अर्थ:</strong> <?= sanitize($suvichar['meaning_mr']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 2. Sanskrit Subhashit / Ovi -->
        <div class="section-box">
            <div class="section-title">
                <span>🕉️ संस्कृत सुभाषित / ज्ञानेश्वरी ओवी</span>
                <span style="font-size:0.8rem; color:#64748b;"><?= sanitize($subhashit['granth'] ?? 'सुभाषित') ?></span>
            </div>
            <div class="subhashit-text">
                <?= nl2br(sanitize($subhashit['text_sa'] ?? '')) ?>
            </div>
            <div style="font-size:0.85rem; color:#334155; margin-top:4px; border-top:1px dotted #e2e8f0; padding-top:4px;">
                <strong>मराठी भावार्थ:</strong> <?= sanitize($subhashit['meaning_mr'] ?? '') ?>
            </div>
        </div>

        <!-- 3. Panchang Box -->
        <div class="section-box">
            <div class="section-title">
                <span>📅 दैनिक पंचांग</span>
                <span style="font-size:0.8rem; color:#64748b;">महाराष्ट्र कालगणना</span>
            </div>
            <div class="panchang-grid">
                <div class="panchang-cell">
                    <small>हिंदू महिना</small>
                    <strong><?= sanitize($panchang['hindu_month'] ?? 'भाद्रपद') ?></strong>
                </div>
                <div class="panchang-cell">
                    <small>पक्ष</small>
                    <strong><?= sanitize($panchang['paksha'] ?? 'शुक्ल पक्ष') ?></strong>
                </div>
                <div class="panchang-cell">
                    <small>नक्षत्र</small>
                    <strong><?= sanitize($panchang['nakshatra'] ?? '-') ?></strong>
                </div>
                <div class="panchang-cell">
                    <small>ऋतू</small>
                    <strong><?= sanitize($panchang['ritu'] ?? 'शरद') ?></strong>
                </div>
                <div class="panchang-cell">
                    <small>सूर्योदय</small>
                    <strong><?= sanitize($panchang['sunrise'] ?? '06:24 AM') ?></strong>
                </div>
                <div class="panchang-cell">
                    <small>सूर्यास्त</small>
                    <strong><?= sanitize($panchang['sunset'] ?? '06:33 PM') ?></strong>
                </div>
                <div class="panchang-cell">
                    <small>योग</small>
                    <strong><?= sanitize($panchang['yoga'] ?? '-') ?></strong>
                </div>
                <div class="panchang-cell">
                    <small>राहू काळ</small>
                    <strong><?= sanitize($panchang['rahu_kaal'] ?? '-') ?></strong>
                </div>
            </div>
        </div>

        <!-- 4. Dinvishesh Highlights -->
        <div class="section-box">
            <div class="section-title">
                <span>📌 आजचे दिनविशेष (महत्त्वाच्या ऐतिहासिक घटना व जयंती)</span>
                <span style="font-size:0.8rem; color:#64748b;">Daily Special</span>
            </div>
            <ul class="din-list">
                <?php 
                $printEvents = array_slice($dinvishesh, 0, 5);
                if (empty($printEvents)) {
                    echo '<li>आजचे विशेष दिन नोंदवलेले नाहीत.</li>';
                } else {
                    foreach ($printEvents as $ev) {
                        $yearTxt = !empty($ev['event_year']) ? '<strong>(' . paripath_marathi_digits($ev['event_year']) . ')</strong> ' : '';
                        $short = !empty($ev['short_info_mr']) ? ' — ' . sanitize($ev['short_info_mr']) : '';
                        echo '<li>• ' . $yearTxt . '<strong>' . sanitize($ev['title_mr']) . '</strong>' . $short . '</li>';
                    }
                }
                ?>
            </ul>
        </div>

        <!-- 5. Word of Day & GK -->
        <div class="section-box">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div>
                    <div style="font-weight:800; font-size:0.88rem; color:#0f172a; margin-bottom:2px;">📖 आजचा शब्द:</div>
                    <div style="font-size:0.95rem; font-weight:700;"><?= sanitize($notes['word_of_day'] ?: 'परिश्रम (Hard Work)') ?></div>
                    <div style="font-size:0.82rem; color:#475569;"><?= sanitize($notes['word_meaning'] ?: 'कार्यात यश मिळवण्यासाठी केलेले सततचे प्रयत्न.') ?></div>
                </div>
                <div>
                    <div style="font-weight:800; font-size:0.88rem; color:#0f172a; margin-bottom:2px;">🧠 सामान्य ज्ञान:</div>
                    <div style="font-size:0.88rem; font-weight:600;"><?= sanitize($notes['gk_question'] ?: 'भारताचे पहिले अंतराळवीर कोण होते?') ?></div>
                    <div style="font-size:0.85rem; font-weight:700; color:#0369a1;">उत्तर: <?= sanitize($notes['gk_answer'] ?: 'विंग कमांडर राकेश शर्मा') ?></div>
                </div>
            </div>
            <?php if (!empty($notes['announcement'])): ?>
            <div style="margin-top:8px; padding-top:6px; border-top:1px dotted #cbd5e1; font-size:0.85rem;">
                <strong>📢 शालेय सूचना:</strong> <?= sanitize($notes['announcement']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div>
                परिपाठ सादरकर्ता विद्यार्थी / वर्ग: ___________________
            </div>
            <div>
                परिपाठ प्रमुख शिक्षक: ___________________
            </div>
            <div>
                मुख्याध्यापक स्वाक्षरी व शिक्का: ___________________
            </div>
        </div>

    </div>

</body>
</html>
