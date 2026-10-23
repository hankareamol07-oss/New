<?php
/**
 * /api/account.php
 * GET  ?action=profile
 * POST {action:"logout"}
 * POST {action:"delete", password, reason}
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/account_delete.php';

$schoolId = getAuthSchoolId();
$db = getDB();
$in = array_merge($_GET, getRequestBody() ?: []);
$action = (string) ($in['action'] ?? 'profile');

if ($action === 'profile') {
    $st = $db->prepare('SELECT id, name, name_mr, udise_code, email, phone, district, taluka, village FROM schools WHERE id = ?');
    $st->execute([$schoolId]);
    apiSuccess(['school' => $st->fetch(PDO::FETCH_ASSOC) ?: []]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Method not allowed', 405);
}

if ($action === 'logout') {
    tg_token_revoke($db, $schoolId, getBearerToken());
    apiSuccess([], 'लॉगआउट झाले');
}

if ($action === 'delete') {
    $st = $db->prepare('SELECT password FROM schools WHERE id = ?');
    $st->execute([$schoolId]);
    $hash = (string) $st->fetchColumn();
    if (!password_verify((string) ($in['password'] ?? ''), $hash)) {
        apiError('चुकीचा पासवर्ड', 403);
    }
    tg_request_account_deletion($db, $schoolId, (string) ($in['reason'] ?? ''), 'android_app');
    apiSuccess([], 'खाते बंद केले. तुमचा डेटा ३० दिवसांत कायमचा हटवला जाईल.');
}

apiError('Unknown action', 404);
