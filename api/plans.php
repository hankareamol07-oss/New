<?php
/**
 * GET /api/plans.php
 * Returns active subscription plans + current school plan for the Android app.
 * Drop this file into hpc_live_production/api/ next to dashboard.php.
 * Requires: Authorization: Bearer <token>
 */

require_once __DIR__ . '/helpers.php';

$schoolId = getAuthSchoolId();
$db       = getDB();

$school = $db->prepare('SELECT plan_id, subscription_end FROM schools WHERE id = ? LIMIT 1');
$school->execute([$schoolId]);
$schoolData = $school->fetch(PDO::FETCH_ASSOC) ?: [];

$rows = $db->query(
    'SELECT id, name, name_mr, max_students, price, duration_months, features, included_modules
     FROM plans WHERE is_active = 1 ORDER BY price ASC, max_students ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$plans = [];
foreach ($rows as $p) {
    $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($p['features'] ?? '')))));
    $modules  = json_decode((string) ($p['included_modules'] ?? '[]'), true);
    $plans[]  = [
        'id'              => (int) $p['id'],
        'name'            => $p['name'],
        'name_mr'         => $p['name_mr'],
        'max_students'    => (int) $p['max_students'],
        'price'           => (float) $p['price'],
        'duration_months' => (int) $p['duration_months'],
        'features'        => $features,
        'modules'         => is_array($modules) ? array_values($modules) : [],
        'is_current'      => (int) $p['id'] === (int) ($schoolData['plan_id'] ?? 0),
    ];
}

apiSuccess([
    'plans'            => $plans,
    'current_plan_id'  => (int) ($schoolData['plan_id'] ?? 0),
    'subscription_end' => $schoolData['subscription_end'] ?? null,
    'checkout_url'     => rtrim(APP_URL, '/') . '/subscription/checkout.php',
]);
