<?php
/**
 * Smart School Hub — Centralized Module Subscriptions & Access Control System
 * 
 * Supports:
 * 1. 100% Free Modules (e.g. Paripath, or any module made free by Admin)
 * 2. Separate Subscriptions (Standalone module subscriptions per school / teacher)
 * 3. Free Trials (Configurable trial days, e.g. 15 days, with days remaining countdown)
 * 4. Combo Subscriptions (Multi-module bundles via Plan included_modules)
 * 5. Full Admin Panel Management
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Check if a school or teacher has access to a specific module.
 * 
 * @param string $module_key e.g. 'que', 'tachan', 'hpc', 'paripath'
 * @param int|null $school_id Defaults to logged in school ID
 * @param int|null $teacher_id Optional teacher ID
 * @return array Access details
 */
function check_module_access(string $module_key, ?int $school_id = null, ?int $teacher_id = null): array
{
    // Single-School Edition: All modules 100% unlocked and active permanently
    return [
        'allowed' => true,
        'type' => 'school_edition',
        'badge_text' => 'सक्रिय मॉड्युल',
        'badge_color' => 'success',
        'days_left' => 9999,
        'end_date' => '2099-12-31',
        'plan_name' => 'शाळा व्यवस्थापन प्रणाली (अनलॉक)',
        'message' => 'हे मॉड्युल आपल्या शाळेसाठी पूर्णपणे सक्रिय आहे.'
    ];
}

/**
 * Configured dynamically by Admin in Admin Panel.
 */
function get_free_plan_modules(): array
{
    static $free_modules = null;
    if ($free_modules !== null) {
        return $free_modules;
    }
    try {
        $db = getDB();
        $stmt = $db->query("SELECT included_modules FROM plans WHERE id = 1 OR price = 0 ORDER BY id ASC LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($row && !empty($row['included_modules'])) {
            $decoded = json_decode($row['included_modules'], true);
            if (is_array($decoded)) {
                return $free_modules = $decoded;
            }
        }
    } catch (Throwable $e) {}
    return $free_modules = ['gr', 'attendance', 'classes', 'cce', 'hpc', 'paripath'];
}

/**
 * Class-level access control for CCE (सातत्यपूर्ण सर्वंकष मूल्यमापन).
 * In Free Plan: Standards 1 to 5 (इयत्ता १ ते ५) are FREE (within school student quota).
 * Standards 6+ (इ. ६ ते ८/१०) require an active paid plan or CCE module subscription.
 */
function can_access_cce_class(int $std, ?int $school_id = null): array
{
    return [
        'allowed' => true,
        'reason'  => 'school_edition',
        'message' => 'शाळा व्यवस्थापन प्रणाली - सर्व वर्ग सक्रिय आहेत.'
    ];
}

/**
 * Gatekeeper for module page access
 */
function require_module_access(string $module_key): array
{
    requireLogin();
    return check_module_access($module_key);
}

/**
 * Grant or extend a Free Trial to a School or Teacher
 */
