<?php
/**
 * Inline SVG chart helpers for the printable cards.
 * Everything is pure SVG (vector) so the browser's "Save as PDF"
 * keeps the graphics sharp at any print size.
 */

function svg_escape($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Horizontal bar chart of subject percentages.
 * $items = [['label' => 'मराठी', 'value' => 72.5], ...]
 */
function svg_subject_bars(array $items, $width = 520, $barH = 22, $gap = 9)
{
    $labelW = 150;
    $chartW = $width - $labelW - 46;
    $h = max(1, count($items)) * ($barH + $gap) + 26;
    $o = '<svg class="cce-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $h . '" width="100%" role="img">';
    $o .= '<defs><linearGradient id="barGrad" x1="0" y1="0" x2="1" y2="0">'
        . '<stop offset="0%" stop-color="#1e6fd9"/><stop offset="100%" stop-color="#37b6a6"/></linearGradient></defs>';

    // grid lines at 0/25/50/75/100
    for ($p = 0; $p <= 100; $p += 25) {
        $x = $labelW + ($chartW * $p / 100);
        $o .= '<line x1="' . $x . '" y1="14" x2="' . $x . '" y2="' . ($h - 14) . '" stroke="#dfe6ee" stroke-width="1"/>';
        $o .= '<text x="' . $x . '" y="10" font-size="9" fill="#8a97a6" text-anchor="middle">' . $p . '</text>';
    }

    $y = 18;
    foreach ($items as $it) {
        $v = max(0, min(100, (float)$it['value']));
        $w = $chartW * $v / 100;
        $o .= '<text x="' . ($labelW - 8) . '" y="' . ($y + $barH * 0.7) . '" font-size="11" text-anchor="end" fill="#243447">'
            . svg_escape(mb_substr($it['label'], 0, 22)) . '</text>';
        $o .= '<rect x="' . $labelW . '" y="' . $y . '" width="' . $chartW . '" height="' . $barH . '" rx="4" fill="#eef2f7"/>';
        $o .= '<rect x="' . $labelW . '" y="' . $y . '" width="' . round($w, 2) . '" height="' . $barH . '" rx="4" fill="url(#barGrad)"/>';
        $o .= '<text x="' . ($labelW + $chartW + 6) . '" y="' . ($y + $barH * 0.72) . '" font-size="10" fill="#243447">'
            . svg_escape(number_format($v, 1)) . '%</text>';
        $y += $barH + $gap;
    }
    return $o . '</svg>';
}

/** Donut chart, used for attendance percentage. */
function svg_donut($percent, $caption = '', $size = 150)
{
    $percent = max(0, min(100, (float)$percent));
    $r = $size * 0.36;
    $c = $size / 2;
    $circ = 2 * M_PI * $r;
    $dash = $circ * $percent / 100;
    $o = '<svg class="cce-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . ($size + 18) . '" width="' . $size . '" role="img">';
    $o .= '<circle cx="' . $c . '" cy="' . $c . '" r="' . $r . '" fill="none" stroke="#e8edf3" stroke-width="' . ($size * 0.12) . '"/>';
    if ($percent > 0) {
        $o .= '<circle cx="' . $c . '" cy="' . $c . '" r="' . $r . '" fill="none" stroke="#2e9e6b" stroke-width="' . ($size * 0.12) . '"'
            . ' stroke-linecap="round" stroke-dasharray="' . round($dash, 2) . ' ' . round($circ - $dash, 2) . '"'
            . ' transform="rotate(-90 ' . $c . ' ' . $c . ')"/>';
    }
    $o .= '<text x="' . $c . '" y="' . ($c + 6) . '" font-size="' . ($size * 0.2) . '" text-anchor="middle" fill="#1d3247" font-weight="bold">'
        . svg_escape(number_format($percent, 0)) . '%</text>';
    if ($caption !== '') {
        $o .= '<text x="' . $c . '" y="' . ($size + 12) . '" font-size="11" text-anchor="middle" fill="#5a6b7d">' . svg_escape($caption) . '</text>';
    }
    return $o . '</svg>';
}

/**
 * Radar / spider chart for area-wise performance.
 * $items = [['label' => '...', 'value' => 0..100], ...]
 */
function svg_radar(array $items, $size = 280)
{
    $n = count($items);
    if ($n < 3) return '';
    $c = $size / 2;
    $r = $size * 0.33;
    $o = '<svg class="cce-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" role="img">';

    for ($ring = 1; $ring <= 4; $ring++) {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $a = -M_PI / 2 + 2 * M_PI * $i / $n;
            $rr = $r * $ring / 4;
            $pts[] = round($c + $rr * cos($a), 2) . ',' . round($c + $rr * sin($a), 2);
        }
        $o .= '<polygon points="' . implode(' ', $pts) . '" fill="none" stroke="#e1e8ef" stroke-width="1"/>';
    }

    $pts = [];
    foreach ($items as $i => $it) {
        $a = -M_PI / 2 + 2 * M_PI * $i / $n;
        $v = max(0, min(100, (float)$it['value']));
        $rr = $r * $v / 100;
        $pts[] = round($c + $rr * cos($a), 2) . ',' . round($c + $rr * sin($a), 2);
        $lx = $c + ($r + 18) * cos($a);
        $ly = $c + ($r + 18) * sin($a);
        $ca = cos($a);
        $anchor = (abs($ca) < 0.3) ? 'middle' : (($ca > 0) ? 'start' : 'end');
        $o .= '<line x1="' . $c . '" y1="' . $c . '" x2="' . round($c + $r * cos($a), 2) . '" y2="' . round($c + $r * sin($a), 2) . '" stroke="#e1e8ef"/>';
        $o .= '<text x="' . round($lx, 2) . '" y="' . round($ly + 4, 2) . '" font-size="9.5" text-anchor="' . $anchor . '" fill="#42566b">'
            . svg_escape(mb_substr($it['label'], 0, 14)) . '</text>';
    }
    $o .= '<polygon points="' . implode(' ', $pts) . '" fill="rgba(46,110,200,0.25)" stroke="#2e6ec8" stroke-width="2"/>';
    return $o . '</svg>';
}

