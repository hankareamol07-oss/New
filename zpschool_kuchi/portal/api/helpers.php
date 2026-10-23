<?php
/**
 * REST API Helper — shared utilities for all Android API endpoints.
 */

// CORS Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Version, X-App-Platform, X-App-Signature, X-Requested-With, Accept, Origin');

// Handle preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include shared config
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/app_tokens.php';

if (!function_exists('array_is_list')) {
    function array_is_list(array $arr): bool {
        if ($arr === []) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

// ─── Response helpers ─────────────────────────────────────────────────────────

if (!function_exists('apiSuccess')) {
    function apiSuccess($data = [], $message = 'success') {
        echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
        exit();
    }
}

if (!function_exists('apiError')) {
    function apiError($message = 'Error occurred', $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
}

// ─── Get Authorization header — works on all PHP/server setups ───────────────

function getAuthToken() {
    // Method 1: Apache getallheaders()
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                return $value;
            }
        }
    }

    // Method 2: $_SERVER variables (works on Nginx, FastCGI, cPanel)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // Method 3: Apache rewrites sometimes put it here
    if (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
        return $_SERVER['HTTP_X_AUTHORIZATION'];
    }

    return '';
}

// ─── Auth: Token-based validation ─────────────────────────────────────────────

function getAuthSchoolId() {
    $token = getAuthToken();

    if (empty($token)) {
        apiError('Unauthorized — missing token', 401);
    }

    // Strip "Bearer " prefix
    if (stripos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    $token = trim($token);

    if (empty($token)) {
        apiError('Unauthorized — empty token', 401);
    }

    $schoolId = tg_token_school(getDB(), $token);
    if ($schoolId <= 0) {
        apiError('Unauthorized — invalid or expired token', 401);
    }

    // Auto-release any previous firewall/proxy ban on this verified school's IP safely
    tg_safe_unban_ip();

    return $schoolId;
}

function tg_safe_unban_ip(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') return;
    try {
        if (class_exists('SecurityGuard') && method_exists('SecurityGuard', 'unbanIp')) {
            SecurityGuard::unbanIp($ip);
            return;
        }
        $secFile = __DIR__ . '/../data/security.sqlite';
        if (file_exists($secFile)) {
            $sdb = new PDO('sqlite:' . $secFile);
            $st = $sdb->prepare("UPDATE security_bans SET is_active = 0 WHERE ip_address = ? AND is_active = 1");
            $st->execute([$ip]);
        }
    } catch (Throwable $e) {}
}

if (!function_exists('getBearerToken')) {
    function getBearerToken(): string {
        $token = trim((string) getAuthToken());
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }
        return $token;
    }
}

function getRequestBody() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
