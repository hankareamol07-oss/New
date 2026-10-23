<?php
/**
 * CCE Theme, Font & Cover Template Helper
 * Unified theme engine for CCE Pragati Pustak, Progress Cards, Result Sheets, and Nondvahi
 */

if (!function_exists('cce_theme_registry')) {
    function cce_theme_registry() {
        return [
            'pro' => [
                'name'        => '🟩 TechGuruji प्रो (Professional Green)',
                'border'      => '#7FAF9C',
                'head_bg'     => '#0F5A43',
                'light_bg'    => '#F1F8F5',
                'head_text'   => '#FFFFFF',
                'accent'      => '#167A5B',
                'badge_bg'    => '#E4F2EC',
                'badge_text'  => '#0B3D2E',
                'sem_head'    => '#0B3D2E',
            ],
            'vidya' => [
                'name'        => '🌿 विद्या क्लासिक (Vidya Signature — फॉरेस्ट व सुवर्ण)',
                'border'      => '#0F4F3B',
                'head_bg'     => '#DCF0E7',
                'light_bg'    => '#F4FBF7',
                'head_text'   => '#0B3D2E',
                'accent'      => '#D97706',
                'badge_bg'    => '#DCF0E7',
                'badge_text'  => '#0F4F3B',
                'sem_head'    => '#0F4F3B',
            ],
            'sahyadri' => [
                'name'        => '🌊 सह्याद्री इंडिगो (Sahyadri Indigo — रॉयल ब्लू व आयव्हरी)',
                'border'      => '#1E3A8A',
                'head_bg'     => '#DBEAFE',
                'light_bg'    => '#F0F7FF',
                'head_text'   => '#172554',
                'accent'      => '#2563EB',
                'badge_bg'    => '#E0E7FF',
                'badge_text'  => '#1E3A8A',
                'sem_head'    => '#1E3A8A',
            ],
            'dnyan' => [
                'name'        => '🌅 ज्ञानदीप अंबर (Dnyandeep Amber — टेराकोटा व उबदार सोनेरी)',
                'border'      => '#C2410C',
                'head_bg'     => '#FFEDD5',
                'light_bg'    => '#FFF7ED',
                'head_text'   => '#7C2D12',
                'accent'      => '#EA580C',
                'badge_bg'    => '#FED7AA',
                'badge_text'  => '#9A3412',
                'sem_head'    => '#C2410C',
            ],
            'shivraj' => [
                'name'        => '💎 शिवतीर्थ नीलम (Shivteerth Sapphire — सागरी टील व सिल्व्हर)',
                'border'      => '#0D9488',
                'head_bg'     => '#CCFBF1',
                'light_bg'    => '#F0FDFA',
                'head_text'   => '#134E4A',
                'accent'      => '#0F766E',
                'badge_bg'    => '#99F6E4',
                'badge_text'  => '#115E59',
                'sem_head'    => '#0D9488',
            ],
            'orchid' => [
                'name'        => '🌺 कस्तुरी ऑर्चिड (Kasturi Orchid — रीगल पर्पल व रोज)',
                'border'      => '#701A75',
                'head_bg'     => '#F5D0FE',
                'light_bg'    => '#FDF4FF',
                'head_text'   => '#4A044E',
                'accent'      => '#A21CAF',
                'badge_bg'    => '#F0ABFC',
                'badge_text'  => '#701A75',
                'sem_head'    => '#701A75',
            ],
            'modern' => [
                'name'        => '⚡ प्रगती मॉडर्न (Pragati Slate — आधुनिक कार्बन व एमराल्ड)',
                'border'      => '#334155',
                'head_bg'     => '#E2E8F0',
                'light_bg'    => '#F8FAFC',
                'head_text'   => '#0F172A',
                'accent'      => '#059669',
                'badge_bg'    => '#E2E8F0',
                'badge_text'  => '#1E293B',
                'sem_head'    => '#334155',
            ],
        ];
    }
}