function grant_module_trial(int $school_id, string $module_key, int $days = 5, ?int $teacher_id = null, string $notes = ''): bool
{
    $db = getDB();
    $today = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$days days"));
    $plan_name = "{$days}-दिवसीय मोफत चाचणी";

    $chk = $db->prepare("SELECT id FROM school_module_access WHERE school_id = ? AND module_key = ? AND (teacher_id <=> ?)");
    $chk->execute([$school_id, $module_key, $teacher_id]);
    $existing_id = $chk->fetchColumn();

    if ($existing_id) {
        $stmt = $db->prepare("UPDATE school_module_access SET access_type = 'trial', plan_name = ?, start_date = ?, end_date = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$plan_name, $today, $end_date, $notes, $existing_id]);
    } else {
        $stmt = $db->prepare("INSERT INTO school_module_access (school_id, teacher_id, module_key, access_type, plan_name, start_date, end_date, notes) VALUES (?, ?, ?, 'trial', ?, ?, ?, ?)");
        return $stmt->execute([$school_id, $teacher_id, $module_key, $plan_name, $today, $end_date, $notes]);
    }
}

/**
 * Activate a Paid Separate Subscription for a School or Teacher
 */
function activate_module_subscription(int $school_id, string $module_key, int $months = 12, string $plan_name = 'स्वतंत्र वार्षिक सदस्यता', ?int $teacher_id = null, string $notes = ''): bool
{
    $db = getDB();
    $today = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$months months"));

    $chk = $db->prepare("SELECT id FROM school_module_access WHERE school_id = ? AND module_key = ? AND (teacher_id <=> ?)");
    $chk->execute([$school_id, $module_key, $teacher_id]);
    $existing_id = $chk->fetchColumn();

    if ($existing_id) {
        $stmt = $db->prepare("UPDATE school_module_access SET access_type = 'subscribed', plan_name = ?, start_date = ?, end_date = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$plan_name, $today, $end_date, $notes, $existing_id]);
    } else {
        $stmt = $db->prepare("INSERT INTO school_module_access (school_id, teacher_id, module_key, access_type, plan_name, start_date, end_date, notes) VALUES (?, ?, ?, 'subscribed', ?, ?, ?, ?)");
        return $stmt->execute([$school_id, $teacher_id, $module_key, $plan_name, $today, $end_date, $notes]);
    }
}

/**
 * Grant 100% Free Lifetime Access for a School or Teacher
 */
function grant_free_module_access(int $school_id, string $module_key, ?int $teacher_id = null, string $notes = 'कायमस्वरूपी मोफत ऍक्सेस'): bool
{
    $db = getDB();
    $today = date('Y-m-d');

    $chk = $db->prepare("SELECT id FROM school_module_access WHERE school_id = ? AND module_key = ? AND (teacher_id <=> ?)");
    $chk->execute([$school_id, $module_key, $teacher_id]);
    $existing_id = $chk->fetchColumn();

    if ($existing_id) {
        $stmt = $db->prepare("UPDATE school_module_access SET access_type = 'free', plan_name = 'कायमस्वरूपी मोफत', start_date = ?, end_date = NULL, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$today, $notes, $existing_id]);
    } else {
        $stmt = $db->prepare("INSERT INTO school_module_access (school_id, teacher_id, module_key, access_type, plan_name, start_date, end_date, notes) VALUES (?, ?, ?, 'free', 'कायमस्वरूपी मोफत', ?, NULL, ?)");
        return $stmt->execute([$school_id, $teacher_id, $module_key, $today, $notes]);
    }
}

/**
 * Revoke or Block access to a module for a School or Teacher
 */
function revoke_module_access(int $school_id, string $module_key, ?int $teacher_id = null, bool $block = false): bool
{
    $db = getDB();
    if ($block) {
        $chk = $db->prepare("SELECT id FROM school_module_access WHERE school_id = ? AND module_key = ? AND (teacher_id <=> ?)");
        $chk->execute([$school_id, $module_key, $teacher_id]);
        $existing_id = $chk->fetchColumn();

        if ($existing_id) {
            $stmt = $db->prepare("UPDATE school_module_access SET access_type = 'blocked', plan_name = 'ब्लॉक केले', start_date = CURDATE(), end_date = CURDATE(), notes = 'ॲडमिनद्वारे बंद', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            return $stmt->execute([$existing_id]);
        } else {
            $stmt = $db->prepare("INSERT INTO school_module_access (school_id, teacher_id, module_key, access_type, plan_name, start_date, end_date, notes) VALUES (?, ?, ?, 'blocked', 'ब्लॉक केले', CURDATE(), CURDATE(), 'ॲडमिनद्वारे बंद')");
            return $stmt->execute([$school_id, $teacher_id, $module_key]);
        }
    } else {
        $where = "school_id = ? AND module_key = ?";
        $params = [$school_id, $module_key];
        if ($teacher_id !== null) {
            $where .= " AND teacher_id = ?";
            $params[] = $teacher_id;
        } else {
            $where .= " AND teacher_id IS NULL";
        }
        $stmt = $db->prepare("DELETE FROM school_module_access WHERE $where");
        return $stmt->execute($params);
    }
}

/**
 * Check if the school has specifically purchased the Tachan module (Standalone ₹499 or All-in-One ₹1499),
 * or if it is currently only in trial mode (where bulk download of old/future dates is restricted).
 */
function is_tachan_module_purchased(?int $school_id = null): bool
{
    if (!$school_id && isset($_SESSION['school_id'])) {
        $school_id = (int)$_SESSION['school_id'];
    }
    if (!$school_id) {
        return false;
    }

    if (function_exists('isAdmin') && isAdmin() && empty($_SESSION['impersonated_by_admin'])) {
        return true;
    }

    $acc = check_module_access('tachan', $school_id);
    
    // In trial, free, expired, or locked mode, it is NOT purchased
    if (empty($acc['allowed']) || in_array($acc['type'], ['trial', 'free', 'expired', 'blocked', 'plan_locked', 'unauthenticated'], true)) {
        return false;
    }

    // Subscribed (standalone purchase) or Combo (full school suite including tachan) or Admin
    if (in_array($acc['type'], ['subscribed', 'combo', 'admin'], true)) {
        return true;
    }

    return false;
}

