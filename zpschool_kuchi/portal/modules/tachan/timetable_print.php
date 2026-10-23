<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/tachan_engine.php';

// Include CCE / HPC Unified Themes & Fonts
if (file_exists(__DIR__ . '/../../includes/cce_theme_helper.php')) {
    require_once __DIR__ . '/../../includes/cce_theme_helper.php';
}

$u = require_login();
$sid = (int) $u['school_id'];
$pdo = db();
$classes = school_classes($sid);
$byId = [];
foreach ($classes as $c) $byId[(int) $c['id']] = $c;
$pref = tachan_prefs_get($pdo, $sid, (int) $u['id']);

$mode = isset($_GET['mode']) && in_array($_GET['mode'], ['teacher', 'class'], true) 
    ? (string)$_GET['mode'] 
    : (($pref['teach_type'] ?? '') === 'periodwise' ? 'teacher' : 'class');

$layout = isset($_GET['layout']) && in_array($_GET['layout'], ['split', 'combined'], true) 
    ? (string)$_GET['layout'] 
    : 'split'; // Default: Separated Saturday morning session

$medium = isset($_GET['medium']) ? tachan_norm_medium((string) $_GET['medium']) : $pref['medium'];
$days = planner_days();
$school = school_row($sid) ?: [];
$schoolName = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? 'शाळा'));
$sanstha = (string) ($school['sanstha'] ?? '');
$udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));
$kendra = (string) ($school['kendra'] ?? ($school['center'] ?? ''));
$taluka = (string) ($school['taluka'] ?? '');
$district = (string) ($school['district'] ?? '');
$address = (string) ($school['address'] ?? ($school['address_line1'] ?? ''));

// Resolve Theme & Font from CCE / HPC Engine
$allThemes = function_exists('cce_theme_registry') ? cce_theme_registry() : [
    'sahyadri' => ['name' => '🌊 सह्याद्री इंडिगो (Royal Blue)', 'border' => '#1E3A8A', 'head_bg' => '#1E3A8A', 'light_bg' => '#F0F7FF', 'head_text' => '#FFFFFF', 'accent' => '#2563EB', 'badge_bg' => '#DBEAFE', 'badge_text' => '#1E3A8A'],
    'pro'      => ['name' => '🟩 TechGuruji प्रो (Emerald Green)', 'border' => '#0F5A43', 'head_bg' => '#0F5A43', 'light_bg' => '#F1F8F5', 'head_text' => '#FFFFFF', 'accent' => '#167A5B', 'badge_bg' => '#E4F2EC', 'badge_text' => '#0B3D2E'],
    'dnyan'    => ['name' => '🌅 ज्ञानदीप अंबर (Terracotta)', 'border' => '#C2410C', 'head_bg' => '#C2410C', 'light_bg' => '#FFF7ED', 'head_text' => '#FFFFFF', 'accent' => '#EA580C', 'badge_bg' => '#FED7AA', 'badge_text' => '#9A3412'],
    'shivraj'  => ['name' => '💎 शिवतीर्थ नीलम (Sapphire Teal)', 'border' => '#0D9488', 'head_bg' => '#0D9488', 'light_bg' => '#F0FDFA', 'head_text' => '#FFFFFF', 'accent' => '#0F766E', 'badge_bg' => '#99F6E4', 'badge_text' => '#115E59'],
    'orchid'   => ['name' => '🌺 कस्तुरी ऑर्चिड (Regal Purple)', 'border' => '#701A75', 'head_bg' => '#701A75', 'light_bg' => '#FDF4FF', 'head_text' => '#FFFFFF', 'accent' => '#A21CAF', 'badge_bg' => '#F0ABFC', 'badge_text' => '#701A75'],
    'modern'   => ['name' => '⚡ प्रगती मॉडर्न (Slate Carbon)', 'border' => '#334155', 'head_bg' => '#334155', 'light_bg' => '#F8FAFC', 'head_text' => '#FFFFFF', 'accent' => '#059669', 'badge_bg' => '#E2E8F0', 'badge_text' => '#1E293B'],
];

