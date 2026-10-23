<?php
/**
 * GET /api/admin/dashboard.php
 * Admin Dashboard Statistics & Overview
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole('admin');
$db = getDB();

// 1. Total counts
$total_students = (int)$db->query("SELECT COUNT(*) FROM students WHERE school_id = " . SCHOOL_ID . " AND is_active = 1")->fetchColumn();
$total_teachers = (int)$db->query("SELECT COUNT(*) FROM teachers WHERE school_id = " . SCHOOL_ID . " AND is_active = 1")->fetchColumn();
$total_users = (int)$db->query("SELECT COUNT(*) FROM users WHERE school_id = " . SCHOOL_ID . " AND is_active = 1")->fetchColumn();
$hpc_completed = (int)$db->query("SELECT COUNT(*) FROM hpc_cards WHERE school_id = " . SCHOOL_ID . " AND academic_year = '" . ACADEMIC_YEAR . "' AND status = 'completed'")->fetchColumn();

// 2. Class-wise breakdown
$classes = $db->query("
    SELECT c.id, c.std, c.name as class_name, c.section,
           t.name_mr as class_teacher_name, t.phone as teacher_phone,
           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.school_id = " . SCHOOL_ID . " AND s.is_active = 1) as student_count,
           (SELECT COUNT(*) FROM students s JOIN hpc_cards hc ON s.id = hc.student_id WHERE s.class_id = c.id AND hc.academic_year = '" . ACADEMIC_YEAR . "') as hpc_count
    FROM classes c
    LEFT JOIN teachers t ON c.class_teacher_id = t.id
    WHERE c.school_id = " . SCHOOL_ID . "
    ORDER BY c.std ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Gender breakdown
$genders = $db->query("
    SELECT gender, COUNT(*) as count 
    FROM students 
    WHERE school_id = " . SCHOOL_ID . " AND is_active = 1 
    GROUP BY gender
")->fetchAll(PDO::FETCH_KEY_PAIR);

apiSuccess([
    'school' => getSchool(),
    'metrics' => [
        'total_students'  => $total_students,
        'total_teachers'  => $total_teachers,
        'total_users'     => $total_users,
        'hpc_completed'   => $hpc_completed,
        'boys'            => $genders['मुलगा'] ?? 0,
        'girls'           => $genders['मुलगी'] ?? 0,
    ],
    'classes' => $classes,
], 'शाळा डॅशबोर्ड सांख्यिकी यशस्वी.');
