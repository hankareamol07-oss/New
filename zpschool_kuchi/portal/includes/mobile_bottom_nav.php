<?php
/**
 * Smart School Hub — Unified Mobile-First App Bottom Navigation Bar
 * - Authenticated Teachers: 5-Tab App Navigation with Center FAB
 * - Public / Guest Visitors on Mobile: Sticky Bottom Login & Free Sign-Up Bar
 */
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$is_auth = function_exists('isLoggedIn') && isLoggedIn();
$is_admin = function_exists('isAdmin') && isAdmin();
$is_auth_page = (strpos($uri, '/auth/') !== false || strpos($uri, '/admin/') !== false);

// Hide completely on auth/admin pages when not logged in as school
if (!$is_auth && $is_auth_page) {
    return;
}

// Active state detection for logged-in teacher bottom bar
$active_tab = 'home';
if (strpos($uri, '/modules/classes') !== false || strpos($uri, '/students') !== false) {
    $active_tab = 'classes';
} elseif (strpos($uri, '/modules/paripath') !== false) {
    $active_tab = 'paripath';
} elseif (strpos($uri, '/hpc') !== false || strpos($uri, '/cards') !== false) {
    $active_tab = 'hpc';
} elseif (strpos($uri, '/cce') !== false) {
    $active_tab = 'cce';
} elseif (strpos($uri, '/dashboard.php') !== false || $uri === '/' || substr($uri, -1) === '/') {
    $active_tab = 'home';
} else {
    $active_tab = '';
}
?>
<?php if (!defined('TG_ICONS_LOADED')): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><?php endif; ?>
<style>
/* ==========================================================================
   MOBILE-FIRST NATIVE-APP BOTTOM NAVIGATION & GUEST STICKY LOGIN/SIGNUP BAR
   ========================================================================== */
.ssh-bottom-nav,
.ssh-guest-bottom-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: rgba(255, 255, 255, 0.97);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-top: 1px solid rgba(207, 231, 220, 0.95);
    box-shadow: 0 -6px 24px rgba(11, 61, 46, 0.12), 0 -1px 3px rgba(15, 23, 42, 0.04);
    padding: 6px 10px calc(6px + env(safe-area-inset-bottom, 0px)) 10px;
    display: flex;
    align-items: center;
    justify-content: space-around;
    user-select: none;
    -webkit-user-select: none;
    touch-action: manipulation;
}

.ssh-guest-bottom-bar {
    padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px)) 12px;
    gap: 10px;
    background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, #F4FBF7 100%);
    border-top: 1.5px solid #BFE3D3;
}

.ssh-guest-btn-login {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 14px;
    border-radius: 13px;
    font-size: 0.95rem;
    font-weight: 800;
    text-decoration: none !important;
    color: #0B3D2E !important;
    background: #EFF8F4;
    border: 1.8px solid #167A5B;
    box-shadow: 0 2px 8px rgba(11, 61, 46, 0.08);
    transition: transform 0.15s ease, background 0.15s ease;
}
.ssh-guest-btn-login:active {
    transform: scale(0.96);
    background: #DCF0E7;
}

.ssh-guest-btn-signup {
    flex: 1.25;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 14px;
    border-radius: 13px;
    font-size: 0.95rem;
    font-weight: 800;
    text-decoration: none !important;
    color: #171105 !important;
    background: linear-gradient(135deg, #FBBF24 0%, #E9A23B 55%, #D97706 100%);
    border: 1.8px solid #D97706;
    box-shadow: 0 6px 16px rgba(233, 162, 59, 0.38);
    transition: transform 0.15s ease;
}
.ssh-guest-btn-signup:active {
    transform: scale(0.96);
}

/* Hide on desktop and tablet screens (lg and up) */
@media (min-width: 992px) {
    .ssh-bottom-nav,
    .ssh-guest-bottom-bar {
        display: none !important;
    }
}

/* CRITICAL: Never show during print or PDF export */
@media print {
    .ssh-bottom-nav,
    .ssh-bottom-nav *,
    .ssh-guest-bottom-bar,
    .ssh-guest-bottom-bar * {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        background: none !important;
    }
    body {
        padding-bottom: 0 !important;
    }
}

/* Ensure mobile content is never hidden behind the bottom bar */
@media (max-width: 991.98px) {
    body {
        padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px)) !important;
    }
}

.ssh-bnav-item {
    flex: 1;
    max-width: 20%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    color: #64748b;
    position: relative;
    padding: 3px 0;
    transition: transform 0.15s ease, color 0.15s ease;
    -webkit-tap-highlight-color: transparent;
}
.ssh-bnav-item:active {
    transform: scale(0.92);
}
.ssh-bnav-icon-wrap {
    width: 44px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 1.25rem;
    line-height: 1;
    color: #64748b;
}
.ssh-bnav-label {
    font-size: 0.70rem;
    font-weight: 600;
    margin-top: 3px;
    line-height: 1.1;
    letter-spacing: -0.01em;
    font-family: 'Mukta', system-ui, -apple-system, sans-serif;
    color: #64748b;
    transition: color 0.2s ease, font-weight 0.2s ease;
}

