<?php
/**
 * Shared bootstrap for the native Android JSON APIs (cce_native / hpc_native / tachan_native / report).
 *
 * Authenticates the bearer token (schools.api_token) and then primes the PHP session exactly the way
 * the web login does, so all existing module libraries (cce_functions, rubric_lib, tachan_engine, ...)
 * work unchanged: they read $_SESSION['school_id'] / $_SESSION['user'].
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';
require_once __DIR__ . '/../modules/lib/auth.php';

function native_boot(): array
{
    $schoolId = getAuthSchoolId();
    $_SESSION['school_id'] = $schoolId;
    $user = current_user();
    if (!$user) {
        apiError('School user not found', 403);
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user'] = $user;
    $_SESSION['role'] = $user['role'] ?? 'admin';
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return ['school_id' => $schoolId, 'user' => $user, 'teacher_id' => (int) $user['id']];
}

function native_input(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode((string) $raw, true);
    if (is_array($json)) {
        return array_merge($_GET, $json);
    }
    return array_merge($_GET, $_POST);
}

function native_action(array $in): string
{
    return (string) ($in['action'] ?? '');
}

function native_int(array $in, string $key, int $def = 0): int
{
    return isset($in[$key]) && $in[$key] !== '' ? (int) $in[$key] : $def;
}

function native_str(array $in, string $key, string $def = ''): string
{
    return isset($in[$key]) ? trim((string) $in[$key]) : $def;
}

function native_list(array $in, string $key): array
{
    $v = $in[$key] ?? [];
    if (is_string($v)) {
        $d = json_decode($v, true);
        $v = is_array($d) ? $d : ($v === '' ? [] : explode(',', $v));
    }
    return is_array($v) ? array_values($v) : [];
}

/** Marathi label helpers (mirror cce/ui) */
function native_semester_label(int $sem): string
{
    return $sem === 2 ? 'द्वितीय सत्र' : 'प्रथम सत्र';
}
