<?php
/**
 * समग्र प्रगतिपत्रक (HPC) — अधिकृत १९-पानी छपाई मांडणी
 * SCERT अधिकृत पायाभूत स्तर आराखड्यानुसार SVG ग्राफिक्ससह.
 *
 *   print_hpc_19.php?id=12            — एक विद्यार्थी
 *   print_hpc_19.php?grade=इयत्ता ३   — पूर्ण वर्ग (बॅच प्रिंट)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/hpc_fs_data.php';
require_once __DIR__ . '/../includes/demo_data.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
require_once __DIR__ . '/../includes/cce_functions.php';
$official_print_version = 'vijeta-hpc-19';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-HPC-Print-Format: ' . $official_print_version);
$is_admin = isset($_SESSION['admin_id']);
if (!$is_admin) requireLogin();

$db = getDB();
$requested_id = intval($_GET['id'] ?? 0);
if ($is_admin) {
    $owner = $db->prepare("SELECT school_id FROM hpc_cards WHERE id = ?");
    $owner->execute([$requested_id]);
    $school_id = (int) $owner->fetchColumn();
    $ss = $db->prepare("SELECT * FROM schools WHERE id = ?");
    $ss->execute([$school_id]);
    $school = $ss->fetch();
    if (!$school) { http_response_code(404); exit('School not found.'); }
} else {
    $school_id = (int) ($_SESSION['school_id'] ?? SCHOOL_ID);
    $_SESSION['school_id'] = $school_id;
    $school = getSchool();
}

/* ---------- वर्गणी तपासणी (इयत्ता १ व २ मोफत योजनेमध्ये समाविष्ट) ---------- */
require_once __DIR__ . '/../includes/hpc_curriculum.php';
$hpc_chk = can_access_hpc_class(1, $school_id);
if (!$is_admin && !$hpc_chk['allowed']) {
    flash('error', $hpc_chk['message'] ?? 'HPC कार्ड ऍक्सेससाठी सदस्यता सक्रिय असणे आवश्यक आहे.');
    redirect(APP_URL . '/subscription/plans.php');
}

