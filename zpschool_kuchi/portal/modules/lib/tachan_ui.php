<?php
declare(strict_types=1);

/**
 * Smart School Hub — Tachan Unified Theme & Layout Helper
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/tenant_db.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/flash.php';

/**
 * Enforce authentication and verify role for Tachan module
 */
function tachan_auth_check(): array
{
    requireLogin();
    
    $sid = (int)($_SESSION['school_id'] ?? 0);
    if ($sid <= 0) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }

    $user = $_SESSION['user'] ?? null;
    if (!$user && !empty($_SESSION['user_id'])) {
        $stmt = getMainDB()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        require_once __DIR__ . '/auth.php';
        $user = current_user();
    }

    if (!$user) {
        $school = getSchool();
        $schoolName = $school ? ($school['name_mr'] ?: $school['name']) : 'शाळा ' . $sid;
        $user = [
            'id' => $sid,
            'school_id' => $sid,
            'name' => $schoolName,
            'role' => 'admin',
        ];
    }

    $_SESSION['user'] = $user;
    $_SESSION['user_id'] = (int)$user['id'];
    if (empty($_SESSION['role'])) {
        $_SESSION['role'] = $user['role'] ?? 'admin';
    }

    // Ensure school_id and school_udise are loaded
    if (empty($_SESSION['school_id']) && !empty($user['school_id'])) {
        $_SESSION['school_id'] = (int)$user['school_id'];
    }
    if (empty($_SESSION['school_udise']) && !empty($_SESSION['school_id'])) {
        getSchoolUdise((int)$_SESSION['school_id']);
    }

    // CSRF initialization
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Anti-scraping and framing headers
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
    }

    return $user;
}

/**
 * Outputs CSRF hidden input field
 */
function tachan_csrf_field(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifies submitted CSRF token
 */
function tachan_verify_csrf(): bool
{
    $token = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Starts the Tachan module layout with Smart School Hub Header & Navigation
 */
function tachan_layout_start(string $title, string $active_tab = 'day', array $extra_breadcrumbs = []): void
{
    global $page_title;
    $page_title = $title . ' - दैनिक टाचण वही';
    
    $user = tachan_auth_check();
    $school = getSchool();
    $school_id = (int)$_SESSION['school_id'];
    $udise = getSchoolUdise($school_id);
    $year = academic_year();

    $flashes = flash_get();

    // Include the main Smart School Hub Header
    require_once __DIR__ . '/../../includes/header.php';
    ?>
    <style>
    /* Tachan Modern & Mobile Theme */
    .tachan-page {
        font-family: 'Mukta', 'Mukta', sans-serif;
    }
    .tachan-banner {
        background: linear-gradient(135deg, #EFF8F4 0%, #DCF0E7 100%);
        border: 1.5px solid #BFE3D3;
        border-radius: 14px;
        padding: 14px 18px;
    }
    .tachan-tab-pill {
        border-radius: 30px;
        padding: 4px;
        background: #F1F5F9;
        display: inline-flex;
        max-width: 100%;
        overflow-x: auto;
        white-space: nowrap;
        scrollbar-width: thin;
    }
    .tachan-tab-btn {
        border-radius: 25px;
        padding: 6px 14px;
        font-size: 0.85rem;
        font-weight: 700;
        text-decoration: none;
        color: #475569;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .tachan-tab-btn:hover {
        color: #0F172A;
        background: rgba(255, 255, 255, 0.8);
    }
    .tachan-tab-btn.active {
        background: #167A5B;
        color: #ffffff !important;
        box-shadow: 0 3px 10px rgba(22,122,91, 0.35);
    }
    .tachan-card {
        border-radius: 14px;
        border: 1px solid #E2E8F0;
        background: #ffffff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .tachan-table th {
        background: #17324D !important;
        color: #ffffff !important;
        vertical-align: middle;
        font-weight: 700;
        font-size: 0.88rem;
    }
    .tachan-table td {
        vertical-align: middle;
        font-size: 0.88rem;
    }
    @media (max-width: 768px) {
        .tachan-banner { padding: 10px 12px; }
        .tachan-tab-btn { font-size: 0.78rem; padding: 5px 10px; }
    }
    </style>

    <div class="tachan-page mb-5">
        <!-- Top Header Banner -->
        <div class="tachan-banner mb-3 shadow-sm">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-2">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark border"><i class="bi bi-journal-check"></i> दैनिक टाचण वही</span>
                        <span class="badge bg-white text-dark border">UDISE: <?= htmlspecialchars((string)$udise) ?></span>
                        <span class="badge bg-white text-dark border">शैक्षणिक वर्ष: <?= htmlspecialchars((string)$year) ?></span>
                    </div>
                    <h3 class="mb-0 fw-bold text-dark d-flex align-items-center fs-4">
                        <i class="bi bi-pencil-square text-warning me-2"></i> <?= htmlspecialchars($title) ?>
                    </h3>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-house"></i> डॅशबोर्ड
                    </a>
                    <a href="<?= APP_URL ?>/modules/tachan/add.php" class="btn btn-primary btn-sm fw-bold" style="background:#167A5B; border-color:#167A5B;">
                        <i class="bi bi-plus-circle"></i> नवीन नोंद
                    </a>
                </div>
            </div>

            <!-- Navigation Tab Pills (8 core sections) -->
            <div class="tachan-tab-pill shadow-sm w-100 d-flex overflow-x-auto gap-1 mt-3">
                <a href="<?= APP_URL ?>/modules/tachan/index.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'home' ? 'active' : '' ?>">
                    <i class="bi bi-grid-fill"></i> हब
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/day.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'day' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-check"></i> दैनिक टाचण
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/weekly.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'weekly' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-week"></i> साप्ताहिक
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/monthly.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'monthly' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-month"></i> मासिक <span class="badge bg-warning text-dark px-1 py-0 ms-1" style="font-size:0.65rem;">PRO</span>
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/timetable.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'timetable' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i> वेळापत्रक
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/annual.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'annual' ? 'active' : '' ?>">
                    <i class="bi bi-book"></i> वार्षिक नियोजन
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/holidays.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'holidays' ? 'active' : '' ?>">
                    <i class="bi bi-sun"></i> सुट्ट्या
                </a>
                <?php if (function_exists('is_tachan_module_purchased') && is_tachan_module_purchased($school_id)): ?>
                <a href="<?= APP_URL ?>/modules/tachan/backdate.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'backdate' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-range"></i> जुने टाचण
                </a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/modules/tachan/class_pref.php" class="tachan-tab-btn flex-fill text-center <?= $active_tab === 'settings' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i> सेटिंग्ज
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show py-2 shadow-sm" role="alert">
                <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>-fill me-2"></i>
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    <?php
}

/**
 * Closes the Tachan layout with Smart School Hub Footer
 */
function tachan_layout_end(): void
{
    echo '</div>'; // close .tachan-page
    require_once __DIR__ . '/../../includes/footer.php';
}