/**
 * Official रुब्रिक (प्राविण्य स्तर) levels.
 * पायाभूत स्तर (बालवाटिका ते इ. २) uses three levels — निर्झर / पर्वत / आकाश —
 * as printed in the SCERT "समग्र प्रगतिपत्रक (HPC) – पायाभूत स्तर" booklet.
 * Higher stages use the four-level ladder of the portal.
 */
function hpc_rubric_levels($std)
{
    $std = (int)$std;
    if ($std <= 2) {
        return [
            ['key' => 'nirjhar', 'label' => 'निर्झर', 'color' => '#2e9ed9'],
            ['key' => 'parvat',  'label' => 'पर्वत',  'color' => '#6a3fb5'],
            ['key' => 'akash',   'label' => 'आकाश',   'color' => '#2e9e6b'],
        ];
    }
    return [
        ['key' => 'pailu',  'label' => 'पैलू',   'color' => '#d98f2e'],
        ['key' => 'pravah', 'label' => 'प्रवाह', 'color' => '#2e9ed9'],
        ['key' => 'parvat', 'label' => 'पर्वत',  'color' => '#6a3fb5'],
        ['key' => 'akash',  'label' => 'आकाश',   'color' => '#2e9e6b'],
    ];
}

/**
 * Rubric ladder. $level may be 1..n or a level key ('parvat').
 * 0 / '' = not assessed.
 */
