<?php
declare(strict_types=1);
/**
 * मॉड्युल्ससाठी डेटाबेस थर — मुख्य पोर्टलचे MySQL कनेक्शन वापरते.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/tenant_db.php';
require_once __DIR__ . '/helpers.php';

function db(): PDO
{
    if (isset($_SESSION['school_id'])) {
        return getSchoolTenantDB((int)$_SESSION['school_id']);
    }
    return getMainDB();
}

function school_row(int $schoolId): array
{
    $stmt = getMainDB()->prepare('SELECT * FROM schools WHERE id=?');
    $stmt->execute([$schoolId]);
    $row = $stmt->fetch() ?: [];
    if ($row && empty($row['name']) && !empty($row['school_name'])) {
        $row['name'] = $row['school_name'];
    }
    return $row;
}

/** चालू शैक्षणिक वर्ष — नसल्यास आपोआप तयार करते */
function current_academic_year(int $schoolId): array
{
    $pdo = getMainDB();
    $stmt = $pdo->prepare('SELECT * FROM academic_years WHERE school_id=? ORDER BY is_current DESC,id DESC LIMIT 1');
    $stmt->execute([$schoolId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $label = academic_year();
    $pdo->prepare('INSERT INTO academic_years(school_id,label,is_current) VALUES(?,?,1)')->execute([$schoolId, $label]);
    return ['id' => (int) $pdo->lastInsertId(), 'school_id' => $schoolId, 'label' => $label, 'is_current' => 1];
}

function academic_years(int $schoolId): array
{
    current_academic_year($schoolId);
    $stmt = getMainDB()->prepare('SELECT * FROM academic_years WHERE school_id=? ORDER BY id DESC');
    $stmt->execute([$schoolId]);
    return $stmt->fetchAll();
}

function school_defaults(int $schoolId): array
{
    $pdo = getMainDB();
    $stmt = $pdo->prepare('SELECT * FROM school_defaults WHERE school_id=?');
    $stmt->execute([$schoolId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $pdo->prepare('INSERT INTO school_defaults(school_id,updated_at) VALUES(?,NOW())')->execute([$schoolId]);
    $stmt->execute([$schoolId]);
    return $stmt->fetch() ?: [];
}

function class_teacher_names(array $classes, int $schoolId): array
{
    $stmt = getMainDB()->prepare('SELECT id,name FROM users WHERE school_id=?');
    $stmt->execute([$schoolId]);
    $names = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
    foreach ($classes as &$class) {
        $class['teacher_name'] = $names[(int) ($class['class_teacher_id'] ?? 0)] ?? null;
    }
    unset($class);
    return $classes;
}
