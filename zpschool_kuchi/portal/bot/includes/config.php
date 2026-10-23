<?php
$dbUrl = getenv('DATABASE_URL');
$parsed = parse_url($dbUrl);

define('DB_HOST', $parsed['host'] ?? 'localhost');
define('DB_PORT', $parsed['port'] ?? 5432);
define('DB_NAME', ltrim($parsed['path'] ?? '/techedu', '/'));
define('DB_USER', $parsed['user'] ?? '');
define('DB_PASS', $parsed['pass'] ?? '');

define('GEMINI_API_KEY',  getenv('AI_INTEGRATIONS_GEMINI_API_KEY') ?: '');
define('GEMINI_BASE_URL', rtrim(getenv('AI_INTEGRATIONS_GEMINI_BASE_URL') ?: '', '/'));
define('TWILIO_SID',      getenv('TWILIO_ACCOUNT_SID') ?: '');
define('TWILIO_TOKEN',    getenv('TWILIO_AUTH_TOKEN') ?: '');
define('TWILIO_WA_FROM',  getenv('TWILIO_WHATSAPP_NUMBER') ?: 'whatsapp:+14155238886');