function svg_rubric_ladder($level, $std = 1, $width = 300)
{
    $levels = hpc_rubric_levels($std);
    $n = count($levels);

    $active_index = 0;
    if (is_numeric($level)) {
        $active_index = (int)$level;
    } else {
        foreach ($levels as $i => $lv) {
            if ($lv['key'] === $level) $active_index = $i + 1;
        }
    }

    $h = 72;
    $barW = min(56, ($width - 16) / $n - 8);
    $stepW = ($width - 16) / $n;
    $o = '<svg class="cce-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $h . '"'
        . ' width="' . $width . '" height="' . $h . '" role="img">';
    $o .= '<line x1="6" y1="' . ($h - 20) . '" x2="' . ($width - 6) . '" y2="' . ($h - 20) . '" stroke="#d6dee6"/>';
    for ($i = 0; $i < $n; $i++) {
        $bh = 12 + $i * (34 / max(1, $n - 1));
        $x = 8 + $i * $stepW + ($stepW - 8 - $barW) / 2;
        $y = $h - 20 - $bh;
        $active = ($i + 1) === $active_index;
        $col = $levels[$i]['color'];
        $o .= '<rect x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($barW, 2) . '" height="' . round($bh, 2) . '" rx="2.5"'
            . ' fill="' . ($active ? $col : '#eceff3') . '" stroke="' . ($active ? $col : '#dbe1e8') . '"/>';
        $o .= '<text x="' . round($x + $barW / 2, 2) . '" y="' . ($h - 6) . '" font-size="10" text-anchor="middle"'
            . ' fill="' . ($active ? $col : '#7d8b99') . '" font-weight="' . ($active ? 'bold' : 'normal') . '">'
            . svg_escape($levels[$i]['label']) . '</text>';
        if ($active) {
            $o .= '<text x="' . round($x + $barW / 2, 2) . '" y="' . round($y - 3, 2) . '" font-size="10" text-anchor="middle" fill="' . $col . '">&#10003;</text>';
        }
    }
    return $o . '</svg>';
}

/**
 * "प्राविण्य स्तर" mountain badge, the circular motif printed beside the
 * teacher remark box in the official booklet. Pure SVG.
 */
function svg_pravinya_mountain($level, $std = 1, $size = 150)
{
    static $uid = 0;
    $uid++;
    $levels = hpc_rubric_levels($std);
    $n = count($levels);
    $active_index = is_numeric($level) ? (int)$level : 0;
    if (!is_numeric($level)) {
        foreach ($levels as $i => $lv) if ($lv['key'] === $level) $active_index = $i + 1;
    }

    $w = $size;
    $h = $size + 20;            // room for the caption under the circle
    $c = $w / 2;
    $cy = $size / 2;
    $r = $size * 0.46;

    $o = '<svg class="cce-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '"'
        . ' width="' . $w . '" height="' . $h . '" role="img">';
    $o .= '<defs><clipPath id="pmClip' . $uid . '"><circle cx="' . $c . '" cy="' . $cy . '" r="' . $r . '"/></clipPath>'
        . '<linearGradient id="skyG' . $uid . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="#8fd0f5"/><stop offset="100%" stop-color="#e4f2fb"/></linearGradient></defs>';
    $o .= '<g clip-path="url(#pmClip' . $uid . ')">';
    $o .= '<rect x="0" y="0" width="' . $w . '" height="' . $size . '" fill="url(#skyG' . $uid . ')"/>';
    $o .= '<rect x="0" y="' . round($size * 0.74, 2) . '" width="' . $w . '" height="' . round($size * 0.26, 2) . '" fill="#7cc487"/>';
    $o .= '<path d="M0 ' . round($size * 0.9, 2) . ' Q' . $c . ' ' . round($size * 0.83, 2) . ' ' . $w . ' ' . round($size * 0.9, 2)
        . ' L' . $w . ' ' . $size . ' L0 ' . $size . ' Z" fill="#4aa3d6"/>';
    // mountain sits on the right half so the level scale stays readable
    $o .= '<polygon points="' . round($w * 0.34, 2) . ',' . round($size * 0.78, 2) . ' ' . round($w * 0.66, 2) . ',' . round($size * 0.3, 2)
        . ' ' . round($w * 0.98, 2) . ',' . round($size * 0.78, 2) . '" fill="#9b8e80"/>';
    $o .= '<polygon points="' . round($w * 0.56, 2) . ',' . round($size * 0.46, 2) . ' ' . round($w * 0.66, 2) . ',' . round($size * 0.3, 2)
        . ' ' . round($w * 0.76, 2) . ',' . round($size * 0.46, 2) . '" fill="#ffffff"/>';
    $o .= '</g>';
    $o .= '<circle cx="' . $c . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#b9c6d2" stroke-width="1.5"/>';

    // vertical proficiency axis on the left third of the circle
    $ax = $w * 0.3;
    $o .= '<line x1="' . round($ax, 2) . '" y1="' . round($size * 0.9, 2) . '" x2="' . round($ax, 2) . '" y2="' . round($size * 0.16, 2) . '" stroke="#1d3247" stroke-width="1.6"/>';
    $o .= '<polygon points="' . round($ax - 4, 2) . ',' . round($size * 0.18, 2) . ' ' . round($ax + 4, 2) . ',' . round($size * 0.18, 2)
        . ' ' . round($ax, 2) . ',' . round($size * 0.1, 2) . '" fill="#1d3247"/>';

    $top = 0.26; $bottom = 0.84;
    for ($i = 0; $i < $n; $i++) {
        $frac = $n > 1 ? $bottom - ($bottom - $top) * $i / ($n - 1) : ($top + $bottom) / 2;
        $y = $size * $frac;
        $active = ($i + 1) === $active_index;
        $col = $active ? $levels[$i]['color'] : '#20364b';
        $o .= '<line x1="' . round($ax - 4, 2) . '" y1="' . round($y, 2) . '" x2="' . round($ax + 4, 2) . '" y2="' . round($y, 2) . '" stroke="#1d3247" stroke-width="1.2"/>';
        if ($active) {
            $o .= '<circle cx="' . round($ax, 2) . '" cy="' . round($y, 2) . '" r="3.4" fill="' . $levels[$i]['color'] . '"/>';
        }
        $o .= '<text x="' . round($ax + 8, 2) . '" y="' . round($y - 4, 2) . '" font-size="9" fill="' . $col . '"'
            . ' font-weight="' . ($active ? 'bold' : 'normal') . '">' . svg_escape($levels[$i]['label']) . '</text>';
    }
    $o .= '<text x="' . $c . '" y="' . ($h - 5) . '" font-size="9.5" text-anchor="middle" fill="#20364b">प्राविण्य स्तर</text>';
    return $o . '</svg>';
}

