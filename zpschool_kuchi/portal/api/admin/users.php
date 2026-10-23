<?php
/**
 * GET / POST /api/admin/users.php
 * Admin: User Management, Account Activation & Password Reset
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole('admin');
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$in = getJsonInput();

if ($method === 'GET') {
    $role = trim((string)($in['role'] ?? ''));
    $q = trim((string)($in['q'] ?? ''));

    $where = ["school_id = ?"];
    $params = [SCHOOL_ID];

    if (!empty($role)) {
        $where[] = "role = ?";
        $params[] = $role;
    }
    if (!empty($q)) {
        $where[] = "(name LIKE ? OR mobile LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    $sql = "SELECT id, school_id, role, name, mobile, email, linked_id, is_active, last_login, created_at 
            FROM users 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY FIELD(role, 'admin', 'teacher', 'student'), id ASC LIMIT 100";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    apiSuccess(['total' => count($users), 'users' => $users], 'वापरकर्ते यादी यशस्वी.');
}

if ($method === 'POST') {
    $action = $in['action'] ?? 'reset_password';

    if ($action === 'reset_password') {
        $target_user_id = (int)($in['user_id'] ?? 0);
        $new_password = (string)($in['new_password'] ?? '');

        if ($target_user_id <= 0 || empty($new_password)) {
            apiError('वापरकर्ता आयडी आणि नवीन पासवर्ड आवश्यक आहे.', 400);
        }

        if (strlen($new_password) < 6) {
            apiError('पासवर्ड किमान ६ अक्षरांचा असावा.', 400);
        }

        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND school_id = ?");
        $stmt->execute([$hash, $target_user_id, SCHOOL_ID]);

        if ($stmt->rowCount() > 0) {
            apiSuccess(['user_id' => $target_user_id], 'पासवर्ड यशस्वीरित्या बदलला गेला!');
        } else {
            apiError('वापरकर्ता सापडला नाही किंवा पासवर्ड बदलला नाही.', 404);
        }
    }

    if ($action === 'toggle_active') {
        $target_user_id = (int)($in['user_id'] ?? 0);
        $is_active = (int)($in['is_active'] ?? 1);

        $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND school_id = ?");
        $stmt->execute([$is_active, $target_user_id, SCHOOL_ID]);

        apiSuccess(['user_id' => $target_user_id, 'is_active' => $is_active], 'खात्याची स्थिती अद्यतनित झाली.');
    }
}
