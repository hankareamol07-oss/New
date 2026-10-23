<?php
declare(strict_types=1);

require_once __DIR__ . '/common/response.php';
require_once __DIR__ . '/common/auth_guard.php';

$user = null;
if (session_status() === PHP_SESSION_NONE) session_start();
$db = getDB();

if (!empty($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT id, school_id, role, name, mobile, email, linked_id, is_active FROM users WHERE id = ? AND school_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id'], SCHOOL_ID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$user) {
    $token = getBearerToken();
    if ($token) {
        $parts = explode('.', $token);
        if (count($parts) === 2) {
            list($encodedPayload, $providedSig) = $parts;
            $expectedSig = hash_hmac('sha256', $encodedPayload, AUTH_SECRET);
            if (hash_equals($expectedSig, $providedSig)) {
                $payload = json_decode(base64UrlDecode($encodedPayload), true);
                if ($payload && isset($payload['uid']) && isset($payload['exp']) && time() <= $payload['exp']) {
                    $stmt = $db->prepare("SELECT id, school_id, role, name, mobile, email, linked_id, is_active FROM users WHERE id = ? AND school_id = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([(int)$payload['uid'], SCHOOL_ID]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        }
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $where = ["m.school_id = ?"];
            $params = [SCHOOL_ID];

            $reqStd = (int)($_GET['std'] ?? ($_SESSION['std'] ?? 0));

            if ($user && $user['role'] === 'student') {
                $st = $db->prepare("SELECT std FROM students WHERE id = ?");
                $st->execute([(int)$user['linked_id']]);
                $stStd = (int)$st->fetchColumn() ?: ($reqStd ?: 6);

                $where[] = "m.std = ?";
                $params[] = $stStd;
            } elseif ($user && $user['role'] === 'teacher') {
                $scope = getTeacherClassScope($user);
                if (!$scope['is_admin']) {
                    if (!empty($scope['stds'])) {
                        $stdList = implode(',', $scope['stds']);
                        $where[] = "(m.std IN ($stdList) OR m.teacher_id = ?)";
                        $params[] = (int)$user['linked_id'];
                    } else {
                        $where[] = "m.teacher_id = ?";
                        $params[] = (int)$user['linked_id'];
                    }
                }
            } else {
                // Public student view / parent portal: filter by requested or session std if provided
                if ($reqStd > 0) {
                    $where[] = "m.std = ?";
                    $params[] = $reqStd;
                }
            }

            $subject = trim((string)($_GET['subject'] ?? ''));
            if (!empty($subject)) {
                $where[] = "m.subject = ?";
                $params[] = $subject;
            }

            $sql = "
                SELECT m.* 
                FROM study_materials m
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.id DESC 
                LIMIT 50
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            apiSuccess(['materials' => $materials, 'total' => count($materials)], 'अभ्यास साहित्य लोड झाले.');
            break;

        case 'upload':
            if (!$user || ($user['role'] !== 'teacher' && $user['role'] !== 'admin')) {
                apiError('केवळ शिक्षक अभ्यास PDF पाठवू शकतात.', 403);
            }

            $title = trim((string)($_POST['title'] ?? ''));
            $subject = trim((string)($_POST['subject'] ?? 'मराठी'));
            $description = trim((string)($_POST['description'] ?? ''));
            $std = (int)($_POST['std'] ?? 0);

            if ($std < 1 || $std > 8) {
                apiError('कृपया योग्य इयत्ता निवडा (१ ते ७).', 400);
            }

            // Verify teacher scope
            $scope = getTeacherClassScope($user);
            if (!$scope['is_admin'] && !in_array($std, $scope['stds'])) {
                apiError('प्रवेश नाकारला: आपण केवळ आपल्या वर्गासाठी PDF पाठवू शकता.', 403);
            }

            if (empty($title)) {
                apiError('कृपया अभ्यास साहित्याचे नाव / शीर्षक प्रविष्ट करा.', 400);
            }

            if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                apiError('कृपया PDF फाईल निवडा.', 400);
            }

            $file = $_FILES['pdf'];
            $origName = basename($file['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['pdf', 'doc', 'docx'])) {
                apiError('केवळ PDF किंवा DOCX फाईल स्वीकारली जाईल.', 400);
            }

            $uploadDir = __DIR__ . '/../uploads/study_materials';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = 'study_std' . $std . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
            $targetPath = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                apiError('फाईल जतन करताना त्रुटी आली.', 500);
            }

            $relPath = 'uploads/study_materials/' . $fileName;
            $teacherId = (int)($user['linked_id'] ?? $user['id']);
            $teacherName = $user['name'] ?? 'शिक्षक';
            $fileSize = (int)$file['size'];

            $ins = $db->prepare("
                INSERT INTO study_materials (school_id, teacher_id, teacher_name, std, subject, title, description, file_path, file_name, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([SCHOOL_ID, $teacherId, $teacherName, $std, $subject, $title, $description, $relPath, $origName, $fileSize]);

            apiSuccess([
                'id'        => (int)$db->lastInsertId(),
                'title'     => $title,
                'file_path' => $relPath
            ], 'अभ्यास PDF विद्यार्थ्यांना यशस्वीरित्या पाठवली गेली! 📚');
            break;

        case 'delete':
            if (!$user || ($user['role'] !== 'teacher' && $user['role'] !== 'admin')) {
                apiError('प्रवेश नाकारला.', 403);
            }

            $in = getJsonInput();
            $matId = (int)($in['id'] ?? $_POST['id'] ?? 0);
            if ($matId <= 0) {
                apiError('अवैध आयडी.', 400);
            }

            $chk = $db->prepare("SELECT teacher_id, file_path FROM study_materials WHERE id = ? AND school_id = ?");
            $chk->execute([$matId, SCHOOL_ID]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                apiError('साहित्य सापडले नाही.', 404);
            }

            $scope = getTeacherClassScope($user);
            if (!$scope['is_admin'] && (int)$user['linked_id'] !== (int)$row['teacher_id']) {
                apiError('आपण केवळ स्वतः पाठवलेले अभ्यास साहित्य हटवू शकता.', 403);
            }

            $fullPath = __DIR__ . '/../' . $row['file_path'];
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }

            $del = $db->prepare("DELETE FROM study_materials WHERE id = ? AND school_id = ?");
            $del->execute([$matId, SCHOOL_ID]);

            apiSuccess([], 'अभ्यास साहित्य हटवण्यात आले.');
            break;

        default:
            apiError('अवैध क्रिया.', 400);
    }
} catch (Throwable $e) {
    apiError('त्रुटी: ' . $e->getMessage(), 500);
}