$allFonts = function_exists('cce_font_registry') ? cce_font_registry() : [
    'mukta' => ['name' => 'मुक्ता (Mukta)', 'family' => "'Mukta', sans-serif", 'google_url' => 'https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&display=swap'],
    'tiro'  => ['name' => 'तिरो देवनागरी (Tiro Devanagari)', 'family' => "'Tiro Devanagari Marathi', serif", 'google_url' => 'https://fonts.googleapis.com/css2?family=Tiro+Devanagari+Marathi:ital@0;1&display=swap'],
    'baloo' => ['name' => 'बालू २ (Baloo 2)', 'family' => "'Baloo 2', cursive", 'google_url' => 'https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&display=swap'],
    'noto'  => ['name' => 'नोटो सॅन्स (Noto Sans)', 'family' => "'Noto Sans Devanagari', sans-serif", 'google_url' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800&display=swap'],
    'rozha' => ['name' => 'रोझा वन (Rozha One)', 'family' => "'Rozha One', serif", 'google_url' => 'https://fonts.googleapis.com/css2?family=Rozha+One&display=swap'],
];

$themeKey = function_exists('cce_current_theme_key') ? cce_current_theme_key() : 'sahyadri';
if (isset($_GET['theme']) && isset($allThemes[$_GET['theme']])) {
    $themeKey = $_GET['theme'];
}
$curTheme = $allThemes[$themeKey] ?? $allThemes['sahyadri'];

$fontKey = function_exists('cce_current_font_key') ? cce_current_font_key() : 'mukta';
if (isset($_GET['font']) && isset($allFonts[$_GET['font']])) {
    $fontKey = $_GET['font'];
}
$curFont = $allFonts[$fontKey] ?? $allFonts['mukta'];

// School logo resolution (base64 embedded for 100% offline & print reliability)
$logoUrl = '';
$candidates = [];
if (!empty($school['logo'])) $candidates[] = (string)$school['logo'];
if (!empty($school['logo_url'])) $candidates[] = (string)$school['logo_url'];
if (!empty($school['school_logo'])) $candidates[] = (string)$school['school_logo'];

$roots = [
    __DIR__ . '/../../',
    dirname(dirname(__DIR__)),
    !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : ''
];

foreach ($candidates as $cand) {
    if (str_starts_with($cand, 'data:') || str_starts_with($cand, 'http://') || str_starts_with($cand, 'https://')) {
        $logoUrl = $cand;
        break;
    }
    $clean = ltrim($cand, '/');
    $basename = basename($clean);
    foreach ($roots as $root) {
        if (!$root) continue;
        $paths = [
            $root . '/' . $clean,
            $root . '/uploads/' . $basename,
            $root . '/uploads/logos/' . $basename,
            $root . '/uploads/photos/' . $basename,
            $root . '/assets/images/' . $basename,
        ];
        foreach ($paths as $p) {
            if (@file_exists($p) && @is_file($p)) {
                $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                $mime = match($ext) {
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    'webp' => 'image/webp',
                    default => 'image/jpeg'
                };
                $b64 = @base64_encode((string)@file_get_contents($p));
                if ($b64 !== '') {
                    $logoUrl = 'data:' . $mime . ';base64,' . $b64;
                    break 3;
                }
            }
        }
    }
}

if ($logoUrl === '') {
    foreach (['techguruji_logo.png', 'logo.png', 'favicon.png'] as $def) {
        foreach ($roots as $root) {
            if (!$root) continue;
            $p = $root . '/assets/images/' . $def;
            if (@file_exists($p) && @is_file($p)) {
                $b64 = @base64_encode((string)@file_get_contents($p));
                if ($b64 !== '') {
                    $logoUrl = 'data:image/png;base64,' . $b64;
                    break 2;
                }
            }
        }
    }
}

$disp = static fn(string $s): string => tachan_display_subject($s, $medium);
$wk = planner_periods(1); // 1 to 9 periods
$sat = planner_periods(6); // 1 to 6 periods
$bw = planner_breaks(1); // 4 => मोठी सुट्टी १.१५ ते १.५५, 7 => लहान सुट्टी ३.४० ते ३.५०
$bs = planner_breaks(6); // 4 => मोठी सुट्टी ९.५० ते १०.२०

if ($mode === 'teacher') {
    $teacherName = (string)(($u['name_mr'] ?? '') ?: ($u['name'] ?? 'शिक्षक'));
    $teacherGrid = tachan_teacher_timetable_get($pdo, $sid, (int) $u['id']);
    if (empty($teacherGrid)) {
        $teacherGrid = planner_get_teacher_template_grid('6', $medium);
    }
    $titleLabel = 'शिक्षकाचे तासिका वेळापत्रक';

    // Period summary
    $subCounts = [];
    $clsCounts = [];
    $total = 0;
    foreach ($teacherGrid as $d => $ps) {
        foreach ($ps as $p => $slot) {
            $s = trim((string)($slot['subject'] ?? ''));
            $c = trim((string)($slot['class_name'] ?? ''));
            if ($s !== '') {
                $subName = $disp($s);
                $subCounts[$subName] = ($subCounts[$subName] ?? 0) + 1;
                $clsName = $c !== '' ? $c : 'इतर';
                $clsCounts[$clsName] = ($clsCounts[$clsName] ?? 0) + 1;
                $total++;
            }
        }
    }
} else {
    $sel = isset($_GET['class_id']) && isset($byId[(int) $_GET['class_id']]) ? (int) $_GET['class_id'] : (int) ($pref['class_id'] ?? ($classes[0]['id'] ?? 0));
    $class = $byId[$sel] ?? null;
    if (!$class) { http_response_code(404); exit('वर्ग सापडला नाही'); }
    $teacherName = resolve_teacher_name($class, $sid, $u);
    $grid = tachan_timetable($pdo, $sid, $class);
    $titleLabel = 'इयत्ता ' . class_label(planner_class_std($class)) . ' वेळापत्रक';

    $subCounts = [];
    $total = 0;
    foreach ($grid as $d => $ps) {
        foreach ($ps as $s) {
            $s = $disp(trim((string) $s));
            if ($s !== '') {
                $subCounts[$s] = ($subCounts[$s] ?? 0) + 1;
                $total++;
            }
        }
    }
}

print_start('वेळापत्रक — ' . $titleLabel, [
    'size' => 'A4 landscape',
    'margin' => '3mm 5mm 3mm 5mm',
    'compact' => true,
    'raw_header' => true
]);
?>
<!-- Web Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= $curFont['google_url'] ?>" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800;900&family=Martel:wght@700;800;900&display=swap" rel="stylesheet">
<?php if (isset($_GET['dl_pdf'])): ?><script>window.addEventListener('load',function(){window.print();});</script><?php endif; ?>

<style>
:root {
  --theme-border: <?= $curTheme['border'] ?>;
  --theme-head-bg: <?= $curTheme['border'] ?>;
  --theme-soft-bg: <?= $curTheme['light_bg'] ?>;
  --theme-badge-bg: <?= $curTheme['badge_bg'] ?>;
  --theme-badge-text: <?= $curTheme['badge_text'] ?>;
  --theme-accent: <?= $curTheme['accent'] ?>;
  --theme-font: <?= $curFont['family'] ?>;
}

@page {
  size: A4 landscape;
  margin: 3mm 5mm 3mm 5mm;
}

html, body {
  font-family: var(--theme-font), 'Mukta', 'Noto Sans Devanagari', -apple-system, sans-serif !important;
  -webkit-font-smoothing: antialiased;
  color: #0f172a;
  margin: 0 !important;
  padding: 0 !important;
  background: #ffffff;
  line-height: 1.22;
}
body > header { display: none !important; }

/* THEME & LAYOUT TOOLBAR (SCREEN ONLY) */
.theme-toolbar {
  background: #0f172a;
  color: #f8fafc;
  padding: 7px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  margin-bottom: 5px;
  font-family: 'Mukta', sans-serif;
}
.theme-toolbar select {
  background: #1e293b;
  color: #fff;
  border: 1px solid #475569;
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 13px;
  margin-left: 4px;
  cursor: pointer;
  font-family: inherit;
}
.theme-toolbar .print-btn {
  background: #eab308;
  color: #0f172a;
  font-weight: 800;
  border: 0;
  border-radius: 6px;
  padding: 5px 16px;
  font-size: 13.5px;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.theme-toolbar .print-btn:hover {
  background: #facc15;
}

/* FULL PAGE CONTAINER - FILLS COMPLETE A4 LANDSCAPE (NO EMPTY SPACE) */
.tt {
  font-size: 11.5px;
  color: #0f172a;
  width: 100%;
  max-width: 287mm;
  min-height: 198mm;
  box-sizing: border-box;
  background: #ffffff;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

/* PRO HEADER */
.pro-hdr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 2px solid var(--theme-border);
  border-radius: 7px;
  background: linear-gradient(180deg, #ffffff 0%, var(--theme-soft-bg) 100%);
  padding: 4px 12px;
  margin-bottom: 4px;
  gap: 12px;
}
.pro-hdr .logo-box {
  flex: 0 0 60px;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
}
.pro-hdr .logo-img {
  max-height: 54px;
  max-width: 60px;
  object-fit: contain;
  border-radius: 4px;
}
.pro-hdr .cnt-box {
  flex: 1 1 auto;
  text-align: center;
  padding: 0 6px;
}
.pro-hdr .sanstha-lbl {
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}
.pro-hdr .sch-name {
  font-family: 'Martel', 'Mukta', serif;
  font-size: 19px;
  font-weight: 900;
  color: var(--theme-border);
  margin: 1px 0;
  letter-spacing: 0.4px;
  line-height: 1.15;
}
.pro-hdr .loc-sub {
  font-size: 11px;
  color: #334155;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
}
.pro-hdr .udise-tag {
  background: #1e293b;
  color: #ffffff;
  padding: 0px 6px;
  border-radius: 3px;
  font-weight: 800;
  font-size: 10.5px;
}
.pro-hdr .ribbon-bar {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 2px;
}
.pro-hdr .main-title-badge {
  background: var(--theme-head-bg);
  color: #ffffff;
  font-weight: 800;
  font-size: 12.5px;
  padding: 2px 12px;
  border-radius: 4px;
  letter-spacing: 0.4px;
}
.pro-hdr .sub-title-badge {
  background: var(--theme-badge-bg);
  color: var(--theme-badge-text);
  border: 1px solid var(--theme-border);
  font-weight: 800;
  font-size: 12px;
  padding: 2px 10px;
  border-radius: 4px;
}

.pro-hdr .info-card {
  flex: 0 0 195px;
  background: #ffffff;
  border: 1.5px solid var(--theme-border);
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 11px;
  line-height: 1.35;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.pro-hdr .info-row {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #1e293b;
}
.pro-hdr .info-row.highlight {
  border-top: 1px dashed #cbd5e1;
  margin-top: 2px;
  padding-top: 2px;
  color: var(--theme-border);
  font-weight: 800;
}
.pro-hdr .period-chip {
  background: var(--theme-badge-bg);
  color: var(--theme-badge-text);
  padding: 0 6px;
  border-radius: 3px;
  font-weight: 800;
}

/* TIMETABLE TABLES - LARGE BOLD VISIBLE TEXT (EXPANDED TO FULL HEIGHT) */
table.tt-tbl {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: 12px;
  border: 2px solid var(--theme-border);
  flex: 1 0 auto;
}
table.tt-tbl th, table.tt-tbl td {
  border: 1.5px solid #64748b;
  padding: 8.5px 3.5px !important;
  text-align: center;
  word-wrap: break-word;
}
table.tt-tbl th {
  background: var(--theme-head-bg) !important;
  color: #ffffff !important;
  font-weight: 800 !important;
  font-size: 14px !important;
  padding: 9px 3.5px !important;
  border: 1.5px solid var(--theme-border) !important;
}
table.tt-tbl td.p-num {
  background: var(--theme-soft-bg);
  font-weight: 900 !important;
  color: var(--theme-border) !important;
  font-size: 16px !important;
}
table.tt-tbl td.brk {
  background: #fef3c7 !important;
  font-weight: 900 !important;
  color: #78350f !important;
  font-size: 13.5px !important;
  padding: 7.5px 3.5px !important;
  border: 1.5px solid #f59e0b !important;
  letter-spacing: 0.3px;
}

/* SUBJECT & TIME TYPOGRAPHY */
.subj-name {
  font-size: 14.5px !important;
  font-weight: 800 !important;
  color: #0f172a !important;
  line-height: 1.3 !important;
  letter-spacing: 0.15px;
  display: block;
}
.time-lbl {
  font-size: 12px !important;
  font-weight: 700 !important;
  color: #1e293b !important;
  white-space: nowrap !important;
}
.c-badge {
  font-size: 10.5px !important;
  font-weight: 800 !important;
  color: var(--theme-badge-text) !important;
  background: var(--theme-badge-bg) !important;
  border: 1px solid var(--theme-border) !important;
  border-radius: 3px;
  padding: 1px 5px !important;
  display: inline-block;
  margin-bottom: 2px;
}
.paripath-lbl {
  font-size: 13px !important;
  font-weight: 800 !important;
  color: #0f172a !important;
}

/* SIDE-BY-SIDE SPLIT LAYOUT */
.tt-split-wrap {
  display: flex;
  gap: 7px;
  align-items: stretch;
  width: 100%;
  margin-bottom: 3px;
}
.tt-pane-week {
  flex: 1 1 73%;
  min-width: 0;
}
.tt-pane-sat {
  flex: 0 0 26.5%;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.pane-title-badge {
  background: var(--theme-border);
  color: #ffffff;
  font-weight: 800;
  font-size: 12.5px;
  padding: 4px 8px;
  text-align: center;
  border-top-left-radius: 5px;
  border-top-right-radius: 5px;
  letter-spacing: 0.3px;
}

/* SATURDAY COMPLETION CARD */
.sat-closure-card {
  border: 1.5px dashed var(--theme-border);
  border-radius: 6px;
  background: linear-gradient(180deg, #ffffff 0%, var(--theme-soft-bg) 100%);
  padding: 7px 8px;
  margin-top: 5px;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: center;
  font-size: 11px;
}
.sat-closure-title {
  color: #0f172a;
  font-weight: 800;
  font-size: 12.5px;
  margin-bottom: 3px;
}
.sat-closure-acts {
  font-size: 10.5px;
  color: #334155;
  font-weight: 600;
  line-height: 1.35;
}

/* COMBINED GRID SATURDAY CLOSURE CELL */
.sat-closed-cell {
  background: #f8fafc !important;
  border: 1.5px dashed #94a3b8 !important;
  vertical-align: middle !important;
  padding: 8px 4px !important;
}

/* NOTE & SUMMARY */
.tt-note {
  margin: 3px 0 2px;
  font-size: 10px !important;
  font-weight: 600 !important;
  color: #334155;
  line-height: 1.25;
}
table.sum {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: 11.5px;
  margin-top: 4px;
  border: 1.5px solid #64748b;
}
table.sum th, table.sum td {
  border: 1px solid #94a3b8;
  padding: 5px 3.5px !important;
  text-align: center;
  word-wrap: break-word;
}
table.sum th {
  background: var(--theme-soft-bg);
  color: var(--theme-border);
  font-weight: 800;
  font-size: 12px;
}

/* FOOTER */
.ft-box {
  margin-top: 5px;
  border-top: 1.5px solid var(--theme-border);
  padding-top: 4px;
}
.ft-cert {
  font-size: 10px;
  color: #475569;
  font-weight: 600;
  text-align: center;
  margin-bottom: 4px;
}
table.ft {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px !important;
}
table.ft td {
  border: 0;
  padding: 1px 4px;
  vertical-align: top;
}
.sig-title {
  font-weight: 800 !important;
  color: #0f172a !important;
  font-size: 12.5px !important;
  margin-bottom: 45px !important; /* Generous stamp and physical signature space */
}
.sig-name {
  font-weight: 700 !important;
  color: #334155 !important;
  font-size: 11.5px !important;
  border-top: 1.5px dashed #64748b;
  padding-top: 4px;
  display: inline-block;
  min-width: 160px;
}

@media print {
  @page { size: A4 landscape; margin: 3.5mm 5mm 3.5mm 5mm; }
  html, body {
    width: 100% !important;
    height: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
    overflow: hidden !important;
  }
  .tt {
    width: 100% !important;
    max-width: 100% !important;
    height: 198mm !important;
    min-height: 198mm !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
  .no-print { display: none !important; }
}
</style>

<!-- SCREEN TOOLBAR -->
<div class="no-print theme-toolbar">
  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
    <strong style="color: #facc15;">🎨 वेळापत्रक पर्याय:</strong>
    <label>लेआउट:
      <select onchange="updateParam('layout', this.value)">
        <option value="split" <?= $layout === 'split' ? 'selected' : '' ?>>⚡ स्वतंत्र शनिवार (शिफारस)</option>
        <option value="combined" <?= $layout === 'combined' ? 'selected' : '' ?>>📋 एकत्रित तक्ता (Combined)</option>
      </select>
    </label>
    <label>थीम:
      <select onchange="updateParam('theme', this.value)">
        <?php foreach ($allThemes as $tk => $tv): ?>
          <option value="<?= $tk ?>" <?= $tk === $themeKey ? 'selected' : '' ?>><?= $tv['name'] ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>फॉन्ट:
      <select onchange="updateParam('font', this.value)">
        <?php foreach ($allFonts as $fk => $fv): ?>
          <option value="<?= $fk ?>" <?= $fk === $fontKey ? 'selected' : '' ?>><?= $fv['name'] ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div>
    <button class="print-btn" onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button>
  </div>
</div>

<div class="tt">
<!-- PRO HEADER -->
<div class="pro-hdr">
  <div class="logo-box">
    <?php if (!empty($logoUrl)): ?>
      <img src="<?= e($logoUrl) ?>" class="logo-img" alt="शाळेचा लोगो" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
      <div style="display:none;">
        <svg viewBox="0 0 100 100" width="54" height="54">
          <circle cx="50" cy="50" r="46" fill="<?= $curTheme['border'] ?>" stroke="#d97706" stroke-width="3"/>
          <circle cx="50" cy="50" r="40" fill="#ffffff" stroke="<?= $curTheme['border'] ?>" stroke-width="1.5"/>
          <path d="M50 16 L53 23 L60 24 L55 29 L56 36 L50 32 L44 36 L45 29 L40 24 L47 23 Z" fill="#d97706"/>
          <path d="M30 60 C38 56 45 57 50 60 C55 57 62 56 70 60 L70 42 C62 38 55 39 50 42 C45 39 38 38 30 42 Z" fill="#f8fafc" stroke="<?= $curTheme['border'] ?>" stroke-width="1.5"/>
          <path d="M50 42 L50 60" stroke="<?= $curTheme['border'] ?>" stroke-width="1.5"/>
          <path d="M50 30 C48 34 46 37 50 41 C54 37 52 34 50 30 Z" fill="#ea580c"/>
          <rect x="22" y="68" width="56" height="11" rx="2" fill="<?= $curTheme['border'] ?>"/>
          <text x="50" y="76" fill="#ffffff" font-family="'Mukta', sans-serif" font-size="6.5" font-weight="700" text-anchor="middle">ज्ञान हेच बळ</text>
        </svg>
      </div>
    <?php else: ?>
      <div>
        <svg viewBox="0 0 100 100" width="54" height="54">
          <circle cx="50" cy="50" r="46" fill="<?= $curTheme['border'] ?>" stroke="#d97706" stroke-width="3"/>
          <circle cx="50" cy="50" r="40" fill="#ffffff" stroke="<?= $curTheme['border'] ?>" stroke-width="1.5"/>
          <path d="M50 16 L53 23 L60 24 L55 29 L56 36 L50 32 L44 36 L45 29 L40 24 L47 23 Z" fill="#d97706"/>
          <path d="M30 60 C38 56 45 57 50 60 C55 57 62 56 70 60 L70 42 C62 38 55 39 50 42 C45 39 38 38 30 42 Z" fill="#f8fafc" stroke="<?= $curTheme['border'] ?>" stroke-width="1.5"/>
          <path d="M50 42 L50 60" stroke="<?= $curTheme['border'] ?>" stroke-width="1.5"/>
          <path d="M50 30 C48 34 46 37 50 41 C54 37 52 34 50 30 Z" fill="#ea580c"/>
          <rect x="22" y="68" width="56" height="11" rx="2" fill="<?= $curTheme['border'] ?>"/>
          <text x="50" y="76" fill="#ffffff" font-family="'Mukta', sans-serif" font-size="6.5" font-weight="700" text-anchor="middle">ज्ञान हेच बळ</text>
        </svg>
      </div>
    <?php endif; ?>
  </div>

  <div class="cnt-box">
    <?php if ($sanstha !== ''): ?><div class="sanstha-lbl"><?= e($sanstha) ?></div><?php endif; ?>
    <h1 class="sch-name"><?= e($schoolName) ?></h1>
    <div class="loc-sub">
      <?php if ($kendra !== ''): ?><span><strong>केंद्र:</strong> <?= e($kendra) ?></span><?php endif; ?>
      <?php if ($taluka !== ''): ?><span><strong>तालुका:</strong> <?= e($taluka) ?></span><?php endif; ?>
      <?php if ($district !== ''): ?><span><strong>जिल्हा:</strong> <?= e($district) ?></span><?php endif; ?>
      <?php if ($udise !== ''): ?><span class="udise-tag">UDISE: <?= e($udise) ?></span><?php endif; ?>
    </div>
    <div class="ribbon-bar">
      <span class="main-title-badge">वेळापत्रक २०२६-२७</span>
      <span class="sub-title-badge"><?= e($titleLabel) ?> (<?= $medium === 'semi_english' ? 'सेमी-इंग्रजी' : 'मराठी माध्यम' ?>)</span>
    </div>
  </div>

  <div class="info-card">
    <div class="info-row"><strong>प्रकार:</strong> <?= $mode === 'teacher' ? 'शिक्षकाचा कार्यभार' : 'वर्गनिहाय वेळापत्रक' ?></div>
    <div class="info-row"><strong><?= $mode === 'teacher' ? 'शिक्षक' : 'वर्गशिक्षक' ?>:</strong> <?= e($teacherName) ?></div>
    <?php if ($mode === 'teacher' && !empty($u['role'])): ?>
    <div class="info-row"><strong>पदनाम:</strong> <?= e($u['role'] === 'teacher' ? 'सहाय्यक शिक्षक' : $u['role']) ?></div>
    <?php endif; ?>
    <div class="info-row highlight"><strong>साप्ताहिक तासिका:</strong> <span class="period-chip"><?= (int)$total ?> तासिका</span></div>
  </div>
</div>

<?php if ($layout === 'split'): ?>
<!-- ========================================== -->
<!-- LAYOUT 1: SEPARATED SATURDAY (SIDE-BY-SIDE) -->
<!-- ========================================== -->
<div class="tt-split-wrap">
  <!-- LEFT PANE: MONDAY TO FRIDAY (PERIODS 1 TO 9) -->
  <div class="tt-pane-week">
    <table class="tt-tbl">
      <thead>
        <tr>
          <th style="width:7.5%">तासिका</th>
          <th style="width:14.5%">वेळ (सो-शु)</th>
          <?php for ($d = 1; $d <= 5; $d++): ?>
            <th style="width:15.6%"><?= e($days[$d]) ?></th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <!-- Assembly -->
        <tr>
          <td class="p-num">*</td>
          <td class="time-lbl"><?= e(planner_pariphath_time(1)) ?></td>
          <?php for ($d = 1; $d <= 5; $d++): ?>
            <td class="paripath-lbl">परिपाठ व राष्ट्रगीत / प्रतिज्ञा</td>
          <?php endfor; ?>
        </tr>

        <!-- Periods 1 to 9 -->
        <?php for ($p = 1; $p <= 9; $p++): ?>
        <tr>
          <td class="p-num"><?= $p ?></td>
          <td class="time-lbl"><?= e($wk[$p] ?? '') ?></td>
          <?php for ($d = 1; $d <= 5; $d++): ?>
            <td>
              <?php if ($mode === 'teacher'): 
                $sl = $teacherGrid[$d][$p] ?? [];
                $sb = trim((string)($sl['subject'] ?? ''));
                $cn = trim((string)($sl['class_name'] ?? ''));
                if ($sb !== ''): ?>
                  <span class="c-badge"><?= e($cn) ?></span>
                  <span class="subj-name"><?= e($disp($sb)) ?></span>
                <?php else: ?>—<?php endif; ?>
              <?php else: 
                $sb = (string)($grid[$d][$p] ?? '');
                if ($sb !== ''): ?>
                  <span class="subj-name"><?= e($disp($sb)) ?></span>
                <?php else: ?>—<?php endif; ?>
              <?php endif; ?>
            </td>
          <?php endfor; ?>
        </tr>

        <?php if (isset($bw[$p])): ?>
        <tr>
          <td class="brk" colspan="7">☕ <?= e($bw[$p]) ?></td>
        </tr>
        <?php endif; ?>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <!-- RIGHT PANE: SATURDAY MORNING SESSION (PERIODS 1 TO 6) -->
  <div class="tt-pane-sat">
    <table class="tt-tbl">
      <thead>
        <tr>
          <th colspan="3" style="background: var(--theme-border) !important; color:#fff !important; font-size:13.5px !important; letter-spacing:0.3px;">
            शनिवार (सकाळ सत्र)
          </th>
        </tr>
        <tr>
          <th style="width:20%">तासिका</th>
          <th style="width:40%">वेळ (शनि)</th>
          <th style="width:40%">विषय <?= $mode === 'teacher' ? '/ वर्ग' : '' ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="p-num">*</td>
          <td class="time-lbl"><?= e(planner_pariphath_time(6)) ?></td>
          <td class="paripath-lbl">परिपाठ व दिनविशेष</td>
        </tr>

        <?php for ($p = 1; $p <= 6; $p++): ?>
        <tr>
          <td class="p-num"><?= $p ?></td>
          <td class="time-lbl"><?= e($sat[$p] ?? '') ?></td>
          <td>
            <?php if ($mode === 'teacher'): 
              $sl = $teacherGrid[6][$p] ?? [];
              $sb = trim((string)($sl['subject'] ?? ''));
              $cn = trim((string)($sl['class_name'] ?? ''));
              if ($sb !== ''): ?>
                <span class="c-badge"><?= e($cn) ?></span>
                <span class="subj-name"><?= e($disp($sb)) ?></span>
              <?php else: ?>—<?php endif; ?>
            <?php else: 
              $sb = (string)($grid[6][$p] ?? '');
              if ($sb !== ''): ?>
                <span class="subj-name"><?= e($disp($sb)) ?></span>
              <?php else: ?>—<?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>

        <?php if (isset($bs[$p])): ?>
        <tr>
          <td class="brk" colspan="3">☕ <?= e($bs[$p]) ?></td>
        </tr>
        <?php endif; ?>
        <?php endfor; ?>
      </tbody>
    </table>

    <!-- Saturday End & Special Activities Box -->
    <div class="sat-closure-card">
      <div class="sat-closure-title">🔔 शाळा सुटली : सकाळी ११.३० वा.</div>
      <div class="sat-closure-acts">
        <strong>★ शनिवार विशेष उपक्रम:</strong><br>
        • आनंददायी शनिवार उपक्रम / AEP<br>
        • क्रीडा, कार्यानुभव व कला सराव<br>
        • वाचन कट्टा व जीवनकौशल्य वर्ग
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ========================================== -->
<!-- LAYOUT 2: COMBINED GRID (PERIODS 1 TO 9) -->
<!-- ========================================== -->
<table class="tt-tbl" style="margin-bottom: 3px;">
<thead>
  <tr>
    <th style="width:6%">तासिका</th>
    <th style="width:11%">वेळ (सो-शु)</th>
    <?php for ($d = 1; $d <= 5; $d++): ?><th style="width:12.4%"><?= e($days[$d]) ?></th><?php endfor; ?>
    <th style="width:10%">वेळ (शनि)</th>
    <th style="width:11%">शनिवार</th>
  </tr>
</thead>
<tbody>
  <tr>
    <td class="p-num">*</td>
    <td class="time-lbl"><?= e(planner_pariphath_time(1)) ?></td>
    <?php for ($d = 1; $d <= 5; $d++): ?><td class="paripath-lbl">परिपाठ व प्रार्थना</td><?php endfor; ?>
    <td class="time-lbl"><?= e(planner_pariphath_time(6)) ?></td>
    <td class="paripath-lbl">परिपाठ</td>
  </tr>

  <?php for ($p = 1; $p <= 9; $p++): ?>
  <tr>
    <td class="p-num"><?= $p ?></td>
    <td class="time-lbl"><?= e($wk[$p] ?? '') ?></td>
    <?php for ($d = 1; $d <= 5; $d++): ?>
      <td>
        <?php if ($mode === 'teacher'): 
          $sl = $teacherGrid[$d][$p] ?? [];
          $sb = trim((string)($sl['subject'] ?? ''));
          $cn = trim((string)($sl['class_name'] ?? ''));
          if ($sb !== ''): ?>
            <span class="c-badge"><?= e($cn) ?></span>
            <span class="subj-name"><?= e($disp($sb)) ?></span>
          <?php else: ?>—<?php endif; ?>
        <?php else: 
          $sb = (string)($grid[$d][$p] ?? '');
          if ($sb !== ''): ?>
            <span class="subj-name"><?= e($disp($sb)) ?></span>
          <?php else: ?>—<?php endif; ?>
        <?php endif; ?>
      </td>
    <?php endfor; ?>

    <!-- Saturday column for Period 1 to 6 -->
    <?php if ($p <= 6): ?>
      <td class="time-lbl"><?= e($sat[$p] ?? '') ?></td>
      <td>
        <?php if ($mode === 'teacher'): 
          $sl = $teacherGrid[6][$p] ?? [];
          $sb = trim((string)($sl['subject'] ?? ''));
          $cn = trim((string)($sl['class_name'] ?? ''));
          if ($sb !== ''): ?>
            <span class="c-badge"><?= e($cn) ?></span>
            <span class="subj-name"><?= e($disp($sb)) ?></span>
          <?php else: ?>—<?php endif; ?>
        <?php else: 
          $sb = (string)($grid[6][$p] ?? '');
          if ($sb !== ''): ?>
            <span class="subj-name"><?= e($disp($sb)) ?></span>
          <?php else: ?>—<?php endif; ?>
        <?php endif; ?>
      </td>
    <?php elseif ($p === 7): ?>
      <!-- Rowspan for Periods 7, Break 2, 8, 9 -->
      <td colspan="2" rowspan="4" class="sat-closed-cell">
        <div style="font-size:12.5px; font-weight:900; color:var(--theme-border); margin-bottom:4px;">
          🔔 शनिवार सकाळ सत्र समाप्ती
        </div>
        <div style="font-size:11px; font-weight:700; color:#475569; margin-bottom:6px;">
          (सकाळी ११.३० वाजता शाळा सुटली)
        </div>
        <div style="font-size:10.5px; font-weight:600; color:#0369a1; background:#e0f2fe; border:1px dashed #38bdf8; border-radius:4px; padding:4px;">
          ★ आनंददायी शनिवार उपक्रम / AEP / क्रीडा सराव
        </div>
      </td>
    <?php endif; ?>
  </tr>

  <!-- Break rows -->
  <?php if ($p === 4): ?>
    <tr>
      <td class="brk" colspan="7">☕ <?= e($bw[4] ?? 'मोठी सुट्टी १.१५ ते १.५५') ?></td>
      <td class="brk" colspan="2">☕ <?= e($bs[4] ?? 'मोठी सुट्टी ९.५० ते १०.२०') ?></td>
    </tr>
  <?php elseif ($p === 7 && isset($bw[7])): ?>
    <tr>
      <td class="brk" colspan="7">☕ <?= e($bw[7]) ?></td>
    </tr>
  <?php endif; ?>

  <?php endfor; ?>
</tbody>
</table>
<?php endif; ?>

<div class="tt-note">
  <strong>टीप:</strong> सोमवार ते शुक्रवार पूर्णवेळ सत्र (१०.३० ते ५.००, एकूण ९ तासिका). शनिवार सकाळ सत्र (७.०० ते ११.३०, एकूण ६ तासिका). AEP - अतिरिक्त समृद्धीकरण तासिका / उपचारात्मक अध्यापन / स्पर्धा परीक्षा सराव.
</div>

<!-- WORKLOAD SUMMARY -->
<table class="sum">
  <tr>
    <th style="width:14%">विषयनिहाय तासिका</th>
    <?php foreach ($subCounts as $s => $n): ?><th><?= e((string) $s) ?></th><?php endforeach; ?>
    <th style="width:10%">एकूण तासिका</th>
  </tr>
  <tr>
    <th style="font-weight:700;">साप्ताहिक भारांश</th>
    <?php foreach ($subCounts as $n): ?><td><?= (int) $n ?></td><?php endforeach; ?>
    <td><strong style="color:var(--theme-border); font-size:12px;"><?= (int) $total ?></strong></td>
  </tr>
</table>

<?php if ($mode === 'teacher' && !empty($clsCounts)): ?>
<table class="sum" style="margin-top: 2px;">
  <tr>
    <th style="width:14%">वर्गनिहाय वाटप</th>
    <?php foreach ($clsCounts as $cn => $cnt): ?><th><?= e((string) $cn) ?></th><?php endforeach; ?>
    <th style="width:10%">एकूण</th>
  </tr>
  <tr>
    <th style="font-weight:700;">साप्ताहिक तासिका</th>
    <?php foreach ($clsCounts as $cnt): ?><td><?= (int) $cnt ?></td><?php endforeach; ?>
    <td><strong style="color:var(--theme-border); font-size:12px;"><?= (int) $total ?></strong></td>
  </tr>
</table>
<?php endif; ?>

<!-- PRO FOOTER & ENDORSEMENTS -->
<div class="ft-box">
  <div class="ft-cert">
    ★ प्रमाणित करण्यात येते की, हे वेळापत्रक बालकांच्या मोफत व सक्तीच्या शिक्षणाचा अधिकार (RTE) व महाराष्ट्र शासन शिक्षण विभागाच्या निकषांनुसार तयार केलेले आहे.
  </div>
  <table class="ft">
    <tr>
      <td style="width:33%; text-align:left;">
        <div class="sig-title"><?= $mode === 'teacher' ? 'विषय शिक्षक स्वाक्षरी' : 'वर्गशिक्षक स्वाक्षरी' ?></div>
        <div class="sig-name"><?= e($teacherName) ?></div>
      </td>
      <td style="width:34%; text-align:center;">
        <div class="sig-title">वेळापत्रक समिती प्रमुख</div>
        <div class="sig-name">स्वाक्षरी व नाव</div>
      </td>
      <td style="width:33%; text-align:right;">
        <div class="sig-title">मुख्याध्यापक स्वाक्षरी व शिक्का</div>
        <div class="sig-name"><?= e($schoolName) ?></div>
      </td>
    </tr>
  </table>
</div>

<script>
function updateParam(k, v) {
    var url = new URL(window.location.href);
    url.searchParams.set(k, v);
    window.location.href = url.toString();
}

function fitTimetablePage() {
    var el = document.querySelector('.tt');
    if (!el) return;
    var maxH = 202 * 96 / 25.4; // 202mm available print height in A4 landscape
    var actualH = el.scrollHeight;
    if (actualH > maxH + 4) {
        var scale = Math.max(0.75, (maxH / actualH) * 0.99);
        if ('zoom' in el.style) {
            el.style.zoom = scale;
        } else {
            el.style.transform = 'scale(' + scale + ')';
            el.style.transformOrigin = 'top center';
        }
    }
}
window.addEventListener('load', fitTimetablePage);
window.addEventListener('beforeprint', fitTimetablePage);
</script>
</div>
<?php print_end(); ?>