/* Active State: Android Material You inspired pill highlight */
.ssh-bnav-item.active .ssh-bnav-icon-wrap {
    background: #DCF0E7;
    color: #167A5B;
}
.ssh-bnav-item.active .ssh-bnav-label {
    color: #13644B;
    font-weight: 800;
}
.ssh-bnav-item.active {
    color: #167A5B;
}

/* Center Elevated FAB (Class Management) */
.ssh-bnav-fab-wrap {
    position: relative;
    flex: 1;
    max-width: 20%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-top: -24px;
    text-decoration: none !important;
    z-index: 1045;
    -webkit-tap-highlight-color: transparent;
}
.ssh-bnav-fab-btn {
    width: 50px;
    height: 50px;
    border-radius: 17px;
    background: linear-gradient(135deg, #1D9370 0%, #0F4F3B 100%);
    color: #ffffff !important;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    box-shadow: 0 6px 16px rgba(15, 79, 59, 0.38), 0 2px 5px rgba(22, 122, 91, 0.2);
    border: 3.5px solid #ffffff;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.ssh-bnav-fab-wrap:active .ssh-bnav-fab-btn {
    transform: scale(0.92);
}
.ssh-bnav-fab-wrap.active .ssh-bnav-fab-btn {
    box-shadow: 0 0 0 3.5px rgba(15, 79, 59, 0.32), 0 8px 22px rgba(15, 79, 59, 0.48);
    transform: translateY(-2px);
}
.ssh-bnav-fab-label {
    font-size: 0.68rem;
    font-weight: 800;
    color: #13644B;
    margin-top: 3px;
    line-height: 1.1;
    font-family: 'Mukta', system-ui, -apple-system, sans-serif;
    transition: color 0.2s ease;
    white-space: nowrap;
}
.ssh-bnav-fab-wrap.active .ssh-bnav-fab-label {
    color: #13644B;
}
</style>

<?php if ($is_auth): ?>
<nav class="ssh-bottom-nav" aria-label="Mobile Bottom Navigation">
    <!-- 1. Home / Dashboard -->
    <a href="<?= APP_URL ?>/dashboard.php" class="ssh-bnav-item <?= $active_tab === 'home' ? 'active' : '' ?>">
        <div class="ssh-bnav-icon-wrap">
            <i class="bi bi-house-door<?= $active_tab === 'home' ? '-fill' : '' ?>"></i>
        </div>
        <span class="ssh-bnav-label">मुख्यपृष्ठ</span>
    </a>

    <!-- 2. Paripath -->
    <a href="<?= APP_URL ?>/modules/paripath/index.php" class="ssh-bnav-item <?= $active_tab === 'paripath' ? 'active' : '' ?>">
        <div class="ssh-bnav-icon-wrap">
            <i class="bi bi-megaphone<?= $active_tab === 'paripath' ? '-fill' : '' ?>"></i>
        </div>
        <span class="ssh-bnav-label">परिपाठ</span>
    </a>

    <!-- 3. Class Management (Center Elevated FAB) -->
    <a href="<?= APP_URL ?>/modules/classes/index.php" class="ssh-bnav-fab-wrap <?= $active_tab === 'classes' ? 'active' : '' ?>" title="वर्ग व्यवस्थापन">
        <div class="ssh-bnav-fab-btn">
            <i class="bi bi-people-fill"></i>
        </div>
        <span class="ssh-bnav-fab-label">वर्ग व्यवस्थापन</span>
    </a>

    <!-- 4. HPC -->
    <a href="<?= APP_URL ?>/hpc/index.php" class="ssh-bnav-item <?= $active_tab === 'hpc' ? 'active' : '' ?>">
        <div class="ssh-bnav-icon-wrap">
            <i class="bi bi-person-badge<?= $active_tab === 'hpc' ? '-fill' : '' ?>"></i>
        </div>
        <span class="ssh-bnav-label">HPC</span>
    </a>

    <!-- 5. CCE Results -->
    <a href="<?= APP_URL ?>/cce/index.php" class="ssh-bnav-item <?= $active_tab === 'cce' ? 'active' : '' ?>">
        <div class="ssh-bnav-icon-wrap">
            <i class="bi bi-bar-chart-line<?= $active_tab === 'cce' ? '-fill' : '' ?>"></i>
        </div>
        <span class="ssh-bnav-label">मूल्यमापन निकाल</span>
    </a>
</nav>
<?php elseif (!$is_admin): ?>
<!-- Guest Mobile Sticky Bottom Login & Sign-Up Bar -->
<div class="ssh-guest-bottom-bar" role="navigation" aria-label="शाळा लॉगिन व नोंदणी">
    <a href="<?= APP_URL ?>/auth/login.php" class="ssh-guest-btn-login">
        <i class="bi bi-box-arrow-in-right fs-6"></i>
        <span>शाळा लॉगिन</span>
    </a>
    <a href="<?= APP_URL ?>/auth/register.php" class="ssh-guest-btn-signup">
        <i class="bi bi-person-plus-fill fs-6"></i>
        <span>मोफत नोंदणी (Sign Up)</span>
    </a>
</div>
<?php endif; ?>
