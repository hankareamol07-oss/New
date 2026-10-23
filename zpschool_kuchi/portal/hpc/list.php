<?php
/**
 * Redirect to Unified HPC Hub (Class 1 & 2)
 */
require_once __DIR__ . '/../config/database.php';
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . APP_URL . '/hpc/index.php' . $query);
exit;