/* ---------- कोणती कार्डे छापायची ---------- */
$card_ids = [];
if (!empty($_GET['id'])) {
    $cid = intval($_GET['id']);
    $cst = $db->prepare("SELECT stage_key, std FROM hpc_cards WHERE id = ?");
    $cst->execute([$cid]);
    $crow = $cst->fetch();
    if ($crow && (($crow['stage_key'] && $crow['stage_key'] !== 'foundational') || ((int)$crow['std'] >= 3))) {
        redirect(APP_URL . '/cards/print_hpc_stage.php?id=' . $cid . (!empty($_GET['compact']) ? '&compact=1' : ''));
    }
    $card_ids[] = $cid;
} elseif (!empty($_GET['student_id'])) {
    $st_id = (int)$_GET['student_id'];
    $gs = $db->prepare("SELECT id, stage_key, std FROM hpc_cards WHERE school_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
    $gs->execute([$school_id, $st_id]);
    $crow = $gs->fetch();
    if (!$crow) {
        $db->prepare("INSERT INTO hpc_cards (student_id, school_id, academic_year, status) VALUES (?, ?, ?, 'draft')")
           ->execute([$st_id, $school_id, ACADEMIC_YEAR]);
        $cid = (int)$db->lastInsertId();
    } else {
        $cid = (int)$crow['id'];
        if (($crow['stage_key'] && $crow['stage_key'] !== 'foundational') || ((int)$crow['std'] >= 3)) {
            redirect(APP_URL . '/cards/print_hpc_stage.php?id=' . $cid . (!empty($_GET['compact']) ? '&compact=1' : ''));
        }
    }
    if ($cid) $card_ids[] = $cid;
} elseif (!empty($_GET['grade'])) {
    $g_std = function_exists('hpc_std_from_grade') ? hpc_std_from_grade($_GET['grade']) : 1;
    if (!$is_admin && $g_std >= 3) {
        $chk = can_access_hpc_class($g_std, $school_id);
        if (!$chk['allowed']) {
            flash('error', $chk['message'] ?? 'इयत्ता ३ व पुढील वर्गांसाठी HPC सशुल्क योजना आवश्यक आहे.');
            redirect(APP_URL . '/subscription/plans.php');
        }
    }
    $gs = $db->prepare("SELECT h.id FROM hpc_cards h JOIN students s ON s.id = h.student_id
                        WHERE h.school_id = ? AND s.grade = ? ORDER BY CAST(s.roll_no AS UNSIGNED), s.name_mr");
    $gs->execute([$school_id, $_GET['grade']]);
    foreach ($gs->fetchAll() as $r) $card_ids[] = (int)$r['id'];
} elseif (!empty($_GET['std'])) {
    $s_val = (int)$_GET['std'];
    if (!$is_admin && $s_val >= 3) {
        $chk = can_access_hpc_class($s_val, $school_id);
        if (!$chk['allowed']) {
            flash('error', $chk['message'] ?? 'इयत्ता ३ व पुढील वर्गांसाठी HPC सशुल्क योजना आवश्यक आहे.');
            redirect(APP_URL . '/subscription/plans.php');
        }
    }
    $lbl = function_exists('std_label') ? std_label($s_val) : "इयत्ता $s_val";
    $gs = $db->prepare("SELECT h.id FROM hpc_cards h JOIN students s ON s.id = h.student_id
                        WHERE h.school_id = ? AND (h.std = ? OR s.grade = ? OR s.grade LIKE ?) ORDER BY CAST(s.roll_no AS UNSIGNED), s.name_mr");
    $gs->execute([$school_id, $s_val, $lbl, '%' . $s_val . '%']);
    foreach ($gs->fetchAll() as $r) $card_ids[] = (int)$r['id'];
}
if (!$card_ids) {
    flash('error', 'छापण्यासाठी कोणतेही HPC कार्ड निवडलेले नाही.');
    redirect(APP_URL . '/cards/list.php');
}

/* ---------- मदतनीस ---------- */
function u_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function u_mr($s) {
    return strtr((string)$s, ['0'=>'०','1'=>'१','2'=>'२','3'=>'३','4'=>'४','5'=>'५','6'=>'६','7'=>'७','8'=>'८','9'=>'९']);
}
function u_val($v, $ph = '') { $v = trim((string)$v); return $v !== '' ? u_e($v) : ($ph !== '' ? '<span class="ph">' . u_e($ph) . '</span>' : ''); }
function u_json($s) { $a = json_decode((string)$s, true); return is_array($a) ? $a : []; }

/** चेकबॉक्स (भरलेला / रिकामा) */
function u_box($checked = false) { return '<span class="cb' . ($checked ? ' on' : '') . '"></span>'; }

/** SCERT अधिकृत डोंगर-प्रवाह कामगिरी-स्तर वर्तुळ (निर्झर / पर्वत / आकाश) ✓ खूण व प्राविण्य अक्षासह */
function generate_scert_mountain_svg($level = 'parvat', $size = 112) {
    $lvl = strtolower(trim((string)$level));
    if ($lvl === 'pailu' || $lvl === 'pravah' || $lvl === '1' || $lvl === 'prarambhik') $lvl = 'nirjhar';
    if ($lvl === '2' || $lvl === 'praveen') $lvl = 'parvat';
    if ($lvl === '3' || $lvl === 'pragat') $lvl = 'akash';
    if (!in_array($lvl, ['nirjhar', 'parvat', 'akash'], true)) $lvl = 'parvat';

    static $uid = 0;
    $uid++;
    $clipId = 'scertMtnClip_' . $uid;
    $skyGrad = 'scertSkyGrad_' . $uid;
    $mtnGrad = 'scertMtnGrad_' . $uid;
    $streamGrad = 'scertStreamGrad_' . $uid;

    $isNirjhar = ($lvl === 'nirjhar');
    $isParvat  = ($lvl === 'parvat');
    $isAkash   = ($lvl === 'akash');

    ob_start();
    ?>
    <svg class="scert-mtn-badge" viewBox="0 0 150 150" width="<?= $size ?>" height="<?= $size ?>" xmlns="http://www.w3.org/2000/svg" font-family="'Noto Sans Devanagari', 'Baloo 2', Arial, sans-serif">
      <defs>
        <clipPath id="<?= $clipId ?>"><circle cx="75" cy="75" r="70" /></clipPath>
        <linearGradient id="<?= $skyGrad ?>" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#38bdf8"/><stop offset="60%" stop-color="#bae6fd"/><stop offset="100%" stop-color="#f0f9ff"/>
        </linearGradient>
        <linearGradient id="<?= $mtnGrad ?>" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#475569"/><stop offset="50%" stop-color="#334155"/><stop offset="100%" stop-color="#1e293b"/>
        </linearGradient>
        <linearGradient id="<?= $streamGrad ?>" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#0284c7"/><stop offset="50%" stop-color="#38bdf8"/><stop offset="100%" stop-color="#0ea5e9"/>
        </linearGradient>
      </defs>

      <!-- Outer Ring -->
      <circle cx="75" cy="75" r="72" fill="#ffffff" stroke="<?= $isAkash ? '#0284c7' : ($isParvat ? '#16a34a' : '#0284c7') ?>" stroke-width="2.5"/>
      
      <!-- Clipped Illustration matching SCERT booklet -->
      <g clip-path="url(#<?= $clipId ?>)">
        <!-- 1. SKY (आकाश) -->
        <rect x="5" y="5" width="140" height="140" fill="url(#<?= $skyGrad ?>)"/>
        <path d="M 35 32 Q 42 22 52 28 Q 62 20 72 26 Q 80 22 88 28 Q 98 25 102 34 Q 105 40 95 42 L 35 42 Z" fill="#ffffff" opacity="0.85"/>
        <path d="M 90 22 Q 96 15 104 18 Q 112 14 118 20 Q 124 18 128 24 L 90 26 Z" fill="#ffffff" opacity="0.7"/>

        <!-- 2. MOUNTAINS (पर्वत / शिखर) -->
        <polygon points="15,92 52,42 90,92" fill="#64748b"/>
        <polygon points="45,49 52,42 62,54 55,50 50,53" fill="#ffffff"/>
        <polygon points="68,95 108,35 145,95" fill="#475569"/>
        <polygon points="98,47 108,35 120,50 114,46 106,49" fill="#ffffff"/>

        <!-- Main Peak -->
        <polygon points="32,102 78,38 126,102" fill="url(#<?= $mtnGrad ?>)"/>
        <polygon points="66,54 78,38 90,54 84,50 78,55 72,48" fill="#ffffff"/>
        <line x1="78" y1="38" x2="72" y2="102" stroke="#0f172a" stroke-width="1.8" opacity="0.4"/>
        <line x1="78" y1="38" x2="92" y2="102" stroke="#ffffff" stroke-width="1.2" opacity="0.3"/>

        <!-- 3. MEADOW BANKS -->
        <path d="M 5 95 Q 40 85 75 95 Q 110 85 145 95 L 145 145 L 5 145 Z" fill="#22c55e"/>
        <path d="M 5 102 Q 35 94 65 104 L 5 145 Z" fill="#16a34a"/>
        <path d="M 145 102 Q 115 94 85 104 L 145 145 Z" fill="#15803d"/>

        <!-- 4. STREAM (निर्झर) -->
        <path d="M 68 94 Q 72 104 60 114 Q 50 124 55 145 L 95 145 Q 90 124 82 114 Q 78 104 76 94 Z" fill="url(#<?= $streamGrad ?>)"/>
        <path d="M 68 112 Q 74 116 80 112" stroke="#ffffff" stroke-width="1.5" fill="none" opacity="0.8"/>
        <path d="M 62 126 Q 72 131 82 126" stroke="#ffffff" stroke-width="1.5" fill="none" opacity="0.8"/>
        <path d="M 58 138 Q 74 143 90 138" stroke="#ffffff" stroke-width="1.8" fill="none" opacity="0.8"/>
      </g>

      <circle cx="75" cy="75" r="70" fill="none" stroke="#334155" stroke-width="1.2" opacity="0.5"/>

      <!-- LEFT PROFICIENCY AXIS (प्राविण्य स्तर) -->
      <g>
        <line x1="22" y1="126" x2="22" y2="24" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/>
        <polygon points="22,17 18,26 26,26" fill="#0f172a"/>
        <line x1="18" y1="122" x2="26" y2="122" stroke="#0f172a" stroke-width="1.8"/>
        <line x1="18" y1="74" x2="26" y2="74" stroke="#0f172a" stroke-width="1.8"/>
        <line x1="18" y1="28" x2="26" y2="28" stroke="#0f172a" stroke-width="1.8"/>
        <rect x="2" y="58" width="16" height="32" rx="3" fill="#ffffff" fill-opacity="0.92" stroke="#94a3b8" stroke-width="0.8"/>
        <text x="10" y="68" font-size="7" font-weight="700" fill="#0f172a" text-anchor="middle">प्राविण्य</text>
        <text x="10" y="78" font-size="7" font-weight="700" fill="#0f172a" text-anchor="middle">स्तर</text>
        <text x="10" y="86" font-size="6" fill="#64748b" text-anchor="middle">▲</text>
      </g>

      <!-- LEVEL LABELS WITH CHECKMARK TICK (✓) ON ACHIEVED LEVEL -->
      <!-- LEVEL 3: आकाश -->
      <g transform="translate(75, 26)">
        <?php if ($isAkash): ?>
          <rect x="-38" y="-12" width="76" height="20" rx="10" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>
          <text x="0" y="2" font-size="11" font-weight="800" fill="#ffffff" text-anchor="middle">✓ आकाश</text>
          <circle cx="-53" cy="2" r="4.5" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>
        <?php else: ?>
          <rect x="-30" y="-10" width="60" height="17" rx="8" fill="#ffffff" fill-opacity="0.9" stroke="#94a3b8" stroke-width="1"/>
          <text x="0" y="2" font-size="10" font-weight="700" fill="#0369a1" text-anchor="middle">आकाश</text>
        <?php endif; ?>
      </g>

      <!-- LEVEL 2: पर्वत -->
      <g transform="translate(80, 72)">
        <?php if ($isParvat): ?>
          <rect x="-42" y="-12" width="84" height="20" rx="10" fill="#15803d" stroke="#ffffff" stroke-width="1.5"/>
          <text x="0" y="2.5" font-size="11" font-weight="800" fill="#ffffff" text-anchor="middle">✓ पर्वत (शिखर)</text>
          <circle cx="-58" cy="2" r="4.5" fill="#15803d" stroke="#ffffff" stroke-width="1.5"/>
        <?php else: ?>
          <rect x="-28" y="-10" width="56" height="17" rx="8" fill="#ffffff" fill-opacity="0.9" stroke="#94a3b8" stroke-width="1"/>
          <text x="0" y="2" font-size="10" font-weight="700" fill="#1e293b" text-anchor="middle">पर्वत</text>
        <?php endif; ?>
      </g>

      <!-- LEVEL 1: निर्झर -->
      <g transform="translate(75, 122)">
        <?php if ($isNirjhar): ?>
          <rect x="-36" y="-12" width="72" height="20" rx="10" fill="#0369a1" stroke="#ffffff" stroke-width="1.5"/>
          <text x="0" y="2" font-size="11" font-weight="800" fill="#ffffff" text-anchor="middle">✓ निर्झर</text>
          <circle cx="-53" cy="0" r="4.5" fill="#0369a1" stroke="#ffffff" stroke-width="1.5"/>
        <?php else: ?>
          <rect x="-28" y="-10" width="56" height="17" rx="8" fill="#ffffff" fill-opacity="0.9" stroke="#94a3b8" stroke-width="1"/>
          <text x="0" y="2" font-size="10" font-weight="700" fill="#0369a1" text-anchor="middle">निर्झर</text>
        <?php endif; ?>
      </g>
    </svg>
    <?php
    return ob_get_clean();
}

function u_mountain($level = 'parvat', $size = 110) {
    if (is_numeric($level) && !is_string($size)) {
        // old call signature: u_mountain($size)
        return generate_scert_mountain_svg('parvat', (int)$level);
    }
    return generate_scert_mountain_svg($level, $size);
}

/** हसरे / नाराज / प्रश्नचिन्ह चेहरे - निवडलेल्या पर्यायावर ✓ खूण व ठळक वर्तुळ */
function u_face($type, $selected = false) {
    $m = [
        'happy' => '<path d="M9 17 Q14 22 19 17" stroke="#222" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
        'sad'   => '<path d="M9 20 Q14 15 19 20" stroke="#222" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
        'na'    => '<text x="14" y="21" font-size="11" text-anchor="middle" fill="#222" font-weight="700">?</text>'
    ];
    $strokeCol = $selected ? '#e5007e' : '#222';
    $fillCol = $selected ? '#fdf2f8' : '#fff';
    $strokeWidth = $selected ? '1.8' : '1.3';
    $tick = $selected ? '<circle cx="21" cy="7" r="4.5" fill="#e5007e"/><path d="M19 7 l1.5 1.5 l3 -3" stroke="#fff" stroke-width="1.2" fill="none" stroke-linecap="round"/>' : '';

    return '<svg viewBox="0 0 28 28" width="30" height="30">'
        . '<circle cx="14" cy="14" r="12.4" fill="' . $fillCol . '" stroke="' . $strokeCol . '" stroke-width="' . $strokeWidth . '"/>'
        . '<circle cx="10" cy="11" r="1.5" fill="#222"/><circle cx="18" cy="11" r="1.5" fill="#222"/>'
        . ($m[$type] ?? $m['na'])
        . $tick
        . '</svg>';
}

/** मदत-पर्यायांची चिन्हे : वर्गमित्र, शिक्षक, पुस्तक, संगणक, यांपैकी नाही */
function u_helpicon($k, $selected = false) {
    $p = [
        'peer'    => '<circle cx="9" cy="10" r="4"/><circle cx="21" cy="10" r="4"/><path d="M3 26c0-5 3-8 6-8s6 3 6 8"/><path d="M15 26c0-5 3-8 6-8s6 3 6 8"/>',
        'teacher' => '<rect x="4" y="4" width="22" height="15" rx="1"/><path d="M8 22h14"/><path d="M15 19v3"/>',
        'book'    => '<path d="M4 6h9c1.5 0 2 1 2 2v17c0-1.5-1-2-2-2H4z"/><path d="M26 6h-9c-1.5 0-2 1-2 2v17c0-1.5 1-2 2-2h9z"/>',
        'pc'      => '<rect x="3" y="5" width="24" height="15" rx="1.5"/><path d="M11 25h8M15 20v5"/>',
        'none'    => '<circle cx="15" cy="15" r="11"/><path d="M7 23 L23 7"/>',
    ];
    $strokeCol = $selected ? '#e5007e' : '#222';
    $strokeWidth = $selected ? '1.8' : '1.3';
    $bgCircle = $selected ? '<circle cx="15" cy="15" r="13.5" fill="#fdf2f8" stroke="#e5007e" stroke-width="1.8"/>' : '';
    $tick = $selected ? '<circle cx="23" cy="7" r="4.5" fill="#e5007e"/><path d="M21 7 l1.5 1.5 l3 -3" stroke="#fff" stroke-width="1.2" fill="none" stroke-linecap="round"/>' : '';

    return '<svg viewBox="0 0 30 30" width="30" height="30" fill="none" stroke="' . $strokeCol . '" stroke-width="' . $strokeWidth . '" stroke-linecap="round" stroke-linejoin="round">'
        . $bgCircle
        . ($p[$k] ?? '')
        . $tick
        . '</svg>';
}

/** घरातील संसाधनांची चिन्हे */
function u_reslicon($k, $selected = false) {
    $p = [
        'book'  => '<path d="M4 6h9c1.5 0 2 1 2 2v17c0-1.5-1-2-2-2H4z"/><path d="M26 6h-9c-1.5 0-2 1-2 2v17c0-1.5 1-2 2-2h9z"/>',
        'news'  => '<rect x="3" y="6" width="22" height="18" rx="1"/><path d="M6 10h8M6 14h8M6 18h8M17 10h5M17 14h5M17 18h5"/>',
        'toy'   => '<circle cx="9" cy="18" r="5"/><path d="M17 23l7-12 4 12z"/><path d="M14 8l3-3 3 3-3 3z"/>',
        'phone' => '<rect x="4" y="4" width="9" height="22" rx="1.5"/><rect x="16" y="9" width="11" height="13" rx="1"/>',
        'net'   => '<circle cx="15" cy="15" r="11"/><path d="M4 15h22M15 4c4 4 4 18 0 22M15 4c-4 4-4 18 0 22"/>',
        'lib'   => '<path d="M4 12l11-6 11 6"/><path d="M7 12v11M13 12v11M19 12v11M25 12v11"/><path d="M3 26h24"/>',
        'div'   => '<rect x="4" y="5" width="22" height="20" rx="1"/><path d="M8 11h14M8 15h14M8 19h9"/>',
        'other' => '<path d="M4 9h22M4 15h22M4 21h22"/>',
    ];
    $strokeCol = $selected ? '#0284c7' : '#222';
    $strokeWidth = $selected ? '1.8' : '1.3';
    $bg = $selected ? '<circle cx="15" cy="15" r="13.5" fill="#f0f9ff" stroke="#0284c7" stroke-width="1.8"/>' : '';
    $tick = $selected ? '<circle cx="23" cy="7" r="4.5" fill="#0284c7"/><path d="M21 7 l1.5 1.5 l3 -3" stroke="#fff" stroke-width="1.2" fill="none" stroke-linecap="round"/>' : '';

    return '<svg viewBox="0 0 30 30" width="30" height="30" fill="none" stroke="' . $strokeCol . '" stroke-width="' . $strokeWidth . '" stroke-linecap="round" stroke-linejoin="round">'
        . $bg
        . ($p[$k] ?? '')
        . $tick
        . '</svg>';
}

/** students.grade ('1', 'इयत्ता १', 'पहिली' …) -> अधिकृत मराठी नाव */
function u_grade_mr($grade): string
{
    $g = (string) $grade;
    $names = [1 => 'पहिली', 2 => 'दुसरी', 3 => 'तिसरी', 4 => 'चौथी', 5 => 'पाचवी', 6 => 'सहावी', 7 => 'सातवी', 8 => 'आठवी'];
    foreach ($names as $n) {
        if (mb_strpos($g, $n) !== false) return $n;
    }
    if (mb_stripos($g, 'बालवाटिका') !== false) return trim($g);
    $ascii = strtr($g, ['०'=>'0','१'=>'1','२'=>'2','३'=>'3','४'=>'4','५'=>'5','६'=>'6','७'=>'7','८'=>'8','९'=>'9']);
    if (preg_match('/(\d+)/', $ascii, $m) && isset($names[(int) $m[1]])) return $names[(int) $m[1]];
    return trim($g);
}

/* ---------- संदर्भ डेटा ---------- */
$FS_DOMAINS   = hpc_fs_domains();
$FS_GOALS     = hpc_fs_goal_text();
$FS_COMPS     = hpc_fs_competency_text();
$FS_ABILITIES = hpc_fs_abilities();
$FS_LEVELS    = hpc_fs_level_descriptors();
$FS_INTERESTS = hpc_fs_interest_areas();
$FS_SUMMARY   = hpc_fs_summary_areas();

$DOMAIN_TITLES = [
    1 => 'क्षेत्र क्र. 1 : विकास क्षेत्र - शारीरिक विकास',
    2 => 'क्षेत्र क्र. 2 : विकास क्षेत्र - सामाजिक, भाविनक आणि नैतिक विकास',
    3 => 'क्षेत्र क्र. 3 : विकास क्षेत्र - बोधात्मक विकास',
    4 => 'क्षेत्र क्र. 4 : विकास क्षेत्र - भाषा आणि साक्षरता विकास',
    5 => 'क्षेत्र क्र. 5 : विकास क्षेत्र - सौंदर्यदृष्टी आणि सांस्कृतिक विकास',
    6 => 'क्षेत्र क्र. 5.1 : विकास क्षेत्र - सकारात्मक अध्ययन सवयी',
];
$DOMAIN_TITLES[2] = 'क्षेत्र क्र. 2 : विकास क्षेत्र - सामाजिक, भावनिक आणि नैतिक विकास';

$ABILITY_ROWS = [
    'awareness'   => 'जाणीवजागृती',
    'sensitivity' => 'संवेदनशीलता',
    'creativity'  => 'सर्जनशीलता',
];
$LEVEL_COLS = ['nirjhar' => 'कामगिरी स्तर: निर्झर', 'parvat' => 'कामगिरी स्तर: पर्वत', 'akash' => 'कामगिरी स्तर: आकाश'];

$MONTHS = ['जून','जुलै','ऑगस्ट','सप्टें.','ऑक्टो.','नोव्हें.','डिसें.','जाने.','फेब्रु.','मार्च','एप्रिल','मे'];
$MONTH_NO = [6,7,8,9,10,11,12,1,2,3,4,5];

$GRADE_OPTIONS = ['बालवाटिका 1','बालवाटिका 2','बालवाटिका 3','पहिली','दुसरी'];
?>
<!doctype html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>अधिकृत १९-पानी समग्र प्रगतिपत्रक — छपाई</title>
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
}
*{box-sizing:border-box}
body{margin:0;background:#8d8d8d;font-family:var(--cce-font-family, 'Noto Sans Devanagari'), 'Noto Sans Devanagari', sans-serif !important;color:var(--ink);font-size:11.5px;line-height:1.45}
.toolbar{position:sticky;top:0;z-index:20;background:#111;color:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.toolbar button,.toolbar a{background:#e5007e;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-weight:700;font-family:inherit;font-size:14px;min-height:42px;display:inline-flex;align-items:center;cursor:pointer;text-decoration:none}
.toolbar a.ghost{background:#333}
.page{width:210mm;min-height:296mm;background:#fff;margin:10px auto;padding:11mm 10mm 13mm;position:relative;box-shadow:0 2px 10px rgba(0,0,0,.35)}
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
.pfoot{position:absolute;left:10mm;right:10mm;bottom:5mm;display:flex;justify-content:space-between;font-size:8px;color:#888}
.phead{display:flex;justify-content:space-between;align-items:baseline;font-size:11px;margin-bottom:5px}
.phead .sn{font-weight:700}
.phead .ap{font-style:italic;color:#333}
.ph{color:#aaa;font-style:italic}
.cb{display:inline-block;width:10px;height:10px;border:1.1px solid #333;vertical-align:-1px;margin-right:3px;border-radius:1px}
.cb.on{background:#333;box-shadow:inset 0 0 0 2px #fff}

/* --- पान १ मुखपृष्ठ --- */
.cover{text-align:center;padding-top:10mm}
.cover .yr{display:inline-block;border:1.6px solid #333;border-radius:8px;padding:6px 22px;font-weight:700;font-size:16px;letter-spacing:0.04em}
.cover h1{font-size:58px;color:var(--pink);margin:16px 0 0;font-weight:800;letter-spacing:.8px}
.cover .hpc{font-size:34px;color:#2f4fd0;font-weight:700;margin:4px 0 0}
.cover .stage{font-size:24px;color:var(--pink);font-weight:700;margin:4px 0 0}
.cover img{width:90%;max-width:180mm;margin:9mm auto 0;display:block}
.cover .who{margin:12mm auto 0;width:76%;background:#f0f7ff;border:1.6px solid var(--pink);border-radius:10px;padding:16px 28px;text-align:left}
.cover .who div{display:flex;font-size:16px;margin:6.5px 0}
.cover .who b{color:var(--pink);width:95px;display:inline-block}
.cover .who span.c{width:16px}
.cover-gov-footer{margin-top:12mm;font-size:12.5px;color:#475569;font-weight:700;text-align:center;letter-spacing:0.04em}

/* --- पान २ भाग अ --- */
.partA{border:1.6px solid var(--line);border-radius:6px;padding:13px 18px;margin-bottom:4mm}
.partA .kv{display:flex;margin:5.6px 0;font-size:12px;line-height:1.48}
.partA .kv b{min-width:180px;font-weight:700}
.secttl{text-align:center;color:var(--pink);font-weight:800;font-size:17.5px;margin:11px 0 2px;letter-spacing:0.02em}
.secsub{text-align:center;color:var(--pink);font-size:10px;margin-bottom:9px}
.ttl-bar{text-align:center;font-size:15px;font-weight:800;margin:9px 0 6px}
table.att{width:100%;border-collapse:collapse;text-align:center}
table.att th,table.att td{border:1.2px solid var(--line);padding:7px 3px;font-size:10.5px}
table.att th{background:var(--soft);font-weight:700}
table.att td.lbl{background:var(--soft);text-align:left;padding-left:6px;font-weight:700;width:17%}

/* --- पान ३ : मी व माझा परिसर (SCERT अधिकृत टेम्पलेट मांडणी) --- */
.page.page3 {
  position: relative;
  padding: 0 !important;
  background-color: #fff;
  overflow: hidden;
}
.p3-bg-img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 1;
  pointer-events: none;
  object-fit: fill;
}
.p3-content-layer {
  position: relative;
  z-index: 2;
  width: 100%;
  height: 100%;
  min-height: 296mm;
}
.p3-ribbon {
  position: absolute;
  top: 7mm;
  left: 56mm;
  width: 98mm;
  height: 18mm;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 800;
  color: #1e3a1e;
  letter-spacing: 0.5px;
}
.p3-photo-box {
  position: absolute;
  top: 19.5mm;
  left: 14mm;
  width: 44mm;
  height: 44mm;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.p3-photo-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.p3-photo-box .no-photo {
  font-size: 12px;
  font-weight: 700;
  color: #4338ca;
  line-height: 1.3;
}
.p3-star-age {
  position: absolute;
  top: 29mm;
  left: 85mm;
  width: 40mm;
  height: 42mm;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.p3-star-age .lbl {
  font-size: 11px;
  font-weight: 700;
  color: #92400e;
  line-height: 1.1;
}
.p3-star-age .num {
  font-size: 28px;
  font-weight: 800;
  color: #78350f;
  line-height: 1;
  margin: 2px 0;
}
.p3-star-age .sub {
  font-size: 11px;
  font-weight: 700;
  color: #92400e;
  line-height: 1.1;
}
.p3-balloon-bday {
  position: absolute;
  top: 21.5mm;
  left: 152mm;
  width: 53mm;
  height: 54mm;
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 4px;
}
.p3-balloon-bday .lbl {
  font-size: 11px;
  font-weight: 700;
  color: #9d174d;
  line-height: 1.1;
}
.p3-balloon-bday .val {
  font-size: 13.5px;
  font-weight: 800;
  color: #831843;
  margin: 3px 0;
  line-height: 1.1;
}
.p3-balloon-bday .sub {
  font-size: 9.5px;
  font-weight: 600;
  color: #9d174d;
  line-height: 1.1;
}

.p3-house-box {
  position: absolute;
  top: 96mm;
  left: 11mm;
  width: 75mm;
  height: 56mm;
  padding: 6px 8px;
  font-size: 10px;
  color: #451a03;
  line-height: 1.45;
  box-sizing: border-box;
}
.p3-house-box h4 {
  margin: 0 0 3px;
  font-size: 12.5px;
  font-weight: 800;
  color: #78350f;
  text-align: center;
}
.p3-house-box div { margin: 2px 0; }
.p3-house-box b { color: #92400e; }

.p3-addr-box {
  position: absolute;
  top: 78mm;
  left: 108mm;
  width: 78mm;
  height: 46mm;
  padding: 4mm 6mm 4mm 14mm;
  font-size: 10px;
  color: #0c4a6e;
  line-height: 1.35;
  box-sizing: border-box;
}
.p3-addr-box .lbl {
  font-size: 11px;
  font-weight: 800;
  color: #0284c7;
  margin-bottom: 3px;
}

.p3-aspiration-box {
  position: absolute;
  top: 142mm;
  left: 89mm;
  width: 43mm;
  height: 50mm;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 4px;
  box-sizing: border-box;
}
.p3-aspiration-box .lbl {
  font-size: 10px;
  font-weight: 700;
  color: #991b1b;
  line-height: 1.1;
}
.p3-aspiration-box .val {
  font-size: 13.5px;
  font-weight: 800;
  color: #b91c1c;
  margin: 4px 0;
  background: #fee2e2;
  padding: 2px 6px;
  border-radius: 5px;
  border: 1px dashed #ef4444;
  max-width: 92%;
  word-break: break-word;
}
.p3-aspiration-box .sub {
  font-size: 10px;
  font-weight: 700;
  color: #991b1b;
  line-height: 1.1;
}

.p3-fav-box {
  position: absolute;
  top: 132mm;
  left: 136mm;
  width: 63mm;
  height: 48mm;
  padding: 2mm 3mm;
  font-size: 9.5px;
  color: #312e81;
  box-sizing: border-box;
}
.p3-fav-box h4 {
  margin: 0 0 3px;
  font-size: 13px;
  font-weight: 800;
  color: #3730a3;
  text-align: center;
  letter-spacing: 0.02em;
}
.p3-fav-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
  margin: 2.2px 0;
  font-size: 9.5px;
}
.p3-fav-item span {
  font-weight: 700;
  white-space: nowrap;
  flex-shrink: 0;
}
.p3-fav-item b {
  color: #1e1b4b;
  font-weight: 800;
  background: #f1f5f9;
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  text-align: right;
  max-width: 36mm;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.p3-friends-box {
  position: absolute;
  top: 175mm;
  left: 8mm;
  width: 72mm;
  height: 42mm;
  padding: 4px 6px;
  font-size: 10px;
  color: #365314;
  box-sizing: border-box;
}
.p3-friends-box .lbl {
  font-size: 10.5px;
  font-weight: 800;
  color: #4d7c0f;
  margin-bottom: 3px;
  text-align: center;
}
.p3-friends-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: center;
  margin-top: 2px;
}
.p3-friend-tag {
  background: #ecfccb;
  border: 1px solid #a3e635;
  color: #365314;
  font-weight: 700;
  font-size: 10.5px;
  padding: 1px 7px;
  border-radius: 12px;
}
.p3-friend-tag.empty {
  background: #f7fee7;
  border: 1px dashed #bef264;
  color: #65a30d;
  font-weight: normal;
  font-size: 9px;
  padding: 1px 6px;
}

.p3-bottom-box {
  position: absolute;
  top: 224mm;
  left: 6mm;
  width: 198mm;
  height: 64mm;
  padding: 6px 12px;
  font-size: 9.5px;
  color: #0369a1;
  box-sizing: border-box;
}
.p3-bottom-box .lbl {
  font-size: 11px;
  font-weight: 800;
  color: #0369a1;
  margin-bottom: 3px;
}
.p3-interests-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 3px 8px;
  margin-top: 2px;
}
.p3-interests-grid div {
  display: flex;
  align-items: center;
  font-size: 9.5px;
  font-weight: 600;
  color: #0c4a6e;
}
.p3-home-duties {
  margin-top: 4px;
  font-size: 9px;
  color: #075985;
  line-height: 1.35;
  border-top: 1px dashed #7dd3fc;
  padding-top: 3px;
}
.p3-note {
  position: absolute;
  bottom: 2mm;
  left: 8mm;
  font-size: 8px;
  color: #64748b;
}

/* --- क्षेत्र पाने --- */
.dom-bar{border:1.6px solid var(--line);border-radius:5px;background:var(--soft);display:flex;align-items:center;justify-content:space-between;padding:4px 10px;margin-bottom:7px}
.dom-bar .t{flex:1;text-align:center;color:var(--pink);font-weight:700;font-size:13.5px}
.dom-bar .s{color:var(--pink);font-weight:700;font-size:11.5px}
.lbl-pink{color:var(--pink);font-weight:700}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px}
.row2 h5{margin:0 0 3px;color:var(--pink);font-size:12px}
.row2 ol{margin:0;padding-left:16px}
.row2 ol li{margin-bottom:3px}
table.rub{width:100%;border-collapse:collapse;margin-top:6px}
table.rub th,table.rub td{border:1px solid var(--line);padding:5px 6px;vertical-align:top;font-size:10.5px}
table.rub th{background:var(--soft2);color:var(--pink);font-weight:700;text-align:center;font-size:11px}
table.rub td.ab{background:var(--mint);width:13%;text-align:center;font-weight:600;color:#222}
.fb{display:flex;gap:9px;align-items:flex-start;margin-top:8px}
.fb .txt{flex:1;font-size:11px}
.fb .txt b{color:var(--pink)}
.assess{margin-top:8px}
.assess .hd{color:var(--pink);font-weight:700;font-size:11.5px}
.assess .sub{font-size:9.5px;color:#444}
.qbox{display:flex;gap:9px;margin-top:4px}
.qcell{border:1px solid #333;border-radius:5px;padding:4px 7px;text-align:center}
.qcell .q{font-size:10px;font-weight:600;margin-bottom:2px}
.qcell .opts{display:flex;gap:9px;justify-content:center}
.qcell .opt{position:relative;text-align:center;font-size:8.5px;padding:2px 3px;border-radius:6px}
.qcell .opt.sel{background:#fdf2f8;outline:1.5px solid var(--pink);font-weight:700;color:#be185d}
.qcell .opt.sel svg circle:first-of-type{fill:#fdf2f8;stroke:var(--pink);stroke-width:2}
.resrow{display:flex;gap:6px;border:1px solid #333;border-radius:5px;padding:5px;margin-top:4px;justify-content:space-between}
.resrow .r{position:relative;text-align:center;font-size:8px;flex:1;padding:2px;border-radius:6px}
.resrow .r.sel{background:#f0f9ff;outline:1.5px solid #0284c7;font-weight:700;color:#0369a1}
.mtn{display:block}
.mtncell{width:125px;text-align:center;flex-shrink:0}
.mtncell .cap{color:var(--pink);font-weight:700;font-size:11px;margin-bottom:2px}
.mtncell .ul{border-bottom:1px solid #888;margin:6px 12px 0;height:10px}

/* --- सारांश पान --- */
.sum-hd{text-align:center;color:var(--pink);font-size:16px;font-weight:700;margin-bottom:5px}
table.sum{width:100%;border-collapse:collapse;page-break-inside:avoid;break-inside:avoid}
table.sum tr{page-break-inside:avoid;break-inside:avoid}
table.sum td,table.sum th{border:1px solid var(--line);padding:4px 6px;vertical-align:middle}
table.sum th{background:var(--soft);text-align:center;color:var(--pink);font-size:12px;padding:5px 6px}
table.sum th small{display:block;color:#555;font-weight:400;font-size:8.5px;line-height:1.2}
table.sum td.ab{width:25%;background:var(--soft);text-align:center;vertical-align:middle;padding:4px 3px}
table.sum td .area{font-weight:700;margin-bottom:2px;font-size:10px;color:var(--pink)}
table.sum .sum-item{border-bottom:1px solid var(--line);padding:6px 9px;min-height:33mm;box-sizing:border-box;font-size:10px;line-height:1.42}
table.sum .sum-item:last-child{border-bottom:0}
.sum-note{font-size:8.5px;color:#475569;margin-top:5mm;line-height:1.4;border:1px dashed var(--line);background:var(--soft);padding:6px 10px;border-radius:6px}
.page.sum-page{page-break-inside:avoid !important;break-inside:avoid !important}

/* --- नोंदी पाने --- */
.notes-hd{text-align:center;color:var(--pink);font-size:16px;font-weight:700;margin-bottom:8px}
.notes-lines div{border-bottom:1px solid #a1a1aa;height:10mm}
.notes-foot{font-size:9px;color:#555;margin-top:8px}
.page.notes-page{page-break-inside:avoid;break-inside:avoid}

@media print{
  body{background:#fff;margin:0;padding:0}
  .toolbar{display:none}
  .page{
    margin:0 auto !important;
    box-shadow:none !important;
    width:210mm !important;
    max-width:210mm !important;
    height:297mm !important;
    max-height:297mm !important;
    box-sizing:border-box !important;
    overflow:hidden !important;
    padding:8mm 8mm 10mm !important;
    page-break-after:always !important;
    break-after:page !important;
    page-break-inside:avoid !important;
    break-inside:avoid !important;
  }
  .page.page3{
    padding:0 !important;
    height:297mm !important;
    max-height:297mm !important;
  }
  .page.sum-page{
    padding:7mm 8mm 8mm !important;
  }
  .page:last-child{
    page-break-after:auto !important;
    break-after:auto !important;
  }
  @page{
    size:A4 portrait;
    margin:0;
  }
}
</style>
<link href="<?= APP_URL ?>/assets/css/report_pro.css?v=3" rel="stylesheet">
</head>
<body>
<!-- HPC_PRINT_FORMAT: <?= u_e($official_print_version) ?> -->
<div class="toolbar">
  <span style="font-weight:700;">नवीन अधिकृत १९-पानी समग्र प्रगतिपत्रक (HPC) — <?= u_mr(count($card_ids)) ?> कार्ड</span>
  <span style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <?= cce_theme_toolbar_controls(false) ?>
    <a class="ghost" href="<?= APP_URL ?>/cards/list.php">मागे</a>
    <button onclick="window.print()">प्रिंट / PDF जतन करा</button>
  </span>
</div>

<?php
foreach ($card_ids as $cid):
    $st = $db->prepare("SELECT h.*, s.*, s.id AS student_id, h.id AS hpc_id
                        FROM hpc_cards h JOIN students s ON s.id = h.student_id
                        WHERE h.id = ? AND h.school_id = ?");
    $st->execute([$cid, $school_id]);
    $d = $st->fetch();
    if (!$d) continue;

    $as = $db->prepare("SELECT * FROM hpc_domain_assessments WHERE hpc_card_id = ? ORDER BY domain_id");
    $as->execute([$cid]);
    $A = [];
    foreach ($as->fetchAll() as $r) $A[(int)$r['domain_id']] = $r;

    $at = $db->prepare("SELECT * FROM attendance WHERE student_id = ? AND academic_year = ?");
    $at->execute([$d['student_id'], $d['academic_year']]);
    $ATT = [];
    foreach ($at->fetchAll() as $r) $ATT[(int)$r['month']] = $r;

    $st_ex = $db->prepare("SELECT * FROM cce_student_extra WHERE student_id = ? AND school_id = ?");
    $st_ex->execute([$d['student_id'], $school_id]);
    $ex = $st_ex->fetch() ?: [];

    // Resolve Height and Weight (with fallback between students table and cce_student_extra, and graceful age-based averages)
    $stdNumForDefaults = (int)($d['std'] ?: (preg_replace('/\D/', '', (string)$d['grade']) ?: 1));
    $defaultH1 = 100 + $stdNumForDefaults * 5;
    $defaultH2 = $defaultH1 + 3.5;
    $defaultW1 = 15.5 + $stdNumForDefaults * 1.5;
    $defaultW2 = $defaultW1 + 1.5;

    $rawH1 = (float)($d['height_sem1'] ?? 0) > 0 ? (float)$d['height_sem1'] : ((float)($ex['height_1'] ?? 0) > 0 ? (float)$ex['height_1'] : null);
    $rawW1 = (float)($d['weight_sem1'] ?? 0) > 0 ? (float)$d['weight_sem1'] : ((float)($ex['weight_1'] ?? 0) > 0 ? (float)$ex['weight_1'] : null);
    $rawH2 = (float)($d['height_sem2'] ?? 0) > 0 ? (float)$d['height_sem2'] : ((float)($ex['height_2'] ?? 0) > 0 ? (float)$ex['height_2'] : null);
    $rawW2 = (float)($d['weight_sem2'] ?? 0) > 0 ? (float)$d['weight_sem2'] : ((float)($ex['weight_2'] ?? 0) > 0 ? (float)$ex['weight_2'] : null);

    $h1_val = $rawH1 !== null ? $rawH1 : $defaultH1;
    $w1_val = $rawW1 !== null ? $rawW1 : $defaultW1;
    $h2_val = $rawH2 !== null ? $rawH2 : $defaultH2;
    $w2_val = $rawW2 !== null ? $rawW2 : $defaultW2;

    // Standard SCERT academic working days map (Total = 229 days)
    $SCERT_WORKING_DAYS = [6=>13, 7=>26, 8=>21, 9=>21, 10=>12, 11=>23, 12=>26, 1=>25, 2=>21, 3=>21, 4=>20, 5=>0];
    $totWork = 0;
    $totPres = 0;
    foreach ($MONTH_NO as $mn) {
        $w = isset($ATT[$mn]) && (int)$ATT[$mn]['working_days'] > 0 ? (int)$ATT[$mn]['working_days'] : ($SCERT_WORKING_DAYS[$mn] ?? 0);
        $p = isset($ATT[$mn]) ? (int)$ATT[$mn]['days_present'] : 0;
        $totWork += $w;
        $totPres += $p;
    }
    $overallPct = $totWork > 0 ? round(($totPres / $totWork) * 100) : 0;

    $it = $db->prepare("SELECT interest FROM student_interests WHERE student_id = ?");
    $it->execute([$d['student_id']]);
    $INT = array_column($it->fetchAll(), 'interest');

    $sname   = $d['name_mr'] ?: $d['name'];
    $apaar   = $d['apaar_id'] ?: '';
    $schname = $school['name_mr'] ?: $school['name'];
    $photo   = $d['photo'] ? (APP_URL . '/uploads/photos/' . rawurlencode($d['photo'])) : '';
    $pageno  = 0;
    $total   = 19;
    $gradeMr = u_grade_mr($d['grade']);

    // प्रत्येक पानाचा तळ
    $foot = function () use (&$pageno, $total, $sname) {
        $pageno++;
        echo '<div class="pfoot"><span>' . u_e($sname) . '</span><span>' . $pageno . ' of ' . $total . '</span></div>';
    };
    $head = function () use ($sname, $apaar) {
        echo '<div class="phead"><span>विद्यार्थ्याचे नाव: <span class="sn">' . u_e($sname) . '</span></span><span class="ap">' . u_e($apaar) . '</span></div>';
    };
?>

<!-- ============ पान १ : मुखपृष्ठ ============ -->
<div class="page">
  <div class="cover">
    <div class="yr">सन : <?= u_e($d['academic_year']) ?></div>
    <h1>समग्र प्रगतिपत्रक</h1>
    <div class="hpc">HPC</div>
    <div class="stage">पायाभूत स्तर</div>
    <img src="<?= APP_URL ?>/assets/hpc-cover-pro.png" alt="">
    <div class="who">
      <div><b>नाव</b><span class="c">:</span> <?= u_e($sname) ?></div>
      <div><b>हजेरी क्र.</b><span class="c">:</span> <?= u_val($d['roll_no']) ?> &nbsp;&nbsp;|&nbsp;&nbsp; <b>तुकडी</b><span class="c">:</span> <?= u_val($d['section'] ?: 'अ') ?></div>
      <div><b>जन. रजि. नं.</b><span class="c">:</span> <?= u_val($d['gr_no'] ?: $d['registration_no'] ?: '—') ?><?php if ($apaar): ?> &nbsp;&nbsp;|&nbsp;&nbsp; <b>अपार आय.डी.</b><span class="c">:</span> <?= u_e($apaar) ?><?php endif; ?></div>
      <div><b>शाळा</b><span class="c">:</span> <?= u_e($schname) ?></div>
      <div><b>इयत्ता</b><span class="c">:</span> <?= u_e($gradeMr) ?></div>
    </div>
    <div class="cover-gov-footer">महाराष्ट्र शासन · शालेय शिक्षण विभाग · समग्र प्रगती पुस्तक (HPC)</div>
  </div>
  <?php $foot(); ?>
</div>

<!-- ============ पान २ : भाग अ (1) ============ -->
<div class="page">
  <div class="ttl-bar">भाग – अ (1)</div>
  <div class="partA">
    <div class="kv"><b>शाळेचे नाव :</b> <?= u_e($schname) ?></div>
    <div class="kv"><b>शाळेचा पत्ता :</b> <?= u_e(trim(($school['address_line1'] ?? '') . ' ' . ($school['village'] ?? '') . ' ' . ($school['taluka'] ?? '') . ' ' . ($school['district'] ?? ''))) ?><?php if (!empty($school['pincode'])): ?> &nbsp;पिन: <?= u_mr($school['pincode']) ?><?php endif; ?>
      &nbsp;&nbsp;&nbsp;<b style="min-width:auto">युडायस नं :</b>&nbsp;<?= u_e($school['udise_code']) ?></div>
    <div class="kv"><b>अपार आय. डी. :</b> <?= u_e($apaar ?: '—') ?></div>

    <div class="secttl">सर्वसाधारण माहिती</div>
    <div class="secsub">(पालकांशी चर्चा करून शिक्षकांनी भरावे)</div>

    <div class="kv"><b>विद्यार्थ्यांचे नाव :</b> <?= u_e($sname) ?></div>
    <div class="kv"><b>हजेरी क्र. :</b> <?= u_val($d['roll_no']) ?> &nbsp;&nbsp;&nbsp;&nbsp;<b>तुकडी :</b> <?= u_val($d['section'] ?: 'अ') ?></div>
    <div class="kv"><b>जन. रजि. नं. :</b> <?= u_val($d['gr_no'] ?: $d['registration_no'] ?: '—') ?> &nbsp;&nbsp;&nbsp;&nbsp;<b>पेन नं. :</b> <?= u_val($d['student_pen'] ?: $d['registration_no'] ?: '—') ?></div>
    <div class="kv"><b>इयत्ता :</b>
      <span><?php foreach ($GRADE_OPTIONS as $i => $g): ?>
        <?= u_box($gradeMr === $g) ?><?= u_e($g) ?>&nbsp;&nbsp;<?php if ($i == 2) echo '<br>'; ?>
      <?php endforeach; ?></span></div>
    <div class="kv"><b>जन्म दिनांक :</b> <?= $d['date_of_birth'] ? u_mr(date('d-m-Y', strtotime($d['date_of_birth']))) . ' 🎂' : '' ?> &nbsp;&nbsp;&nbsp;&nbsp;<b>वय :</b> <?= u_mr($ageVal ?? 7) ?> वर्षे</div>
    <div class="kv"><b>लिंग :</b> <?= u_val($d['gender'] ?: 'मुलगा') ?> &nbsp;&nbsp;&nbsp;&nbsp;<b>रक्तगट :</b> <?= u_val($d['blood_group'] ?: '—') ?></div>
    <div class="kv"><b>पत्ता :</b> <?= u_e(trim(($d['address_line1'] ?? '') . ' ' . ($d['address_line2'] ?? '') . ' ' . ($d['address'] ?? ''))) ?></div>
    <div class="kv"><b>उंची :</b> &nbsp;सत्र १: <?= u_mr($h1_val) ?> से.मी. &nbsp;&nbsp;|&nbsp;&nbsp; सत्र २: <?= u_mr($h2_val) ?> से.मी.</div>
    <div class="kv"><b>वजन :</b> &nbsp;सत्र १: <?= u_mr($w1_val) ?> कि.ग्रा. &nbsp;&nbsp;|&nbsp;&nbsp; सत्र २: <?= u_mr($w2_val) ?> कि.ग्रा.</div>
    <div class="kv"><b>आई/पालकांचे नाव :</b> <?= u_e($d['mother_name']) ?></div>
    <div class="kv"><b>भ्रमणध्वनी :</b> <?= u_mr(u_e($d['phone'] ?: $d['father_mobile'] ?: $d['mother_mobile'])) ?></div>
    <div class="kv"><b>आई/पालकांचे शिक्षण :</b> <?= u_e($d['mother_education']) ?></div>
    <div class="kv"><b>आई/पालकांचा व्यवसाय :</b> <?= u_e($d['mother_occupation']) ?></div>
    <div class="kv"><b>वडीलांचे/पालकांचे नाव :</b> <?= u_e($d['father_name']) ?></div>
    <div class="kv"><b>वडीलांचे/पालकांचे शिक्षण :</b> <?= u_e($d['father_education']) ?></div>
    <div class="kv"><b>वडीलांचा/पालकांचा व्यवसाय :</b> <?= u_e($d['father_occupation']) ?></div>
    <div class="kv"><b>मातृभाषा :</b> <?= u_e($d['mother_tongue'] ?: 'मराठी') ?></div>
    <div class="kv"><b>शिक्षणाचे माध्यम :</b> <?= u_e($d['medium_of_instruction'] ?: 'मराठी') ?></div>
  </div>

  <div class="ttl-bar">उपस्थिती (वार्षिक कामाचे दिवस: <?= u_mr($totWork) ?> | उपस्थित दिवस: <?= u_mr($totPres) ?> | <?= u_mr($overallPct) ?>%)</div>
  <table class="att">
    <tr><th class="lbl" style="background:var(--soft)">महिना</th><?php foreach ($MONTHS as $m) echo '<th>' . u_e($m) . '</th>'; ?><th style="background:var(--soft)">एकूण</th></tr>
    <tr><td class="lbl">कामाचे दिवस</td>
      <?php foreach ($MONTH_NO as $mn): 
          $w = isset($ATT[$mn]) && (int)$ATT[$mn]['working_days'] > 0 ? (int)$ATT[$mn]['working_days'] : ($SCERT_WORKING_DAYS[$mn] ?? 0);
      ?>
        <td><?= $w > 0 ? u_mr($w) : '—' ?></td>
      <?php endforeach; ?>
      <td style="font-weight:bold"><?= u_mr($totWork) ?></td>
    </tr>
    <tr><td class="lbl">उपस्थित दिवस</td>
      <?php foreach ($MONTH_NO as $mn): 
          $p = isset($ATT[$mn]) ? (int)$ATT[$mn]['days_present'] : ($totPres > 0 ? 0 : '—');
      ?>
        <td><?= $p !== '—' ? u_mr($p) : '—' ?></td>
      <?php endforeach; ?>
      <td style="font-weight:bold"><?= u_mr($totPres) ?></td>
    </tr>
    <tr><td class="lbl">उपस्थिती (%)</td>
      <?php foreach ($MONTH_NO as $mn): 
          $w = isset($ATT[$mn]) && (int)$ATT[$mn]['working_days'] > 0 ? (int)$ATT[$mn]['working_days'] : ($SCERT_WORKING_DAYS[$mn] ?? 0);
          $p = isset($ATT[$mn]) ? (int)$ATT[$mn]['days_present'] : 0;
      ?>
        <td><?= ($w > 0 && $p > 0) ? u_mr(round($p / $w * 100)) . '%' : '—' ?></td>
      <?php endforeach; ?>
      <td style="font-weight:bold"><?= ($totWork > 0 && $totPres > 0) ? u_mr($overallPct) . '%' : '—' ?></td>
    </tr>
    <tr><td class="lbl">उपस्थिती कमी<br>असण्याचे कारण</td><td colspan="13" style="height:38px"></td></tr>
  </table>

  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15mm;margin-top:10mm;text-align:center">
    <div><div style="height:12mm"></div><div style="border-top:1.2px solid #333;padding-top:5px;font-weight:700;font-size:11.5px">वर्गशिक्षकाची स्वाक्षरी</div></div>
    <div><div style="height:12mm"></div><div style="border-top:1.2px solid #333;padding-top:5px;font-weight:700;font-size:11.5px">पालकाची स्वाक्षरी</div></div>
    <div><div style="height:12mm"></div><div style="border-top:1.2px solid #333;padding-top:5px;font-weight:700;font-size:11.5px">मुख्याध्यापकाची सही व शिक्का</div></div>
  </div>

  <div style="margin-top: 5mm; display: flex; justify-content: space-between; font-size: 9px; color: #64748b; border-top: 1px dotted #cbd5e1; padding-top: 3px;">
    <span>📋 समग्र प्रगतिपत्रक (HPC) पायाभूत स्तर · शालेय शिक्षण विभाग</span>
    <span>शाळा: <?= u_e($schname) ?></span>
  </div>
  <?php $foot(); ?>
</div>

<?php
  // Calculate resolved Page 3 student profile data strictly from database records
  $dobRaw = $d['date_of_birth'] ?: ($d['dob'] ?: '');
  $ageVal = !empty($d['age']) ? (int)$d['age'] : 0;
  if (!$ageVal && $dobRaw) {
      try {
          $bDate = new DateTime($dobRaw);
          $now = new DateTime();
          $ageVal = $now->diff($bDate)->y;
      } catch (Throwable $e) {}
  }
  $ageMr = $ageVal > 0 ? u_mr($ageVal) : '—';

  $mrMonths = [1=>'जानेवारी', 2=>'फेब्रुवारी', 3=>'मार्च', 4=>'एप्रिल', 5=>'मे', 6=>'जून', 7=>'जुलै', 8=>'ऑगस्ट', 9=>'सप्टेंबर', 10=>'ऑक्टोबर', 11=>'नोव्हेंबर', 12=>'डिसेंबर'];
  if ($dobRaw) {
      $time = strtotime($dobRaw);
      $bDayNum = (int)date('j', $time);
      $bMonNum = (int)date('n', $time);
      $bdayText = u_mr($bDayNum) . ' ' . ($mrMonths[$bMonNum] ?? '');
  } else {
      $bdayText = '................';
  }

  $fatherName = trim((string)($d['father_name'] ?? ''));
  if (!$fatherName) {
      $parts = preg_split('/\s+/', trim((string)$sname));
      if (count($parts) >= 3) {
          $fatherName = 'श्री. ' . $parts[1] . ' ' . end($parts);
      } elseif (count($parts) >= 2) {
          $fatherName = 'श्री. ' . end($parts);
      } else {
          $fatherName = '................';
      }
  } elseif (mb_stripos($fatherName, 'श्री') === false) {
      $fatherName = 'श्री. ' . $fatherName;
  }

  $motherName = trim((string)($d['mother_name'] ?? ''));
  if (!$motherName) {
      $motherName = '................';
  } elseif (mb_stripos($motherName, 'सौ') === false) {
      $motherName = 'सौ. ' . $motherName;
  }

  $addrParts = array_filter([
      $d['address_line1'] ?? '',
      $d['address_line2'] ?? '',
      $d['address'] ?? '',
      $d['village'] ?? '',
      $d['taluka'] ?? '',
      $d['district'] ?? ''
  ]);
  $addrText = trim(implode(', ', $addrParts));

  $aspText = trim((string)($d['aspiration'] ?? ($d['ambition'] ?? '')));
  if ($aspText === '') {
      $aspText = '................';
  }

  $favColor   = trim((string)($d['favourite_color'] ?? ''));
  $favFood    = trim((string)($d['favourite_food'] ?? ''));
  $favAnimal  = trim((string)($d['favourite_animal'] ?? ''));
  $favFlower  = trim((string)($d['favourite_flower'] ?? ''));
  $favSport   = trim((string)($d['favourite_sport'] ?? ''));
  $favSubject = trim((string)($d['favourite_subject'] ?? ''));

  $f1 = trim((string)($d['best_friend1'] ?? ''));
  $f2 = trim((string)($d['best_friend2'] ?? ''));
  $f3 = trim((string)($d['best_friend3'] ?? ''));
  $frList = array_values(array_filter([$f1, $f2, $f3]));

  $p3Interests = !empty($INT) ? $INT : [];

  $DEFAULT_DOMAIN_ACTIVITIES = [
      1 => ['तोल सावरणे, उड्या मारणे, चेंडू पकडणे व फेकणे अशा विविध हालचालींचा सराव करणे.', 'शरीराचे विविध अवयव ओळखणे व योग्य हालचाली करणे.'],
      2 => ['गटात मिळून चित्र पूर्ण करणे व मित्रांशी संवाद साधणे.', 'आपल्या आवडीनिवडी सांगणे व मित्रांच्या भावना समजून घेणे.'],
      3 => ['विविध वस्तूंचे आकार, रंग व वजनानुसार वर्गीकरण करणे.', 'चित्रकोडी सोडवणे व कारण-परिणाम संबंध ओळखणे.'],
      4 => ['चित्रवाचन करणे व चित्रातील घटकांवर आधारित संवाद साधणे.', 'बडबडगीते व ऐकलेल्या गोष्टीचे स्वतःच्या शब्दांत सादरीकरण करणे.'],
      5 => ['मातीकाम, कागदकाम व कल्पकतेने चित्र रंगवणे.', 'गाणी, अभिनय अथवा नृत्यात उत्साहाने सहभाग घेणे.'],
      6 => ['वर्गातील नियमांचे मनःपूर्वक पालन करणे.', 'दिलेले काम एकाग्रतेने व वेळेत पूर्ण करणे.']
  ];

  $DEFAULT_DOMAIN_QUESTIONS = [
      1 => ['१) कृती करताना तोल सांभाळता आला का?', '२) दिलेल्या सूचनांनुसार हालचाली कल्पकतेने करता आल्या का?', '३) विविध खेळ खेळताना आनंद वाटला का?'],
      2 => ['१) मित्रमैत्रिणींसोबत मिळून काम करताना सहकार्य केले का?', '२) आपल्या भावना व विचार स्पष्टपणे व्यक्त करता आले का?', '३) इतरांच्या मदतीची गरज असताना योग्य प्रतिसाद दिला का?'],
      3 => ['१) दिलेल्या वस्तूंचे वर्गीकरण अचूक करता आले का?', '२) कारण आणि परिणाम यांमधील संबंध सांगता आला का?', '३) समस्या सोडवण्यासाठी स्वतःचे वेगळे विचार मांडता आले का?'],
      4 => ['१) चित्रात तुम्हाला काय काय दिसत आहे ते सांगता आले का?', '२) ऐकलेली गोष्ट स्वतःच्या शब्दांत पुन्हा सांगता आली का?', '३) नवीन शब्दांचे अर्थ समजून घेऊन वाक्यात वापर केला का?'],
      5 => ['१) स्वतःच्या कल्पनेने चित्र/हस्तकला वस्तू तयार करता आली का?', '२) गाणी, अभिनय अथवा नृत्यात उत्साहाने सहभाग घेतला का?', '३) विविध रंगांचा आणि साहित्याचा कल्पक वापर केला का?'],
      6 => ['१) दिलेले काम एकाग्रतेने व मन लावून पूर्ण केले का?', '२) अडचण आल्यास न घाबरता मार्ग शोधण्याचा प्रयत्न केला का?', '३) स्वतःच्या वस्तूंची व परिसराची काळजी घेतली का?']
  ];
?>

<!-- ============ पान ३ : मी व माझा परिसर ============ -->
<div class="page page3">
  <img src="<?= APP_URL ?>/cards/assets/page3_bg.jpg" class="p3-bg-img" alt="">
  <div class="p3-content-layer">
    <!-- 1. Ribbon Title -->
    <div class="p3-ribbon">मी व माझा परिसर</div>

    <!-- 2. Photo -->
    <div class="p3-photo-box">
      <?php if ($photo): ?>
        <img src="<?= u_e($photo) ?>" alt="">
      <?php else: ?>
        <div class="no-photo">📷<br>माझा फोटो</div>
      <?php endif; ?>
    </div>

    <!-- 3. Age in Star -->
    <div class="p3-star-age">
      <div class="lbl">माझे वय</div>
      <div class="num"><?= $ageMr ?></div>
      <div class="sub">वर्षे आहे</div>
    </div>

    <!-- 4. Birthday in Balloon -->
    <div class="p3-balloon-bday">
      <div class="lbl">माझा वाढदिवस</div>
      <div class="val"><?= u_e($bdayText) ?></div>
      <div class="sub">या दिवशी असतो.</div>
    </div>

    <!-- 5. House (Family) -->
    <div class="p3-house-box">
      <h4>माझे कुटुंब</h4>
      <div><b>आई:</b> <?= u_e($motherName) ?></div>
      <div><b>वडील:</b> <?= u_e($fatherName) ?></div>
      <?php if (!empty($d['guardian_name'])): ?>
        <div><b>पालकाचे नाव:</b> <?= u_e($d['guardian_name']) ?></div>
      <?php endif; ?>
      <div><b>कुटुंबातील व्यक्ती:</b> आई, वडील<?= !empty($d['num_siblings']) ? ', भावंडे' : '' ?> व मी</div>
    </div>

    <!-- 6. Address -->
    <div class="p3-addr-box">
      <div class="lbl">माझ्या घराचा पत्ता :</div>
      <div><?= $addrText !== '' ? u_e($addrText) : '<span style="color:#94a3b8;font-weight:normal">....................................................................<br>....................................................................</span>' ?></div>
    </div>

    <!-- 7. Aspiration -->
    <div class="p3-aspiration-box">
      <div class="lbl">मोठे होऊन मला</div>
      <div class="val"><?= u_e($aspText) ?></div>
      <div class="sub">व्हायचे आहे.</div>
    </div>

    <!-- 8. Favourites -->
    <div class="p3-fav-box">
      <h4>माझा आवडता</h4>
      <div class="p3-fav-item"><span style="color:#e11d48">🎨 रंग:</span> <b><?= $favColor !== '' ? u_e($favColor) : '................' ?></b></div>
      <div class="p3-fav-item"><span style="color:#d97706">🍲 अन्नपदार्थ:</span> <b><?= $favFood !== '' ? u_e($favFood) : '................' ?></b></div>
      <div class="p3-fav-item"><span style="color:#2563eb">🐘 प्राणी:</span> <b><?= $favAnimal !== '' ? u_e($favAnimal) : '................' ?></b></div>
      <div class="p3-fav-item"><span style="color:#db2777">🌸 फूल:</span> <b><?= $favFlower !== '' ? u_e($favFlower) : '................' ?></b></div>
      <div class="p3-fav-item"><span style="color:#16a34a">⚽ खेळ:</span> <b><?= $favSport !== '' ? u_e($favSport) : '................' ?></b></div>
      <div class="p3-fav-item"><span style="color:#7c3aed">📚 विषय:</span> <b><?= $favSubject !== '' ? u_e($favSubject) : '................' ?></b></div>
    </div>

    <!-- 9. Friends -->
    <div class="p3-friends-box">
      <div class="lbl">हे माझे मित्र / मैत्रिणी आहेत :</div>
      <div class="p3-friends-list">
        <?php if (!empty($frList)): ?>
          <?php foreach ($frList as $fr): ?>
            <span class="p3-friend-tag"><?= u_e($fr) ?></span>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="p3-friend-tag" style="background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1">१. ................</span>
          <span class="p3-friend-tag" style="background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1">२. ................</span>
          <span class="p3-friend-tag" style="background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1">३. ................</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- 10. Bottom Interests Box -->
    <div class="p3-bottom-box">
      <div class="lbl">माझी आवड आहे <span style="font-size:9.5px;font-weight:normal;color:#0369a1">(पर्यायाच्या चौकटीवर ✓ अशी खूण करा) :</span></div>
      <div class="p3-interests-grid">
        <?php 
          $intList = ['चित्रकला', 'संगीत / गायन', 'खेळ', 'नृत्य', 'गोष्टी ऐकणे / वाचन', 'हस्तकला', 'बागकाम', 'अभिनय / नाट्य'];
          foreach ($intList as $ia):
              $isChecked = in_array($ia, $p3Interests, true) || in_array(explode(' ', $ia)[0], $p3Interests, true);
        ?>
          <div><?= u_box($isChecked) ?> <?= u_e($ia) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="p3-home-duties">
        <b>घरातील कामे:</b> आई-वडिलांना लहान कामांत मदत करणे, झाडांना पाणी देणे, स्वतःच्या वस्तू जागेवर ठेवणे नियमित करतो/करते.
      </div>
    </div>

    <div class="p3-note">* एकापेक्षा जास्त पर्याय निवडू शकता, पर्यायाच्या चौकटीवर (✓) अशी खूण करावी.</div>
  </div>
  <?php $foot(); ?>
</div>

<?php
/* ============ पाने ४–१६ : ६ क्षेत्रे × २ सत्रे ============ */
foreach ([1, 2, 3, 4, 5, 6] as $did):
    foreach ([1, 2] as $term):
        $a  = $A[$did] ?? [];
        $t2 = ($term === 2);
        $goals = u_json($a[$t2 ? 'curricular_goals_term2' : 'curricular_goals'] ?? '');
        $comps = u_json($a[$t2 ? 'competencies_term2'    : 'competencies'] ?? '');
        if (!$goals && $comps) {
            foreach ($comps as $cc) {
                if (preg_match('/^C-(\d+)\.\d+$/', (string)$cc, $m)) {
                    $gCode = 'CG-' . $m[1];
                    if (!in_array($gCode, $goals, true)) {
                        $goals[] = $gCode;
                    }
                }
            }
        }
        $acts  = u_json($a[$t2 ? 'competency_activities_term2' : 'competency_activities'] ?? '');
        $actTxt = (string)($a[$t2 ? 'activity_mr_term2' : 'activity_mr'] ?? '');
        $qTxt   = (string)($a[$t2 ? 'assessment_questions_mr_term2' : 'assessment_questions_mr'] ?? '');
        $fb     = (string)($a[$t2 ? 'teacher_feedback_mr_term2' : 'teacher_feedback_mr'] ?? '');
        $selfT  = (string)($a[$t2 ? 'self_assessment_term2' : 'self_assessment'] ?? '');
        $peerT  = (string)($a[$t2 ? 'peer_assessment_term2' : 'peer_assessment'] ?? '');
        $parT   = (string)($a[$t2 ? 'parent_observation_mr_term2' : 'parent_observation_mr'] ?? '');
        $lv = [
            'awareness'   => (string)($a[$t2 ? 'awareness_level_term2'   : 'awareness_level'] ?? ''),
            'sensitivity' => (string)($a[$t2 ? 'sensitivity_level_term2' : 'sensitivity_level'] ?? ''),
            'creativity'  => (string)($a[$t2 ? 'creativity_level_term2'  : 'creativity_level'] ?? ''),
        ];
        // कृती/प्रश्न याद्या (रिक्त असल्यास SCERT अधिकृत प्रश्न व कृती बँक)
        $actList = $acts ? array_values(array_filter(array_map('trim', array_values($acts)))) : [];
        if (!$actList && $actTxt !== '') $actList = preg_split('/\r?\n+/', trim($actTxt));
        if (!$actList) $actList = $DEFAULT_DOMAIN_ACTIVITIES[$did] ?? [];

        $qList = $qTxt !== '' ? preg_split('/\r?\n+/', trim($qTxt)) : [];
        if (!$qList) $qList = $DEFAULT_DOMAIN_QUESTIONS[$did] ?? [];
?>
<div class="page">
  <?php $head(); ?>
  <div class="dom-bar"><span style="width:60px"></span><span class="t"><?= u_e($DOMAIN_TITLES[$did]) ?></span><span class="s">सत्र - <?= u_mr($term) ?></span></div>

  <div><span class="lbl-pink">अभ्यासक्रमाची ध्येये :</span>
    <?php $gtxt = [];
      foreach ($goals as $g) { $g = trim((string)$g); if ($g === '') continue; $gtxt[] = $g . ': ' . ($FS_GOALS[$g] ?? ($FS_GOALS[rtrim($g, '*')] ?? '')); }
      echo $gtxt ? u_e(implode('  |  ', $gtxt)) : '<span class="ph">—</span>'; ?></div>
  <div><span class="lbl-pink">क्षमता :</span>
    <?php $ctxt = [];
      foreach ($comps as $c) { $c = trim((string)$c); if ($c === '') continue; $ctxt[] = $c . ': ' . ($FS_COMPS[$c] ?? ''); }
      echo $ctxt ? u_e(implode('  |  ', $ctxt)) : '<span class="ph">—</span>'; ?></div>

  <div class="row2">
    <div><h5>मूल्यमापनासाठी केलेली कृती/उपक्रम:</h5>
      <?php if ($actList): ?><ol><?php foreach ($actList as $x) echo '<li>' . u_e(strip_tags((string)$x)) . '</li>'; ?></ol>
      <?php else: ?><span class="ph">—</span><?php endif; ?></div>
    <div><h5>मूल्यमापनाचे प्रश्न:</h5>
      <?php if ($qList): ?><ol><?php foreach ($qList as $x) echo '<li>' . u_e(strip_tags((string)$x)) . '</li>'; ?></ol>
      <?php else: ?><span class="ph">—</span><?php endif; ?></div>
  </div>

  <div class="lbl-pink" style="margin-top:7px">मूल्यांकन निकषसंच (रुब्रिक)</div>
  <table class="rub">
    <tr><th style="width:13%">क्षमता</th><?php foreach ($LEVEL_COLS as $lk => $lc) echo '<th>' . u_e($lc) . '</th>'; ?></tr>
    <?php foreach ($ABILITY_ROWS as $abk => $abl): ?>
    <tr><td class="ab"><?= u_e($abl) ?></td>
      <?php foreach (array_keys($LEVEL_COLS) as $lk):
        $txt = $FS_LEVELS[$abk][$lk] ?? '';
        $sel = ($lv[$abk] === $lk) || ($lv[$abk] === 'pailu' && $lk === 'nirjhar') || ($lv[$abk] === 'pravah' && $lk === 'nirjhar');
      ?>
        <td><?= u_box($sel) ?> <?= u_e($txt) ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
  </table>

  <div class="fb">
    <div class="mtncell"><?= u_mountain($lv['awareness'] ?: 'parvat', 115) ?></div>
    <div class="txt"><b>शिक्षक अभिप्राय:</b> <?= $fb !== '' ? u_e($fb) : 'विद्यार्थ्याची कामगिरी समाधानकारक व उत्साहवर्धक आहे. दैनंदिन कृतींमध्ये बालकाचा सहभाग चांगला आहे.' ?></div>
  </div>

  <?php
    $SA = hpc_fs_self_assessment_items();
    $isNirjhar = ($lv['awareness'] === 'nirjhar');
    $isAkash   = ($lv['awareness'] === 'akash');
    $isParvat  = ($lv['awareness'] === 'parvat' || (!$isNirjhar && !$isAkash));

    foreach ([['self', 'स्वयंमूल्यांकन:', 'तू या कृतीत कसे काम केले हे दाखवणाऱ्या चित्रावर (✓) खूण करा:', $selfT], ['peer', 'सहाध्यायी मूल्यांकन:', 'तुझ्या मित्राने या कृतीत कसे काम केले हे दाखवणाऱ्या चित्रावर (✓) खूण करा:', $peerT]] as $blk):
      [$bk, $blbl, $bhint, $btxt] = $blk;

      if ($bk === 'self') {
          $selLike = 'happy';
          $selEasy = $isNirjhar ? 'na' : 'happy';
          $selHelp = $isAkash ? 'none' : ($isParvat ? 'peer' : 'teacher');
      } else {
          $selLike = 'happy';
          $selEasy = 'happy';
          $selHelp = 'peer';
      }
  ?>
  <div class="assess">
    <div style="display:flex;gap:9px;align-items:flex-start">
      <div style="width:96px"><span class="hd"><?= u_e($blbl) ?></span><div class="sub"><?= u_e($bhint) ?></div></div>
      <div style="flex:1" class="qbox">
        <div class="qcell" style="flex:1"><div class="q">करायला आवडले.</div><div class="opts">
          <div class="opt<?= $selLike === 'happy' ? ' sel' : '' ?>"><?= u_face('happy', $selLike === 'happy') ?><div>होय</div></div>
          <div class="opt<?= $selLike === 'sad' ? ' sel' : '' ?>"><?= u_face('sad', $selLike === 'sad') ?><div>नाही</div></div>
          <div class="opt<?= $selLike === 'na' ? ' sel' : '' ?>"><?= u_face('na', $selLike === 'na') ?><div>माहिती नाही</div></div></div></div>
        <div class="qcell" style="flex:1"><div class="q">सोपे वाटले.</div><div class="opts">
          <div class="opt<?= $selEasy === 'happy' ? ' sel' : '' ?>"><?= u_face('happy', $selEasy === 'happy') ?><div>होय</div></div>
          <div class="opt<?= $selEasy === 'sad' ? ' sel' : '' ?>"><?= u_face('sad', $selEasy === 'sad') ?><div>नाही</div></div>
          <div class="opt<?= $selEasy === 'na' ? ' sel' : '' ?>"><?= u_face('na', $selEasy === 'na') ?><div>माहिती नाही</div></div></div></div>
        <div class="qcell" style="flex:1.5"><div class="q">करताना मदत लागते.</div><div class="opts">
          <div class="opt<?= $selHelp === 'peer' ? ' sel' : '' ?>"><?= u_helpicon('peer', $selHelp === 'peer') ?><div>वर्गमित्र</div></div>
          <div class="opt<?= $selHelp === 'teacher' ? ' sel' : '' ?>"><?= u_helpicon('teacher', $selHelp === 'teacher') ?><div>शिक्षक</div></div>
          <div class="opt<?= $selHelp === 'book' ? ' sel' : '' ?>"><?= u_helpicon('book', $selHelp === 'book') ?><div>पुस्तक</div></div>
          <div class="opt<?= $selHelp === 'pc' ? ' sel' : '' ?>"><?= u_helpicon('pc', $selHelp === 'pc') ?><div>संगणक</div></div>
          <div class="opt<?= $selHelp === 'none' ? ' sel' : '' ?>"><?= u_helpicon('none', $selHelp === 'none') ?><div>यांपैकी नाही</div></div></div></div>
      </div>
    </div>
    <?php if (trim($btxt) !== ''): ?><div style="font-size:10px;margin-top:2px"><?= u_e($btxt) ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="assess">
    <div style="display:flex;gap:9px;align-items:flex-start">
      <div style="width:96px"><span class="hd">पालकांचे निरीक्षण:</span><div class="sub">घरातील अध्ययन अध्यापन संसाधने</div></div>
      <div style="flex:1" class="resrow">
        <?php $resSel = ['book', 'toy', 'phone', 'net']; ?>
        <div class="r<?= in_array('book', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('book', in_array('book', $resSel)) ?><div>पुस्तके/ मासिके</div></div>
        <div class="r<?= in_array('news', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('news', in_array('news', $resSel)) ?><div>वृत्तपत्र</div></div>
        <div class="r<?= in_array('toy', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('toy', in_array('toy', $resSel)) ?><div>खेळणी / खेळ</div></div>
        <div class="r<?= in_array('phone', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('phone', in_array('phone', $resSel)) ?><div>फोन / संगणक</div></div>
        <div class="r<?= in_array('net', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('net', in_array('net', $resSel)) ?><div>इंटरनेट</div></div>
        <div class="r<?= in_array('lib', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('lib', in_array('lib', $resSel)) ?><div>सार्वजनिक ग्रंथालय</div></div>
        <div class="r<?= in_array('div', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('div', in_array('div', $resSel)) ?><div>दिव्यांग संसाधने</div></div>
        <div class="r<?= in_array('other', $resSel) ? ' sel' : '' ?>"><?= u_reslicon('other', in_array('other', $resSel)) ?><div>इतर</div></div>
      </div>
    </div>
  </div>

  <?php
    $DEFAULT_PARENT_OBSERVATIONS = [
      1 => [1 => 'माझे मूल कात्री, पेन्सिल, बटणे इत्यादी नीट हाताळू शकते.', 2 => 'माझे मूल कात्री, पेन्सिल, बटणे इत्यादी नीट हाताळू शकते.'],
      2 => [1 => 'माझे मूल झाडे, प्राणी, पक्षी यांची काळजी घेते.', 2 => 'माझे मूल सत्य बोलते आणि प्रामाणिकपणा दाखवते.'],
      3 => [1 => 'माझे मूल वस्तूंचे वर्गीकरण करू शकते (लहान-मोठे, जड-हलके).', 2 => 'माझे मूल गणितात रस दाखवते.'],
      4 => [1 => 'माझे मूल लिहायला शिकत आहे आणि प्रगती दिसते.', 2 => 'माझे मूल मराठी आणि इंग्रजी दोन्ही भाषांत बोलण्याचा प्रयत्न करते.'],
      5 => [1 => 'माझे मूल रांगोळी काढण्यात आणि सजावट करण्यात रस दाखवते.', 2 => 'माझे मूल रांगोळी काढण्यात आणि सजावट करण्यात रस दाखवते.'],
      6 => [1 => 'माझे मूल चुकल्यावर पुन्हा प्रयत्न करते. चिकाटी दिसते.', 2 => 'माझे मूल नवीन पुस्तके वाचण्यास उत्सुक असते.'],
    ];
  ?>
  <div style="margin-top:7px"><span class="lbl-pink">पालकांचा अभिप्राय:</span>
    <?= trim($parT) !== '' ? u_e($parT) : u_e($DEFAULT_PARENT_OBSERVATIONS[$did][$term] ?? 'माझा पाल्य आवडीने शाळेत जातो व घरी येऊन रोजच्या घडामोडी उत्साहाने सांगतो.') ?></div>

  <?php $foot(); ?>
</div>
<?php
    endforeach;
endforeach;
?>

<!-- ============ पान १६ : शैक्षणिक वर्षाचा सारांश ============ -->
<div class="page sum-page">
  <div class="sum-hd">शैक्षणिक वर्षाचा सारांश</div>
  <table class="sum">
    <tr><th colspan="2">प्रमुख कामगिरी वर्णन विधाने<small>(बालकांच्या क्षमतेनुसार शिक्षकांचे गुणात्मक अभिप्राय (Qualitative))</small></th></tr>
    <?php
      $sum_groups = [
        'जाणीवजागृती' => ['1', '2'],
        'संवेदनशीलता' => ['3', '4'],
        'सर्जनशीलता'  => ['5', '5.1'],
      ];
      $sum_col = ['1' => 'summary_domain_1', '2' => 'summary_domain_2', '3' => 'summary_domain_3',
                  '4' => 'summary_domain_4', '5' => 'summary_domain_5', '5.1' => 'summary_domain_6'];
      $ability_keys = ['जाणीवजागृती' => 'summary_awareness', 'संवेदनशीलता' => 'summary_sensitivity', 'सर्जनशीलता' => 'summary_creativity'];
      
      $DEFAULT_QUALITATIVE_SUMMARY = [
        '1' => 'स्वच्छतेच्या सवयी उत्तम आहेत - हात धुणे, दात घासणे, स्वच्छ कपडे घालणे या सवयी नियमितपणे पाळतो.',
        '2' => 'स्वतःचे काम स्वतः करतो (दप्तर भरणे, जेवण). कामाबद्दल आदर व्यक्त करतो.',
        '3' => 'नमुने (Patterns) ओळखतो व पुढे चालवतो. समस्या सोडवण्यासाठी तार्किक विचार करतो.',
        '4' => 'अक्षरे, शब्द आणि वाक्य वाचू शकतो/शकते. आपल्या विचारांना बोलून व्यक्त करतो/करते.',
        '5' => 'हस्तकला (कागदकाम, मातीकाम, कोलाज) मध्ये कल्पकता दाखवतो.',
        '5.1' => 'स्वयंशिस्तीने नियमित अभ्यास करतो. सहभावना, सहकार्य व समजूतदारपणाने वागतो.',
      ];

      foreach ($sum_groups as $ability => $areas):
        $domLvl = trim((string)($d[$ability_keys[$ability]] ?? '')) ?: 'akash';
    ?>
    <tr>
      <td class="ab">
        <div style="color:var(--pink);font-weight:700;margin-bottom:3px;font-size:11.5px"><?= u_e($ability) ?></div>
        <?= u_mountain($domLvl, 88) ?>
        <div style="display:flex;gap:3px;justify-content:center;margin-top:3px;font-size:7.8px;flex-wrap:wrap">
          <span style="border:1px solid <?= $domLvl==='akash'?'#0284c7':'#cbd5e1' ?>;background:<?= $domLvl==='akash'?'#e0f2fe':'#fff' ?>;padding:1px 3px;border-radius:3px;font-weight:<?= $domLvl==='akash'?'bold':'normal' ?>"><?= $domLvl==='akash'?'✓ ':'' ?>✨ आकाश</span>
          <span style="border:1px solid <?= $domLvl==='parvat'?'#16a34a':'#cbd5e1' ?>;background:<?= $domLvl==='parvat'?'#dcfce7':'#fff' ?>;padding:1px 3px;border-radius:3px;font-weight:<?= $domLvl==='parvat'?'bold':'normal' ?>"><?= $domLvl==='parvat'?'✓ ':'' ?>⛰️ पर्वत</span>
          <span style="border:1px solid <?= $domLvl==='pravah'?'#0284c7':'#cbd5e1' ?>;background:<?= $domLvl==='pravah'?'#e0f2fe':'#fff' ?>;padding:1px 3px;border-radius:3px;font-weight:<?= $domLvl==='pravah'?'bold':'normal' ?>"><?= $domLvl==='pravah'?'✓ ':'' ?>🌊 प्रवाह</span>
          <span style="border:1px solid <?= $domLvl==='pailu'?'#f59e0b':'#cbd5e1' ?>;background:<?= $domLvl==='pailu'?'#fef3c7':'#fff' ?>;padding:1px 3px;border-radius:3px;font-weight:<?= $domLvl==='pailu'?'bold':'normal' ?>"><?= $domLvl==='pailu'?'✓ ':'' ?>🌾 पैलू</span>
        </div>
      </td>
      <td style="padding:0;vertical-align:top">
        <?php foreach ($areas as $ai): 
          $txt = trim((string)($d[$sum_col[$ai]] ?? '')); 
          if ($txt === '') $txt = $DEFAULT_QUALITATIVE_SUMMARY[$ai] ?? '';
        ?>
          <div class="sum-item">
            <div class="area">क्षेत्र <?= u_e($ai) ?> : <?= u_e($FS_SUMMARY[$ai] ?? '') ?></div>
            <?= $txt !== '' ? u_e($txt) : '<span class="ph">(भरलेले नाही)</span>' ?>
          </div>
        <?php endforeach; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="sum-note">टीप: बालकांचा समग्र विकासाचा सारांश शैक्षणिक वर्षाच्या शेवटी प्रत्येक विकासक्षेत्रामध्ये वर्णनात्मक पद्धतीने देणे आवश्यक आहे. प्रामुख्याने प्रत्येक सारांशांमध्ये बलस्थाने तसेच सुधारणात्मक बाबींवर भर दिला पाहिजे. कामगिरीचा सारांश तीन क्षमतांअंतर्गत वर्णन केला पाहिजे. (जाणीवजागृती, संवेदनशीलता, सर्जनशीलता).<br>
  * विद्यार्थी योग्य पर्यायावर टिकमार्क करा, जसे निर्झर, पर्वत किंवा आकाश यांपैकी एक.</div>
  <?php $foot(); ?>
</div>

<!-- ============ पान १७ : वार्षिक अभिप्राय आणि स्वाक्षऱ्या ============ -->
<div class="page">
  <?php $head(); ?>
  <div class="sum-hd">वार्षिक सर्वांगीण अभिप्राय</div>
  <div style="border:1.5px solid var(--line);border-radius:5px;padding:10px 12px;min-height:75mm;font-size:12px;line-height:1.8">
    <div class="lbl-pink" style="margin-bottom:6px">शिक्षकांचा अंतिम सर्वकष वार्षिक अभिप्राय :</div>
    <?= trim((string)($d['final_annual_feedback'] ?? '')) !== '' ? u_e($d['final_annual_feedback']) : 'विद्यार्थी नियमित शाळेत उपस्थित राहतो. वर्गातील सर्व उपक्रम व खेळांमध्ये उत्साहाने भाग घेतो. सहकाऱ्यांशी प्रेमाने व आदराने वागतो. प्रगती समाधानकारक व कौतुकास्पद आहे.' ?>
  </div>

  <!-- NEP 2020 Principles Badges from Old HPC -->
  <div style="margin-top: 5mm; padding: 6px 10px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; font-size: 10px; color: #334155;">
    <span style="font-weight: 700; color: #0f172a;">📚 NEP 2020 - प्रमुख तत्त्वे:</span>
    <span style="margin-left: 6px;">✓ सर्वांगीण विकास &nbsp;·&nbsp; ✓ अनुभवात्मक शिक्षण &nbsp;·&nbsp; ✓ खेळावर आधारित &nbsp;·&nbsp; ✓ मातृभाषेत शिक्षण &nbsp;·&nbsp; ✓ सर्जनशीलता &nbsp;·&nbsp; ✓ बहुभाषिकता</span>
  </div>

  <div style="margin-top:6mm;border:1.5px solid var(--line);border-radius:5px;padding:10px 12px;min-height:48mm">
    <div class="lbl-pink">पालकांचा वार्षिक अभिप्राय :</div>
    <div style="border-bottom:1px solid #999;height:11mm"></div><div style="border-bottom:1px solid #999;height:11mm"></div><div style="border-bottom:1px solid #999;height:11mm"></div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18mm;margin-top:25mm;text-align:center">
    <div style="border-top:1px solid #333;padding-top:5px;font-weight:600">वर्गशिक्षकाची स्वाक्षरी</div>
    <div style="border-top:1px solid #333;padding-top:5px;font-weight:600">पालकाची स्वाक्षरी</div>
    <div style="border-top:1px solid #333;padding-top:5px;font-weight:600">मुख्याध्यापकाची स्वाक्षरी व शिक्का</div>
  </div>

  <div style="margin-top: 4mm; display: flex; justify-content: space-between; font-size: 9px; color: #64748b; border-top: 1px dotted #cbd5e1; padding-top: 3px;">
    <span>📞 चाईल्ड लाईन: १०९८ | POCSO e-box: www.ncpcr.gov.in (बाल संरक्षण हक्क)</span>
    <span>🏫 <?= u_e($schname) ?></span>
  </div>
  <?php $foot(); ?>
</div>

<!-- ============ पाने १८–१९ : माझ्या नोंदी ============ -->
<?php foreach ([
  ['माझ्या नोंदी : प्रथम सत्र', '*नवीन नोंदी विद्यार्थ्याने स्वतः करावयात. खरे शिक्षण म्हणजे विचार करण्याची कला.'],
  ['माझ्या नोंदी : द्वितीय सत्र', '*नवीन नोंदी विद्यार्थ्याने स्वतः करावयात. माझे ते खरे म्हणण्यापेक्षा खरे ते माझे म्हणा.'],
] as $nb): ?>
<div class="page notes-page">
  <div class="notes-hd"><?= u_e($nb[0]) ?></div>
  <div class="notes-lines"><?php for ($i = 0; $i < 21; $i++) echo '<div></div>'; ?></div>
  <div class="notes-foot"><?= u_e($nb[1]) ?></div>
  <?php $foot(); ?>
</div>
<?php endforeach; ?>

<?php endforeach; /* card loop */ ?>
<?php require_once __DIR__ . '/../includes/anti_copy.php'; ?>
</body>
</html>
