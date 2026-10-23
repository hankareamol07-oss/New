<?php
/**
 * Role-Based Authentication & Authorization Guard
 * Enforces strict role isolation (Admin, Teacher, Student)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/response.php';

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

/**
 * Generate Secure Signed Token
 */
function issueUserToken(array $user): string {
    $payload = [
        'uid'  => (int)$user['id'],
        'role' => (string)$user['role'],
        'lid'  => (int)($user['linked_id'] ?? 0),
        'mob'  => (string)$user['mobile'],
        'iat'  => time(),
        'exp'  => time() + (86400 * 30), // 30 days validity
    ];
    $json = json_encode($payload);
    $encoded = base64UrlEncode($json);
    $sig = hash_hmac('sha256', $encoded, AUTH_SECRET);
    return $encoded . '.' . $sig;
}

/**
 * Extract Authorization Token from Header
 */
function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (empty($header) && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $header = $v;
                break;
            }
        }
    }
    if (!empty($header) && preg_match('/Bearer\s(\S+)/i', $header, $matches)) {
        return $matches[1];
    }
    return !empty($_GET['token']) ? trim($_GET['token']) : null;
}

/**
 * Validate Token & Retrieve User Record
 */
function requireAuth(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // 1. Check active PHP session first
    if (!empty($_SESSION['user_id'])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, school_id, role, name, mobile, email, linked_id, is_active FROM users WHERE id = ? AND school_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id'], SCHOOL_ID]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) return $user;
    }

    // 2. Check Bearer token
    $token = getBearerToken();
    if (!$token) {
        apiError('अनधिकृत: कृपया प्रथम लॉगिन करा (Token missing).', 401);
    }

    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        apiError('अनधिकृत: अवैध टोकन स्वरूप.', 401);
    }

    list($encodedPayload, $providedSig) = $parts;
    $expectedSig = hash_hmac('sha256', $encodedPayload, AUTH_SECRET);

    if (!hash_equals($expectedSig, $providedSig)) {
        apiError('अनधिकृत: अवैध किंवा छेडछाड केलेले टोकन.', 401);
    }

    $payload = json_decode(base64UrlDecode($encodedPayload), true);
    if (!$payload || !isset($payload['uid']) || !isset($payload['exp'])) {
        apiError('अनधिकृत: चुकीचा टोकन डेटा.', 401);
    }

    if (time() > $payload['exp']) {
        apiError('आपले लॉगिन सत्र संपले आहे. कृपया पुन्हा लॉगिन करा (Token expired).', 401);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, school_id, role, name, mobile, email, linked_id, is_active FROM users WHERE id = ? AND school_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([(int)$payload['uid'], SCHOOL_ID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        apiError('वापरकर्ता खाते सापडले नाही किंवा निष्क्रिय आहे.', 403);
    }

    // Set active PHP session for backward compatibility with existing PDF/print renderers
    $_SESSION['school_id'] = (int)$user['school_id'];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = (string)$user['role'];
    $_SESSION['user'] = $user;

    return $user;
}

/**
 * Require Specific Role(s)
 */
function requireRole(array|string $allowedRoles): array {
    $user = requireAuth();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    if (!in_array($user['role'], $roles, true)) {
        apiError('प्रवेश नाकारला: या सुविधेसाठी आपल्या खात्याला (' . $user['role'] . ') परवानगी नाही.', 403);
    }
    return $user;
}

/**
 * Teacher Scope: Return teacher's assigned standard(s) & class ID(s)
 * Strict class-only visibility!
 */
function getTeacherClassScope(array $user): array {
    $db = getDB();
    if ($user['role'] === 'admin') {
        // Admin has access to all classes 1 to 7
        $classes = $db->query("SELECT id, std, name, section FROM classes WHERE school_id = " . SCHOOL_ID . " ORDER BY std")->fetchAll();
        return [
            'is_admin' => true,
            'classes' => $classes,
            'class_ids' => array_column($classes, 'id'),
            'stds' => array_column($classes, 'std'),
        ];
    }

    // Teacher: strictly query teacher_classes mapping
    $teacherId = (int)$user['linked_id'];
    $stmt = $db->prepare("
        SELECT tc.class_id, tc.std, tc.division, tc.is_class_teacher, c.name as class_name 
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        WHERE tc.school_id = ? AND tc.teacher_id = ?
        ORDER BY tc.std
    ");
    $stmt->execute([SCHOOL_ID, $teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($classes)) {
        // Fallback: check classes where class_teacher_id = teacherId
        $stmt2 = $db->prepare("SELECT id as class_id, std, section as division, 1 as is_class_teacher, name as class_name FROM classes WHERE school_id = ? AND class_teacher_id = ?");
        $stmt2->execute([SCHOOL_ID, $teacherId]);
        $classes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    $stds = array_map('intval', array_column($classes, 'std'));
    $classIds = array_map('intval', array_column($classes, 'class_id'));
    if (in_array(6, $stds, true) || in_array(7, $stds, true)) {
        if (!in_array(6, $stds, true)) $stds[] = 6;
        if (!in_array(7, $stds, true)) $stds[] = 7;
        sort($stds);
    }

    return [
        'is_admin' => false,
        'teacher_id' => $teacherId,
        'classes' => $classes,
        'class_ids' => $classIds,
        'stds' => $stds,
    ];
}

/**
 * Verify Teacher Has Access to a Specific Student
 */
function assertTeacherStudentAccess(array $user, int $studentId): array {
    $db = getDB();
    $scope = getTeacherClassScope($user);

    $st = $db->prepare("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ? AND s.school_id = ? LIMIT 1");
    $st->execute([$studentId, SCHOOL_ID]);
    $student = $st->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        apiError('विद्यार्थी सापडला नाही.', 404);
    }

    if ($scope['is_admin']) {
        return $student;
    }

    // Strict check: Student's class_id or std must belong to teacher's assigned classes
    if (!in_array((int)$student['class_id'], $scope['class_ids']) && !in_array((int)$student['std'], $scope['stds'])) {
        apiError('प्रवेश नाकारला: हा विद्यार्थी आपल्या नेमून दिलेल्या वर्गातील नाही.', 403);
    }

    return $student;
}