/** Small sparkline of monthly attendance. */
function svg_attendance_line(array $values, $width = 520, $height = 110)
{
    $n = count($values);
    if ($n === 0) return '';
    $padL = 26; $padB = 22; $padT = 10;
    $iw = $width - $padL - 10;
    $ih = $height - $padB - $padT;
    $o = '<svg class="cce-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" role="img">';
    foreach ([0, 50, 100] as $g) {
        $y = $padT + $ih - $ih * $g / 100;
        $o .= '<line x1="' . $padL . '" y1="' . $y . '" x2="' . ($padL + $iw) . '" y2="' . $y . '" stroke="#e6ecf2"/>';
        $o .= '<text x="' . ($padL - 4) . '" y="' . ($y + 3) . '" font-size="8" text-anchor="end" fill="#95a3b1">' . $g . '</text>';
    }
    $pts = [];
    $i = 0;
    foreach ($values as $label => $v) {
        $x = $padL + ($n > 1 ? $iw * $i / ($n - 1) : $iw / 2);
        $y = $padT + $ih - $ih * max(0, min(100, (float)$v)) / 100;
        $pts[] = round($x, 2) . ',' . round($y, 2);
        $o .= '<circle cx="' . round($x, 2) . '" cy="' . round($y, 2) . '" r="2.6" fill="#1e6fd9"/>';
        $o .= '<text x="' . round($x, 2) . '" y="' . ($height - 6) . '" font-size="8" text-anchor="middle" fill="#6a7b8c">'
            . svg_escape(mb_substr((string)$label, 0, 4)) . '</text>';
        $i++;
    }
    $o .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#1e6fd9" stroke-width="2" stroke-linejoin="round"/>';
    return $o . '</svg>';
}