if (!function_exists('cce_font_registry')) {
    function cce_font_registry() {
        return [
            'mukta' => [
                'name'       => 'मुक्ता (Mukta - आधुनिक व स्वच्छ)',
                'family'     => "'Mukta', sans-serif",
                'google_url' => 'https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&display=swap',
            ],
            'noto' => [
                'name'       => 'नोटो सॅन्स (Noto Sans - अधिकृत)',
                'family'     => "'Mukta', sans-serif",
                'google_url' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800&display=swap',
            ],
            'baloo' => [
                'name'       => 'बालू २ (Baloo 2 - विद्यार्थीस्नेही)',
                'family'     => "'Mukta', cursive",
                'google_url' => 'https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&display=swap',
            ],
            'rozha' => [
                'name'       => 'रोझा वन (Rozha One - भव्य क्लासिक)',
                'family'     => "'Rozha One', serif",
                'google_url' => 'https://fonts.googleapis.com/css2?family=Rozha+One&display=swap',
            ],
            'yatra' => [
                'name'       => 'यात्रा वन (Yatra One - पारंपरिक सुलेखन)',
                'family'     => "'Yatra One', cursive",
                'google_url' => 'https://fonts.googleapis.com/css2?family=Yatra+One&display=swap',
            ],
            'tiro' => [
                'name'       => 'तिरो देवनागरी (Tiro Devanagari - पुस्तकी)',
                'family'     => "'Tiro Devanagari Marathi', serif",
                'google_url' => 'https://fonts.googleapis.com/css2?family=Tiro+Devanagari+Marathi:ital@0;1&display=swap',
            ],
        ];
    }
}

if (!function_exists('cce_bg_registry')) {
    function cce_bg_registry() {
        return [
            '1' => [
                'name' => '🎨 कव्हर १ : रॉयल ३D कव्हर (Royal 3D Cover)',
                'file' => 'assets/cce/card-01.png',
            ],
            '2' => [
                'name' => '🌟 कव्हर २ : बालमित्र क्रिएटिव्ह कव्हर (Creative Learning Cover)',
                'file' => 'assets/cce/card-02.png',
            ],
            '3' => [
                'name' => '✨ कव्हर ३ : TechGuruji प्रो कव्हर (Professional Green)',
                'file' => 'assets/cce/card-03.png',
            ],
        ];
    }
}

if (!function_exists('cce_current_theme_key')) {
    function cce_current_theme_key() {
        $themes = cce_theme_registry();
        $aliases = [
            'cyan'    => 'vidya',
            'navy'    => 'sahyadri',
            'emerald' => 'vidya',
            'maroon'  => 'dnyan',
            'saffron' => 'dnyan',
            'purple'  => 'orchid',
        ];

        if (isset($_GET['theme'])) {
            $raw = (string)$_GET['theme'];
            $key = $aliases[$raw] ?? $raw;
            if (isset($themes[$key])) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['cce_theme'] = $key;
                }
                return $key;
            }
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['cce_theme'])) {
            $raw = (string)$_SESSION['cce_theme'];
            $key = $aliases[$raw] ?? $raw;
            if (isset($themes[$key])) {
                return $key;
            }
        }
        return 'vidya';
    }
}

if (!function_exists('cce_current_font_key')) {
    function cce_current_font_key() {
        $fonts = cce_font_registry();
        if (isset($_GET['font']) && isset($fonts[$_GET['font']])) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['cce_font'] = $_GET['font'];
            }
            return $_GET['font'];
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['cce_font']) && isset($fonts[$_SESSION['cce_font']])) {
            return $_SESSION['cce_font'];
        }
        return 'mukta';
    }
}

if (!function_exists('cce_current_bg_key')) {
    function cce_current_bg_key() {
        $bgs = cce_bg_registry();
        if (isset($_GET['bg']) && isset($bgs[$_GET['bg']])) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['cce_bg'] = $_GET['bg'];
            }
            return $_GET['bg'];
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['cce_bg']) && isset($bgs[$_SESSION['cce_bg']])) {
            return $_SESSION['cce_bg'];
        }
        return '1';
    }
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('cce_get_bg_file')) {
    function cce_get_bg_file() {
        $bgs = cce_bg_registry();
        $key = cce_current_bg_key();
        return $bgs[$key]['file'] ?? 'assets/cce/card-01.png';
    }
}

