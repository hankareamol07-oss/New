<?php
require_once __DIR__ . '/includes/functions.php';

$paperId = (int)($_GET['id'] ?? 0);
$paper = null;
$questions = [];
$totalQuestions = 0;
$answers = []; // question_index (1-based) => option (1..4)
$sectionRanges = []; // [ ['title' => ..., 'from' => 1, 'to' => 25], ... ]

$currentSet = strtoupper(trim($_GET['set'] ?? 'A'));
if (!in_array($currentSet, ['A', 'B', 'C', 'D'], true)) {
    $currentSet = 'A';
}

if ($paperId > 0) {
    $paper = ep_paper($paperId);
    if ($paper) {
        $paper = ep_scramble_paper($paper, $currentSet);
    }
}

if ($paper) {
    $allIds = [];
    $qNo = 0;
    foreach ($paper['sections'] as $sec) {
        $secCount = count($sec['question_ids']);
        if ($secCount > 0) {
            $from = $qNo + 1;
            $to = $qNo + $secCount;
            $sectionRanges[] = [
                'title' => $sec['title'] . ($sec['subtitle'] ? ' - ' . $sec['subtitle'] : ''),
                'from' => $from,
                'to' => $to,
                'marks_per_q' => $sec['marks_per_q'] ?? null,
            ];
            $qNo += $secCount;
            $allIds = array_merge($allIds, $sec['question_ids']);
        }
    }
    $totalQuestions = count($allIds);
    $loadedQs = [];
    foreach (ep_questions_by_ids($allIds) as $q) {
        $loadedQs[$q['question_id']] = $q;
    }
    $idx = 0;
    foreach ($allIds as $qid) {
        $idx++;
        if (isset($loadedQs[$qid])) {
            $opt = ep_parse_omr_option($loadedQs[$qid]['answer_markup'] ?: ($loadedQs[$qid]['answer_markup2'] ?? ''));
            if ($opt !== null) {
                $answers[$idx] = $opt;
            }
        }
    }
} else {
    // Standalone OMR mode - default to 75 questions
    $totalQuestions = (int)($_GET['count'] ?? 75);
    if ($totalQuestions <= 0) $totalQuestions = 75;
    if ($totalQuestions > 150) $totalQuestions = 150;
}

// User-overridable count
if (!empty($_GET['count']) && (int)$_GET['count'] > 0) {
    $totalQuestions = min(150, max(1, (int)$_GET['count']));
}

$isKey = !empty($_GET['key']);

// Default option format is A, B, C, D ('letters')
$optFmt = ($_GET['fmt'] ?? 'letters') === 'numbers' ? 'numbers' : 'letters';
$optionLabels = ($optFmt === 'numbers') ? ['1', '2', '3', '4'] : ['A', 'B', 'C', 'D'];

// Layout mode: 'abcd' (Clean A B C D 3-column setup like demo sheet without state council headers) or 'standard'
$layout = $_GET['layout'] ?? 'abcd';
if (!in_array($layout, ['abcd', 'standard'], true)) {
    $layout = 'abcd';
}

// User-selected or auto-calculated column count
$userCols = (int)($_GET['cols'] ?? 0);
if ($userCols >= 1 && $userCols <= 5) {
    $numCols = $userCols;
} else {
    // Balanced auto column calculation as per question count
    if ($totalQuestions <= 25) {
        // e.g. 10 to 25 questions -> 2 columns (e.g. 20 Qs => 2 columns of 10 rows)
        $numCols = ($totalQuestions <= 8) ? 1 : 2;
    } elseif ($totalQuestions <= 50) {
        // e.g. 26 to 50 questions -> 2 columns (e.g. 40 Qs => 20 rows, 50 Qs => 25 rows)
        $numCols = 2;
    } elseif ($totalQuestions <= 75) {
        // e.g. 51 to 75 questions -> 3 columns (e.g. 75 Qs => 25 rows)
        $numCols = 3;
    } elseif ($totalQuestions <= 100) {
        // e.g. 76 to 100 questions -> 4 columns (e.g. 100 Qs => 25 rows)
        $numCols = 4;
    } else {
        $numCols = 5;
    }
}
$rowsPerCol = (int)ceil($totalQuestions / $numCols);

// Dynamic row and bubble sizing to adapt perfectly to question & column count
if ($rowsPerCol <= 12) {
    $rowHeight = '7.0mm';
    $bubbleSize = '17px';
    $bubbleFont = '8.5pt';
    $qNumFont = '9pt';
} elseif ($rowsPerCol <= 18) {
    $rowHeight = '6.3mm';
    $bubbleSize = '16.5px';
    $bubbleFont = '8pt';
    $qNumFont = '8.5pt';
} elseif ($rowsPerCol <= 25) {
    $rowHeight = ($numCols >= 4) ? '5.4mm' : '5.75mm';
    $bubbleSize = ($numCols >= 4) ? '14.5px' : '15.5px';
    $bubbleFont = ($numCols >= 4) ? '7.2pt' : '7.8pt';
    $qNumFont = ($numCols >= 4) ? '7.5pt' : '8pt';
} else {
    $rowHeight = '5.0mm';
    $bubbleSize = '13.5px';
    $bubbleFont = '6.8pt';
    $qNumFont = '7pt';
}

// Metadata labels
$schoolName = ep_setting('school_name', 'शाळेचे नाव / SCHOOL NAME');
$schoolAddr = ep_setting('school_address', '');
$udiseCode = ep_setting('udise_code', '');
$logoUrl = ep_logo_url();

$examTitle = $paper ? ($paper['title'] ?: $paper['exam_name']) : ($_GET['title'] ?? 'सराव परीक्षा / PRACTICE EXAMINATION');
$examSub = $paper ? ($paper['exam_name'] ?: $paper['subject_name']) : ($_GET['sub'] ?? 'वस्तुनिष्ठ बहुपर्यायी चाचणी (MCQ TEST)');
$stdLabel = $paper ? ($paper['std_label'] ?: $paper['standard_name']) : ($_GET['std'] ?? 'इयत्ता ५ वी');
$dateStr = $paper && !empty($paper['exam_date']) ? date('d/m/Y', strtotime($paper['exam_date'])) : date('d/m/Y');
$totalMarks = $paper ? $paper['total_marks'] : ($totalQuestions * 2);
$duration = $paper ? $paper['duration'] : '';
$mediumText = $paper ? ($paper['medium'] ?? 'मराठी / Marathi') : 'मराठी / Marathi';

