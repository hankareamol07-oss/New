<?php
/**
 * GET /api/teacher/classes.php
 * Returns classes assigned strictly to this teacher.
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$scope = getTeacherClassScope($user);

apiSuccess([
    'teacher' => [
        'id'       => (int)($user['linked_id'] ?? $user['id']),
        'name'     => $user['name'],
        'role'     => $user['role'],
        'is_admin' => $scope['is_admin'],
    ],
    'classes' => $scope['classes'],
], 'शिक्षकांचे वर्ग यशस्वीरित्या लोड झाले.');
