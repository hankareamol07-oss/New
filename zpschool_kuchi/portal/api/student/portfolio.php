<?php
declare(strict_types=1);

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['student', 'teacher', 'admin']);
$db = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            if ($user['role'] === 'student') {
                $studentId = (int)($user['linked_id'] ?? 0);
            } else {
                $studentId = (int)($_GET['student_id'] ?? 0);
                if ($studentId <= 0) {
                    apiError('विद्यार्थी आयडी आवश्यक आहे.', 400);
                }
                // Verify teacher scope
                $scope = getTeacherClassScope($user);
                if (!$scope['is_admin']) {
                    $st = $db->prepare("SELECT std, class_id FROM students WHERE id = ?");
                    $st->execute([$studentId]);
                    $s = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$s || (!in_array((int)$s['std'], $scope['stds']) && !in_array((int)$s['class_id'], $scope['class_ids']))) {
                        apiError('प्रवेश नाकारला.', 403);
                    }
                }
            }

            $stmt = $db->prepare("
                SELECT p.*, s.name, s.name_mr, s.std, s.roll_no 
                FROM student_portfolio p
                JOIN students s ON p.student_id = s.id
                WHERE p.student_id = ? AND p.school_id = ?
                ORDER BY p.id DESC
            ");
            $stmt->execute([$studentId, SCHOOL_ID]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            apiSuccess(['items' => $items, 'total' => count($items)], 'पोर्टफोलिओ सूची लोड झाली.');
            break;

        case 'upload':
            if ($user['role'] !== 'student') {
                apiError('केवळ विद्यार्थी स्वतःचे उपक्रम फोटो अपलोड करू शकतात.', 403);
            }

            $studentId = (int)($user['linked_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'चित्रकला'));
            $description = trim((string)($_POST['description'] ?? ''));

            if (empty($title)) {
                apiError('कृपया उपक्रम / चित्राचे नाव प्रविष्ट करा.', 400);
            }

            if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                apiError('कृपया उपक्रमाचा फोटो निवडा.', 400);
            }

            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                apiError('केवळ JPG, PNG किंवा WEBP फोटो स्वीकारले जातील.', 400);
            }

            $uploadDir = __DIR__ . '/../../uploads/portfolio';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = 'port_' . $studentId . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
            $targetPath = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                apiError('फोटो जतन करताना त्रुटी आली.', 500);
            }

            // Fetch student std
            $stRow = $db->prepare("SELECT std FROM students WHERE id = ?");
            $stRow->execute([$studentId]);
            $std = (int)$stRow->fetchColumn() ?: 1;

            $relPath = 'uploads/portfolio/' . $fileName;

            $ins = $db->prepare("
                INSERT INTO student_portfolio (school_id, student_id, std, academic_year, title, category, description, image_path, created_at)
                VALUES (?, ?, ?, '2026-27', ?, ?, ?, ?, NOW())
            ");
            $ins->execute([SCHOOL_ID, $studentId, $std, $title, $category, $description, $relPath]);

            apiSuccess([
                'id'         => (int)$db->lastInsertId(),
                'title'      => $title,
                'image_path' => $relPath
            ], 'उपक्रम फोटो आपल्या फोल्डरमध्ये यशस्वीरित्या जतन झाला! 🎉');
            break;

        case 'feedback':
            if ($user['role'] !== 'teacher' && $user['role'] !== 'admin') {
                apiError('केवळ शिक्षक अभिप्राय देऊ शकतात.', 403);
            }

            $in = getJsonInput();
            $itemId = (int)($in['item_id'] ?? $_POST['item_id'] ?? 0);
            $remarks = trim((string)($in['remarks'] ?? $_POST['remarks'] ?? ''));
            $stars = (int)($in['stars'] ?? $_POST['stars'] ?? 5);

            if ($itemId <= 0) {
                apiError('अवैध उपक्रम आयडी.', 400);
            }

            $up = $db->prepare("UPDATE student_portfolio SET teacher_remarks = ?, stars = ? WHERE id = ? AND school_id = ?");
            $up->execute([$remarks, $stars, $itemId, SCHOOL_ID]);

            apiSuccess([], 'शिक्षकांचा अभिप्राय नोंदवला गेला! ⭐');
            break;

        case 'delete':
            $in = getJsonInput();
            $itemId = (int)($in['item_id'] ?? $_POST['item_id'] ?? 0);
            if ($itemId <= 0) {
                apiError('अवैध उपक्रम आयडी.', 400);
            }

            $chk = $db->prepare("SELECT student_id, image_path FROM student_portfolio WHERE id = ? AND school_id = ?");
            $chk->execute([$itemId, SCHOOL_ID]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                apiError('उपक्रम सापडला नाही.', 404);
            }

            if ($user['role'] === 'student' && (int)$user['linked_id'] !== (int)$row['student_id']) {
                apiError('आपण केवळ स्वतःचे फोटो हटवू शकता.', 403);
            }

            // Remove file if exists
            $fullPath = __DIR__ . '/../../' . $row['image_path'];
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }

            $del = $db->prepare("DELETE FROM student_portfolio WHERE id = ? AND school_id = ?");
            $del->execute([$itemId, SCHOOL_ID]);

            apiSuccess([], 'उपक्रम हटवण्यात आला.');
            break;

        default:
            apiError('अवैध क्रिया.', 400);
    }
} catch (Throwable $e) {
    apiError('त्रुटी: ' . $e->getMessage(), 500);
}
