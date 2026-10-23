<?php
declare(strict_types=1);

require_once __DIR__ . '/common/response.php';
require_once __DIR__ . '/common/auth_guard.php';

$user = requireRole(['student', 'teacher', 'admin']);
$db = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $where = ["g.school_id = ?"];
            $params = [SCHOOL_ID];

            if ($user['role'] === 'student') {
                // Fetch student's standard
                $st = $db->prepare("SELECT std FROM students WHERE id = ?");
                $st->execute([(int)$user['linked_id']]);
                $stStd = (int)$st->fetchColumn() ?: 1;

                $where[] = "(g.visibility = 'all' OR (g.visibility = 'class' AND g.std = ?))";
                $params[] = $stStd;
            } elseif ($user['role'] === 'teacher') {
                $scope = getTeacherClassScope($user);
                if (!$scope['is_admin']) {
                    $stdList = !empty($scope['stds']) ? implode(',', $scope['stds']) : '0';
                    $where[] = "(g.visibility = 'all' OR g.teacher_id = ? OR (g.visibility = 'class' AND g.std IN ($stdList)))";
                    $params[] = (int)$user['linked_id'];
                }
            }

            $sql = "
                SELECT g.* 
                FROM school_gallery g
                WHERE " . implode(' AND ', $where) . "
                ORDER BY g.id DESC 
                LIMIT 50
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            apiSuccess(['posts' => $posts, 'total' => count($posts)], 'उपक्रम दालन सूची लोड झाली.');
            break;

        case 'create':
            if ($user['role'] !== 'teacher' && $user['role'] !== 'admin') {
                apiError('केवळ शिक्षक शाळा उपक्रम फोटो प्रकाशित करू शकतात.', 403);
            }

            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $visibility = trim((string)($_POST['visibility'] ?? 'all'));
            $std = isset($_POST['std']) && $_POST['std'] !== '' ? (int)$_POST['std'] : null;

            if ($visibility !== 'all' && $visibility !== 'class') {
                $visibility = 'all';
            }

            if (empty($title)) {
                apiError('कृपया उपक्रमाचे शीर्षक प्रविष्ट करा.', 400);
            }

            if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                apiError('कृपया उपक्रमाचा फोटो निवडा.', 400);
            }

            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                apiError('केवळ JPG, PNG किंवा WEBP फोटो स्वीकारले जातील.', 400);
            }

            $uploadDir = __DIR__ . '/../uploads/gallery';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = 'gal_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
            $targetPath = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                apiError('फोटो जतन करताना त्रुटी आली.', 500);
            }

            $relPath = 'uploads/gallery/' . $fileName;
            $teacherId = (int)($user['linked_id'] ?? $user['id']);
            $teacherName = $user['name'] ?? 'शिक्षक';

            $ins = $db->prepare("
                INSERT INTO school_gallery (school_id, teacher_id, teacher_name, title, description, image_path, visibility, std, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([SCHOOL_ID, $teacherId, $teacherName, $title, $description, $relPath, $visibility, $std]);

            apiSuccess([
                'id'         => (int)$db->lastInsertId(),
                'title'      => $title,
                'image_path' => $relPath
            ], 'उपक्रम फोटो शाळेच्या वॉलवर यशस्वीरित्या प्रकाशित झाला! 📸');
            break;

        case 'delete':
            if ($user['role'] !== 'teacher' && $user['role'] !== 'admin') {
                apiError('प्रवेश नाकारला.', 403);
            }

            $in = getJsonInput();
            $postId = (int)($in['post_id'] ?? $_POST['post_id'] ?? 0);
            if ($postId <= 0) {
                apiError('अवैध पोस्ट आयडी.', 400);
            }

            $chk = $db->prepare("SELECT teacher_id, image_path FROM school_gallery WHERE id = ? AND school_id = ?");
            $chk->execute([$postId, SCHOOL_ID]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                apiError('पोस्ट आढळली नाही.', 404);
            }

            $scope = getTeacherClassScope($user);
            if (!$scope['is_admin'] && (int)$user['linked_id'] !== (int)$row['teacher_id']) {
                apiError('आपण केवळ स्वतः प्रकाशित केलेल्या पोस्ट हटवू शकता.', 403);
            }

            $fullPath = __DIR__ . '/../' . $row['image_path'];
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }

            $del = $db->prepare("DELETE FROM school_gallery WHERE id = ? AND school_id = ?");
            $del->execute([$postId, SCHOOL_ID]);

            apiSuccess([], 'उपक्रम पोस्ट हटवण्यात आली.');
            break;

        default:
            apiError('अवैध क्रिया.', 400);
    }
} catch (Throwable $e) {
    apiError('त्रुटी: ' . $e->getMessage(), 500);
}
