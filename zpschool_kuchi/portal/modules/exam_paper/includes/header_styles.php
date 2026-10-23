<?php
/**
 * Selectable Header Branding Styles for Exam Papers:
 * 1. 'board'   - Classic State Board Examination Pattern (Seat No. boxes, Centre code, Supervisor sign)
 * 2. 'center'  - Centered Emblem / Logo Layout (Emblem centered top, royal header, decorative divider)
 * 3. 'modern'  - Modern Two-Column Card (School identity on left, exam info badge on right)
 * 4. 'compact' - Minimalist Space Saver (Ultra compact 1-line header to maximize questions on Page 1)
 */

function ep_render_header(array $params): void
{
    $style       = $params['style'] ?? 'board';
    $schoolName  = $params['school_name'] ?? '';
    $schoolAddr  = $params['school_address'] ?? '';
    $logoUrl     = $params['logo_url'] ?? '';
    $title       = $params['title'] ?? '';
    $stdLabel    = $params['std_label'] ?? '';
    $subject     = $params['subject'] ?? '';
    $totalMarks  = $params['total_marks'] ?? 0;
    $examDate    = $params['exam_date'] ?? '';
    $duration    = $params['duration'] ?? '';
    $set         = strtoupper($params['set'] ?? 'A');
    $showAnswers = !empty($params['show_answers']);
    $english     = !empty($params['english']);
    $isAssessment= !empty($params['is_assessment']);
    $linesFormat = !empty($params['lines_format']);

    $L = $english
        ? ['seat' => 'Seat No.', 'center' => 'Centre Code', 'sign' => 'Supervisor Sign', 'set' => 'SET',
           'std' => 'Std.', 'subj' => 'Subject', 'marks' => 'Total Marks', 'date' => 'Date', 'time' => 'Time',
           'obtained' => 'Marks Obtained', 'name' => 'Student Name', 'roll' => 'Roll No.']
        : ['seat' => 'आसन क्र. (Seat No.)', 'center' => 'केंद्र क्र.', 'sign' => 'पर्यवेक्षक स्वाक्षरी', 'set' => 'संच (SET)',
           'std' => 'इयत्ता', 'subj' => 'विषय', 'marks' => 'एकूण गुण', 'date' => 'दिनांक', 'time' => 'वेळ',
           'obtained' => 'प्राप्त गुण', 'name' => 'विद्यार्थ्याचे नाव', 'roll' => 'हजेरी क्र.'];

    $setHtml = '<span class="set-badge-box" style="border:2px solid #000; padding:2px 10px; font-weight:800; font-size:11pt; border-radius:3px; background:#f8fafc; display:inline-block; letter-spacing:1px;">' . $L['set'] . ' : ' . htmlspecialchars($set) . '</span>';
    $answersTag = $showAnswers ? ' <span style="background:#dc3545; color:#fff; font-size:9.5pt; padding:1px 6px; border-radius:3px; vertical-align:middle;">उत्तरतालिका (ANSWER KEY)</span>' : '';

    if ($style === 'center'): ?>
      <!-- STYLE 2: CENTERED EMBLEM LAYOUT -->
      <header class="ep-header-center" style="text-align:center; border-bottom:2px solid #000; padding-bottom:6px; margin-bottom:8px;">
        <?php if ($logoUrl): ?>
          <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="height:24mm; width:auto; max-width:40mm; object-fit:contain; margin-bottom:4px;">
        <?php endif; ?>
        <h1 style="margin:0; font-size:18pt; font-weight:800; line-height:1.2;"><?= htmlspecialchars($schoolName) ?></h1>
        <?php if ($schoolAddr): ?>
          <div style="font-size:10pt; color:#333; margin-top:2px;"><?= htmlspecialchars($schoolAddr) ?></div>
        <?php endif; ?>
        <div style="margin-top:6px; display:flex; align-items:center; justify-content:space-between; border-top:1px solid #777; padding-top:4px;">
          <div style="text-align:left; font-size:10.5pt;"><b><?= $L['std'] ?>:</b> <?= htmlspecialchars($stdLabel) ?> &nbsp;|&nbsp; <b><?= $L['subj'] ?>:</b> <?= htmlspecialchars($subject) ?></div>
          <div style="font-size:13pt; font-weight:700;"><?= htmlspecialchars($title) ?><?= $answersTag ?></div>
          <div style="text-align:right;"><?= $setHtml ?></div>
        </div>
      </header>

    <?php elseif ($style === 'modern'): ?>
      <!-- STYLE 3: MODERN TWO-COLUMN CARD -->
      <header class="ep-header-modern" style="display:flex; justify-content:space-between; align-items:stretch; gap:12px; border-bottom:2px solid #000; padding-bottom:8px; margin-bottom:8px;">
        <div style="flex:1; display:flex; align-items:center; gap:10px; border:1px solid #cbd5e1; border-radius:6px; padding:6px 10px; background:#f8fafc;">
          <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="height:18mm; width:18mm; object-fit:contain;"><?php endif; ?>
          <div>
            <h1 style="margin:0; font-size:15pt; font-weight:800; color:#0f172a; line-height:1.2;"><?= htmlspecialchars($schoolName) ?></h1>
            <?php if ($schoolAddr): ?><div style="font-size:9.5pt; color:#475569; margin-top:2px;"><?= htmlspecialchars($schoolAddr) ?></div><?php endif; ?>
            <div style="font-size:11.5pt; font-weight:700; color:#1e3a8a; margin-top:2px;"><?= htmlspecialchars($title) ?><?= $answersTag ?></div>
          </div>
        </div>
        <div style="flex:0 0 42%; border:1px solid #cbd5e1; border-radius:6px; padding:6px 10px; background:#fff; font-size:10pt;">
          <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
            <span><b><?= $L['std'] ?>:</b> <?= htmlspecialchars($stdLabel) ?></span>
            <span><b><?= $L['marks'] ?>:</b> <?= $totalMarks ?></span>
          </div>
          <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
            <span><b><?= $L['subj'] ?>:</b> <?= htmlspecialchars($subject) ?></span>
            <span><b><?= $L['time'] ?>:</b> <?= htmlspecialchars($duration ?: '१ तास') ?></span>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px; padding-top:3px; border-top:1px dashed #cbd5e1;">
            <span><b><?= $L['date'] ?>:</b> <?= $examDate ?></span>
            <span><?= $setHtml ?></span>
          </div>
        </div>
      </header>

    <?php elseif ($style === 'compact'): ?>
      <!-- STYLE 4: COMPACT MINIMALIST SPACE SAVER -->
      <header class="ep-header-compact" style="border-bottom:1.5px solid #000; padding-bottom:4px; margin-bottom:6px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
          <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="height:12mm; width:12mm; object-fit:contain;"><?php endif; ?>
          <div style="flex:1;">
            <div style="font-size:13pt; font-weight:800; line-height:1.1;"><?= htmlspecialchars($schoolName) ?> <span style="font-weight:400; font-size:10pt; color:#475569;">(<?= htmlspecialchars($schoolAddr) ?>)</span></div>
            <div style="font-size:11pt; font-weight:700; margin-top:1px;"><?= htmlspecialchars($title) ?> &bull; <span style="font-weight:600; font-size:10.5pt;"><?= htmlspecialchars($stdLabel) ?> &mdash; <?= htmlspecialchars($subject) ?></span><?= $answersTag ?></div>
          </div>
          <div style="text-align:right; font-size:10pt; line-height:1.3;">
            <div><b><?= $L['marks'] ?>:</b> <?= $totalMarks ?> &nbsp;|&nbsp; <b><?= $L['time'] ?>:</b> <?= htmlspecialchars($duration ?: '१ तास') ?></div>
            <div style="margin-top:2px;"><?= $setHtml ?></div>
          </div>
        </div>
      </header>

    <?php else: ?>
      <!-- STYLE 1 (DEFAULT): OFFICIAL BOARD EXAMINATION PATTERN -->
      <header class="ep-header-board" style="border-bottom:2px solid #000; padding-bottom:6px; margin-bottom:8px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:4px;">
          <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="height:18mm; width:18mm; object-fit:contain; flex:0 0 18mm;"><?php endif; ?>
          <div style="flex:1; text-align:center;">
            <h1 style="margin:0; font-size:17pt; font-weight:800; line-height:1.2; letter-spacing:0.5px;"><?= htmlspecialchars($schoolName) ?></h1>
            <?php if ($schoolAddr): ?><div style="font-size:9.5pt; font-weight:500; color:#222;"><?= htmlspecialchars($schoolAddr) ?></div><?php endif; ?>
            <h2 style="margin:2px 0 0; font-size:12.5pt; font-weight:700;"><?= htmlspecialchars($title) ?><?= $answersTag ?></h2>
          </div>
          <div style="flex:0 0 auto; text-align:right;">
            <?= $setHtml ?>
          </div>
        </div>

        <!-- Official State Board Candidate Info Grid -->
        <table class="board-exam-grid" style="width:100%; border-collapse:collapse; margin-top:6px; font-size:10pt; border:1.5px solid #000;">
          <tr>
            <td style="border:1px solid #000; padding:3px 6px; width:45%;">
              <div style="display:flex; align-items:center; gap:4px;">
                <b style="white-space:nowrap;"><?= $L['seat'] ?>:</b>
                <div class="seat-boxes" style="display:inline-flex; gap:2px; margin-left:4px;">
                  <?php for ($b = 0; $b < 7; $b++): ?>
                    <span style="display:inline-block; width:17px; height:18px; border:1px solid #000; text-align:center; font-size:9pt; background:#fff;"></span>
                  <?php endfor; ?>
                </div>
              </div>
            </td>
            <td style="border:1px solid #000; padding:3px 6px; width:25%;">
              <b><?= $L['center'] ?>:</b>
              <span style="display:inline-flex; gap:2px; margin-left:2px;">
                <?php for ($b = 0; $b < 4; $b++): ?>
                  <span style="display:inline-block; width:16px; height:18px; border:1px solid #000; text-align:center; font-size:9pt; background:#fff;"></span>
                <?php endfor; ?>
              </span>
            </td>
            <td style="border:1px solid #000; padding:3px 6px; width:30%; text-align:right;">
              <b><?= $L['marks'] ?>:</b> <span style="font-size:11pt; font-weight:800;"><?= $totalMarks ?></span>
            </td>
          </tr>
          <tr>
            <td style="border:1px solid #000; padding:3px 6px;">
              <b><?= $L['std'] ?>:</b> <?= htmlspecialchars($stdLabel) ?> &nbsp;&bull;&nbsp; <b><?= $L['subj'] ?>:</b> <?= htmlspecialchars($subject) ?>
            </td>
            <td style="border:1px solid #000; padding:3px 6px;">
              <b><?= $L['date'] ?>:</b> <?= $examDate ?>
            </td>
            <td style="border:1px solid #000; padding:3px 6px; text-align:right;">
              <b><?= $L['time'] ?>:</b> <?= htmlspecialchars($duration ?: '१ तास') ?> &nbsp;|&nbsp; <b><?= $L['obtained'] ?>:</b> _____
            </td>
          </tr>
        </table>
      </header>
    <?php endif;
}
