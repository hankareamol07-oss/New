<?php
/**
 * Single-School System Configuration
 * School: Z. P. School No. 2 Kuchi (UDISE: 27350302306)
 */

// Load .env file
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'hpc_new');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// School Constants
define('SCHOOL_ID', (int)(getenv('SCHOOL_ID') ?: 2));
define('SCHOOL_NAME', getenv('SCHOOL_NAME') ?: 'Z. P. School No 2 Kuchi');
define('SCHOOL_NAME_MR', getenv('SCHOOL_NAME_MR') ?: 'जिल्हा परिषद शाळा नं २ कुची');
define('UDISE_CODE', getenv('UDISE_CODE') ?: '27350302306');
define('TALUKA', getenv('TALUKA') ?: 'कवठे महांकाळ');
define('DISTRICT', getenv('DISTRICT') ?: 'सांगली');
define('STATE', getenv('STATE') ?: 'महाराष्ट्र');
define('ACADEMIC_YEAR', getenv('ACADEMIC_YEAR') ?: '2026-27');
define('AUTH_SECRET', getenv('AUTH_SECRET') ?: 'Kuchi_ZP2_SecureSecretKey_2026_NEP2020');
define('APP_NAME', 'जि. प. शाळा नं. २ कुची');
define('APP_NAME_EN', 'Z. P. School No 2 Kuchi');
define('APP_VERSION', '1.0.0');
define('PLAY_STORE_PACKAGE', 'com.techguruji.zpschool2kuchi');
define('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.techguruji');

// App URL detection
$detected_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$detected_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ? 'https' : 'http';
$project_root = str_replace('\\', '/', realpath(dirname(__DIR__)));
$doc_root = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$detected_base = '';
if ($doc_root && str_starts_with($project_root, $doc_root)) {
    $detected_base = rtrim(substr($project_root, strlen($doc_root)), '/');
}
define('APP_URL', $detected_scheme . '://' . $detected_host . $detected_base);
define('DATA_DIR', dirname(__DIR__) . '/data');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * PDO Database Connection Singleton
 */
function getDB($reset = false): PDO {
    static $pdo = null;
    if ($reset) {
        $pdo = null;
        return null;
    }
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET NAMES " . DB_CHARSET);
    }
    return $pdo;
}

function getMainDB() {
    return getDB();
}

function academic_year(): string {
    return ACADEMIC_YEAR;
}

/**
 * Single-School: Always active, all features unlocked (No SaaS paywall)
 */
if (!function_exists('isSubscribedSchool')) {
    function isSubscribedSchool($school = null): bool {
        return true;
    }
}

if (!function_exists('can_access_hpc_class')) {
    function can_access_hpc_class(int $std, ?int $school_id = null): array {
        return ['allowed' => true, 'is_higher' => ($std >= 3), 'message' => 'सक्रिय'];
    }
}

if (!function_exists('getSchoolStudentQuota')) {
    function getSchoolStudentQuota($school_id): array {
        return [
            'count'        => 190,
            'max'          => 999999,
            'remaining'    => 999999,
            'can_add'      => true,
            'is_unlimited' => true,
            'plan_name'    => 'शाळा व्यवस्थापन प्रणाली (अमर्यादित)',
            'is_free'      => false
        ];
    }
}

function getSchool($forceRefresh = false): array {
    static $school = null;
    if ($school !== null && !$forceRefresh) return $school;
    $db = getDB();
    $st = $db->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
    $st->execute([SCHOOL_ID]);
    $school = $st->fetch();
    if (!$school) {
        $school = [
            'id' => SCHOOL_ID,
            'name' => SCHOOL_NAME,
            'name_mr' => SCHOOL_NAME_MR,
            'udise_code' => UDISE_CODE,
            'taluka' => TALUKA,
            'district' => DISTRICT,
            'state' => STATE,
        ];
    }
    return $school;
}

/**
 * Teacher Class Scope Guard (Strict Class-Level Isolation)
 * Returns assigned class/standard for teacher, or null if admin/student.
 */
function get_current_teacher_scope(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
        return null;
    }
    static $scopes = [];
    $teacherId = (int)($_SESSION['linked_id'] ?? $_SESSION['user']['linked_id'] ?? 0);
    if (isset($scopes[$teacherId])) return $scopes[$teacherId];
    $db = getDB();
    if (!$db) return null;

    $stmt = $db->prepare("
        SELECT tc.class_id, tc.std, tc.division, c.name as class_name 
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.id
        WHERE tc.school_id = ? AND tc.teacher_id = ?
        ORDER BY tc.std
    ");
    $stmt->execute([SCHOOL_ID, $teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($classes)) {
        $stmt2 = $db->prepare("SELECT id as class_id, std, section as division, name as class_name FROM classes WHERE school_id = ? AND class_teacher_id = ?");
        $stmt2->execute([SCHOOL_ID, $teacherId]);
        $classes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    $stds = array_map('intval', array_column($classes, 'std'));
    $class_ids = array_map('intval', array_column($classes, 'class_id'));

    // Strict Class Isolation: Teacher gets assigned class standard. Upper Primary (6th and 7th) share both 6th & 7th.
    $assigned_stds = !empty($stds) ? array_values(array_unique($stds)) : [1];
    if (in_array(6, $assigned_stds, true) || in_array(7, $assigned_stds, true)) {
        if (!in_array(6, $assigned_stds, true)) $assigned_stds[] = 6;
        if (!in_array(7, $assigned_stds, true)) $assigned_stds[] = 7;
    }
    sort($assigned_stds);

    $scope = [
        'teacher_id'   => $teacherId,
        'classes'      => $classes,
        'stds'         => $assigned_stds, // Strictly assigned class only
        'class_stds'   => $stds,
        'subject_stds' => $stds,
        'class_ids'    => $class_ids,
        'std'          => !empty($stds) ? $stds[0] : 1,
        'class_id'     => !empty($class_ids) ? $class_ids[0] : 1,
        'class_name'   => !empty($classes[0]['class_name']) ? $classes[0]['class_name'] : ('इयत्ता ' . (!empty($stds) ? $stds[0] : 1) . ' वी'),
    ];
    $scopes[$teacherId] = $scope;
    return $scope;
}

/**
 * AWS Bedrock AI Generation Helper (Claude 3.5 Haiku)
 */
function generateBedrockContent(string $prompt, string $systemPrompt = "You are an expert Marathi primary school teacher."): ?string {
    $baseUrl = getenv('OPENAI_BASE_URL') ?: 'https://bedrock-mantle.us-east-1.api.aws/v1';
    $apiKey = getenv('OPENAI_API_KEY');
    $model = getenv('BEDROCK_MODEL_HAIKU') ?: 'anthropic.claude-3-5-haiku-20241022-v1:0';

    if (empty($apiKey)) return null;

    $url = rtrim($baseUrl, '/') . '/chat/completions';
    $body = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2000
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 25
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $json = json_decode($res, true);
        return $json['choices'][0]['message']['content'] ?? null;
    }
    return null;
}
