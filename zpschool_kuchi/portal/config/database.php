<?php
/**
 * Single-School Compatibility Bridge
 * Routes to /config/config.php
 * School: Z. P. School No. 2 Kuchi
 */

require_once __DIR__ . '/config.php';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) return array_map('sanitize', $data);
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return !empty($_SESSION['user_id']) || !empty($_SESSION['school_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            redirect(APP_URL . '/index.php');
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isAdmin()) {
            redirect(APP_URL . '/index.php');
        }
    }
}

if (!function_exists('flash')) {
    function flash($key, $message = null) {
        if ($message === null) {
            $msg = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
        $_SESSION['flash'][$key] = $message;
    }
}

if (!function_exists('getFlash')) {
    function getFlash($key) {
        return flash($key);
    }
}

if (!function_exists('displayFlash')) {
    function displayFlash() {
        $types = [
            'success' => ['class' => 'alert-success', 'icon' => 'bi-check-circle-fill'],
            'error'   => ['class' => 'alert-danger',  'icon' => 'bi-exclamation-triangle-fill'],
            'warning' => ['class' => 'alert-warning', 'icon' => 'bi-exclamation-circle-fill'],
            'info'    => ['class' => 'alert-info',    'icon' => 'bi-info-circle-fill']
        ];
        foreach ($types as $key => $meta) {
            $msg = flash($key);
            if ($msg) {
                echo '<div class="alert ' . $meta['class'] . ' alert-dismissible fade show mb-4 shadow-sm" role="alert" style="border-radius:10px;">';
                echo '<i class="bi ' . $meta['icon'] . ' me-2"></i>' . sanitize($msg);
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
        }
    }
}

if (!function_exists('currentAcademicYear')) {
    function currentAcademicYear() {
        return ACADEMIC_YEAR;
    }
}

if (!function_exists('academicYears')) {
    function academicYears() {
        return ['2026-27', '2025-26', '2024-25'];
    }
}