// Helper to check which section a question belongs to
if (!function_exists('ep_get_section_for_q')) {
    function ep_get_section_for_q(int $qNum, array $sectionRanges): ?array {
        foreach ($sectionRanges as $sr) {
            if ($qNum >= $sr['from'] && $qNum <= $sr['to']) {
                return $sr;
            }
        }
        return null;
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isKey ? 'आदर्श उत्तरतालिका (OMR ANSWER KEY)' : 'ओ.एम.आर. उत्तरपत्रिका (OMR SHEET)' ?> - <?= h($examTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Tiro+Devanagari+Marathi:ital@0;1&family=Mukta:wght@400;500;600;700;800&family=Martel:wght@700;800;900&display=swap" rel="stylesheet">
<style>
/* CSS Reset & Page Setup */
* { box-sizing: border-box; }
@page {
    size: A4 portrait;
    margin: 4mm 5mm;
}
body {
    margin: 0;
    padding: 0;
    background: #e2e8f0;
    font-family: 'Tiro Devanagari Marathi', 'Utsaah', 'Mukta', Arial, sans-serif;
    color: #000;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    font-size: 10pt;
    line-height: 1.25;
}

/* Screen Toolbar */
.omr-toolbar {
    position: sticky;
    top: 0;
    background: #1e293b;
    color: #fff;
    padding: 10px 16px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}
.omr-toolbar button, .omr-toolbar a.btn {
    border: none;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.btn-print { background: #2563eb; color: #fff; }
.btn-print:hover { background: #1d4ed8; }
.btn-toggle-key { background: #f59e0b; color: #000; font-weight: 700; }
.btn-toggle-key:hover { background: #d97706; color: #fff; }
.btn-toggle-fmt { background: #475569; color: #fff; }
.btn-toggle-fmt:hover { background: #334155; }
.btn-toggle-layout { font-weight: 700; }
.btn-back { background: #334155; color: #cbd5e1; }
.btn-back:hover { background: #1e293b; color: #fff; }
.omr-preset-group {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    font-size: 12.5px;
    color: #94a3b8;
}
.omr-preset-group select {
    background: #0f172a;
    color: #fff;
    border: 1px solid #475569;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
}

/* ============================================================
   A B C D OMR SHEET (CLEAN SCHOOL PATTERN LIKE DEMO SHEET)
   ============================================================ */
.omr-sheet-container {
    width: 210mm;
    margin: 10px auto 25px;
    background: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    padding: 0;
    box-sizing: border-box;
}
.omr-sheet-page {
    width: 210mm;
    box-sizing: border-box;
    padding: 4mm 5.5mm;
    background: #fff;
    color: #000;
    font-family: 'Tiro Devanagari Marathi', 'Utsaah', 'Mukta', Arial, sans-serif;
    font-size: 9.5pt;
    line-height: 1.2;
}
.omr-outer-frame {
    border: 1.5px solid #000;
    padding: 2.5mm;
    box-sizing: border-box;
}

/* School & Exam Header */
.school-header-wrap {
    display: flex;
    align-items: center;
    border-bottom: 1.5px solid #000;
    padding-bottom: 2mm;
    margin-bottom: 1.5mm;
    gap: 3mm;
}
.school-logo-box {
    width: 20mm;
    height: 20mm;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.school-logo-box img {
    max-width: 20mm;
    max-height: 20mm;
    object-fit: contain;
}
.school-titles-box {
    flex: 1;
    text-align: center;
}
.school-name-text {
    font-size: 14pt;
    font-weight: 800;
    margin: 0;
    line-height: 1.15;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-family: 'Martel', 'Tiro Devanagari Marathi', serif;
}
.school-sub-text {
    font-size: 8.5pt;
    font-weight: 600;
    color: #333;
    margin-top: 1px;
}
.exam-info-text {
    font-size: 11pt;
    font-weight: 700;
    margin: 2px 0 0;
    line-height: 1.2;
}
.omr-badge-row {
    margin-top: 2px;
}
.omr-badge {
    font-size: 9.5pt;
    font-weight: 800;
    letter-spacing: 0.4px;
    background: #f1f5f9;
    display: inline-block;
    padding: 1px 12px;
    border: 1px solid #000;
    border-radius: 3px;
}
.omr-badge.is-key {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #b91c1c;
}

/* Row 1: Candidate Details / Set Code / Invigilator Sign */
.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5mm;
}
.details-table td {
    border: 1px solid #000;
    vertical-align: top;
    padding: 2.5px 5px;
}
.cand-fields {
    font-size: 8.5pt;
    line-height: 1.45;
}
.cand-fields div {
    display: flex;
    align-items: baseline;
    margin-bottom: 1px;
}
.cand-fields b {
    width: 25mm;
    flex-shrink: 0;
}
.cand-fields .line {
    flex: 1;
    border-bottom: 1px dotted #888;
    height: 1.1em;
    padding-left: 4px;
    font-weight: 600;
}
.set-code-cell {
    text-align: center;
    font-size: 8.5pt;
}
.set-code-row1 {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 600;
    margin-bottom: 2px;
}
.set-box-sq {
    display: inline-block;
    width: 19px;
    height: 19px;
    border: 1.5px solid #000;
    background: #fff;
    vertical-align: middle;
}
.set-code-row2 {
    font-size: 8pt;
    color: #333;
    margin-bottom: 2px;
}
.set-bubbles {
    display: flex;
    justify-content: center;
    gap: 9px;
    margin-top: 3px;
}
.set-bubble {
    width: 18px;
    height: 18px;
    border: 1.2px solid #000;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 8.5pt;
    background: #fff;
}
.set-bubble.shaded {
    background: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
}
.invig-sign-cell {
    text-align: center;
    font-size: 7.8pt;
    font-weight: 600;
    height: 100%;
}
.invig-sign-blank {
    margin-top: 4px;
    border: 1px dashed #777;
    height: 12mm;
    background: #fafafa;
}

/* Row 2: Instructions & A B C D Marking Method */
.instruct-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5mm;
}
.instruct-table td {
    border: 1px solid #000;
    vertical-align: top;
    padding: 2.5px 4px;
}
.barcode-box {
    text-align: center;
    font-size: 7.5pt;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: #fafafa;
    min-height: 38mm;
    padding: 4px;
}
.rules-head {
    font-size: 8.5pt;
    font-weight: 800;
    margin-bottom: 1.5px;
}
.rules-list {
    margin: 0;
    padding-left: 14px;
    font-size: 7.2pt;
    line-height: 1.25;
}
.rules-list li {
    margin-bottom: 1px;
}
.method-head {
    font-weight: 700;
    font-size: 7.8pt;
    text-align: center;
    line-height: 1.15;
    margin-bottom: 2px;
}
.method-row {
    display: flex;
    justify-content: center;
    gap: 3.5px;
    margin-bottom: 2px;
}
.demo-c {
    width: 14px;
    height: 14px;
    border: 1.1px solid #000;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 7.5pt;
    font-weight: 700;
    background: #fff;
    color: #000;
}
.demo-c.shaded {
    background: #000 !important;
    color: #fff !important;
}
.demo-c.wrong {
    font-size: 7pt;
    font-weight: 900;
    color: #dc2626;
}
.demo-c.tick {
    font-size: 7pt;
    font-weight: 900;
    color: #16a34a;
}
.demo-c.half {
    font-size: 8pt;
}

/* Row 3: A B C D Question Response Grid (Columns + Invigilator Sidebar) */
.answers-grid-wrap {
    display: flex;
    gap: 1.8mm;
    margin-bottom: 1.5mm;
}
.q-column {
    flex: 1;
    border: 1px solid #000;
    background: #fff;
    overflow: hidden;
}
.q-column-head {
    display: flex;
    background: #f1f5f9;
    border-bottom: 1px solid #000;
    font-weight: 700;
    font-size: 8pt;
    text-align: center;
}
.q-column-head .th-q {
    width: 25px;
    border-right: 1px solid #000;
    padding: 1.5px 0;
}
.q-column-head .th-opt {
    flex: 1;
    padding: 1.5px 0;
}
.q-row {
    display: flex;
    align-items: center;
    border-bottom: 0.8px solid #cbd5e1;
    height: <?= $rowHeight ?>;
}
.q-row:nth-child(5n) {
    border-bottom: 1.3px solid #64748b;
}
.q-row:last-child {
    border-bottom: none;
}
.q-num {
    width: <?= ($numCols >= 4) ? '22px' : '26px' ?>;
    text-align: center;
    font-size: <?= $qNumFont ?>;
    font-weight: 700;
    border-right: 1px solid #000;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
}
.q-bubbles {
    flex: 1;
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 0 2px;
}
.q-bubble-opt {
    width: <?= $bubbleSize ?>;
    height: <?= $bubbleSize ?>;
    border: 1.1px solid #000;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: <?= $bubbleFont ?>;
    font-weight: 700;
    background: #fff;
    color: #000;
    user-select: none;
}
.q-bubble-opt.shaded {
    background: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
}
.q-sec-banner {
    background: #1e293b;
    color: #fff;
    font-size: 7.2pt;
    font-weight: 700;
    padding: 1.5px 4px;
    text-align: center;
    border-bottom: 1px solid #000;
    border-top: 1px solid #000;
    letter-spacing: 0.2px;
    line-height: 1.2;
}

/* Right Sidebar: पर्यविक्षकांसाठी */
.invig-sidebar {
    width: 27mm;
    flex-shrink: 0;
    border: 1px solid #000;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 2.5px 3px 4px;
    text-align: center;
    font-size: 7.5pt;
    box-sizing: border-box;
    background: #fff;
}
.side-head {
    font-weight: 800;
    font-size: 7.8pt;
    border-bottom: 1px solid #000;
    padding-bottom: 2px;
    margin-bottom: 3px;
    line-height: 1.15;
}
.side-label {
    font-weight: 600;
    font-size: 7.5pt;
    margin-top: 2px;
    line-height: 1.15;
}
.side-bubble {
    width: 16px;
    height: 16px;
    border: 1.2px solid #000;
    border-radius: 50%;
    margin: 1.5px auto 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 8pt;
}
.side-boxes {
    display: flex;
    justify-content: center;
    gap: 3px;
    margin: 2px 0 5px;
}
.box-sq {
    width: 6.5mm;
    height: 7.5mm;
    border: 1.2px solid #000;
    background: #fff;
    display: inline-block;
}
.total-sq {
    width: 17mm;
    height: 8mm;
    border: 1.3px solid #000;
    margin: 2px auto 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 10pt;
    background: #f8fafc;
}

/* Row 4: Signatures Footer */
.footer-table {
    width: 100%;
    border-collapse: collapse;
    border-top: 1px solid #000;
    margin-top: 1mm;
    padding-top: 1.5mm;
}
.footer-table td {
    vertical-align: bottom;
    padding: 2px 6px;
    font-size: 8.5pt;
}
.cand-sign-cell {
    text-align: center;
}
.cand-sign-dots {
    border-bottom: 1px dotted #000;
    width: 80%;
    margin: 0 auto 3px;
    height: 6mm;
}
.invig-fields div {
    display: flex;
    align-items: baseline;
    margin-bottom: 2px;
}
.invig-fields b {
    width: 28mm;
    flex-shrink: 0;
}
.invig-fields .dots {
    flex: 1;
    border-bottom: 1px dotted #888;
    height: 1.1em;
}

/* ============================================================
   LAYOUT 2: STANDARD / CANDIDATE NAME GRID OMR SHEET
   ============================================================ */
.omr-sheet-wrapper {
    width: 210mm;
    margin: 15px auto 30px;
    background: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    position: relative;
}
.omr-page {
    width: 210mm;
    min-height: 295mm;
    max-height: 297mm;
    padding: 6mm 8mm;
    box-sizing: border-box;
    background: #fff;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.omr-corner {
    position: absolute;
    width: 6.5mm;
    height: 6.5mm;
    background: #000;
}
.omr-corner.tl { top: 3.5mm; left: 3.5mm; }
.omr-corner.tr { top: 3.5mm; right: 3.5mm; }
.omr-corner.bl { bottom: 3.5mm; left: 3.5mm; }
.omr-corner.br { bottom: 3.5mm; right: 3.5mm; }
.omr-timing-track {
    position: absolute;
    left: 2.5mm;
    top: 15mm;
    bottom: 15mm;
    width: 3.5mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    pointer-events: none;
}
.omr-timing-dash {
    width: 100%;
    height: 1.8mm;
    background: #000;
}
.omr-header {
    border: 2px solid #000;
    border-radius: 4px;
    padding: 4px 8px;
    margin-bottom: 5px;
    background: #fff;
}
.omr-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.omr-header-logo {
    width: 16mm;
    height: 16mm;
    object-fit: contain;
    flex-shrink: 0;
}
.omr-header-text {
    flex: 1;
    text-align: center;
}
.omr-school-name {
    font-size: 15pt;
    font-weight: 800;
    line-height: 1.1;
    font-family: 'Martel', serif;
    margin: 0;
    text-transform: uppercase;
}
.omr-school-sub {
    font-size: 9.5pt;
    font-weight: 600;
    color: #222;
    margin-top: 1px;
}
.omr-title-badge {
    text-align: center;
    margin: 3px 0 2px;
    padding: 2px 6px;
    font-size: 12pt;
    font-weight: 800;
    letter-spacing: 0.5px;
    border-top: 1.5px solid #000;
    border-bottom: 1.5px solid #000;
    background: #f1f5f9;
}
.omr-title-badge.is-key {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #b91c1c;
}
.omr-meta-strip {
    display: flex;
    justify-content: space-between;
    font-size: 9.5pt;
    font-weight: 600;
    padding-top: 2px;
    border-top: 1px dashed #666;
}
.omr-info-grid {
    display: grid;
    grid-template-columns: 2.1fr 1.3fr 1.6fr;
    gap: 5px;
    margin-bottom: 5px;
}
.omr-box {
    border: 1.5px solid #000;
    border-radius: 3px;
    padding: 4px 6px;
    background: #fff;
}
.omr-box-title {
    font-size: 9pt;
    font-weight: 800;
    margin-bottom: 3px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ccc;
    padding-bottom: 2px;
}
.name-boxes-grid {
    display: grid;
    grid-template-columns: repeat(20, 1fr);
    gap: 1.5px;
    margin-top: 3px;
}
.name-char-box {
    height: 6mm;
    border: 1px solid #000;
    background: #fafafa;
    text-align: center;
    line-height: 6mm;
    font-weight: bold;
    font-size: 9pt;
}
.roll-coding-container {
    display: flex;
    gap: 4px;
}
.roll-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
}
.roll-input-box {
    width: 100%;
    height: 5.5mm;
    border: 1px solid #000;
    background: #fff;
    margin-bottom: 2px;
    text-align: center;
    line-height: 5.5mm;
    font-weight: bold;
    font-size: 8.5pt;
}
.bubble-digit {
    width: 15px;
    height: 15px;
    border: 1px solid #000;
    border-radius: 50%;
    margin-bottom: 1.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 7.5pt;
    font-weight: 700;
    color: #111;
    background: #fff;
}
.bubble-digit.shaded {
    background: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
}
.instructions-list {
    margin: 0;
    padding-left: 14px;
    font-size: 8.2pt;
    line-height: 1.25;
}
.instructions-list li {
    margin-bottom: 1.5px;
}
.method-demo {
    display: flex;
    align-items: center;
    justify-content: space-around;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    padding: 2px 4px;
    margin-top: 3px;
    font-size: 8pt;
}
.demo-bubble {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid #000;
    font-size: 8pt;
    font-weight: bold;
    margin-left: 2px;
}
.demo-bubble.correct { background: #000; color: #fff; border-color: #000; }
.demo-bubble.wrong-cross { position: relative; color: #dc2626; font-weight: 900; }
.demo-bubble.wrong-tick { position: relative; color: #16a34a; font-weight: 900; }
.omr-grid-container {
    display: grid;
    grid-template-columns: repeat(<?= $numCols ?>, 1fr);
    gap: 4.5mm;
    flex: 1;
    margin-bottom: 5px;
    align-items: start;
}
.omr-column {
    border: 1.5px solid #000;
    border-radius: 3px;
    background: #fff;
    overflow: hidden;
}
.omr-col-header {
    background: #000;
    color: #fff;
    display: flex;
    justify-content: space-between;
    padding: 2px 6px;
    font-size: 8.5pt;
    font-weight: 800;
    text-transform: uppercase;
}
.omr-rows-table {
    width: 100%;
    border-collapse: collapse;
}
.omr-row {
    border-bottom: 1px solid #e2e8f0;
    height: <?= $totalQuestions > 75 ? '5.6mm' : ($totalQuestions > 40 ? '6.2mm' : '7.0mm') ?>;
}
.omr-row:nth-child(5n) {
    border-bottom: 1.5px solid #64748b;
}
.omr-row:last-child {
    border-bottom: none;
}
.omr-row-qno {
    width: 28px;
    text-align: center;
    font-weight: 800;
    font-size: <?= $totalQuestions > 75 ? '8.5pt' : '9.5pt' ?>;
    background: #f8fafc;
    border-right: 1px solid #cbd5e1;
    padding: 0 2px;
}
.omr-row-bubbles {
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding: 0 4px;
    height: 100%;
}
.bubble-opt {
    width: <?= $totalQuestions > 75 ? '16px' : ($totalQuestions > 40 ? '18px' : '20px') ?>;
    height: <?= $totalQuestions > 75 ? '16px' : ($totalQuestions > 40 ? '18px' : '20px') ?>;
    border: 1.2px solid #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: <?= $totalQuestions > 75 ? '7.5pt' : '8.5pt' ?>;
    font-weight: 700;
    background: #fff;
    color: #111;
    user-select: none;
}
.bubble-opt.shaded {
    background: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
    box-shadow: inset 0 0 0 1px #000;
}
.omr-footer {
    display: grid;
    grid-template-columns: 1fr 1fr 1.6fr;
    gap: 5px;
    border: 1.5px solid #000;
    border-radius: 3px;
    padding: 4px 6px;
    background: #fff;
}
.sig-box {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 17mm;
    border-right: 1px dashed #999;
    padding-right: 6px;
}
.sig-box:last-child {
    border-right: none;
    padding-right: 0;
}
.sig-title {
    font-size: 8.5pt;
    font-weight: 800;
}
.sig-line {
    font-size: 8pt;
    color: #555;
    border-top: 1px dotted #000;
    padding-top: 2px;
    text-align: center;
    font-weight: 600;
}
.eval-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.8pt;
    text-align: center;
}
.eval-table th, .eval-table td {
    border: 1px solid #000;
    padding: 1.5px 2px;
}
.eval-table th {
    background: #f1f5f9;
    font-weight: 700;
}

/* Print Specific Optimizations */
@media print {
    body {
        background: #fff !important;
    }
    .no-print {
        display: none !important;
    }
    .omr-sheet-wrapper, .omr-sheet-container {
        width: 100% !important;
        margin: 0 !important;
        box-shadow: none !important;
    }
    .omr-page, .omr-sheet-page {
        width: 100% !important;
        min-height: auto !important;
        max-height: 288mm !important;
        padding: 0 !important;
        page-break-after: avoid !important;
        page-break-inside: avoid !important;
    }
    .omr-timing-track, .omr-corner, .bubble-opt, .q-bubble-opt {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
</head>
<body>

<!-- Screen Toolbar -->
<div class="omr-toolbar no-print">
    <button class="btn-print" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> प्रिंट / Save as PDF
    </button>

    <a href="omr_view.php?<?= http_build_query(array_merge($_GET, ['key' => $isKey ? 0 : 1])) ?>" class="btn btn-toggle-key">
        <i class="bi <?= $isKey ? 'bi-file-earmark' : 'bi-check-circle-fill' ?>"></i>
        <?= $isKey ? 'विद्यार्थी कोरी उत्तरपत्रिका (Blank OMR)' : 'आदर्श उत्तरतालिका OMR (Answer Key)' ?>
    </a>

    <!-- Layout Toggle Button -->
    <a href="omr_view.php?<?= http_build_query(array_merge($_GET, ['layout' => $layout === 'abcd' ? 'standard' : 'abcd'])) ?>" class="btn btn-toggle-layout" style="background: <?= $layout === 'abcd' ? '#4f46e5' : '#059669' ?>; color:#fff;">
        <i class="bi <?= $layout === 'abcd' ? 'bi-layout-text-sidebar' : 'bi-patch-check-fill' ?>"></i>
        नमुना: <b><?= $layout === 'abcd' ? '📑 A B C D सराव नमुना' : '📋 सर्वसाधारण OMR' ?></b> (बदला)
    </a>

    <a href="omr_view.php?<?= http_build_query(array_merge($_GET, ['fmt' => $optFmt === 'numbers' ? 'letters' : 'numbers'])) ?>" class="btn btn-toggle-fmt">
        <i class="bi bi-arrow-left-right"></i>
        पर्याय: <b><?= $optFmt === 'letters' ? '(A, B, C, D)' : '(1, 2, 3, 4)' ?></b>
    </a>

    <?php if ($paper): ?>
        <div class="omr-preset-group">
            <span><i class="bi bi-collection"></i> संच:</span>
            <div style="display:inline-flex; gap:3px;">
                <?php foreach (['A', 'B', 'C', 'D'] as $s): ?>
                    <a href="omr_view.php?<?= http_build_query(array_merge($_GET, ['set' => $s])) ?>" 
                       style="padding: 2px 7px; border-radius: 4px; font-weight: bold; font-size: 11px; text-decoration: none; color: #fff; background: <?= $currentSet === $s ? '#0d6efd' : '#334155' ?>; border: 1px solid <?= $currentSet === $s ? '#0d6efd' : '#475569' ?>;">
                       संच <?= $s ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="paper_view.php?id=<?= $paperId ?>&set=<?= $currentSet ?>" class="btn btn-back">
            <i class="bi bi-file-earmark-text"></i> प्रश्नपत्रिका पहा
        </a>
    <?php endif; ?>

    <a href="index.php?tab=competitive" class="btn btn-back">
        <i class="bi bi-house"></i> डॅशबोर्ड
    </a>

    <!-- Column Switcher Dropdown -->
    <div class="omr-preset-group" style="margin-left: 0;">
        <span><i class="bi bi-layout-three-columns"></i> कॉलम्स:</span>
        <select onchange="location.href='omr_view.php?<?= http_build_query(array_diff_key($_GET, ['cols' => ''])) ?>&cols=' + this.value">
            <option value="0" <?= $userCols == 0 ? 'selected' : '' ?>>Auto (<?= $numCols ?> कॉलम्स)</option>
            <option value="1" <?= $userCols == 1 ? 'selected' : '' ?>>1 कॉलम</option>
            <option value="2" <?= $userCols == 2 ? 'selected' : '' ?>>2 कॉलम्स</option>
            <option value="3" <?= $userCols == 3 ? 'selected' : '' ?>>3 कॉलम्स</option>
            <option value="4" <?= $userCols == 4 ? 'selected' : '' ?>>4 कॉलम्स</option>
            <option value="5" <?= $userCols == 5 ? 'selected' : '' ?>>5 कॉलम्स</option>
        </select>
    </div>

    <!-- Presets Dropdown -->
    <div class="omr-preset-group">
        <span><i class="bi bi-sliders"></i> प्रश्न संख्या:</span>
        <select onchange="location.href='omr_view.php?<?= http_build_query(array_diff_key($_GET, ['count' => ''])) ?>&count=' + this.value">
            <?php if ($paper): ?>
                <option value="<?= $totalQuestions ?>" selected>★ <?= $totalQuestions ?> प्रश्न (या प्रश्नपत्रिकेनुसार)</option>
            <?php endif; ?>
            <option value="15" <?= $totalQuestions == 15 ? 'selected' : '' ?>>15 प्रश्न (लहान चाचणी)</option>
            <option value="20" <?= $totalQuestions == 20 ? 'selected' : '' ?>>20 प्रश्न (घटक चाचणी)</option>
            <option value="25" <?= $totalQuestions == 25 ? 'selected' : '' ?>>25 प्रश्न (सराव संच)</option>
            <option value="40" <?= $totalQuestions == 40 ? 'selected' : '' ?>>40 प्रश्न (नवोदय सराव)</option>
            <option value="50" <?= $totalQuestions == 50 ? 'selected' : '' ?>>50 प्रश्न (सराव परीक्षा)</option>
            <option value="75" <?= $totalQuestions == 75 ? 'selected' : '' ?>>75 प्रश्न (शिष्यवृत्ती A B C D सराव)</option>
            <option value="80" <?= $totalQuestions == 80 ? 'selected' : '' ?>>80 प्रश्न (नवोदय JNVST)</option>
            <option value="90" <?= $totalQuestions == 90 ? 'selected' : '' ?>>90 प्रश्न (८ वी NMMS)</option>
            <option value="100" <?= $totalQuestions == 100 ? 'selected' : '' ?>>100 प्रश्न (MPSC / स्पर्धा परीक्षा)</option>
        </select>
    </div>
</div>

<?php if ($layout === 'abcd'): ?>
<!-- ============================================================
     A B C D OMR ANSWER SHEET (CLEAN SCHOOL PATTERN LIKE DEMO SHEET)
     ============================================================ -->
<div class="omr-sheet-container">
    <div class="omr-sheet-page">
        <div class="omr-outer-frame">

            <!-- SCHOOL & EXAM HEADER (NO STATE COUNCIL BRANDING) -->
            <div class="school-header-wrap">
                <?php if ($logoUrl): ?>
                    <div class="school-logo-box">
                        <img src="<?= h($logoUrl) ?>" alt="School Logo">
                    </div>
                <?php endif; ?>

                <div class="school-titles-box">
                    <h1 class="school-name-text"><?= h($schoolName) ?></h1>
                    <?php if ($schoolAddr || $udiseCode): ?>
                        <div class="school-sub-text">
                            <?= h($schoolAddr) ?> <?= $udiseCode ? ' | UDISE: ' . h($udiseCode) : '' ?>
                        </div>
                    <?php endif; ?>
                    <div class="exam-info-text">
                        <b><?= h($examTitle) ?></b>
                        <?php if ($examSub): ?> • <?= h($examSub) ?><?php endif; ?>
                        <?php if ($stdLabel): ?> • <b><?= h($stdLabel) ?></b><?php endif; ?>
                    </div>
                    <div class="omr-badge-row">
                        <span class="omr-badge <?= $isKey ? 'is-key' : '' ?>">
                            <?= $isKey ? ('★ आदर्श उत्तरतालिका (OMR ANSWER KEY' . ($paper ? ' - संच ' . $currentSet : '') . ') ★') : ('ओ.एम.आर. उत्तरपत्रिका (OMR ANSWER SHEET' . ($paper ? ' - संच ' . $currentSet : '') . ')') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ROW 1: CANDIDATE DETAILS / SET CODE / INVIGILATOR SIGN -->
            <table class="details-table">
                <tr>
                    <td style="width: 35%;">
                        <div class="cand-fields">
                            <div><b>Centre No. :</b> <span class="line"><?= h($udiseCode ?: '') ?></span></div>
                            <div><b>Seat No. &nbsp;&nbsp;&nbsp;:</b> <span class="line"></span></div>
                            <div><b>Medium &nbsp;&nbsp;:</b> <span class="line"><?= h($mediumText) ?></span></div>
                            <div><b>Block No. &nbsp;:</b> <span class="line"></span></div>
                        </div>
                    </td>
                    <td class="set-code-cell" style="width: 36%;">
                        <div class="set-code-row1">
                            <span>प्रश्नपत्रिका संचकोड (Set Code) अचूक नोंदवा</span>
                            <span class="set-box-sq" style="font-weight:800; font-size:10pt; line-height:18px; text-align:center;"><?= ($paper || isset($_GET['set'])) ? h($currentSet) : '' ?></span>
                        </div>
                        <div class="set-code-row2">प्रश्नपत्रिका संचकोड वर्तुळ अचूक रंगवावे</div>
                        <div class="set-bubbles">
                            <?php foreach (['A', 'B', 'C', 'D'] as $sLet): ?>
                                <span class="set-bubble <?= (($isKey || isset($_GET['set'])) && $currentSet === $sLet) ? 'shaded' : '' ?>"><?= $sLet ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td style="width: 29%;">
                        <div class="invig-sign-cell">
                            <div>संचकोड अचूक नोंदविलेबाबत पर्यवेक्षकाची स्वाक्षरी</div>
                            <div class="invig-sign-blank"></div>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- ROW 2: INSTRUCTIONS & A B C D MARKING METHOD -->
            <table class="instruct-table">
                <tr>
                    <!-- Blank verification / candidate box -->
                    <td style="width: 25%;">
                        <div class="barcode-box">
                            <?php if ($logoUrl): ?>
                                <img src="<?= h($logoUrl) ?>" style="max-height:16mm; max-width:90%; object-fit:contain; margin-bottom:2px;" alt="">
                            <?php endif; ?>
                            <div style="font-weight:700; font-size:8pt; line-height:1.2;"><?= h($schoolName) ?></div>
                            <?php if ($schoolAddr): ?>
                                <div style="font-size:7pt; color:#444; margin-top:1px;"><?= h($schoolAddr) ?></div>
                            <?php endif; ?>
                            <div style="font-size:7pt; color:#666; margin-top:5px; border-top:1px dashed #aaa; padding-top:2px; width:90%;">
                                [ विद्यार्थ्याचे नाव / बारकोड स्टिकर जागा ]
                            </div>
                        </div>
                    </td>

                    <!-- 8 General Instructions -->
                    <td style="width: 53%;">
                        <div class="rules-head">सर्वसाधारण सूचना (Important Instructions) :</div>
                        <ol class="rules-list">
                            <li>पेन्सिलने नोंदविलेली उत्तरे ग्राह्य धरली जाणार नाहीत.</li>
                            <li>प्रत्येक प्रश्नाच्या समोरील उत्तराचा योग्य पर्याय वर्तुळात नोंदविताना काळ्या किंवा निळ्या रंगाच्या बॉलपेनचाच वापर करावा.</li>
                            <li>एकदा रंगविलेले वर्तुळ बदलता येणार नाही.</li>
                            <li>एकापेक्षा अधिक रंगविलेली वर्तुळे ग्राह्य धरली जाणार नाहीत.</li>
                            <li>खाडाखोड केलेल्या, अर्धवट भरलेल्या वर्तुळाच्या उत्तरास शून्य गुण दिले जातील.</li>
                            <li>उत्तरपत्रिकेस घडी घालू नये, स्टेपल करू नये तसेच उत्तरपत्रिकेवर खाडाखोड करू नये.</li>
                            <li>उत्तरपत्रिका फाटल्यामुळे अथवा खराब झाल्यामुळे उत्तरपत्रिकेत रंगविलेले पर्याय दिसत नसतील तर सदर प्रश्नांचे गुण दिले जाणार नाहीत.</li>
                            <li>उत्तरपत्रिका व्यवस्थितपणे हाताळावी व परीक्षा संपल्यावर पर्यवेक्षकांकडे जमा करावी.</li>
                        </ol>
                    </td>

                    <!-- Marking Method Demo with A B C D -->
                    <td style="width: 22%;">
                        <div class="method-head">उत्तर नोंदविण्याची<br>योग्य पद्धत</div>
                        <div class="method-row">
                            <span class="demo-c"><?= $optionLabels[0] ?></span>
                            <span class="demo-c shaded">●</span>
                            <span class="demo-c"><?= $optionLabels[2] ?></span>
                            <span class="demo-c"><?= $optionLabels[3] ?></span>
                        </div>

                        <div class="method-head" style="border-top:1px solid #000; padding-top:2px; margin-top:3px;">
                            चुकीच्या पद्धती
                        </div>
                        <div class="method-row">
                            <span class="demo-c wrong">✘</span>
                            <span class="demo-c"><?= $optionLabels[1] ?></span>
                            <span class="demo-c"><?= $optionLabels[2] ?></span>
                            <span class="demo-c"><?= $optionLabels[3] ?></span>
                        </div>
                        <div class="method-row">
                            <span class="demo-c"><?= $optionLabels[0] ?></span>
                            <span class="demo-c tick">✔</span>
                            <span class="demo-c"><?= $optionLabels[2] ?></span>
                            <span class="demo-c"><?= $optionLabels[3] ?></span>
                        </div>
                        <div class="method-row">
                            <span class="demo-c"><?= $optionLabels[0] ?></span>
                            <span class="demo-c"><?= $optionLabels[1] ?></span>
                            <span class="demo-c half">◐</span>
                            <span class="demo-c"><?= $optionLabels[3] ?></span>
                        </div>
                        <div class="method-row">
                            <span class="demo-c"><?= $optionLabels[0] ?></span>
                            <span class="demo-c"><?= $optionLabels[1] ?></span>
                            <span class="demo-c"><?= $optionLabels[2] ?></span>
                            <span class="demo-c tick">✔</span>
                        </div>
                        <div class="method-row">
                            <span class="demo-c"><?= $optionLabels[0] ?></span>
                            <span class="demo-c"><?= $optionLabels[1] ?></span>
                            <span class="demo-c shaded">●</span>
                            <span class="demo-c shaded">●</span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- ROW 3: A B C D RESPONSE GRID (COLUMNS + INVIGILATOR BOX) -->
            <div class="answers-grid-wrap">
                <?php
                for ($col = 0; $col < $numCols; $col++):
                    $startQ = $col * $rowsPerCol + 1;
                    $endQ = min($totalQuestions, ($col + 1) * $rowsPerCol);
                    if ($startQ > $totalQuestions) break;
                ?>
                    <div class="q-column">
                        <div class="q-column-head">
                            <span class="th-q">प्र.क्र.</span>
                            <span class="th-opt">उत्तरांचे पर्याय (Options)</span>
                        </div>

                        <?php for ($qNum = $startQ; $qNum <= $endQ; $qNum++): 
                            $sec = ep_get_section_for_q($qNum, $sectionRanges);
                            $isSecStart = ($sec && $sec['from'] === $qNum);
                            $correctOpt = $answers[$qNum] ?? null;
                        ?>
                            <?php if ($isSecStart): ?>
                                <div class="q-sec-banner">
                                    <?= h($sec['title']) ?> (प्र. <?= $sec['from'] ?> ते <?= $sec['to'] ?>)
                                </div>
                            <?php endif; ?>
                            <div class="q-row">
                                <span class="q-num"><?= $qNum ?></span>
                                <div class="q-bubbles">
                                    <?php for ($optIdx = 1; $optIdx <= 4; $optIdx++): 
                                        $isShaded = ($isKey && $correctOpt === $optIdx);
                                    ?>
                                        <span class="q-bubble-opt <?= $isShaded ? 'shaded' : '' ?>">
                                            <?= $optionLabels[$optIdx - 1] ?>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>

                <!-- Right Column: पर्यविक्षकांसाठी (Invigilator Block) -->
                <div class="invig-sidebar">
                    <div class="side-head">पर्यवेक्षकांनी स्वतः<br>भरावे.</div>

                    <div>
                        <div class="side-label">उपस्थित</div>
                        <div class="side-bubble">P</div>

                        <div class="side-label" style="margin-top:6px;">अनुपस्थित</div>
                        <div class="side-bubble">A</div>
                    </div>

                    <div>
                        <div class="side-label">सोडविलेल्या<br>प्रश्नांची संख्या</div>
                        <div class="side-boxes">
                            <span class="box-sq"></span>
                            <span class="box-sq"></span>
                        </div>

                        <div class="side-label" style="margin-top:6px;">न सोडविलेल्या<br>प्रश्नांची संख्या</div>
                        <div class="side-boxes">
                            <span class="box-sq"></span>
                            <span class="box-sq"></span>
                        </div>

                        <div class="side-label" style="margin-top:8px;">एकूण प्रश्न संख्या</div>
                        <div class="total-sq"><?= (int)$totalQuestions ?></div>
                    </div>
                </div>
            </div>

            <!-- ROW 4: BOTTOM SIGNATURES FOOTER -->
            <table class="footer-table">
                <tr>
                    <td class="cand-sign-cell" style="width: 45%;">
                        <div class="cand-sign-dots"></div>
                        <div style="font-weight:700;">विद्यार्थ्याची स्वाक्षरी (Candidate's Sign)</div>
                    </td>
                    <td style="width: 55%;">
                        <div class="invig-fields">
                            <div><b>पर्यवेक्षकाचे नाव :</b> <span class="dots"></span></div>
                            <div><b>स्वाक्षरी &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</b> <span class="dots"></span></div>
                        </div>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</div>

<?php else: ?>
<!-- ============================================================
     LAYOUT 2: STANDARD / CANDIDATE NAME GRID OMR ANSWER SHEET
     ============================================================ -->
<div class="omr-sheet-wrapper">
    <div class="omr-page">

        <!-- 4 Scanner Registration Corner Markers -->
        <div class="omr-corner tl"></div>
        <div class="omr-corner tr"></div>
        <div class="omr-corner bl"></div>
        <div class="omr-corner br"></div>

        <!-- Left Timing Track for Optical Scanner Emulation -->
        <div class="omr-timing-track">
            <?php for ($t = 0; $t < 42; $t++): ?>
                <div class="omr-timing-dash"></div>
            <?php endfor; ?>
        </div>

        <!-- HEADER SECTION -->
        <header class="omr-header">
            <div class="omr-header-row">
                <?php if ($logoUrl): ?>
                    <img src="<?= h($logoUrl) ?>" class="omr-header-logo" alt="Logo">
                <?php endif; ?>
                <div class="omr-header-text">
                    <h1 class="omr-school-name"><?= h($schoolName) ?></h1>
                    <?php if ($schoolAddr || $udiseCode): ?>
                        <div class="omr-school-sub">
                            <?= h($schoolAddr) ?> <?= $udiseCode ? ' | UDISE: ' . h($udiseCode) : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="omr-title-badge <?= $isKey ? 'is-key' : '' ?>">
                <?= $isKey ? ('★ आदर्श उत्तरतालिका (OMR ANSWER KEY - FOR EVALUATION' . ($paper ? ' - संच ' . $currentSet : '') . ') ★') : ('ओ.एम.आर. उत्तरपत्रिका (OMR ANSWER SHEET' . ($paper ? ' - संच ' . $currentSet : '') . ')') ?>
            </div>

            <div class="omr-meta-strip">
                <div><b>परीक्षा:</b> <?= h($examTitle) ?></div>
                <div><b>विषय:</b> <?= h($examSub) ?></div>
                <div><b>इयत्ता:</b> <?= h($stdLabel) ?></div>
                <div><b>दिनांक:</b> <?= h($dateStr) ?></div>
                <div><b>एकूण प्रश्न:</b> <?= (int)$totalQuestions ?></div>
                <div><b>एकूण गुण:</b> <?= (float)$totalMarks ?></div>
            </div>
        </header>

        <!-- TOP IDENTIFICATION & INSTRUCTIONS BLOCK -->
        <div class="omr-info-grid">

            <!-- 1. Candidate Full Name & Seat Number -->
            <div class="omr-box">
                <div class="omr-box-title">
                    <span>१. विद्यार्थ्याचे नाव (CANDIDATE'S NAME IN CAPITAL)</span>
                </div>
                <div class="name-boxes-grid">
                    <?php for ($i = 0; $i < 20; $i++): ?>
                        <div class="name-char-box"></div>
                    <?php endfor; ?>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:5px;">
                    <div>
                        <div style="font-size:8pt; font-weight:700; margin-bottom:1px;">हजेरी क्रमांक / वर्ग:</div>
                        <div style="display:flex; gap:3px;">
                            <div class="name-char-box" style="width:7mm;"></div>
                            <div class="name-char-box" style="width:7mm;"></div>
                            <div class="name-char-box" style="width:7mm;"></div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:8pt; font-weight:700; margin-bottom:1px;">केंद्र कोड (CENTER CODE):</div>
                        <div style="display:flex; gap:3px; justify-content:flex-end;">
                            <div class="name-char-box" style="width:6mm;"></div>
                            <div class="name-char-box" style="width:6mm;"></div>
                            <div class="name-char-box" style="width:6mm;"></div>
                            <div class="name-char-box" style="width:6mm;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Roll / Seat Number OMR Bubble Column -->
            <div class="omr-box">
                <div class="omr-box-title">
                    <span>२. बैठक क्र. (ROLL NO.)</span>
                    <span style="font-size:7.5pt; font-weight:normal; color:#444;">संच (SET)</span>
                </div>
                <div style="display:flex; gap:6px;">
                    <!-- Roll Number Digits Matrix (5 digits) -->
                    <div class="roll-coding-container" style="flex:1;">
                        <?php for ($c = 0; $c < 5; $c++): ?>
                            <div class="roll-col">
                                <div class="roll-input-box"></div>
                                <?php for ($d = 0; $d <= 9; $d++): ?>
                                    <div class="bubble-digit"><?= $d ?></div>
                                <?php endfor; ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Booklet Set Code (A, B, C, D) -->
                    <div style="width:30px; display:flex; flex-direction:column; align-items:center; border-left:1px dashed #ccc; padding-left:4px;">
                        <div class="roll-input-box" style="width:100%; font-weight:800; font-size:9pt; line-height:18px; text-align:center;"><?= ($paper || isset($_GET['set'])) ? h($currentSet) : '' ?></div>
                        <?php foreach (['A', 'B', 'C', 'D'] as $setLetter): ?>
                            <div class="bubble-digit <?= (($isKey || isset($_GET['set'])) && $currentSet === $setLetter) ? 'shaded' : '' ?>" style="font-size:8pt; font-weight:800;"><?= $setLetter ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Important Instructions & Proper Method -->
            <div class="omr-box">
                <div class="omr-box-title">
                    <span>३. सूचना व रंगविण्याची योग्य पद्धत</span>
                </div>
                <ul class="instructions-list">
                    <li>केवळ <b>निळा किंवा काळा</b> बॉलपेन वापरावा.</li>
                    <li>पर्यायाचे वर्तुळ पूर्णपणे गडद (काळे/निळे) करावे.</li>
                    <li>एका प्रश्नाला एकापेक्षा जास्त वर्तुळ रंगवू नये.</li>
                    <li>उत्तरपत्रिकेवर अनावश्यक खुणा किंवा खाडाखोड करू नये.</li>
                </ul>

                <div class="method-demo">
                    <div>
                        <b>योग्य पद्धत:</b>
                        <span class="demo-bubble correct">●</span>
                    </div>
                    <div style="border-left:1px solid #ccc; padding-left:6px;">
                        <b>अयोग्य पद्धत:</b>
                        <span class="demo-bubble wrong-cross">✘</span>
                        <span class="demo-bubble wrong-tick">✔</span>
                        <span class="demo-bubble" style="background: radial-gradient(circle, #000 35%, #fff 40%);">⊙</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- MAIN QUESTION RESPONSE GRID (COLUMNS) -->
        <div class="omr-grid-container">
            <?php
            for ($col = 0; $col < $numCols; $col++):
                $startQ = $col * $rowsPerCol + 1;
                $endQ = min($totalQuestions, ($col + 1) * $rowsPerCol);
                if ($startQ > $totalQuestions) break;
            ?>
                <div class="omr-column">
                    <div class="omr-col-header">
                        <span>प्र.क्र.</span>
                        <span>उत्तर पर्याय (OPTIONS)</span>
                    </div>

                    <table class="omr-rows-table">
                        <tbody>
                        <?php for ($qNum = $startQ; $qNum <= $endQ; $qNum++): 
                            $sec = ep_get_section_for_q($qNum, $sectionRanges);
                            $isSecStart = ($sec && $sec['from'] === $qNum);
                            $correctOpt = $answers[$qNum] ?? null;
                        ?>
                            <?php if ($isSecStart): ?>
                                <tr>
                                    <td colspan="2" class="omr-section-banner">
                                        <?= h($sec['title']) ?> (प्र. <?= $sec['from'] ?> ते <?= $sec['to'] ?>)
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <tr class="omr-row">
                                <td class="omr-row-qno"><?= sprintf('%02d', $qNum) ?></td>
                                <td>
                                    <div class="omr-row-bubbles">
                                        <?php for ($optIdx = 1; $optIdx <= 4; $optIdx++): 
                                            $isShaded = ($isKey && $correctOpt === $optIdx);
                                        ?>
                                            <div class="bubble-opt <?= $isShaded ? 'shaded' : '' ?>">
                                                <?= $optionLabels[$optIdx - 1] ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php endfor; ?>
        </div>

        <!-- FOOTER VERIFICATION & EVALUATION BLOCK -->
        <footer class="omr-footer">
            <!-- Candidate Signature -->
            <div class="sig-box">
                <div class="sig-title">विद्यार्थ्याची स्वाक्षरी (Candidate's Sign):</div>
                <div class="sig-line">विद्यार्थ्याने परीक्षा संपण्यापूर्वी येथे सही करावी</div>
            </div>

            <!-- Invigilator Signature -->
            <div class="sig-box">
                <div class="sig-title">पर्यवेक्षकाची स्वाक्षरी (Invigilator's Sign):</div>
                <div class="sig-line">पर्यवेक्षक स्वाक्षरी व केंद्र शिक्का</div>
            </div>

            <!-- Evaluation / Score Summary -->
            <div class="sig-box">
                <div class="sig-title" style="display:flex; justify-content:space-between;">
                    <span>मूल्यमापन व गुणतक्ता</span>
                    <span>परीक्षक स्वाक्षरी: ________</span>
                </div>
                <table class="eval-table">
                    <thead>
                        <tr>
                            <th>एकूण प्रश्न</th>
                            <th>सोडवलेले</th>
                            <th>बरोबर</th>
                            <th>चूक</th>
                            <th>प्राप्त गुण</th>
                            <th>टक्केवारी</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><b><?= (int)$totalQuestions ?></b></td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp; / <?= (float)$totalMarks ?></td>
                            <td>&nbsp;%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </footer>

    </div>
</div>
<?php endif; ?>

<script>
// Keyboard shortcut for printing
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        window.print();
    }
});
</script>
</body>
</html>