if (!function_exists('cce_theme_head_tags')) {
    function cce_theme_head_tags() {
        $themes = cce_theme_registry();
        $fonts = cce_font_registry();
        $tKey = cce_current_theme_key();
        $fKey = cce_current_font_key();
        $theme = $themes[$tKey] ?? $themes['vidya'];
        $font = $fonts[$fKey] ?? $fonts['mukta'];

        $out = "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=5.0\">\n";
        $app_url = defined('APP_URL') ? APP_URL : '';
        $out .= "<link rel=\"icon\" type=\"image/svg+xml\" href=\"{$app_url}/favicon.svg\">\n";
        $out .= "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"{$app_url}/favicon.png\">\n";
        $out .= "<link rel=\"shortcut icon\" href=\"{$app_url}/favicon.ico\">\n";
        $out .= "<!-- सातत्यपूर्ण मूल्यमापन Dynamic Theme & Font -->\n";
        $out .= "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
        $out .= "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
        $out .= "<link href=\"{$font['google_url']}\" rel=\"stylesheet\">\n";
        $out .= "<style>\n";
        $out .= ":root {\n";
        $out .= "  --cce-theme-border: {$theme['border']};\n";
        $out .= "  --cce-theme-head-bg: {$theme['head_bg']};\n";
        $out .= "  --cce-theme-light-bg: {$theme['light_bg']};\n";
        $out .= "  --cce-theme-head-text: {$theme['head_text']};\n";
        $out .= "  --cce-theme-accent: {$theme['accent']};\n";
        $out .= "  --cce-theme-badge-bg: {$theme['badge_bg']};\n";
        $out .= "  --cce-theme-badge-text: {$theme['badge_text']};\n";
        $out .= "  --cce-theme-sem-head: {$theme['sem_head']};\n";
        $out .= "  --cce-font-family: {$font['family']};\n";
        $out .= "}\n";
        $out .= "body, table, th, td, select, input, button, .cce-page, .sh, .page, .sheet, .pc-sheet, .rte-card, .reg-sheet, .grd-sheet, .sheet-container, .card {\n";
        $out .= "  font-family: var(--cce-font-family) !important;\n";
        $out .= "}\n";
        $out .= "/* Theme overrides for सातत्यपूर्ण मूल्यमापन tables and report sheets */\n";
        $out .= "table.bk-att-table, table.bk-hlth-table, table.inside-table, table.sem-table, table.cce-table, table.nond-table, table.report-table,\n";
        $out .= "table.nk-table, table.sum-table, table.att, table.res, table.narr, table.idx-table, table.mk-table, table.nt-table,\n";
        $out .= "table.att-table, table.subj-table, table.rte-table, table.reg-table, table.annual-register, table.grd-table,\n";
        $out .= "table.marks-table, table.summary-table, table.index-table, table.comp-register, table.grade-matrix, table.enrolment-table, .card {\n";
        $out .= "  border-color: var(--cce-theme-border) !important;\n";
        $out .= "}\n";
        $script_basename = basename($_SERVER['PHP_SELF'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
        $is_pragati_pustak = ($script_basename === 'pragati_pustak.php');

        if (!$is_pragati_pustak) {
            $out .= "table.bk-att-table, table.bk-hlth-table, table.inside-table, table.sem-table, table.cce-table, table.nond-table, table.report-table,\n";
            $out .= "table.nk-table, table.sum-table, table.att, table.res, table.narr, table.idx-table, table.mk-table, table.nt-table,\n";
            $out .= "table.att-table, table.subj-table, table.rte-table, table.reg-table, table.annual-register, table.grd-table, table.marks-table,\n";
            $out .= "table.summary-table, table.index-table, table.comp-register, table.grade-matrix, table.enrolment-table {\n";
            $out .= "  border: 1px solid #111111 !important;\n";
            $out .= "  border-collapse: collapse !important;\n";
            $out .= "}\n";
            $out .= "table.bk-att-table th, table.bk-att-table td,\n";
            $out .= "table.bk-hlth-table th, table.bk-hlth-table td,\n";
            $out .= "table.inside-table th, table.inside-table td,\n";
            $out .= "table.sem-table th, table.sem-table td,\n";
            $out .= "table.cce-table th, table.cce-table td,\n";
            $out .= "table.nond-table th, table.nond-table td,\n";
            $out .= "table.report-table th, table.report-table td,\n";
            $out .= "table.nk-table th, table.nk-table td,\n";
            $out .= "table.sum-table th, table.sum-table td,\n";
            $out .= "table.att th, table.att td,\n";
            $out .= "table.res th, table.res td,\n";
            $out .= "table.narr th, table.narr td,\n";
            $out .= "table.idx-table th, table.idx-table td,\n";
            $out .= "table.mk-table th, table.mk-table td,\n";
            $out .= "table.nt-table th, table.nt-table td,\n";
            $out .= "table.att-table th, table.att-table td,\n";
            $out .= "table.subj-table th, table.subj-table td,\n";
            $out .= "table.rte-table th, table.rte-table td,\n";
            $out .= "table.reg-table th, table.reg-table td,\n";
            $out .= "table.annual-register th, table.annual-register td,\n";
            $out .= "table.grd-table th, table.grd-table td,\n";
            $out .= "table.marks-table th, table.marks-table td,\n";
            $out .= "table.summary-table th, table.summary-table td,\n";
            $out .= "table.index-table th, table.index-table td,\n";
            $out .= "table.comp-register th, table.comp-register td,\n";
            $out .= "table.grade-matrix th, table.grade-matrix td,\n";
            $out .= "table.enrolment-table th, table.enrolment-table td {\n";
            $out .= "  border: 1px solid #111111 !important;\n";
            $out .= "}\n";
        } else {
            $out .= "table.bk-att-table th, table.bk-att-table td,\n";
            $out .= "table.bk-hlth-table th, table.bk-hlth-table td,\n";
            $out .= "table.inside-table th, table.inside-table td,\n";
            $out .= "table.sem-table th, table.sem-table td,\n";
            $out .= "table.cce-table th, table.cce-table td,\n";
            $out .= "table.nond-table th, table.nond-table td,\n";
            $out .= "table.report-table th, table.report-table td,\n";
            $out .= "table.nk-table th, table.nk-table td,\n";
            $out .= "table.sum-table th, table.sum-table td,\n";
            $out .= "table.att th, table.att td,\n";
            $out .= "table.res th, table.res td,\n";
            $out .= "table.narr th, table.narr td,\n";
            $out .= "table.idx-table th, table.idx-table td,\n";
            $out .= "table.mk-table th, table.mk-table td,\n";
            $out .= "table.nt-table th, table.nt-table td,\n";
            $out .= "table.att-table th, table.att-table td,\n";
            $out .= "table.subj-table th, table.subj-table td,\n";
            $out .= "table.rte-table th, table.rte-table td,\n";
            $out .= "table.reg-table th, table.reg-table td,\n";
            $out .= "table.annual-register th, table.annual-register td,\n";
            $out .= "table.grd-table th, table.grd-table td,\n";
            $out .= "table.marks-table th, table.marks-table td,\n";
            $out .= "table.summary-table th, table.summary-table td,\n";
            $out .= "table.index-table th, table.index-table td,\n";
            $out .= "table.comp-register th, table.comp-register td,\n";
            $out .= "table.grade-matrix th, table.grade-matrix td,\n";
            $out .= "table.enrolment-table th, table.enrolment-table td {\n";
            $out .= "  border-color: var(--cce-theme-border) !important;\n";
            $out .= "}\n";
        }
        $out .= "table.bk-att-table thead th, table.bk-hlth-table thead th, table.inside-table thead th, table.sem-table thead th, table.cce-table thead th, table.nond-table thead th, table.report-table thead th,\n";
        $out .= "table.nk-table thead th, table.sum-table thead th, table.att thead th, table.att th, table.res thead th, table.narr thead th, table.narr th, table.idx-table thead th, table.mk-table thead th, table.nt-table thead th,\n";
        $out .= "table.att-table thead th, table.subj-table thead th, table.rte-table thead th, table.reg-table thead th, table.annual-register thead tr.main-head th, table.grd-table thead th, table.marks-table thead tr.trth, table.marks-table thead th, table.index-table thead th, table.summary-table th, table.comp-register thead th,\n";
        $out .= "table.grade-matrix thead th, table.enrolment-table thead th, table.enrolment-table th {\n";
        $out .= "  background: var(--cce-theme-head-bg) !important;\n";
        $out .= "  color: var(--cce-theme-head-text) !important;\n";
        $out .= "}\n";
        $out .= "table.bk-hlth-table th.hl-head-title, table.nk-table thead th.th-light, table.nk-table thead th.th-tot,\n";
        $out .= "table.annual-register thead tr.sub-head th, table.rte-table th.sub-th, table.reg-table td.tot-cell, table.marks-table th.sub-th {\n";
        $out .= "  background: var(--cce-theme-light-bg) !important;\n";
        $out .= "  color: var(--cce-theme-accent) !important;\n";
        $out .= "}\n";
        $out .= "table.sem-table tr.bg-tint td.sb-no,\n";
        $out .= "table.sem-table tr.bg-tint td.sb-name,\n";
        $out .= "table.sem-table tr.bg-tint td.sb-grd,\n";
        $out .= "table.sem-table td.note-head-cell {\n";
        $out .= "  background: var(--cce-theme-light-bg) !important;\n";
        $out .= "  color: var(--cce-theme-head-text) !important;\n";
        $out .= "}\n";
        $out .= ".sem-head, .sec-title, .report-subtitle, .sheet-header h2 {\n";
        $out .= "  color: var(--cce-theme-accent) !important;\n";
        $out .= "}\n";
        $out .= ".sch-header, .meta-line, .sheet-header, .meta-row {\n";
        $out .= "  border-bottom-color: var(--cce-theme-border) !important;\n";
        $out .= "}\n";
        $out .= ".cover-border {\n";
        $out .= "  border-color: var(--cce-theme-border) !important;\n";
        $out .= "}\n";
        $out .= ".cv-cyan-line {\n";
        $out .= "  background-color: var(--cce-theme-border) !important;\n";
        $out .= "}\n";
        $out .= "/* High-Visibility Print Page Toolbars */\n";
        $out .= ".bar, .toolbar, .tb, .pc-toolbar, .no-print-bar {\n";
        $out .= "  position: sticky !important;\n";
        $out .= "  top: 0 !important;\n";
        $out .= "  z-index: 9999 !important;\n";
        $out .= "  background: #17324d !important;\n";
        $out .= "  color: #ffffff !important;\n";
        $out .= "  padding: 10px 14px !important;\n";
        $out .= "  display: flex !important;\n";
        $out .= "  align-items: center !important;\n";
        $out .= "  gap: 10px !important;\n";
        $out .= "  flex-wrap: wrap !important;\n";
        $out .= "  box-shadow: 0 3px 12px rgba(0,0,0,0.22) !important;\n";
        $out .= "  box-sizing: border-box !important;\n";
        $out .= "  width: 100% !important;\n";
        $out .= "  font-family: var(--cce-font-family), system-ui, sans-serif !important;\n";
        $out .= "}\n";
        $out .= ".bar a, .bar button, .toolbar a, .toolbar button, .tb a, .tb button, .tb .btn-link, .pc-toolbar a, .pc-toolbar button {\n";
        $out .= "  display: inline-flex !important;\n";
        $out .= "  align-items: center !important;\n";
        $out .= "  justify-content: center !important;\n";
        $out .= "  gap: 6px !important;\n";
        $out .= "  padding: 8px 16px !important;\n";
        $out .= "  border-radius: 8px !important;\n";
        $out .= "  font-size: 14.5px !important;\n";
        $out .= "  font-weight: 700 !important;\n";
        $out .= "  min-height: 42px !important;\n";
        $out .= "  text-decoration: none !important;\n";
        $out .= "  cursor: pointer !important;\n";
        $out .= "  line-height: 1.2 !important;\n";
        $out .= "  box-sizing: border-box !important;\n";
        $out .= "  border: none !important;\n";
        $out .= "  white-space: nowrap !important;\n";
        $out .= "  font-family: inherit !important;\n";
        $out .= "}\n";
        $out .= ".bar button, .toolbar button, .tb .btn-print, .pc-toolbar .btn-print {\n";
        $out .= "  background: #10b981 !important;\n";
        $out .= "  color: #ffffff !important;\n";
        $out .= "  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3) !important;\n";
        $out .= "}\n";
        $out .= ".bar button:hover, .toolbar button:hover, .tb .btn-print:hover, .pc-toolbar .btn-print:hover {\n";
        $out .= "  background: #059669 !important;\n";
        $out .= "}\n";
        $out .= ".bar a, .toolbar a, .tb .btn-link, .pc-toolbar .btn-back {\n";
        $out .= "  background: #334155 !important;\n";
        $out .= "  color: #f1f5f9 !important;\n";
        $out .= "}\n";
        $out .= ".bar a:hover, .toolbar a:hover, .tb .btn-link:hover, .pc-toolbar .btn-back:hover {\n";
        $out .= "  background: #475569 !important;\n";
        $out .= "}\n";
        $out .= ".cce-tb-select {\n";
        $out .= "  background: #0B3D2E !important;\n";
        $out .= "  color: #F4FBF7 !important;\n";
        $out .= "  border: 1.5px solid #1D9370 !important;\n";
        $out .= "  border-radius: 10px !important;\n";
        $out .= "  padding: 8px 14px !important;\n";
        $out .= "  font-size: 13.5px !important;\n";
        $out .= "  font-weight: 700 !important;\n";
        $out .= "  min-height: 42px !important;\n";
        $out .= "  cursor: pointer !important;\n";
        $out .= "  outline: none !important;\n";
        $out .= "  box-shadow: 0 2px 6px rgba(11, 61, 46, 0.25) !important;\n";
        $out .= "  display: inline-flex !important;\n";
        $out .= "  align-items: center !important;\n";
        $out .= "  line-height: 1.2 !important;\n";
        $out .= "  box-sizing: border-box !important;\n";
        $out .= "  font-family: inherit !important;\n";
        $out .= "}\n";
        $out .= ".cce-tb-select:hover {\n";
        $out .= "  border-color: #E9A23B !important;\n";
        $out .= "  background: #0F4F3B !important;\n";
        $out .= "}\n";
        $out .= ".cce-tb-select option {\n";
        $out .= "  background: #0B3D2E !important;\n";
        $out .= "  color: #ffffff !important;\n";
        $out .= "  padding: 10px !important;\n";
        $out .= "  font-size: 14px !important;\n";
        $out .= "}\n";
        $out .= "@media screen and (max-width: 991.98px) {\n";
        $out .= "  html, body {\n";
        $out .= "    max-width: 100vw !important;\n";
        $out .= "    overflow-x: hidden !important;\n";
        $out .= "  }\n";
        $out .= "  .bar, .toolbar, .tb, .pc-toolbar, .no-print-bar {\n";
        $out .= "    position: sticky !important;\n";
        $out .= "    top: 0 !important;\n";
        $out .= "    padding: 10px 10px !important;\n";
        $out .= "    gap: 8px !important;\n";
        $out .= "    width: 100% !important;\n";
        $out .= "  }\n";
        $out .= "  .bar a, .bar button, .toolbar a, .toolbar button, .tb a, .tb button, .tb .btn-link, .pc-toolbar a, .pc-toolbar button {\n";
        $out .= "    font-size: 14px !important;\n";
        $out .= "    padding: 8px 14px !important;\n";
        $out .= "    min-height: 42px !important;\n";
        $out .= "    flex-grow: 1 !important;\n";
        $out .= "  }\n";
        $out .= "  .cce-tb-select {\n";
        $out .= "    font-size: 13.5px !important;\n";
        $out .= "    padding: 8px 12px !important;\n";
        $out .= "    min-height: 42px !important;\n";
        $out .= "    max-width: 100% !important;\n";
        $out .= "    flex-grow: 1 !important;\n";
        $out .= "  }\n";
        $out .= "  .page, .sheet, .nond-page, .nikal-page, .sh {\n";
        $out .= "    width: 100% !important;\n";
        $out .= "    max-width: 100% !important;\n";
        $out .= "    min-height: auto !important;\n";
        $out .= "    height: auto !important;\n";
        $out .= "    margin: 8px auto !important;\n";
        $out .= "    padding: 12px 10px !important;\n";
        $out .= "    box-shadow: 0 1px 8px rgba(0,0,0,0.1) !important;\n";
        $out .= "    border-radius: 8px !important;\n";
        $out .= "    overflow-x: auto !important;\n";
        $out .= "    -webkit-overflow-scrolling: touch !important;\n";
        $out .= "    box-sizing: border-box !important;\n";
        $out .= "  }\n";
        $out .= "  .page table, .sheet table, .nond-page table, .nikal-page table, .sh table, .cce-marks-table {\n";
        $out .= "    min-width: 600px !important;\n";
        $out .= "  }\n";
        $out .= "}\n";
        $out .= "@media print {\n";
        $out .= "  .cce-tb-select, .toolbar, .tb, .bar, .pc-toolbar, .no-print-bar {\n";
        $out .= "    display: none !important;\n";
        $out .= "  }\n";
        $out .= "}\n";
        $out .= "</style>\n";
        return $out;
    }
}

if (!function_exists('cce_theme_toolbar_controls')) {
    function cce_theme_toolbar_controls($include_bg = false) {
        $themes = cce_theme_registry();
        $fonts = cce_font_registry();
        $bgs = cce_bg_registry();
        $curT = cce_current_theme_key();
        $curF = cce_current_font_key();
        $curBg = cce_current_bg_key();

        ob_start();
        ?>
        <!-- Theme Selector -->
        <select class="cce-tb-select" title="रंग थीम निवडा (Color Theme)" onchange="cceUpdateParam('theme', this.value)">
            <?php foreach ($themes as $k => $t): ?>
                <option value="<?= $k ?>" <?= (string)$k === (string)$curT ? 'selected' : '' ?>>
                    <?= $t['name'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Font Selector -->
        <select class="cce-tb-select" title="फॉन्ट निवडा (Font Family)" onchange="cceUpdateParam('font', this.value)">
            <?php foreach ($fonts as $k => $f): ?>
                <option value="<?= $k ?>" <?= (string)$k === (string)$curF ? 'selected' : '' ?>>
                    <?= $f['name'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($include_bg): ?>
        <!-- Background Template Selector -->
        <select class="cce-tb-select" title="कव्हर डिझाइन निवडा (Cover Design)" onchange="cceUpdateParam('bg', this.value)">
            <?php foreach ($bgs as $k => $b): ?>
                <option value="<?= $k ?>" <?= (string)$k === (string)$curBg ? 'selected' : '' ?>>
                    <?= $b['name'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <script>
        function cceUpdateParam(param, value) {
            var url = new URL(window.location.href);
            url.searchParams.set(param, value);
            // Save in localStorage for client consistency
            try {
                localStorage.setItem('cce_' + param, value);
            } catch(e) {}
            window.location.href = url.toString();
        }
        </script>
        <?php
        return ob_get_clean();
    }
}
