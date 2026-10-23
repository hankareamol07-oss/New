<?php
declare(strict_types=1);
/**
 * मॉड्युल्ससाठी वापरकर्ता थर — मुख्य पोर्टलचे सेशन वापरते.
 * शाळेच्या लॉगिनसाठी users टेबलमध्ये एक admin नोंद आपोआप तयार होते.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function module_base(): string
{
    return '/modules';
}

function current_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $schoolId = (int) ($_SESSION['school_id'] ?? 0);
    if ($schoolId <= 0) {
        return null;
    }
    $pdo = getMainDB();
    $school = school_row($schoolId);
    if (!$school) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE school_id=? ORDER BY id LIMIT 1');
    $stmt->execute([$schoolId]);
    $user = $stmt->fetch();
    if (!$user) {
        $phone = (string) ($school['phone'] ?? '');
        if ($phone === '') {
            $phone = 'school-' . $schoolId;
        }
        $ins = $pdo->prepare('INSERT INTO users(school_id,name,phone,password_hash,role,pending,created_at) VALUES(?,?,?,?,?,0,NOW())');
        try {
            $ins->execute([$schoolId, (string) ($school['name_mr'] ?: $school['name']), $phone, '', 'admin']);
        } catch (PDOException $e) {
            $ins->execute([$schoolId, (string) ($school['name_mr'] ?: $school['name']), $phone . '-' . $schoolId, '', 'admin']);
        }
        $stmt->execute([$schoolId]);
        $user = $stmt->fetch();
    }
    return $user ?: null;
}

function school_id(): int
{
    return (int) ($_SESSION['school_id'] ?? 0);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $loginUrl = defined('APP_URL') ? APP_URL . '/auth/login.php' : '/auth/login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
    return $user;
}

function require_role(array|string $roles): array
{
    return require_login();
}

function login_user(array $user): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['school_id'] = (int) $user['school_id'];
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
}
