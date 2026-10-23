<?php
/**
 * Shared app-shell partials (sidebar + topbar) for the TechGuruji "Vidya" design.
 * Usage: tg_shell_open($groups, $opts) ... page ... tg_shell_close()
 *   $groups = [ ['label' => 'मुख्य', 'items' => [ ['href','bi-icon','लेबल', isActive], ... ]], ... ]
 */
if (!function_exists('tg_h')) {
    function tg_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('tg_head_assets')) {
    function tg_head_assets(string $base): void
    {
        $css = __DIR__ . '/../assets/css/theme.css';
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">';
        echo '<meta name="theme-color" content="#0B3D2E">';
        echo '<link href="' . $base . '/assets/css/theme.css?v=' . (int) @filemtime($css) . '" rel="stylesheet">';
    }
}

if (!function_exists('tg_shell_open')) {
    function tg_shell_open(array $groups, array $opts = []): void
    {
        $base = defined('APP_URL') ? APP_URL : '';
        $brandTitle = $opts['brand_title'] ?? 'TechGuruji';
        $brandSub = $opts['brand_sub'] ?? 'स्मार्ट स्कूल हब';
        $logoVer = (int) @filemtime(__DIR__ . '/../assets/images/logo-icon.svg') ?: 4;
        $logo = $opts['logo'] ?? ($base . '/assets/images/zp_sangli_logo.png');
        $home = $opts['home'] ?? ($base . '/dashboard.php');
        $title = $opts['title'] ?? '';
        $chip = $opts['chip'] ?? '';
        $userName = $opts['user_name'] ?? '';
        $userSub = $opts['user_sub'] ?? '';
        $menu = $opts['user_menu'] ?? [];
        $foot = $opts['side_foot'] ?? '';
        ?>
<div class="tg-backdrop" data-tg-close></div>
<aside class="tg-sidebar" id="tgSidebar" aria-label="मुख्य मेनू">
    <a class="tg-brand" href="<?= tg_h($home) ?>">
        <span class="tg-logo"><img src="<?= tg_h($logo) ?>" alt="" width="40" height="40" loading="eager" onerror="this.remove();this.parentNode.textContent='T'"></span>
        <span><span class="tg-brand-name d-block"><?= tg_h($brandTitle) ?></span><span class="tg-brand-sub d-block text-truncate" style="max-width:170px"><?= tg_h($brandSub) ?></span></span>
    </a>
    <nav class="tg-nav">
        <?php foreach ($groups as $g): ?>
            <?php if (!empty($g['label'])): ?><div class="tg-nav-label"><?= tg_h($g['label']) ?></div><?php endif; ?>
            <?php foreach ($g['items'] as $it): [$href, $icon, $label, $active] = array_pad($it, 4, false); ?>
                <a href="<?= tg_h($href) ?>" class="<?= $active ? 'active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?>><i class="bi <?= tg_h($icon) ?>"></i><span><?= tg_h($label) ?></span></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <?php if ($foot): ?><div class="tg-side-foot"><?= $foot ?></div><?php endif; ?>
</aside>
<div class="tg-content">
    <header class="tg-topbar">
        <button class="tg-icon-btn d-lg-none" type="button" data-tg-toggle aria-label="मेनू उघडा"><i class="bi bi-list"></i></button>
        <div class="tg-topbar-title flex-grow-1"><?= tg_h($title) ?></div>
        <?php if ($chip): ?><span class="tg-chip d-none d-md-inline-flex"><i class="bi bi-calendar3"></i><?= tg_h($chip) ?></span><?php endif; ?>
        <?php if ($userName !== ''): ?>
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 p-1 border-0" data-bs-toggle="dropdown" aria-expanded="false" type="button">
                <span class="tg-avatar"><?= tg_h(mb_substr($userName, 0, 1)) ?></span>
                <span class="d-none d-md-block text-start lh-sm"><span class="fw-semibold d-block text-truncate" style="max-width:180px"><?= tg_h($userName) ?></span><?php if ($userSub): ?><small class="text-muted"><?= tg_h($userSub) ?></small><?php endif; ?></span>
                <i class="bi bi-chevron-down small text-muted d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($menu as $m): if ($m === '-') { echo '<li><hr class="dropdown-divider"></li>'; continue; } [$href, $icon, $label, $cls] = array_pad($m, 4, ''); ?>
                    <li><a class="dropdown-item <?= tg_h($cls) ?>" href="<?= tg_h($href) ?>"><i class="bi <?= tg_h($icon) ?> me-2"></i><?= tg_h($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </header>
        <?php
    }
}

if (!function_exists('tg_shell_script')) {
    function tg_shell_script(): void
    {
        echo <<<'JS'
<script>
(function(){var b=document.body;document.addEventListener('click',function(e){if(e.target.closest('[data-tg-toggle]')){b.classList.toggle('tg-nav-open');}else if(e.target.closest('[data-tg-close]')){b.classList.remove('tg-nav-open');}});
document.addEventListener('keydown',function(e){if(e.key==='Escape')b.classList.remove('tg-nav-open');});
if('IntersectionObserver' in window){var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('is-in');io.unobserve(x.target);}});},{rootMargin:'0px 0px -40px 0px'});document.querySelectorAll('.tg-reveal').forEach(function(el){io.observe(el);});}else{document.querySelectorAll('.tg-reveal').forEach(function(el){el.classList.add('is-in');});}
var a=document.querySelector('.tg-nav a.active'),n=document.querySelector('.tg-nav');if(a&&n&&a.offsetTop+a.offsetHeight>n.clientHeight){n.scrollTop=a.offsetTop-n.clientHeight/2;}})();
</script>
JS;
    }
}

if (!function_exists('tg_text_filter')) {
    /** Shows "सातत्यपूर्ण मूल्यमापन" instead of the "CCE" abbreviation in visible text (incl. DB-sourced labels). */
    function tg_text_filter(string $html): string
    {
        $parts = preg_split('#(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>|<[^>]+>)#si', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) return $html;
        foreach ($parts as $i => $p) {
            if ($p === '' || $p[0] === '<' || strpos($p, 'CCE') === false) continue;
            $p = preg_replace(['/\(CCE\)/u', '/CCE\s*(?:९\.०|9\.0)/u', '/CCE\s+मूल्यमापन/u', '/(?<![\w\/.\-])CCE(?![\w\/.\-])/u'],
                ['', 'सातत्यपूर्ण मूल्यमापन', 'सातत्यपूर्ण मूल्यमापन', 'सातत्यपूर्ण मूल्यमापन'], $p);
            $parts[$i] = str_replace('सर्वंकष मूल्यमापन ', 'सातत्यपूर्ण मूल्यमापन ', $p);
        }
        return implode('', $parts);
    }
}
