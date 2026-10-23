<?php
/**
 * समग्र प्रगतिपत्रक (HPC) — SCERT मांडणी, पूर्वतयारी स्तर (इ. ३–५) व पूर्वमाध्यमिक स्तर (इ. ६–८)
 * Drop-in for cards/print_hpc_stage.php.
 *
 *   print_hpc_stage.php?id=12              — one card
 *   print_hpc_stage.php?grade=इयत्ता ६     — whole class
 *   &mode=compact                          — 1 page per subject × term (full SCERT layout is the default)
 *   php print_hpc_stage.php demo=6         — optional no-DB demo; needs hpc_demo_card.php (not included).
 *                                            Use tools/seed_hpc_stage_demo.php for DB-backed demo cards instead.
 *
 * hpc/generate_pdf.php routes: std 1–2 -> print_hpc_19.php, std 3–8 -> this file.
 */
$DEMO = 0;
foreach ($argv ?? [] as $a) if (preg_match('/^demo=(\d)$/', $a, $m)) $DEMO = (int) $m[1];
$COMPACT = (($_GET['mode'] ?? '') === 'compact') || in_array('mode=compact', $argv ?? [], true);
if (!$DEMO && isset($_GET['demo'])) $DEMO = (int) $_GET['demo'];

if ($DEMO) {
    define('HPC_FORCE_JSON', true);
    define('APP_URL', '.');
    require_once __DIR__ . '/../includes/hpc_curriculum.php';
    if (!is_file(__DIR__ . '/hpc_demo_card.php')) { http_response_code(404); exit('demo mode needs hpc_demo_card.php — run tools/seed_hpc_stage_demo.php and open ?id=<card> instead'); }
    require_once __DIR__ . '/hpc_demo_card.php';               // $school, $cards[] sample
    [$school, $CARDS] = hpc_demo_card($DEMO);
} else {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/cce_functions.php';   // std_from_grade()
    require_once __DIR__ . '/../includes/hpc_curriculum.php';
    require_once __DIR__ . '/../includes/cce_theme_helper.php';
    $is_admin = isset($_SESSION['admin_id']);
    if (!$is_admin) requireLogin();
    $db = getDB();
    $school_id = $is_admin ? 0 : (int) ($_SESSION['school_id'] ?? SCHOOL_ID);
    $_SESSION['school_id'] = $school_id;
    $school = $is_admin ? null : getSchool();

    if (!$is_admin && !empty($_GET['grade'])) {
        $g_std = function_exists('hpc_std_from_grade') ? hpc_std_from_grade($_GET['grade']) : 3;
        if ($g_std >= 3) {
            $chk = can_access_hpc_class($g_std, $school_id);
            if (!$chk['allowed']) {
                flash('error', $chk['message'] ?? 'इयत्ता ३ व पुढील वर्गांसाठी HPC सशुल्क योजना आवश्यक आहे. मोफत योजना फक्त इ. १ व २ साठी आहे.');
                redirect(APP_URL . '/subscription/plans.php');
            }
        }
    }
    if (!$is_admin && !empty($_GET['std'])) {
        $s_val = (int)$_GET['std'];
        if ($s_val >= 3) {
            $chk = can_access_hpc_class($s_val, $school_id);
            if (!$chk['allowed']) {
                flash('error', $chk['message'] ?? 'इयत्ता ३ व पुढील वर्गांसाठी HPC सशुल्क योजना आवश्यक आहे. मोफत योजना फक्त इ. १ व २ साठी आहे.');
                redirect(APP_URL . '/subscription/plans.php');
            }
        }
    }

    $card_ids = [];
    if (!empty($_GET['id'])) $card_ids[] = (int) $_GET['id'];
    elseif (!empty($_GET['student_id'])) {
        $gs = $db->prepare("SELECT id FROM hpc_cards WHERE school_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
        $gs->execute([$school_id, (int)$_GET['student_id']]);
        $cid = (int)$gs->fetchColumn();
        if (!$cid) {
            $db->prepare("INSERT INTO hpc_cards (student_id, school_id, academic_year, status) VALUES (?, ?, ?, 'draft')")
               ->execute([(int)$_GET['student_id'], $school_id, ACADEMIC_YEAR]);
            $cid = (int)$db->lastInsertId();
        }
        if ($cid) $card_ids[] = $cid;
    }
    elseif (!empty($_GET['grade'])) {
        $gs = $db->prepare("SELECT h.id FROM hpc_cards h JOIN students s ON s.id = h.student_id
                            WHERE h.school_id = ? AND s.grade = ? ORDER BY CAST(s.roll_no AS UNSIGNED), s.name_mr");
        $gs->execute([$school_id, $_GET['grade']]);
        $card_ids = array_map(fn($r) => (int) $r['id'], $gs->fetchAll());
    } elseif (!empty($_GET['std'])) {
        $s_val = (int)$_GET['std'];
        $lbl = function_exists('std_label') ? std_label($s_val) : "इयत्ता $s_val";
        $gs = $db->prepare("SELECT h.id FROM hpc_cards h JOIN students s ON s.id = h.student_id
                            WHERE h.school_id = ? AND (h.std = ? OR s.grade = ? OR s.grade LIKE ?) ORDER BY CAST(s.roll_no AS UNSIGNED), s.name_mr");
        $gs->execute([$school_id, $s_val, $lbl, '%' . $s_val . '%']);
        $card_ids = array_map(fn($r) => (int) $r['id'], $gs->fetchAll());
    }
    if (!$card_ids) { flash('error', 'छापण्यासाठी HPC कार्ड निवडलेले नाही.'); redirect(APP_URL . '/cards/list.php'); }

    $CARDS = [];
    foreach ($card_ids as $cid) {
        $st = $db->prepare("SELECT h.*, s.*, s.id AS student_id, h.id AS hpc_id FROM hpc_cards h JOIN students s ON s.id = h.student_id WHERE h.id = ?" . ($is_admin ? '' : ' AND h.school_id = ?'));
        $st->execute($is_admin ? [$cid] : [$cid, $school_id]);
        $d = $st->fetch(PDO::FETCH_ASSOC);
        if (!$d) continue;
        if ($is_admin && !$school) { $ss = $db->prepare("SELECT * FROM schools WHERE id = ?"); $ss->execute([$d['school_id']]); $school = $ss->fetch(PDO::FETCH_ASSOC); }
        $std = (int) ($d['std'] ?? 0) ?: hpc_std_from_grade($d['grade']);
        if (($std < 3 || ($d['stage_key'] ?? '') === 'foundational') && count($card_ids) === 1) {
            redirect(APP_URL . '/cards/print_hpc_19.php?id=' . $cid . '&format=official19');
        }
        if (!$is_admin && $std >= 3) {
            $hpc_chk = can_access_hpc_class($std, $school_id);
            if (!$hpc_chk['allowed']) {
                flash('error', $hpc_chk['message'] ?? 'इयत्ता ३ व पुढील वर्गांसाठी HPC सशुल्क योजना आवश्यक आहे.');
                redirect(APP_URL . '/subscription/plans.php');
            }
        }
        $rows = []; $sum = []; $att = []; $pa = [];
        $q = $db->prepare("SELECT * FROM hpc_subject_assessments WHERE hpc_card_id = ?"); $q->execute([$cid]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int) $r['subject_id']][(int) $r['term']] = $r;
        $q = $db->prepare("SELECT * FROM hpc_subject_summary WHERE hpc_card_id = ?"); $q->execute([$cid]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $sum[(int) $r['subject_id']] = $r;
        $q = $db->prepare("SELECT * FROM attendance WHERE student_id = ? AND academic_year = ?"); $q->execute([$d['student_id'], $d['academic_year']]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $att[(int) $r['month']] = $r;
        try { $q = $db->prepare("SELECT * FROM hpc_card_parta WHERE hpc_card_id = ?"); $q->execute([$cid]); $pa = $q->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}
        $ex = [];
        try {
            $qEx = $db->prepare("SELECT * FROM cce_student_extra WHERE student_id = ? AND academic_year = ? LIMIT 1");
            $qEx->execute([$d['student_id'], $d['academic_year']]);
            $ex = $qEx->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}
        $CARDS[] = ['d' => $d, 'std' => $std, 'rows' => $rows, 'summary' => $sum, 'att' => $att, 'parta' => $pa, 'extra' => $ex];
    }
}

/* ---------- helpers (guarded so files can coexist) ---------- */
if (!function_exists('u_e'))  { function u_e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('u_mr')) { function u_mr($s) { return strtr((string) $s, ['0'=>'०','1'=>'१','2'=>'२','3'=>'३','4'=>'४','5'=>'५','6'=>'६','7'=>'७','8'=>'८','9'=>'९']); } }
if (!function_exists('u_box')) { function u_box($on = false) { return '<span class="cb' . ($on ? ' on' : '') . '"></span>'; } }
if (!function_exists('u_j')) { function u_j($s) { if (is_array($s)) return $s; $a = json_decode((string) $s, true); return is_array($a) ? $a : []; } }
if (!function_exists('u_lines')) { function u_lines($s) { $s = trim((string) $s); return $s === '' ? [] : preg_split('/\r?\n+/', $s); } }
if (!function_exists('u_ph')) { function u_ph($s, $ph = '') { $s = trim((string) $s); return $s === '' ? '<span class="ph">' . u_e($ph) . '</span>' : u_e($s); } }

/** SCERT smiley set: yes | some | no | unsure  (4-option middle) / yes | no | unsure (3-option prep) */
if (!function_exists('u_smile')) {
function u_smile($k, $sel = false) {
    $c = ['yes' => '#2e9e4f', 'some' => '#e6a700', 'no' => '#d9232d', 'unsure' => '#e07b00'][$k] ?? '#555';
    $face = [
        'yes'    => '<path d="M11 19c2 3 8 3 10 0" /><circle cx="12" cy="13" r="1.3" fill="' . $c . '"/><circle cx="20" cy="13" r="1.3" fill="' . $c . '"/>',
        'some'   => '<path d="M11 19h10" /><circle cx="12" cy="13" r="1.3" fill="' . $c . '"/><circle cx="20" cy="13" r="1.3" fill="' . $c . '"/>',
        'no'     => '<path d="M11 21c2-3 8-3 10 0" /><circle cx="12" cy="13" r="1.3" fill="' . $c . '"/><circle cx="20" cy="13" r="1.3" fill="' . $c . '"/>',
        'unsure' => '<text x="16" y="21" text-anchor="middle" font-size="13" font-weight="700" fill="' . $c . '" stroke="none">??</text>',
    ][$k];
    $ring = $sel ? '<circle cx="16" cy="16" r="15.2" fill="none" stroke="#e5007e" stroke-width="2"/>' : '';
    return '<svg viewBox="0 0 32 32" width="30" height="30" fill="none" stroke="' . $c . '" stroke-width="1.6" stroke-linecap="round">'
         . '<circle cx="16" cy="16" r="12"/>' . $face . $ring . '</svg>';
}
}
if (!function_exists('u_smile_lbl')) { function u_smile_lbl($k) { return ['yes' => 'हो', 'some' => 'काही प्रमाणात', 'no' => 'नाही', 'unsure' => 'खात्री नाही'][$k] ?? $k; } }

/** Part-C gauge (semi-circle red/yellow/green with needle on the given level index 0..2) */
if (!function_exists('u_gauge')) {
function u_gauge($lvl = -1) {
    $ang = [ -150, -90, -30 ][$lvl] ?? null;
    $needle = $ang === null ? '' : '<g transform="rotate(' . ($ang + 90) . ' 50 52)"><line x1="50" y1="52" x2="50" y2="18" stroke="#1d3f8f" stroke-width="3" stroke-linecap="round"/></g>';
    return '<svg viewBox="0 0 100 58" width="74" height="43">'
        . '<path d="M8 52 A42 42 0 0 1 29 15.6 L40 33.6 A21 21 0 0 0 29 52Z" fill="#e5443b"/>'
        . '<path d="M29 15.6 A42 42 0 0 1 71 15.6 L60 33.6 A21 21 0 0 0 40 33.6Z" fill="#f4d23c"/>'
        . '<path d="M71 15.6 A42 42 0 0 1 92 52 L71 52 A21 21 0 0 0 60 33.6Z" fill="#6fbf5a"/>'
        . $needle . '<circle cx="50" cy="52" r="4" fill="#fff" stroke="#1d3f8f" stroke-width="2"/></svg>';
}
}

/** Middle-stage progress wheel: 3 domains × 3 evaluators × 3 concentric rings (B/P/A) */
if (!function_exists('u_wheel')) {
function u_wheel(array $w, array $abilities = [], array $levels = [], $size = 250) {
    static $uid = 0;
    $uid++;

    $domains = [
        'awareness' => [
            'name_mr'   => 'जाणीवजागृती',
            'color'     => '#f43f5e',
            'bg_color'  => '#fb7185',
            'fill_color'=> '#fda4af',
            'soft'      => '#fff1f2'
        ],
        'sensitivity' => [
            'name_mr'   => 'संवेदनशीलता',
            'color'     => '#eab308',
            'bg_color'  => '#fde047',
            'fill_color'=> '#fef08a',
            'soft'      => '#fefce8'
        ],
        'creativity' => [
            'name_mr'   => 'सर्जनशीलता',
            'color'     => '#0284c7',
            'bg_color'  => '#38bdf8',
            'fill_color'=> '#7dd3fc',
            'soft'      => '#f0f9ff'
        ]
    ];

    $evaluators = [
        'teacher' => 'शिक्षक',
        'student' => 'विद्यार्थी',
        'peer'    => 'सहाध्यायी'
    ];

    $levelDepth = [
        'prarambhik' => 0, '1' => 0, 'b' => 0, 'प्रारंभिक' => 0, 'nirjhar' => 0,
        'praveen'    => 1, '2' => 1, 'p' => 1, 'प्रवीण'    => 1, 'parvat'  => 1,
        'pragat'     => 2, '3' => 2, 'a' => 2, 'प्रगत'     => 2, 'akash'   => 2,
    ];

    $cx = 150;
    $cy = 150;
    $rCore = 32;
    $rB    = 58;
    $rP    = 84;
    $rA    = 110;
    $rEval = 126;
    $rOuter= 148;

    $rings = [
        0 => ['r1' => $rCore, 'r2' => $rB, 'key' => 'B', 'label' => 'प्रारंभिक'],
        1 => ['r1' => $rB,    'r2' => $rP, 'key' => 'P', 'label' => 'प्रवीण'],
        2 => ['r1' => $rP,    'r2' => $rA, 'key' => 'A', 'label' => 'प्रगत'],
    ];

    $arc = function ($r1, $r2, $a1, $a2) use ($cx, $cy) {
        $p = function($r, $deg) use ($cx, $cy) {
            $rad = deg2rad($deg);
            return [$cx + $r * cos($rad), $cy + $r * sin($rad)];
        };
        [$x1, $y1] = $p($r2, $a1);
        [$x2, $y2] = $p($r2, $a2);
        [$x3, $y3] = $p($r1, $a2);
        [$x4, $y4] = $p($r1, $a1);
        $large = ($a2 - $a1) > 180 ? 1 : 0;
        return sprintf("M %.2f %.2f A %d %d 0 %d 1 %.2f %.2f L %.2f %.2f A %d %d 0 %d 0 %.2f %.2f Z",
            $x1, $y1, $r2, $r2, $large, $x2, $y2,
            $x3, $y3, $r1, $r1, $large, $x4, $y4
        );
    };

    $textArc = function($r, $a1, $a2, $reverse = false) use ($cx, $cy) {
        $p = function($radius, $deg) use ($cx, $cy) {
            $rad = deg2rad($deg);
            return [$cx + $radius * cos($rad), $cy + $radius * sin($rad)];
        };
        if (!$reverse) {
            [$x1, $y1] = $p($r, $a1);
            [$x2, $y2] = $p($r, $a2);
            $large = ($a2 - $a1) > 180 ? 1 : 0;
            return sprintf("M %.2f %.2f A %d %d 0 %d 1 %.2f %.2f", $x1, $y1, $r, $r, $large, $x2, $y2);
        } else {
            [$x1, $y1] = $p($r, $a2);
            [$x2, $y2] = $p($r, $a1);
            $large = ($a2 - $a1) > 180 ? 1 : 0;
            return sprintf("M %.2f %.2f A %d %d 0 %d 0 %.2f %.2f", $x1, $y1, $r, $r, $large, $x2, $y2);
        }
    };

    $domAngles = [
        'awareness'   => ['start' => 150, 'end' => 270],
        'sensitivity' => ['start' => 270, 'end' => 390],
        'creativity'  => ['start' => 30,  'end' => 150],
    ];

    ob_start();
    ?>
    <svg viewBox="0 0 300 300" width="<?= $size ?>" height="<?= $size ?>" xmlns="http://www.w3.org/2000/svg" font-family="'Noto Sans Devanagari', 'Baloo 2', Arial, sans-serif">
      <defs>
        <filter id="pwShadow<?= $uid ?>" x="-10%" y="-10%" width="120%" height="120%">
          <feDropShadow dx="0" dy="1" stdDeviation="1.5" flood-opacity="0.15"/>
        </filter>
        <?php foreach ($domAngles as $domKey => $ang): 
            $dPathId = "domPath_{$uid}_{$domKey}";
            $rDomText = ($rEval + $rOuter) / 2;
            $isBottom = ($domKey === 'creativity');
            $dArcStr = $textArc($rDomText + ($isBottom ? 2 : 0), $ang['start'] + 2, $ang['end'] - 2, $isBottom);
        ?>
          <path id="<?= $dPathId ?>" d="<?= $dArcStr ?>" fill="none" />
        <?php endforeach; ?>
      </defs>

      <!-- Draw 3 Domains -->
      <?php foreach ($domAngles as $domKey => $ang): 
          $dom = $domains[$domKey];
          $dStart = $ang['start'];
          $dEnd   = $ang['end'];
          $dSpan  = $dEnd - $dStart;
          $evalSpan = $dSpan / 3;
          $dPathId = "domPath_{$uid}_{$domKey}";
      ?>
        <!-- Outer Domain Banner -->
        <path d="<?= $arc($rEval, $rOuter, $dStart, $dEnd) ?>" fill="<?= $dom['bg_color'] ?>" stroke="#ffffff" stroke-width="1.5" />
        
        <!-- Domain Title along arc -->
        <text font-size="11" font-weight="800" fill="#1e293b">
          <textPath href="#<?= $dPathId ?>" startOffset="50%" text-anchor="middle">
            <?= $dom['name_mr'] ?>
          </textPath>
        </text>

        <!-- 3 Evaluators in this Domain: शिक्षक, विद्यार्थी, सहाध्यायी -->
        <?php 
          $eIdx = 0;
          foreach ($evaluators as $evalKey => $evalLabel): 
              $eStart = $dStart + $eIdx * $evalSpan;
              $eEnd   = $eStart + $evalSpan;

              // Determine student's level for this domain and evaluator
              $currLevelRaw = strtolower(trim((string)($w[$domKey][$evalKey] ?? '')));
              $maxDepth = $levelDepth[$currLevelRaw] ?? 2; // Default to Pragat (B + P + A filled)
              
              // Evaluator Label Arc
              $rEvalText = ($rA + $rEval) / 2;
        ?>
              <path d="<?= $arc($rA, $rEval, $eStart, $eEnd) ?>" fill="<?= $dom['soft'] ?>" stroke="#ffffff" stroke-width="1.2" />
              
              <!-- Evaluator text -->
              <?php 
                $eMidRad = deg2rad(($eStart + $eEnd) / 2);
                $txEval = $cx + $rEvalText * cos($eMidRad);
                $tyEval = $cy + $rEvalText * sin($eMidRad);
                $rotEval = ($eStart + $eEnd) / 2 + 90;
                if ($rotEval > 90 && $rotEval < 270) $rotEval += 180;
              ?>
              <text x="<?= $txEval ?>" y="<?= $tyEval + 3 ?>" font-size="7.5" font-weight="700" fill="#334155" text-anchor="middle" transform="rotate(<?= $rotEval ?>, <?= $txEval ?>, <?= $tyEval ?>)">
                <?= $evalLabel ?>
              </text>

              <!-- Concentric Level Rings: 0 (B), 1 (P), 2 (A) -->
              <?php foreach ($rings as $depth => $rInfo): 
                  $isFilled = ($depth <= $maxDepth);
                  $fill = $isFilled ? $dom['fill_color'] : '#ffffff';
                  $stroke = '#94a3b8';
              ?>
                <path d="<?= $arc($rInfo['r1'], $rInfo['r2'], $eStart, $eEnd) ?>" fill="<?= $fill ?>" stroke="<?= $stroke ?>" stroke-width="0.8" />
              <?php endforeach; ?>

        <?php 
              $eIdx++;
          endforeach; 
        ?>

        <!-- Level Letters B, P, A on dividing spokes -->
        <?php 
          $spRad = deg2rad($dStart);
          foreach ($rings as $depth => $rInfo): 
              $rMid = ($rInfo['r1'] + $rInfo['r2']) / 2;
              $bx = $cx + $rMid * cos($spRad);
              $by = $cy + $rMid * sin($spRad);
        ?>
            <circle cx="<?= $bx ?>" cy="<?= $by ?>" r="5.5" fill="#ffffff" stroke="#64748b" stroke-width="0.8" />
            <text x="<?= $bx ?>" y="<?= $by + 3 ?>" font-size="7.5" font-weight="800" fill="#0f172a" text-anchor="middle"><?= $rInfo['key'] ?></text>
        <?php endforeach; ?>

      <?php endforeach; ?>

      <!-- Center Circle: कामगिरीदर्शक स्तर -->
      <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $rCore ?>" fill="#ffffff" stroke="#0284c7" stroke-width="2.5" filter="url(#pwShadow<?= $uid ?>)" />
      
      <!-- Mini Progress Bars Icon inside Center Circle -->
      <g transform="translate(<?= $cx - 11 ?>, <?= $cy - 15 ?>)">
        <rect x="0" y="8" width="4" height="6" rx="1" fill="#f43f5e" />
        <rect x="6" y="4" width="4" height="10" rx="1" fill="#eab308" />
        <rect x="12" y="1" width="4" height="13" rx="1" fill="#0284c7" />
        <rect x="18" y="5" width="4" height="9" rx="1" fill="#22c55e" />
      </g>
      
      <text x="<?= $cx ?>" y="<?= $cy + 8 ?>" font-size="7.5" font-weight="800" fill="#0f172a" text-anchor="middle">कामगिरीदर्शक</text>
      <text x="<?= $cx ?>" y="<?= $cy + 17 ?>" font-size="7.5" font-weight="800" fill="#0284c7" text-anchor="middle">स्तर</text>
    </svg>
    <?php
    return ob_get_clean();
}
}

$MONTHS = ['जून','जुलै','ऑगस्ट','सप्टें.','ऑक्टो.','नोव्हें.','डिसें.','जाने.','फेब्रु.','मार्च','एप्रिल'];
$MONTH_NO = [6,7,8,9,10,11,12,1,2,3,4];
$STD_MR = [3 => 'तिसरी', 4 => 'चौथी', 5 => 'पाचवी', 6 => 'सहावी', 7 => 'सातवी', 8 => 'आठवी'];
?>
<!doctype html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>समग्र प्रगतिपत्रक (HPC) — छपाई</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
:root{
  --ink:#1a1a1a;
  --pink: var(--cce-theme-accent, #e5007e);
  --pink2: var(--cce-theme-accent, #ec008c);
  --line: var(--cce-theme-border, #e5007e);
  --soft: var(--cce-theme-light-bg, #fdeef6);
  --soft2: var(--cce-theme-badge-bg, #fbe4ef);
  --blue: var(--cce-theme-accent, #1d4ed8);
  --mint:#d9efe6;
  --cream:#fdeacd;
  /* SCERT prep/middle card palette */
  --peach:#fde2cf; --peach2:#fbd0b0; --orange:#e9701a; --orange2:#f39a4b; --brick:#c6453d;
  --sky:#d9ecfa; --leaf:#dcebd9; --leaf2:#c9e3c3; --sand:#fff4e0;
  --ab1:#f4b6c8; --ab2:#fdf3b0; --ab3:#b7dcf3;
}
*{box-sizing:border-box}
body{margin:0;background:#8d8d8d;font-family:var(--cce-font-family, 'Noto Sans Devanagari'), 'Noto Sans Devanagari', sans-serif !important;color:var(--ink);font-size:11.2px;line-height:1.45}
.toolbar{position:sticky;top:0;z-index:20;background:#111;color:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.toolbar button,.toolbar a{background:var(--pink);color:#fff;border:0;border-radius:6px;padding:8px 16px;font-weight:700;font-family:inherit;font-size:14px;min-height:42px;display:inline-flex;align-items:center;cursor:pointer;text-decoration:none}
.toolbar a.ghost{background:#333}
.page{width:210mm;min-height:296mm;background:#fff;margin:10px auto;padding:11mm 10mm 13mm;position:relative;box-shadow:0 2px 10px rgba(0,0,0,.35);overflow:hidden}
@media screen and (max-width: 991.98px) {
  html, body { overflow-x: hidden !important; width: 100% !important; }
  .toolbar { padding: 10px 12px; gap: 8px; width: 100%; }
  .toolbar a, .toolbar button { font-size: 14px; padding: 8px 14px; min-height: 42px; flex-grow: 1; justify-content: center; }
  .page {
    width: 100% !important;
    max-width: 100% !important;
    min-height: auto !important;
    height: auto !important;
    margin: 8px auto !important;
    padding: 12px 10px !important;
    border-radius: 8px;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    box-sizing: border-box !important;
  }
  .page table { min-width: 580px !important; }
}
.pfoot{position:absolute;left:10mm;right:10mm;bottom:5mm;display:flex;justify-content:space-between;font-size:8px;color:#888;border-top:1.2px solid #3aa7e0;padding-top:2px}
.phead{display:flex;justify-content:space-between;align-items:baseline;font-size:10.5px;margin-bottom:5px;color:#333}
.phead .sn{font-weight:700}
.ph{color:#aaa;font-style:italic}
.cb{display:inline-block;width:10px;height:10px;border:1.1px solid #333;vertical-align:-1px;margin-right:3px;border-radius:1px;background:#fff}
.cb.on{background:#333;box-shadow:inset 0 0 0 2px #fff}
/* SCERT corner triangles */
.tri{position:absolute;width:0;height:0;pointer-events:none}
.tri.tr{top:0;right:0;border-top:26mm solid var(--orange);border-left:26mm solid transparent}
.tri.tr2{top:0;right:0;border-top:14mm solid var(--orange2);border-left:14mm solid transparent}
.tri.bl{bottom:0;left:0;border-bottom:18mm solid var(--orange);border-right:18mm solid transparent}
.tri.br{bottom:0;right:0;border-bottom:12mm solid var(--brick);border-left:12mm solid transparent}
/* cover */
.cover{position:relative;height:272mm;background:linear-gradient(180deg,#fff 0,#fff 58%,var(--sand) 58%,var(--sand) 100%)}
.cover .band{position:absolute;left:0;right:0;top:0;height:120mm;background:linear-gradient(135deg,var(--orange) 0,var(--orange) 48%,#f39a4b 48%,#f39a4b 100%);clip-path:polygon(0 0,100% 0,100% 70%,0 100%)}
.cover .logos{position:absolute;top:8mm;left:10mm;right:10mm;display:flex;justify-content:space-between;align-items:center;color:#fff}
.cover .logos img{height:20mm}
.cover .logos .lg{width:22mm;height:22mm;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;color:var(--orange);font-weight:800;font-size:9px;text-align:center;overflow:hidden}
.cover .ttl{position:absolute;left:12mm;top:132mm}
.cover .ttl h1{margin:0;font-size:38px;color:var(--brick);font-weight:800;line-height:1.1}
.cover .ttl h2{margin:2px 0 0;font-size:17px;color:#444;font-weight:600}
.cover .ttl .stg{margin-top:8px;font-size:18px;color:var(--orange);font-weight:700}
.cover .ttl .std{font-size:15px;color:#333}
.cover .who{position:absolute;left:12mm;right:12mm;bottom:22mm;background:#fff;border:1.6px solid var(--orange);border-radius:10px;padding:10px 18px}
.cover .who div{display:flex;font-size:14px;margin:5px 0}
.cover .who b{color:var(--brick);width:110px;display:inline-block}
.cover .yr{position:absolute;right:12mm;top:132mm;border:1.4px solid var(--brick);border-radius:6px;padding:4px 14px;font-weight:700;font-size:14px;color:var(--brick)}
/* generic SCERT blocks */
.bar{background:var(--peach);border:1.2px solid var(--pink);text-align:center;font-weight:700;font-size:13px;padding:4px 8px;color:#222}
.bar.sub{background:var(--sky);font-size:12px}
.bar.o{background:var(--orange);color:#fff;border-color:var(--orange)}
.ttl-bar{text-align:center;font-size:16px;font-weight:700;color:var(--brick);margin:4px 0 6px}
table.f{width:100%;border-collapse:collapse}
table.f th,table.f td{border:1px solid var(--pink);padding:4px 6px;vertical-align:top;font-size:10.5px}
table.f th{background:var(--peach);font-weight:700;text-align:center}
table.f td.k{background:var(--leaf);font-weight:600;width:22%}
table.f td.k2{background:var(--sand);font-weight:600}
table.f.tight td,table.f.tight th{padding:2.5px 5px}
.codes span{display:inline-block;margin:1px 8px 1px 0;font-family:Arial,sans-serif;font-size:10.5px;white-space:nowrap}
.kv{display:flex;margin:3px 0}
.kv b{min-width:150px;font-weight:600}
.ln{border-bottom:1px solid #444;min-height:15px;flex:1}
.lines div{border-bottom:1px solid #999;height:8mm}
table.att{width:100%;border-collapse:collapse;text-align:center;margin-top:4px}
table.att th,table.att td{border:1px solid var(--pink);padding:3px 2px;font-size:9.5px}
table.att th{background:var(--peach);font-weight:600}
table.att td.lbl{background:var(--sand);text-align:left;padding-left:5px;font-weight:600}
/* rubric */
table.rub{width:100%;border-collapse:collapse;margin-top:0}
table.rub th,table.rub td{border:1px solid var(--pink);padding:4px 6px;vertical-align:top;font-size:10px;text-align:left}
table.rub th{background:var(--leaf);font-weight:700;text-align:center;font-size:11px}
table.rub td.ab{width:17%;text-align:center;font-weight:700;vertical-align:middle;font-size:11px}
table.rub td.sel{background:#fff7fb;outline:2px solid var(--pink);outline-offset:-2px}
table.rub td.tick{text-align:center;font-size:13px;font-weight:700}
/* smiley rows */
table.sm{width:100%;border-collapse:collapse}
table.sm td{border:1px solid var(--pink);padding:5px 8px;font-size:10.5px;vertical-align:middle}
table.sm td.opts{width:46%;padding:3px 4px}
.opts .row{display:flex;justify-content:space-around;align-items:flex-end}
.opts .o{text-align:center;font-size:8.5px;line-height:1.1}
.opts .o.sel{font-weight:700;color:var(--pink)}
.opts .o.sel div{background:var(--peach)}
/* progress chart */
table.pc{width:100%;border-collapse:collapse}
table.pc th{border:1px solid var(--pink);padding:3px;font-size:11px;font-weight:700}
table.pc td{border:1px solid var(--pink);padding:3px 4px;font-size:8.6px;text-align:center;vertical-align:middle;line-height:1.3;height:12mm}
table.pc td.on{background:var(--peach2);font-weight:700;outline:1.6px solid var(--pink);outline-offset:-2px}
table.pc td.cnt{font-size:10px;text-align:left;background:var(--sand);height:auto;padding:6px 8px}
/* misc */
.two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.three{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.box{border:1px solid var(--pink);padding:5px 8px;min-height:22mm}
.box h5{margin:0 0 3px;font-size:11px}
.chk div{margin:2px 0;font-size:10.5px}
.small{font-size:9px;color:#444}
.note{font-size:8.5px;color:#555}
.sumblk{margin-bottom:9px;border:1px solid var(--orange);}
.sumblk .h{display:flex}
.sumblk .h .nm{flex:1;background:var(--orange);color:#fff;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center}
.sumblk .h .gg{flex:1.05;text-align:center}
.sumblk .h .gg .cap{background:var(--orange);color:#fff;font-weight:700;font-size:11px;padding:2px}
.sumblk .h .gg .g3{display:flex;justify-content:space-around;padding:3px 0 0}
.sumblk .h .gg .g3 div{font-size:9.5px;font-weight:600}
.sumblk table{width:100%;border-collapse:collapse}
.sumblk td{border:1px solid var(--orange2);padding:2.5px 6px;font-size:10px;text-align:center}
.sumblk td.k{background:var(--peach2);font-weight:600;text-align:left;width:22%}
.sumblk td.k0{background:var(--peach);font-weight:700;width:14%;text-align:center}
.sumblk .rem{padding:3px 8px;font-size:10px;min-height:9mm;border-top:1px solid var(--orange2)}
table.cr{width:100%;border-collapse:collapse;margin-top:6px}
table.cr th{background:var(--orange2);color:#222;font-weight:600;font-size:9.5px;border:1px solid #fff;padding:4px 3px}
table.cr th.t{background:var(--brick);color:#fff;font-size:13px}
table.cr td{border:1px solid #f1c9a7;padding:3px 6px;font-size:10.5px;text-align:center}
table.cr td.l{text-align:left}
table.cr tr:nth-child(odd) td{background:#fff6ee}
.sig{display:flex;justify-content:space-between;margin-top:14mm;font-size:11px}
.sig div{text-align:center;width:30%}
.sig div span{display:block;border-top:1px solid #333;margin-top:14mm;padding-top:3px}
@media print{
  body{background:#fff}
  .toolbar{display:none}
  .page{margin:0;box-shadow:none;width:auto;min-height:auto;padding:9mm 9mm 11mm;page-break-after:always;height:296mm}
  .page:last-child{page-break-after:auto}
}
.cpt{font-size:8.6px;line-height:1.25}
.cpt .rub td:last-child{font-size:8px !important}
.cpt .codes span{margin:0 3px 0 0}
.cpt .bar{padding:2px 6px;margin-top:2px}
.cpt .box{padding:2px 5px}
.cpt .box h5{margin:0 0 1px}
.cpt .rub td,.cpt .rub th{padding:2px 4px}
.cpt .f td,.cpt .f th{padding:1px 4px}
.cpt .sig{font-size:9px}
.cpt .sig div span{margin-top:6mm;padding-top:1px}
.cpt.dense{font-size:7.6px;line-height:1.15}
.cpt.dense .box{min-height:0 !important}
.cpt.dense .box h5{margin:0}
.cpt.dense .two{margin-top:1px !important}
.cpt.dense .whl svg{height:122px !important;width:122px !important}
.cpt.dense .rub td:last-child{font-size:7.4px !important}
.cpt.dense .box{padding:1px 4px}
.cpt.dense .rub td,.cpt.dense .rub th,.cpt.dense .f td,.cpt.dense .f th{padding:1px 3px}
.cpt.dense .bar{padding:1px 6px;margin-top:1px}
.cpt .sumgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px 8px;align-items:start}
.cpt .sumblk{margin-bottom:0}
.cpt .sumblk .h .nm{font-size:11px;padding:0 3px;text-align:center}
.cpt .sumblk .h .nm span{display:none}
.cpt .sumblk .h .gg .cap{font-size:9.5px;padding:1px}
.cpt .sumblk .h .gg .g3 div{font-size:8.5px}
.cpt .sumblk .h .gg .g3{padding:1px 0 0}
.cpt .sumblk .h .gg .g3 svg{height:22px;width:auto}
.cpt .sumblk td{padding:1.5px 6px;font-size:9.5px}
.cpt .sumblk .rem{min-height:15mm;font-size:9.5px}
.cpt .box,.cpt .rub tr,.cpt .f tr,.cpt .sumblk,.cpt .sig{page-break-inside:avoid;break-inside:avoid}
@media print{
  .page.cpt{height:auto;min-height:290mm;overflow:visible}
  @page{size:A4 portrait;margin:0}
}
</style>
<link href="<?= APP_URL ?>/assets/css/report_pro.css?v=3" rel="stylesheet">
</head>
<body>
<div class="toolbar">
  <span style="font-weight:700;">समग्र प्रगतिपत्रक (HPC) — अधिकृत मांडणी — <?= u_mr(count($CARDS)) ?> कार्ड<?= $COMPACT ? ' · संक्षिप्त' : '' ?></span>
  <span style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <?= cce_theme_toolbar_controls(false) ?>
    <?php $qs = $_GET; $qs['mode'] = $COMPACT ? 'full' : 'compact'; $toggle = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($qs); ?>
    <a class="ghost" href="<?= APP_URL ?>/cards/list.php">मागे</a>
    <a class="ghost" href="<?= u_e($toggle) ?>"><?= $COMPACT ? 'पूर्ण अधिकृत मांडणी' : 'संक्षिप्त (१ पान/विषय)' ?></a>
    <button onclick="window.print()">प्रिंट / PDF जतन करा</button>
  </span>
</div>

<?php foreach ($CARDS as $C):
    $d = $C['d']; $std = $C['std']; $stage = hpc_stage_for_std($std); $mid = ($stage === 'middle');
    $subjects = hpc_subjects_for_std($std);
    $ABIL = hpc_rubric_abilities($stage); $LVLS = hpc_rubric_levels($stage);
    $lvName = []; foreach ($LVLS as $l) $lvName[$l['level_key']] = $l['name_mr'];
    $lvIdx  = []; foreach (array_values($LVLS) as $i => $l) $lvIdx[$l['level_key']] = $i;
    $sname = $d['name_mr'] ?: $d['name']; $apaar = $d['apaar_id'] ?? '';
    $schname = $school['name_mr'] ?: $school['name'];
    $logo = !empty($school['logo']) ? APP_URL . '/uploads/logos/' . rawurlencode($school['logo']) : '';
    $stdMr = $STD_MR[$std] ?? u_mr($std);
    $pageno = 0;
    $total = ($COMPACT ? 3 : 4) + count($subjects) * ($COMPACT ? 1 : 2 * ($mid ? 4 : 3)) + (int) ceil(count($subjects) / ($COMPACT ? 12 : 3)) + 1;
    $foot = function () use (&$pageno, $total, $sname) { $pageno++; echo '<div class="pfoot"><span>' . u_e($sname) . '</span><span>' . $pageno . ' / ' . $total . '</span></div>'; };
    $head = function ($right = '') use ($sname, $apaar) { echo '<div class="phead"><span>विद्यार्थ्याचे नाव: <span class="sn">' . u_e($sname) . '</span></span><span>' . u_e($right ?: $apaar) . '</span></div>'; };
    $corners = '<div class="tri tr"></div><div class="tri tr2"></div><div class="tri bl"></div><div class="tri br"></div>';
    $PA = ['intro' => u_j($C['parta']['intro_json'] ?? ''), 'parent' => u_j($C['parta']['parent_json'] ?? ''), 'pt' => u_j($C['parta']['parent_teacher_json'] ?? '')];
?>

<!-- ============ पान १ : मुखपृष्ठ ============ -->
<div class="page">
  <div class="cover">
    <div class="band"></div>
    <div class="logos">
      <div class="lg"><?php if ($logo): ?><img src="<?= u_e($logo) ?>" alt="" style="height:100%"><?php else: ?>शाळा<br>लोगो<?php endif; ?></div>
      <div style="text-align:center;font-weight:700;font-size:13px;line-height:1.3">महाराष्ट्र शासन · शालेय शिक्षण विभाग · समग्र प्रगती पुस्तक (HPC)<br><span style="font-weight:500;font-size:11px">Holistic Progress Card · NCF-SE / SCF-SE 2024</span></div>
      <div class="lg" style="font-size:10px">PARAKH<br>PM SHRI</div>
    </div>
    <div class="yr">सन : <?= u_e($d['academic_year']) ?></div>
    <div class="ttl">
      <h1>समग्र प्रगतिपत्रक</h1>
      <h2>Holistic Progress Card (HPC)</h2>
      <div class="stg"><?= u_e(hpc_stage_name_mr($stage)) ?></div>
      <div class="std"><?= u_e(hpc_stage_std_range($stage)) ?></div>
    </div>
    <div class="who">
      <div><b>विद्यार्थ्याचे नाव</b>: <?= u_e($sname) ?></div>
      <div><b>शाळा</b>: <?= u_e($schname) ?></div>
      <div><b>इयत्ता / तुकडी</b>: <?= u_e($stdMr) ?><?= !empty($d['section']) ? ' / ' . u_e($d['section']) : '' ?>&nbsp;&nbsp;&nbsp;<b style="width:auto">हजेरी क्र.</b>: <?= u_e($d['roll_no'] ?? '') ?></div>
      <div><b>अपार आय.डी.</b>: <?= u_ph($apaar, '—') ?></div>
    </div>
  </div>
  <?php $foot(); ?>
</div>

<!-- ============ पान २ : भाग अ (1) सर्वसाधारण माहिती + उपस्थिती ============ -->
<?php
  $ex = $C['extra'] ?? [];
  $rawH1 = (float)($d['height_sem1'] ?? 0) > 0 ? (float)$d['height_sem1'] : ((float)($ex['height_1'] ?? 0) > 0 ? (float)$ex['height_1'] : null);
  $rawW1 = (float)($d['weight_sem1'] ?? 0) > 0 ? (float)$d['weight_sem1'] : ((float)($ex['weight_1'] ?? 0) > 0 ? (float)$ex['weight_1'] : null);
  $rawH2 = (float)($d['height_sem2'] ?? 0) > 0 ? (float)$d['height_sem2'] : ((float)($ex['height_2'] ?? 0) > 0 ? (float)$ex['height_2'] : null);
  $rawW2 = (float)($d['weight_sem2'] ?? 0) > 0 ? (float)$d['weight_sem2'] : ((float)($ex['weight_2'] ?? 0) > 0 ? (float)$ex['weight_2'] : null);

  $h1_val = $rawH1 ? (string)$rawH1 : '';
  $w1_val = $rawW1 ? (string)$rawW1 : '';
  $h2_val = $rawH2 ? (string)$rawH2 : '';
  $w2_val = $rawW2 ? (string)$rawW2 : '';
?>
<div class="page<?= $COMPACT ? ' cpt' : '' ?>"><?= $corners ?>
  <div class="ttl-bar">भाग – अ (1)</div>
  <div class="bar">सर्वसाधारण माहिती <span class="small">(पालकांशी चर्चा करून शिक्षकांनी भरावे)</span></div>
  <table class="f tight" style="margin-top:6px">
    <tr><td class="k">शाळेचे नाव</td><td colspan="3"><?= u_e($schname) ?></td></tr>
    <tr><td class="k">शाळेचा पत्ता</td><td colspan="3"><?= u_e(trim(($school['address_line1'] ?? '') . ' ' . ($school['village'] ?? '') . ' ' . ($school['taluka'] ?? '') . ' ' . ($school['district'] ?? ''))) ?></td></tr>
    <tr><td class="k">युडायस क्र.</td><td><?= u_e($school['udise_code'] ?? '') ?></td><td class="k">शैक्षणिक वर्ष</td><td><?= u_e($d['academic_year']) ?></td></tr>
    <tr><td class="k">विद्यार्थ्याचे नाव</td><td colspan="3"><?= u_e($sname) ?></td></tr>
    <tr><td class="k">अपार आय.डी.</td><td><?= u_ph($apaar) ?></td><td class="k">पेन / रजि. क्र.</td><td><?= u_ph($d['registration_no'] ?? '') ?></td></tr>
    <tr><td class="k">इयत्ता / तुकडी</td><td><?= u_e($stdMr) ?> <?= u_e($d['section'] ?? '') ?></td><td class="k">हजेरी क्र.</td><td><?= u_ph($d['roll_no'] ?? '') ?></td></tr>
    <tr><td class="k">जन्मतारीख</td><td><?= !empty($d['date_of_birth']) ? u_mr(date('d/m/Y', strtotime($d['date_of_birth']))) : '' ?></td><td class="k">लिंग</td><td><?= u_e($d['gender'] ?? '') ?></td></tr>
    <tr><td class="k">आईचे नाव</td><td><?= u_ph($d['mother_name'] ?? '') ?></td><td class="k">वडिलांचे नाव</td><td><?= u_ph($d['father_name'] ?? '') ?></td></tr>
    <tr><td class="k">पालकांचा संपर्क</td><td><?= u_ph($d['phone'] ?? $d['father_mobile'] ?? '') ?></td><td class="k">मातृभाषा</td><td><?= u_ph($d['mother_tongue'] ?? '') ?></td></tr>
    <tr><td class="k">पत्ता</td><td colspan="3"><?= u_ph(trim(($d['address_line1'] ?? '') . ' ' . ($d['address_line2'] ?? ''))) ?></td></tr>
    <tr>
      <td class="k">रक्तगट</td>
      <td><?= u_ph($d['blood_group'] ?? '') ?></td>
      <td class="k">उंची / वजन</td>
      <td>
        <?php if ($h1_val || $w1_val || $h2_val || $w2_val): ?>
          <span style="font-weight:600">सत्र १:</span> <?= u_mr($h1_val ?: '—') ?> सेमी / <?= u_mr($w1_val ?: '—') ?> किग्रॅ<br>
          <span style="font-weight:600">सत्र २:</span> <?= u_mr($h2_val ?: '—') ?> सेमी / <?= u_mr($w2_val ?: '—') ?> किग्रॅ
        <?php else: ?>
          <?= u_ph('') ?>
        <?php endif; ?>
      </td>
    </tr>
    <tr><td class="k">विशेष गरजा (CWSN)</td><td colspan="3"><?= u_ph($d['handicap'] ?? '') ?></td></tr>
  </table>

  <div class="bar" style="margin-top:12px">उपस्थिती</div>
  <table class="att">
    <tr><th style="width:16%"></th><?php foreach ($MONTHS as $m): ?><th><?= $m ?></th><?php endforeach; ?><th>एकूण</th></tr>
    <?php $tw = $tp = 0; ?>
    <tr><td class="lbl">कामाचे दिवस</td><?php foreach ($MONTH_NO as $n): $w = (int) ($C['att'][$n]['working_days'] ?? 0); $tw += $w; ?><td><?= $w ? u_mr($w) : '' ?></td><?php endforeach; ?><td><b><?= u_mr($tw) ?></b></td></tr>
    <tr><td class="lbl">उपस्थित दिवस</td><?php foreach ($MONTH_NO as $n): $p = (int) ($C['att'][$n]['days_present'] ?? 0); $tp += $p; ?><td><?= $p ? u_mr($p) : '' ?></td><?php endforeach; ?><td><b><?= u_mr($tp) ?></b></td></tr>
    <tr><td class="lbl">टक्केवारी</td><?php foreach ($MONTH_NO as $n): $w = (int) ($C['att'][$n]['working_days'] ?? 0); $p = (int) ($C['att'][$n]['days_present'] ?? 0); ?><td><?= $w ? u_mr(round($p * 100 / $w)) . '%' : '' ?></td><?php endforeach; ?><td><b><?= $tw ? u_mr(round($tp * 100 / $tw)) . '%' : '' ?></b></td></tr>
  </table>
  <div class="bar" style="margin-top:12px">आरोग्य तपासणी नोंद</div>
  <table class="f tight">
    <tr><th>तपासणी / घटक</th><th>सत्र १</th><th>सत्र २</th></tr>
    <tr><td class="k2">उंची (सें.मी.)</td><td style="font-weight:600;"><?= $h1_val ? u_mr($h1_val) . ' सेमी' : '—' ?></td><td style="font-weight:600;"><?= $h2_val ? u_mr($h2_val) . ' सेमी' : '—' ?></td></tr>
    <tr><td class="k2">वजन (कि.ग्रॅ.)</td><td style="font-weight:600;"><?= $w1_val ? u_mr($w1_val) . ' किग्रॅ' : '—' ?></td><td style="font-weight:600;"><?= $w2_val ? u_mr($w2_val) . ' किग्रॅ' : '—' ?></td></tr>
    <?php foreach (['दृष्टी', 'श्रवण', 'दंत', 'लसीकरण / इतर'] as $h): ?><tr><td class="k2"><?= $h ?></td><td>&nbsp;</td><td>&nbsp;</td></tr><?php endforeach; ?>
  </table>
<?php if (!$COMPACT): ?>
  <?php $foot(); ?>
</div>

<!-- ============ पान ३ : भाग अ (2) माझ्याबद्दल ============ -->
<div class="page"><?= $corners ?>
<?php endif; ?>
  <div class="ttl-bar"<?= $COMPACT ? ' style="margin-top:4mm"' : '' ?>>भाग – अ (2)</div>
  <div class="bar"><?= $mid ? 'माझ्याबद्दल थोडेसे' : 'मी व माझा परिसर' ?></div>
  <?php $I = $PA['intro'];
    $fields = $mid
      ? ['मला हे करायला आवडते' => 'likes', 'मी यात चांगला/चांगली आहे' => 'good_at', 'मला हे शिकायचे आहे' => 'want_learn', 'माझा आदर्श' => 'role_model', 'मोठे होऊन मला व्हायचे आहे' => 'aspiration', 'माझे छंद' => 'hobbies', 'मला यात मदत लागते' => 'need_help', 'माझे आवडते पुस्तक / खेळ' => 'fav_book_sport']
      : ['माझे वय' => 'age', 'माझा वाढदिवस' => 'birthday', 'माझ्या घराचा पत्ता' => 'address', 'माझ्या कुटुंबातील व्यक्ती' => 'family', 'माझा आवडता रंग / खाऊ / प्राणी' => 'favourites', 'माझे मित्र-मैत्रिणी' => 'friends', 'मोठे होऊन मला व्हायचे आहे' => 'aspiration', 'मला हे करायला आवडते' => 'likes'];
  ?>
  <table class="f" style="margin-top:6px">
    <?php foreach ($fields as $lbl => $k): ?><tr><td class="k" style="width:36%"><?= $lbl ?></td><td style="<?= $COMPACT ? 'height:6mm' : 'min-height:12mm;height:12mm' ?>"><?= u_e($I[$k] ?? '') ?></td></tr><?php endforeach; ?>
  </table>
  <div class="bar" style="margin-top:10px">माझी आवड आहे <span class="small">(एकापेक्षा जास्त पर्याय निवडू शकता)</span></div>
  <div class="chk" style="display:grid;grid-template-columns:repeat(4,1fr);padding:6px 4px">
    <?php $ints = (array) ($I['interests'] ?? []);
      foreach (['चित्रकला','संगीत','नृत्य','नाट्य','खेळ','वाचन','लेखन','विज्ञान प्रयोग','गणित कोडी','संगणक','हस्तकला','बागकाम','समाजसेवा','भाषा','प्रवास','इतर'] as $x): ?>
      <div><?= u_box(in_array($x, $ints, true)) ?><?= $x ?></div>
    <?php endforeach; ?>
  </div>
  <div class="bar" style="margin-top:<?= $COMPACT ? 4 : 8 ?>px"><?= $mid ? 'माझी ध्येये (या वर्षासाठी)' : 'माझा फोटो / चित्र' ?></div>
  <div class="lines" style="padding:<?= $COMPACT ? 2 : 4 ?>px 2px"><div></div><div></div><?= $COMPACT ? '' : '<div></div>' ?></div>
  <?php $foot(); ?>
</div>

<!-- ============ पान ४ : भाग अ (3) पालकांकडून माहिती / पालक-शिक्षक सहयोग ============ -->
<div class="page"><?= $corners ?>
  <div class="ttl-bar">भाग – अ (3)</div>
  <div class="bar">पालक-शिक्षक सहयोग नोंदपत्रक</div>
  <?php $P = $PA['parent']; $PT = $PA['pt']; ?>
  <div class="small" style="margin:6px 0 3px">तुमच्या पाल्याकडे घरी उपलब्ध असलेल्या संसाधनांवर (✓) अशी खूण करा.</div>
  <div class="chk" style="display:grid;grid-template-columns:repeat(4,1fr);border:1px solid var(--pink);padding:5px 8px">
    <?php $res = (array) ($P['resources'] ?? []);
      foreach (['पुस्तके / गोष्टींची पुस्तके','वर्तमानपत्र / मासिके','खेळणी / खेळ','मोबाईल / टॅब','इंटरनेट','संगणक','ग्रंथालय सुविधा','शांत अभ्यासाची जागा','दूरचित्रवाणी','वाद्य / कला साहित्य','इतर','यांपैकी काही नाही'] as $x): ?>
      <div><?= u_box(in_array($x, $res, true)) ?><?= $x ?></div>
    <?php endforeach; ?>
  </div>
  <div class="bar" style="margin-top:8px">पाल्याच्या शिक्षणात पालकांचा सहभाग</div>
  <table class="sm">
    <?php foreach ([
        'p1' => 'मी माझ्या पाल्याला दररोज अभ्यासात/गृहपाठात मदत करतो/करते.',
        'p2' => 'मी माझ्या पाल्याशी शाळेतील दिवसाबद्दल नियमित बोलतो/बोलते.',
        'p3' => 'माझा पाल्य घरी वाचन/ चित्र काढणे/ खेळ यांसाठी वेळ देतो/देते.',
        'p4' => 'मी शाळेच्या पालक सभांना उपस्थित राहतो/राहते.',
        'p5' => 'माझा पाल्य घरातील कामांत नियमित मदत करतो/करते.',
      ] as $k => $t): $v = $P[$k] ?? ''; ?>
      <tr><td><?= $t ?></td><td class="opts"><div class="row"><?php foreach (['yes', 'some', 'no'] as $o): ?><div class="o <?= $v === $o ? 'sel' : '' ?>"><?= u_smile($o, $v === $o) ?><br><?= u_smile_lbl($o) ?></div><?php endforeach; ?></div></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="bar" style="margin-top:8px">पालकांचे मत</div>
  <table class="f" style="margin-top:0">
    <tr><td class="k" style="width:40%">माझ्या पाल्याचे सामर्थ्य / आवडी</td><td style="height:16mm"><?= u_e($P['strengths'] ?? '') ?></td></tr>
    <tr><td class="k">ज्यात सुधारणा हवी असे वाटते</td><td style="height:16mm"><?= u_e($P['improve'] ?? '') ?></td></tr>
    <tr><td class="k">शाळेकडून अपेक्षा</td><td style="height:16mm"><?= u_e($P['expect'] ?? '') ?></td></tr>
  </table>
  <div class="bar" style="margin-top:8px">शिक्षकांनी पालकांना दिलेल्या सूचना / पुढील पावले</div>
  <div class="two" style="margin-top:4px">
    <div class="box"><h5>सत्र १</h5><?= nl2br(u_e($PT['term1'] ?? '')) ?></div>
    <div class="box"><h5>सत्र २</h5><?= nl2br(u_e($PT['term2'] ?? '')) ?></div>
  </div>
  <div class="sig"><div><span>पालकांची सही</span></div><div><span>वर्गशिक्षक</span></div><div><span>मुख्याध्यापक</span></div></div>
  <?php $foot(); ?>
</div>

<?php
/* ============ भाग ब : प्रत्येक विषय × सत्र ============ */
$subjNo = 0;
foreach ($subjects as $S):
    $subjNo++;
    $GOALS = hpc_goals($S); $COMPS = hpc_competencies($S);
    $prefix = $S['code_prefix'];
    $split = false;
    if ($COMPACT) {
        $load = 2 * (count($GOALS) + count($COMPS)) * 9 + ($mid ? 500 : 0);
        foreach ([1, 2] as $t) {
            $rr = $C['rows'][(int) $S['id']][$t] ?? [];
            $ru = u_j($rr['rubric_json'] ?? '');
            foreach (u_j($rr['comp_codes'] ?? '') as $cc) $load += mb_strlen($COMPS[$cc]['text_mr'] ?? '') + 12;
            foreach ($ru as $ab => $cells) foreach ((array) $cells as $lk => $tx) if ($lk !== 'src') $load += (int) (mb_strlen((string) $tx) * 0.5);
            $load += mb_strlen($rr['activity_mr'] ?? '') + mb_strlen($rr['assessment_questions_mr'] ?? '') + mb_strlen($rr['teacher_notes_mr'] ?? '') + mb_strlen($rr['teacher_observation_mr'] ?? '') + mb_strlen($rr['teacher_help_mr'] ?? '');
        }
        $dense = $load > 2500;   // long text → tighter type first…
        $split = $load > ($mid ? 3700 : 5000);   // …and each term on its own page when very long
    }
    foreach ([1, 2] as $term):
        $r = $C['rows'][(int) $S['id']][$term] ?? [];
        $gsel = u_j($r['goal_codes'] ?? ''); $csel = u_j($r['comp_codes'] ?? '');
        $rub = u_j($r['rubric_json'] ?? '');
        $lv = ['awareness' => $r['level_awareness'] ?? '', 'sensitivity' => $r['level_sensitivity'] ?? '', 'creativity' => $r['level_creativity'] ?? ''];
        $appr = u_j($r['activity_approach'] ?? '');
        $termMr = $term === 1 ? 'प्रथम सत्र' : 'द्वितीय सत्र';
        $subjTitle = ($subjNo === 1 && $term === 1 ? 'भाग – ब' : '');

        $TOP = $TOP ?? [];
        if (!isset($TOP[$S['subject_key']])) {
            $TOP[$S['subject_key']] = [];
            foreach (hpc_topics((int)$std, $S['subject_key']) as $tp) {
                $TOP[$S['subject_key']][$tp['key']] = $tp;
            }
        }
        $topSelKeys = u_j($r['topic_keys'] ?? '');
        $tsel = array_values(array_filter(array_map(function($k) use ($TOP, $S) {
            if (isset($TOP[$S['subject_key']][$k])) {
                $item = $TOP[$S['subject_key']][$k];
                return ($item['seq'] ? u_mr($item['seq']) . '. ' : '') . $item['topic'];
            }
            return '';
        }, (array)$topSelKeys)));
        if ($COMPACT):
            $sr = u_j($r['self_reflection'] ?? ''); $pr = u_j($r['peer_reflection'] ?? '');
            $sc = u_j($r['self_progress_count'] ?? ''); $pcnt = u_j($r['peer_progress_count'] ?? '');
            $W = u_j($r['wheel_json'] ?? '');
            foreach ($ABIL as $ab) { 
                $k = $ab['ability_key']; 
                $tLev = !empty($W[$k]['teacher']) ? $W[$k]['teacher'] : (!empty($lv[$k]) ? $lv[$k] : 'pragat');
                $W[$k]['teacher'] = $tLev;
                if (empty($W[$k]['student'])) $W[$k]['student'] = (isset($sc[$k]) && $sc[$k] !== '') ? hpc_level_from_count('middle', (int) $sc[$k]) : $tLev;
                if (empty($W[$k]['peer'])) $W[$k]['peer'] = (isset($pcnt[$k]) && $pcnt[$k] !== '') ? hpc_level_from_count('middle', (int) $pcnt[$k]) : $tLev;
            }
            $st = u_j($r['strengths'] ?? ''); $hu = u_j($r['hurdles'] ?? '');
            $lbl = function ($set, $sel) { $o = []; foreach ($set as $s) { if (trim($s['text_mr']) === 'इतर') continue; $k = $s['option_set'] ?? $s['text_mr']; if (in_array($k, $sel, true)) $o[] = $s['text_mr']; } if (!empty($sel['other'])) $o[] = 'इतर: ' . $sel['other']; return $o; };
            $stL = $lbl(hpc_statements($stage, 'strength'), $st); $huL = $lbl(hpc_statements($stage, 'hurdle'), $hu);
            $smileCount = function ($arr) { $c = []; foreach ($arr as $v) if ($v !== '') $c[$v] = ($c[$v] ?? 0) + 1; return $c; };
?>
<!-- ---- ब (संक्षिप्त) : १ पान / विषय (दोन्ही सत्रे) ---- -->
<?php if ($term === 1 || $split): ?>
<div class="page cpt<?= ($dense && !$split) ? ' dense' : '' ?>">
  <?php if ($subjTitle): ?><div class="ttl-bar" style="margin-top:-4px"><?= $subjTitle ?></div><?php else: $head(hpc_stage_name_mr($stage)); endif; ?>
<?php else: ?>
  <div style="height:3mm"></div>
<?php endif; ?>
  <div class="bar"><?= u_e($S['name_mr']) ?> <span style="font-family:Arial">(<?= u_e($S['subject_key']) ?>)</span> &nbsp;·&nbsp; <?= $termMr ?></div>
  <table class="f tight">
    <tr><td class="k" style="width:17%">ध्येये</td><td class="codes"><?php foreach ($GOALS as $code => $g): ?><span><?= u_box(in_array($code, $gsel, true)) ?><?= u_e($code) ?></span><?php endforeach; ?></td></tr>
    <tr><td class="k">क्षमता</td><td class="codes"><?php foreach ($COMPS as $code => $c): ?><span><?= u_box(in_array($code, $csel, true)) ?><?= u_e($code) ?></span><?php endforeach; ?></td></tr>
    <?php if ($tsel): ?>
    <tr><td class="k">पाठ / घटक</td><td class="small" style="text-align:left"><?= u_e(implode('  ●  ', $tsel)) ?></td></tr>
    <?php endif; ?>
    <?php $chosen = array_filter(array_map(fn($c) => isset($COMPS[$c]) ? $c . ' : ' . $COMPS[$c]['text_mr'] : '', $csel)); if ($chosen): ?>
    <tr><td class="k">निवडलेल्या क्षमता</td><td class="small" style="text-align:left"><?= u_e(implode('  ●  ', $chosen)) ?></td></tr><?php endif; ?>
  </table>
  <div class="two" style="gap:0;margin-top:3px">
    <div class="box" style="min-height:12mm"><h5>कृती :<?php if ($mid && !empty($appr['keys'])): ?> <span class="small">(<?= u_e(implode(', ', (array) $appr['keys'])) ?>)</span><?php endif; ?></h5><?= nl2br(u_ph($r['activity_mr'] ?? '', '(भरलेले नाही)')) ?></div>
    <div class="box" style="min-height:12mm;border-left:0"><h5>मूल्यांकन प्रश्न :</h5><?php $ql = u_lines($r['assessment_questions_mr'] ?? ''); if ($ql): ?><ol style="margin:0;padding-left:16px"><?php foreach ($ql as $q) echo '<li>' . u_e($q) . '</li>'; ?></ol><?php else: ?><span class="ph">(भरलेले नाही)</span><?php endif; ?></div>
  </div>
  <div class="bar" style="margin-top:3px">मूल्यांकन निकष (रुब्रिक) — शिक्षकांचा अभिप्राय</div>
  <table class="rub">
    <tr><th style="width:13%">क्षमता</th><?php foreach ($LVLS as $l): ?><th style="width:29%"><?= u_e($l['name_mr']) ?></th><?php endforeach; ?></tr>
    <?php foreach ($ABIL as $i => $ab): $k = $ab['ability_key']; $bg = ['var(--ab1)', 'var(--ab2)', 'var(--ab3)'][$i] ?? '#eee'; ?>
    <tr><td class="ab" style="background:<?= $bg ?>"><?= u_e($mid && $S['subject_key'][0] === 'R' ? 'भाषिक ' . $ab['name_mr'] : $ab['name_mr']) ?></td>
      <?php foreach ($LVLS as $l): $sel = ($lv[$k] === $l['level_key']); $txt = ($sel && !empty($rub[$k]['text'])) ? $rub[$k]['text'] : ($rub[$k][$l['level_key']] ?? ''); ?>
      <td class="<?= $sel ? 'sel' : '' ?>" style="height:auto;text-align:left;vertical-align:top"><?= $sel ? '<span style="color:var(--pink);font-weight:700">✓ </span>' : '' ?><?= u_e($txt) ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
  </table>
  <?php if ($mid): ?>
  <div style="display:flex;gap:6px;align-items:center;border:1px solid var(--pink);border-top:0;padding:3px 6px">
    <div class="whl"><?= u_wheel($W, $ABIL, $LVLS, 140) ?></div>
    <table class="f tight" style="flex:1;margin:0"><tr><th>क्षमता</th><th>शिक्षक मूल्यांकन</th><th>विद्यार्थी आत्मचिंतन</th><th>सहाध्यायी अभिप्राय</th></tr>
      <?php foreach ($ABIL as $ab): $k = $ab['ability_key']; ?><tr><td class="k2"><?= u_e($ab['name_mr']) ?></td><td style="text-align:center"><?= u_e($lvName[$W[$k]['teacher'] ?? ''] ?? '—') ?></td><td style="text-align:center"><?= u_e($lvName[$W[$k]['student'] ?? ''] ?? '—') ?></td><td style="text-align:center"><?= u_e($lvName[$W[$k]['peer'] ?? ''] ?? '—') ?></td></tr><?php endforeach; ?></table>
    <div class="small" style="flex:1">
      <?php $sm1 = $smileCount($sr); $pm1 = $smileCount($pr); ?>
      <b>आत्मचिंतन:</b> <?= $sm1 ? u_e(implode(', ', array_map(fn($o, $n) => u_smile_lbl($o) . ' ' . u_mr($n), array_keys($sm1), $sm1))) : '—' ?><br>
      <b>सहाध्यायी अभिप्राय<?= !empty($r['peer_name']) ? ' (' . u_e($r['peer_name']) . ')' : '' ?>:</b> <?= $pm1 ? u_e(implode(', ', array_map(fn($o, $n) => u_smile_lbl($o) . ' ' . u_mr($n), array_keys($pm1), $pm1))) : '—' ?><br>
      <?php if (!empty($r['my_learning_mr'])): ?><b>मी शिकले:</b> <?= u_e($r['my_learning_mr']) ?><br><?php endif; ?>
      <?php if (!empty($r['need_practice_mr'])): ?><b>सराव हवा:</b> <?= u_e($r['need_practice_mr']) ?><?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="small" style="border:1px solid var(--pink);border-top:0;padding:3px 6px"><?php $sm1 = $smileCount($sr); ?><b>स्वयं-मूल्यांकन:</b> <?= $sm1 ? u_e(implode(', ', array_map(fn($o, $n) => u_smile_lbl($o) . ' ' . u_mr($n), array_keys($sm1), $sm1))) : '—' ?></div>
  <?php endif; ?>
  <div class="two" style="gap:0;margin-top:3px">
    <div class="box" style="min-height:10mm"><h5>बलस्थान क्षेत्र</h5><?= $stL ? u_e(implode(' · ', $stL)) : '<span class="ph">—</span>' ?></div>
    <div class="box" style="min-height:10mm;border-left:0"><h5>यशस्वी होण्यातील अडथळे</h5><?= $huL ? u_e(implode(' · ', $huL)) : '<span class="ph">—</span>' ?></div>
  </div>
  <?php if (!$mid): ?>
  <div class="two" style="gap:0">
    <div class="box" style="min-height:10mm;border-top:0"><h5>आव्हाने</h5><?= nl2br(u_e($r['challenges_mr'] ?? '')) ?></div>
    <div class="box" style="min-height:10mm;border-top:0;border-left:0"><h5>मात / शिक्षकांची मदत</h5><?= nl2br(u_e($r['support_mr'] ?? '')) ?></div>
  </div>
  <?php endif; ?>
  <div class="box" style="border-top:0;min-height:12mm"><h5><?= $mid ? 'शिक्षकांचे निरीक्षण आणि शिफारशी :' : 'निरीक्षणात्मक नोंदी / शिफारशी :' ?></h5>
    <?php $nl = u_lines(trim(($r['teacher_notes_mr'] ?? '') . "\n" . ($r['teacher_observation_mr'] ?? ''))); if ($nl): ?><ul style="margin:0;padding-left:16px"><?php foreach ($nl as $x) echo '<li>' . u_e($x) . '</li>'; ?></ul><?php endif; ?>
    <?php if (!empty($r['teacher_help_mr'])): ?><div style="margin-top:3px"><b><?= $mid ? 'मदत:' : 'पालकांचे निरीक्षण:' ?></b> <?= nl2br(u_e($r['teacher_help_mr'])) ?></div><?php endif; ?></div>
<?php if ($term === 2 || $split): ?>
  <div class="sig" style="margin-top:3px"><div><span>पालकांची सही</span></div><div><span>विषय शिक्षक</span></div><div><span>वर्गशिक्षक</span></div></div>
  <?php $foot(); ?>
</div>
<?php endif; ?>
<?php continue; endif; ?>
<!-- ---- ब-१ : ध्येये/क्षमता, कृती, रुब्रिक ---- -->
<div class="page">
  <?php if ($subjTitle): ?><div class="ttl-bar" style="margin-top:-4px"><?= $subjTitle ?></div><?php else: $head(hpc_stage_name_mr($stage)); endif; ?>
  <div class="bar"><?= u_e($S['name_mr']) ?> <span style="font-family:Arial">(<?= u_e($S['subject_key']) ?>)</span> &nbsp;·&nbsp; <?= $termMr ?></div>
  <table class="f">
    <tr><td class="k">अभ्यासक्रमाची ध्येये<br><span class="small">(एक किंवा अधिक निवडा.)</span></td>
      <td class="codes"><?php foreach ($GOALS as $code => $g): ?><span title="<?= u_e($g['text_mr']) ?>"><?= u_box(in_array($code, $gsel, true)) ?><?= u_e($code) ?></span><?php endforeach; ?></td></tr>
    <tr><td class="k">क्षमता<br><span class="small">(एक किंवा अधिक निवडा.)</span></td>
      <td class="codes"><?php foreach ($COMPS as $code => $c): ?><span><?= u_box(in_array($code, $csel, true)) ?><?= u_e($code) ?></span><?php endforeach; ?></td></tr>
    <?php if ($tsel): ?>
    <tr><td class="k">पाठ / घटक<br><span class="small">(2026-27 तचन)</span></td>
      <td class="small" style="text-align:left;line-height:1.4"><?= u_e(implode('  ●  ', $tsel)) ?></td></tr>
    <?php endif; ?>
  </table>
  <?php $chosen = array_filter(array_map(fn($c) => isset($COMPS[$c]) ? $c . ' : ' . $COMPS[$c]['text_mr'] : '', $csel));
    if ($chosen): ?><div class="small" style="border:1px solid var(--pink);border-top:0;padding:3px 6px;background:#fffafd"><b>निवडलेल्या क्षमता :</b> <?= u_e(implode('  ●  ', $chosen)) ?></div><?php endif; ?>

  <div class="bar" style="margin-top:6px">कृती / उपक्रम</div>
  <?php if ($mid): $ap = hpc_statements('middle', 'approach'); ?>
  <div style="border:1px solid var(--pink);border-top:0;padding:4px 8px"><b>कृतीचा दृष्टिकोन</b> <span class="small">(लागू होणाऱ्या पर्यायावर ✓ करा.)</span><br>
    <?php foreach ($ap as $a): $k = $a['option_set'] ?? $a['text_mr']; ?><span style="display:inline-block;width:32%"><?= u_box(in_array($k, (array) ($appr['keys'] ?? []), true)) ?><?= u_e($a['text_mr']) ?></span><?php endforeach; ?>
    <?php if (!empty($appr['other'])): ?><span>: <?= u_e($appr['other']) ?></span><?php endif; ?></div>
  <?php endif; ?>
  <?php
    $actText = trim((string)($r['activity_mr'] ?? ''));
    if ($actText === '') {
        $abData = hpc_activity_bank((int)$std, $S['subject_key'] ?? '');
        foreach ((array)$csel as $cc) {
            if (!empty($abData[$cc]['activity_mr'])) { $actText = $abData[$cc]['activity_mr']; break; }
        }
        if ($actText === '' && !empty($abData)) {
            $first = reset($abData);
            $actText = $first['activity_mr'] ?? '';
        }
    }
    $ql = u_lines($r['assessment_questions_mr'] ?? '');
    if (!$ql) {
        $ql = hpc_get_assessment_questions((int)$std, $S['subject_key'] ?? '', (array)$csel);
    }
  ?>
  <div class="two" style="gap:0">
    <div class="box" style="min-height:38mm;border-top:0"><h5>कृती :</h5><?= nl2br(u_e($actText !== '' ? $actText : '—')) ?></div>
    <div class="box" style="min-height:38mm;border-top:0;border-left:0"><h5>मूल्यांकन प्रश्न :</h5>
      <?php if ($ql): ?><ol style="margin:0;padding-left:16px"><?php foreach ($ql as $i => $q) echo '<li>' . u_e($q) . '</li>'; ?></ol><?php else: ?><span class="ph">—</span><?php endif; ?></div>
  </div>

  <div class="bar" style="margin-top:6px">मूल्यांकन निकष (रुब्रिक)</div>
  <div class="bar sub">कामगिरीदर्शक स्तर</div>
  <table class="rub">
    <tr><th style="width:17%">क्षमता</th><?php foreach ($LVLS as $l): ?><th><?= u_e($l['name_mr']) ?></th><?php endforeach; ?></tr>
    <?php foreach ($ABIL as $i => $ab): $k = $ab['ability_key']; $bg = ['var(--ab1)', 'var(--ab2)', 'var(--ab3)'][$i] ?? '#eee'; ?>
    <tr><td class="ab" style="background:<?= $bg ?>"><?= u_e($mid && $S['subject_key'][0] === 'R' ? 'भाषिक ' . $ab['name_mr'] : $ab['name_mr']) ?></td>
      <?php foreach ($LVLS as $l): $sel = ($lv[$k] === $l['level_key']); $txt = ($sel && !empty($rub[$k]['text'])) ? $rub[$k]['text'] : ($rub[$k][$l['level_key']] ?? ''); ?>
      <td class="<?= $sel ? 'sel' : '' ?>" style="height:22mm;font-size:<?= mb_strlen($txt) > 140 ? '8.5px' : '10px' ?>"><?= $sel ? '<div style="color:var(--pink);font-weight:700;margin-bottom:2px">✓ ' . u_e($l['name_mr']) . '</div>' : '' ?><?= u_e($txt) ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
  </table>
  <div class="note">* विद्यार्थ्याच्या कृती/उपक्रमातील सहभाग आणि कामगिरीचा दर्जा लक्षात घेत मूल्यांकन निकष (रुब्रिक) तक्त्यात योग्य ठिकाणी खूण करावी.<?php $rsrc = $rub[$ABIL[0]['ability_key'] ?? 'awareness']['src'] ?? ''; if ($rsrc): ?> निकष क्षमता <?= u_e($rsrc) ?> वर आधारित.<?php endif; ?></div>
  <?php $foot(); ?>
</div>

<!-- ---- ब-२ : शिक्षकांचा अभिप्राय + (prep) स्वयं-मूल्यांकन | (middle) आत्मचिंतन + प्रगतीचा आराखडा ---- -->
<div class="page">
  <?php $head(u_e($S['name_mr']) . ' · ' . $termMr); ?>
  <?php if (!$mid): ?>
  <div class="bar">शिक्षकांचा अभिप्राय</div>
  <table class="rub">
    <tr><th style="width:17%">क्षमता</th><th colspan="3" style="background:var(--sky)">मुख्य कामगिरी स्तर दर्शके</th></tr>
    <tr><th></th><?php foreach ($LVLS as $l): ?><th><?= u_e($l['name_mr']) ?></th><?php endforeach; ?></tr>
    <?php foreach ($ABIL as $ab): $k = $ab['ability_key']; ?>
    <tr><td class="ab" style="background:var(--leaf)"><?= u_e($ab['name_mr']) ?></td><?php foreach ($LVLS as $l): ?><td class="tick"><?= $lv[$k] === $l['level_key'] ? '(✓)' : '' ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
  </table>
  <div class="note">* कृपया प्रत्येक क्षमतेचा कामगिरी स्तर दर्शविण्यासाठी (✓) अशी खूण करा.</div>
  <div class="bar" style="text-align:left;margin-top:6px">निरीक्षणात्मक नोंदी :</div>
  <div class="box" style="min-height:34mm;border-top:0"><?php $nl = u_lines($r['teacher_notes_mr'] ?? ''); if ($nl): ?><ul style="margin:0;padding-left:16px"><?php foreach ($nl as $x) echo '<li>' . u_e($x) . '</li>'; ?></ul><?php endif; ?></div>
  <div class="bar" style="text-align:left;margin-top:8px">अध्ययनार्थीने कशी कामगिरी केली याबाबत विचार करा.</div>
  <table class="f" style="margin-top:0">
    <tr><th style="background:var(--leaf);text-align:left">अध्ययनार्थीला कोणत्या आव्हानांना तोंड द्यावे लागले?</th><th style="background:var(--leaf);text-align:left">अध्ययनार्थीने त्यावर कशी मात केली? तुम्ही अध्ययनार्थीला कशी मदत केली?</th></tr>
    <tr><td style="height:30mm"><?= nl2br(u_e($r['challenges_mr'] ?? '')) ?></td><td><?= nl2br(u_e($r['support_mr'] ?? '')) ?></td></tr>
  </table>
  <div class="bar" style="margin-top:8px">स्वयं-मूल्यांकन <span class="small">(प्रत्येक विधानासाठी इमोजी रंगवा.)</span></div>
  <table class="sm">
    <?php $sr = u_j($r['self_reflection'] ?? ''); foreach (hpc_statements('preparatory', 'self') as $i => $s): $v = $sr[(string) ($i + 1)] ?? ''; ?>
    <tr><td><?= u_e($s['text_mr']) ?></td><td class="opts"><div class="row"><?php foreach (['yes', 'no', 'unsure'] as $o): ?><div class="o <?= $v === $o ? 'sel' : '' ?>"><?= u_smile($o, $v === $o) ?><br><?= u_smile_lbl($o) ?></div><?php endforeach; ?></div></td></tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="bar">विद्यार्थ्यांचे आत्मचिंतन</div>
  <div class="small" style="margin:3px 0">तू केलेल्या कृतीतील अनुभवावर आधारित, योग्य प्रतिसादास <span style="border:1.2px solid var(--pink);border-radius:50%;padding:0 3px">गोल</span> कर.</div>
  <table class="sm">
    <?php $sr = u_j($r['self_reflection'] ?? ''); foreach (hpc_statements('middle', 'self') as $i => $s): $v = $sr[(string) ($i + 1)] ?? ''; ?>
    <tr><td><?= u_e($s['text_mr']) ?></td><td class="opts"><div class="row"><?php foreach (['yes', 'some', 'no', 'unsure'] as $o): ?><div class="o <?= $v === $o ? 'sel' : '' ?>"><?= u_smile($o, $v === $o) ?><br><?= u_smile_lbl($o) ?></div><?php endforeach; ?></div></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="bar" style="margin-top:6px">माझ्या प्रगतीचा आराखडा</div>
  <div class="small" style="margin:3px 0">खाली काही विधाने दिली आहेत. प्रत्येक विधान काळजीपूर्वक वाचा. कृतीसंदर्भात तुमच्या कामगिरीचा दर्जा लक्षात घेऊन योग्य विधानाभोवती गोल करा.</div>
  <?php $pc = hpc_statements('middle', 'self_progress'); $sp = u_j($r['self_progress'] ?? ''); $cnt = u_j($r['self_progress_count'] ?? '');
    $byAb = []; foreach ($pc as $s) $byAb[$s['ability_key']][] = $s['text_mr']; ?>
  <table class="pc">
    <tr><?php foreach ($ABIL as $i => $ab): ?><th colspan="2" style="background:<?= ['var(--ab1)', 'var(--ab2)', 'var(--ab3)'][$i] ?>"><?= u_e($ab['name_mr']) ?></th><?php endforeach; ?></tr>
    <?php for ($row = 0; $row < 3; $row++): ?><tr>
      <?php foreach ($ABIL as $ab): $k = $ab['ability_key']; for ($c = 0; $c < 2; $c++): $idx = $row * 2 + $c; $on = !empty($sp[$k][$idx]); ?>
        <td class="<?= $on ? 'on' : '' ?>"><?= u_e($byAb[$k][$idx] ?? '') ?></td>
      <?php endfor; endforeach; ?></tr><?php endfor; ?>
    <tr><td colspan="6" class="bar" style="border:1px solid var(--pink)">(केवळ शिक्षकांच्या वापरासाठी)</td></tr>
    <tr><?php foreach ($ABIL as $ab): $k = $ab['ability_key']; $n = $cnt[$k] ?? (isset($sp[$k]) ? count(array_filter((array) $sp[$k])) : ''); ?>
      <td colspan="2" class="cnt"><?= u_e($ab['name_mr']) ?> स्तंभात गोल केलेल्या विधानांची संख्या : <b><?= $n !== '' ? u_mr($n) : '____' ?></b></td><?php endforeach; ?></tr>
  </table>
  <?php endif; ?>
  <?php $foot(); ?>
</div>

<?php if ($mid): ?>
<!-- ---- ब-३ (middle) : माझे शिकणे + सहाध्यायी अभिप्राय ---- -->
<div class="page">
  <?php $head(u_e($S['name_mr']) . ' · ' . $termMr); ?>
  <div class="bar">माझे शिकणे</div>
  <div style="border:1px solid var(--pink);border-top:0;padding:6px 8px">
    <div class="kv"><b style="min-width:auto">या कृतीच्या माध्यमातून मी हे शिकले की,</b><span class="ln"><?= u_e($r['my_learning_mr'] ?? '') ?></span></div>
    <div class="ln"></div>
    <div class="kv"><b style="min-width:auto">या कृतीत मला सर्वांत आवडलेली बाब</b><span class="ln"><?= u_e($r['liked_most_mr'] ?? '') ?></span></div>
    <div class="kv"><b style="min-width:auto">मला</b><span class="ln"><?= u_e($r['need_practice_mr'] ?? '') ?></span><b style="min-width:auto">या सरावाची गरज आहे.</b></div>
    <div class="kv"><b style="min-width:auto">मला</b><span class="ln"><?= u_e($r['need_help_mr'] ?? '') ?></span><b style="min-width:auto">यांच्या मदतीची गरज आहे.</b></div>
  </div>
  <div class="bar" style="margin-top:8px">सहाध्यायी अभिप्राय</div>
  <div style="border:1px solid var(--pink);border-top:0;padding:4px 8px" class="small">माझे नाव <b><?= u_e($sname) ?></b> आहे. &nbsp; माझ्या सहाध्यायीचे नाव <b><?= u_ph($r['peer_name'] ?? '', '______________') ?></b> आहे.<br>तुमच्या सहाध्यायीच्या कृतीतील सहभागाच्या निरीक्षणावरून योग्य पर्यायाभोवती गोल कर.</div>
  <table class="sm">
    <?php $pr = u_j($r['peer_reflection'] ?? ''); foreach (hpc_statements('middle', 'peer') as $i => $s): $v = $pr[(string) ($i + 1)] ?? ''; ?>
    <tr><td><?= u_e($s['text_mr']) ?></td><td class="opts"><div class="row"><?php foreach (['yes', 'some', 'no', 'unsure'] as $o): ?><div class="o <?= $v === $o ? 'sel' : '' ?>"><?= u_smile($o, $v === $o) ?><br><?= u_smile_lbl($o) ?></div><?php endforeach; ?></div></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="bar" style="margin-top:6px">माझ्या सहाध्यायीच्या प्रगतीचा आराखडा</div>
  <?php $pp = hpc_statements('middle', 'peer_progress'); $sp = u_j($r['peer_progress'] ?? ''); $cnt = u_j($r['peer_progress_count'] ?? '');
    $byAb = []; foreach ($pp as $s) $byAb[$s['ability_key']][] = $s['text_mr']; ?>
  <table class="pc">
    <tr><?php foreach ($ABIL as $i => $ab): ?><th colspan="2" style="background:<?= ['var(--ab1)', 'var(--ab2)', 'var(--ab3)'][$i] ?>"><?= u_e($ab['name_mr']) ?></th><?php endforeach; ?></tr>
    <?php for ($row = 0; $row < 3; $row++): ?><tr>
      <?php foreach ($ABIL as $ab): $k = $ab['ability_key']; for ($c = 0; $c < 2; $c++): $idx = $row * 2 + $c; $on = !empty($sp[$k][$idx]); ?>
        <td class="<?= $on ? 'on' : '' ?>"><?= u_e($byAb[$k][$idx] ?? '') ?></td>
      <?php endfor; endforeach; ?></tr><?php endfor; ?>
    <tr><td colspan="6" style="text-align:left;font-size:10px;height:auto;padding:5px 8px">माझ्या सहाध्यायीस <u>&nbsp;<?= u_e($r['peer_need_practice_mr'] ?? '') ?>&nbsp;</u> या सरावाची गरज आहे. &nbsp;&nbsp; माझ्या सहाध्यायीस <u>&nbsp;<?= u_e($r['peer_need_help_mr'] ?? '') ?>&nbsp;</u> यांच्या मदतीची गरज आहे.</td></tr>
    <tr><td colspan="6" class="bar" style="border:1px solid var(--pink)">(केवळ शिक्षकांच्या वापरासाठी)</td></tr>
    <tr><?php foreach ($ABIL as $ab): $k = $ab['ability_key']; $n = $cnt[$k] ?? (isset($sp[$k]) ? count(array_filter((array) $sp[$k])) : ''); ?>
      <td colspan="2" class="cnt"><?= u_e($ab['name_mr']) ?> स्तंभात गोल केलेल्या विधानांची संख्या : <b><?= $n !== '' ? u_mr($n) : '____' ?></b></td><?php endforeach; ?></tr>
  </table>
  <?php $foot(); ?>
</div>

<!-- ---- ब-४ (middle) : समग्र प्रगती सारांश, प्रगतिचक्र, बलस्थाने/अडथळे ---- -->
<div class="page">
  <?php $head(u_e($S['name_mr']) . ' · ' . $termMr); ?>
  <div class="bar">समग्र प्रगती सारांश कसा विकसित करावा? (शिक्षकांसाठी)</div>
  <div style="display:flex;gap:8px;border:1px solid var(--pink);border-top:0;padding:5px 8px;background:#fffafd">
    <div class="small" style="flex:1">विद्यार्थी आत्मचिंतन आणि सहाध्यायी अभिप्रायाच्या आधारे प्रत्येक जाणीवजागृती, संवेदनशीलता, सर्जनशीलता या क्षमतांमधील कामगिरी दर्शविण्यासाठी केलेल्या गोलांची संख्या मोजा. दिलेल्या तक्त्याप्रमाणे कामगिरीसाठी केलेल्या गुणांकनावरून स्तर ठरवायचा आहे. विद्यार्थ्याच्या आत्मचिंतनातील गुणांकन व सहाध्यायी अभिप्रायाच्या गुणांकनावरून पुढील प्रगतिचक्र रेखांकित करावयाचे आहे.</div>
    <div style="border:1px solid var(--pink);padding:4px 8px;font-size:10.5px;white-space:nowrap"><b>गुणांकन योजना :</b><br><?php foreach ($LVLS as $l): ?><?= u_e($l['name_mr']) ?> – <?= u_mr($l['score_min'] ?? '') ?> ते <?= u_mr($l['score_max'] ?? '') ?><br><?php endforeach; ?></div>
  </div>
  <div class="bar" style="margin-top:6px">शिक्षक अभिप्राय</div>
  <div style="display:flex;gap:10px;align-items:center;border:1px solid var(--pink);border-top:0;padding:6px">
    <?php 
      $W = u_j($r['wheel_json'] ?? '');
      foreach ($ABIL as $ab) { 
          $k = $ab['ability_key']; 
          $tLev = !empty($W[$k]['teacher']) ? $W[$k]['teacher'] : (!empty($lv[$k]) ? $lv[$k] : 'pragat');
          $W[$k]['teacher'] = $tLev;
          if (empty($W[$k]['student'])) { 
              $sc = u_j($r['self_progress_count'] ?? ''); 
              $W[$k]['student'] = (isset($sc[$k]) && $sc[$k] !== '') ? hpc_level_from_count('middle', (int) $sc[$k]) : $tLev; 
          }
          if (empty($W[$k]['peer'])) { 
              $pcnt = u_j($r['peer_progress_count'] ?? ''); 
              $W[$k]['peer'] = (isset($pcnt[$k]) && $pcnt[$k] !== '') ? hpc_level_from_count('middle', (int) $pcnt[$k]) : $tLev; 
          } 
      } 
    ?>
    <div><?= u_wheel($W, $ABIL, $LVLS, 235) ?></div>
    <div style="flex:1">
      <div style="font-weight:700;color:var(--pink);text-align:center;margin-bottom:4px">विद्यार्थी प्रगतिचक्र / मूल्यांकन चक्र</div>
      <table class="f tight"><tr><th>क्षमता</th><th>शिक्षक मूल्यांकन</th><th>विद्यार्थी आत्मचिंतन</th><th>सहाध्यायी अभिप्राय</th></tr>
        <?php foreach ($ABIL as $ab): $k = $ab['ability_key']; ?><tr><td class="k2"><?= u_e($ab['name_mr']) ?></td><td style="text-align:center"><?= u_e($lvName[$W[$k]['teacher'] ?? ''] ?? '—') ?></td><td style="text-align:center"><?= u_e($lvName[$W[$k]['student'] ?? ''] ?? '—') ?></td><td style="text-align:center"><?= u_e($lvName[$W[$k]['peer'] ?? ''] ?? '—') ?></td></tr><?php endforeach; ?></table>
      <div class="note" style="margin-top:4px">चक्रामध्ये प्रत्येक क्षमतेअंतर्गत तीन भाग आहेत – शिक्षक, विद्यार्थी व सहाध्यायी मूल्यांकन. कामगिरीचा स्तर केंद्रापासून परिघापर्यंत B (प्रारंभिक) → P (प्रवीण) → A (प्रगत) असा वाढत जातो.</div>
    </div>
  </div>
  <div class="three" style="margin-top:6px;gap:0">
    <?php $st = u_j($r['strengths'] ?? ''); $hu = u_j($r['hurdles'] ?? ''); ?>
    <div class="box chk"><h5>बलस्थान क्षेत्र <span class="small">(योग्य ठिकाणी ✓ करा.)</span></h5>
      <?php foreach (hpc_statements('middle', 'strength') as $s): if (trim($s['text_mr']) === 'इतर') continue; $k = $s['option_set'] ?? $s['text_mr']; ?><div><?= u_box(in_array($k, $st, true)) ?><?= u_e($s['text_mr']) ?></div><?php endforeach; ?>
      <div><?= u_box(!empty($st['other'])) ?>इतर <u><?= u_e($st['other'] ?? '') ?></u></div></div>
    <div class="box chk" style="border-left:0"><h5>यशस्वी होण्यातील अडथळे <span class="small">(योग्य ठिकाणी ✓ करा.)</span></h5>
      <?php foreach (hpc_statements('middle', 'hurdle') as $s): if (trim($s['text_mr']) === 'इतर') continue; $k = $s['option_set'] ?? $s['text_mr']; ?><div><?= u_box(in_array($k, $hu, true)) ?><?= u_e($s['text_mr']) ?></div><?php endforeach; ?>
      <div><?= u_box(!empty($hu['other'])) ?>इतर <u><?= u_e($hu['other'] ?? '') ?></u></div></div>
    <div class="box" style="border-left:0"><h5>विद्यार्थ्याच्या प्रगतीत वाढ होण्यासाठी मी मदत करू शकतो का?</h5><?= nl2br(u_e($r['teacher_help_mr'] ?? '')) ?><div class="lines"><div></div><div></div><div></div></div></div>
  </div>
  <div class="box" style="border-top:0;min-height:26mm"><h5>शिक्षकांचे निरीक्षण आणि शिफारशी :</h5><?= nl2br(u_e($r['teacher_observation_mr'] ?? '')) ?></div>
  <?php $foot(); ?>
</div>
<?php else: ?>
<!-- ---- ब-३ (prep) : सामर्थ्य / सुधारणा + पालक निरीक्षण ---- -->
<div class="page">
  <?php $head(u_e($S['name_mr']) . ' · ' . $termMr); ?>
  <div class="bar">कामगिरी — सामर्थ्य व सुधारणेची क्षेत्रे</div>
  <div class="two" style="gap:0">
    <?php $st = u_j($r['strengths'] ?? ''); $hu = u_j($r['hurdles'] ?? ''); ?>
    <div class="box chk" style="border-top:0;min-height:40mm"><h5>बलस्थान क्षेत्र <span class="small">(योग्य ठिकाणी ✓ करा.)</span></h5>
      <?php foreach (hpc_statements('preparatory', 'strength') as $s): if (trim($s['text_mr']) === 'इतर') continue; $k = $s['option_set'] ?? $s['text_mr']; ?><div><?= u_box(in_array($k, $st, true)) ?><?= u_e($s['text_mr']) ?></div><?php endforeach; ?>
      <div><?= u_box(!empty($st['other'])) ?>इतर <u><?= u_e($st['other'] ?? '') ?></u></div></div>
    <div class="box chk" style="border-top:0;border-left:0;min-height:40mm"><h5>यशस्वी होण्यातील अडथळे <span class="small">(योग्य ठिकाणी ✓ करा.)</span></h5>
      <?php foreach (hpc_statements('preparatory', 'hurdle') as $s): if (trim($s['text_mr']) === 'इतर') continue; $k = $s['option_set'] ?? $s['text_mr']; ?><div><?= u_box(in_array($k, $hu, true)) ?><?= u_e($s['text_mr']) ?></div><?php endforeach; ?>
      <div><?= u_box(!empty($hu['other'])) ?>इतर <u><?= u_e($hu['other'] ?? '') ?></u></div></div>
  </div>
  <div class="bar" style="margin-top:8px">शिक्षकांचे निरीक्षण आणि शिफारशी</div>
  <div class="box" style="border-top:0;min-height:34mm"><?= nl2br(u_e($r['teacher_observation_mr'] ?? '')) ?></div>
  <div class="bar" style="margin-top:8px">पालकांचे निरीक्षण <span class="small">(घरातील निरीक्षणावर आधारित)</span></div>
  <div class="box" style="border-top:0;min-height:30mm"><?= nl2br(u_e($r['teacher_help_mr'] ?? '')) ?><div class="lines"><div></div><div></div><div></div></div></div>
  <div class="sig"><div><span>पालकांची सही</span></div><div><span>विषय शिक्षक</span></div><div><span>वर्गशिक्षक</span></div></div>
  <?php $foot(); ?>
</div>
<?php endif; ?>
<?php endforeach; endforeach; ?>

<!-- ============ भाग क : शैक्षणिक वर्षाचा सारांश ============ -->
<?php $per = $COMPACT ? 12 : 3;
$chunks = array_chunk($subjects, $per); foreach ($chunks as $ci => $chunk): ?>
<div class="page<?= $COMPACT ? ' cpt' : '' ?>">
  <?php if ($ci === 0): ?><div class="ttl-bar">भाग – क</div><div style="text-align:center;font-weight:700;font-size:14px">शैक्षणिक वर्षाचा सारांश</div>
  <div class="small" style="margin:3px 0 8px">वर्षभरातील विद्यार्थ्याच्या संपादनाचा आढावा घेत, प्रत्येक क्षमतेअंतर्गत योग्य त्या दर्शकावर (✓) अशी खूण करावी. निरीक्षणात आढळलेल्या बाबींचे वर्णन लिहावे.</div>
  <?php else: $head('भाग – क (पुढे)'); endif; ?>
  <div class="sumgrid">
  <?php foreach ($chunk as $S): $sm = $C['summary'][(int) $S['id']] ?? []; ?>
  <div class="sumblk">
    <div class="h"><div class="nm"><?= u_e($S['name_mr']) ?> <span style="font-family:Arial;font-weight:400;font-size:11px;margin-left:6px">(<?= u_e($S['subject_key']) ?>)</span></div>
      <div class="gg"><div class="cap">कामगिरी स्तर दर्शके</div>
        <div class="g3"><?php foreach ($LVLS as $i => $l): $any = in_array($l['level_key'], [$sm['level_awareness'] ?? '', $sm['level_sensitivity'] ?? '', $sm['level_creativity'] ?? ''], true); ?><div><?= u_gauge($i) ?><br><?= u_e($l['name_mr']) ?></div><?php endforeach; ?></div></div></div>
    <table>
      <?php foreach ($ABIL as $i => $ab): $k = $ab['ability_key']; $cur = $sm['level_' . $k] ?? ''; ?>
      <tr><?php if ($i === 0): ?><td class="k0" rowspan="3">क्षमता</td><?php endif; ?><td class="k"><?= u_e($ab['name_mr']) ?></td>
        <?php foreach ($LVLS as $l): ?><td><?= u_box($cur === $l['level_key']) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
    </table>
    <div class="rem"><?= nl2br(u_e($sm['remark_mr'] ?? '')) ?></div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php $foot(); ?>
</div>
<?php endforeach; ?>

<!-- ============ परिशिष्ट – १ : क्रेडिट्स (NCrF) ============ -->
<?php $CR = hpc_credit_std($std); ?>
<div class="page"><?= $corners ?>
  <div class="ttl-bar">परिशिष्ट – १</div>
  <div style="font-size:10.5px"><b><?= u_e(hpc_stage_name_mr($stage)) ?>ावरील समग्र प्रगतिपत्रक (HPC) हे राष्ट्रीय क्रेडिट फ्रेमवर्क (NCrF) शी सुसंगत आहे.</b><br>
    राष्ट्रीय क्रेडिट फ्रेमवर्क (NCrF) च्या तत्त्वानुसार एकूण क्रेडिट भारांशांपैकी ७०% भारांश हा आकारिक मूल्यमापनासाठी दिलेला आहे आणि तो समग्र प्रगतिपत्रक (HPC) द्वारे प्राप्त करता येतो.</div>
  <?php if ($CR): ?>
  <div style="font-weight:700;margin:8px 0 2px">एचपीसीद्वारे मिळवलेले क्रेडिट पॉईंट्स</div>
  <table class="cr">
    <tr><th>स्तर / टप्पा</th><th>इयत्ता / प्रतिवर्ष क्रेडिट्स</th><th>एच पी सी पूर्ण करून मिळविलेले क्रेडिट्स (७०%)</th><th>राष्ट्रीय क्रेडिट फ्रेमवर्क स्तर</th><th>एकूण क्रेडिट पॉईंट्स</th><th>एचपीसी क्रेडिट पॉईंट्स (७०%)</th></tr>
    <tr><td><?= u_e(hpc_stage_name_mr($stage)) ?><br><span class="small"><?= u_mr($CR['hours_per_year']) ?> तास प्रतिवर्ष</span></td><td>इयत्ता <?= u_e($stdMr) ?><br><b><?= u_mr($CR['credits_per_year']) ?></b></td><td><?= u_mr($CR['hpc_credits_70pct']) ?></td><td><?= u_mr($CR['ncrf_level']) ?></td><td><?= u_mr($CR['total_credit_points']) ?></td><td><?= u_mr($CR['hpc_credit_points']) ?></td></tr>
  </table>
  <div class="note" style="margin-top:4px">टीप : शालेय शिक्षणाच्या प्रत्येक वर्षासाठी एकूण क्रेडिट्स सर्व शैक्षणिक मानकांमध्ये समान पद्धतीने वितरित केले पाहिजेत.</div>
  <table class="cr" style="margin-top:10px">
    <tr><th class="t" colspan="6">इयत्ता <?= u_e($stdMr) ?></th></tr>
    <tr><th>अ. क्र.</th><th>अध्ययन मानके<br><span class="small">स्तर १</span></th><th>समग्र प्रगतिपत्रक पूर्ण करून मिळवलेले क्रेडिट्स (७०%)<br><span class="small">स्तर २</span></th><th>राष्ट्रीय क्रेडिट फ्रेमवर्कची पातळी<br><span class="small">स्तर ३</span></th><th>राष्ट्रीय क्रेडिट पॉईंट्स<br><span class="small">स्तर ४ (स्तंभ २ × स्तंभ ३)</span></th><th>विद्यार्थ्याने मिळवलेले क्रेडिट पॉईंट्स<br><span class="small">स्तर ५</span></th></tr>
    <?php foreach ($CR['subjects'] as $i => $cs): $sid = null; foreach ($subjects as $S) if ($S['subject_key'] === $cs['subject_key']) $sid = (int) $S['id']; $earned = $C['summary'][$sid]['credit_points_earned'] ?? ''; ?>
    <tr><td><?= u_mr($i + 1) ?>.</td><td class="l"><?= u_e($cs['name_mr']) ?> <span style="font-family:Arial;font-size:9px">(<?= u_e($cs['subject_key']) ?>)</span></td><td><?= u_mr($cs['hpc_credits']) ?></td><td><?= u_mr($cs['ncrf_level']) ?></td><td><?= u_mr($cs['credit_points']) ?></td><td><?= $earned !== '' ? u_mr($earned) : '' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><div class="ph">क्रेडिट तक्ता उपलब्ध नाही.</div><?php endif; ?>
  <div class="bar" style="margin-top:12px">वार्षिक अभिप्राय</div>
  <div class="box" style="border-top:0;min-height:28mm"><?= nl2br(u_e($d['final_annual_feedback'] ?? '')) ?></div>
  <div class="sig"><div><span>वर्गशिक्षक</span></div><div><span>मुख्याध्यापक (सही व शिक्का)</span></div><div><span>पालक</span></div></div>
  <?php $foot(); ?>
</div>

<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/anti_copy.php'; ?>
</body>
</html>
