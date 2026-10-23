<?php
/**
 * Webhook handler — supports:
 *  1. Meta WhatsApp Cloud API (native webhook, GET verify + POST messages)
 *  2. Twilio WhatsApp (POST with From/Body fields, returns TwiML)
 *
 * For n8n use the /api/chat.php endpoint instead.
 */
require_once __DIR__ . '/includes/bot.php';

/* ── Meta webhook verification (GET) ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = getenv('WHATSAPP_VERIFY_TOKEN') ?: 'techedu_verify_2026';
    $mode        = $_GET['hub_mode']          ?? '';
    $token       = $_GET['hub_verify_token']  ?? '';
    $challenge   = $_GET['hub_challenge']     ?? '';

    if ($mode === 'subscribe' && $token === $verifyToken) {
        echo $challenge;
    } else {
        http_response_code(403);
        echo 'Forbidden';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$raw  = file_get_contents('php://input');
$json = json_decode($raw, true);

/* ── Meta WhatsApp Cloud API (JSON body) ── */
if ($json && isset($json['object']) && $json['object'] === 'whatsapp_business_account') {
    $accessToken = getenv('WHATSAPP_ACCESS_TOKEN') ?: '';
    $phoneId     = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '';

    foreach ($json['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            $messages = $change['value']['messages'] ?? [];
            foreach ($messages as $m) {
                if (($m['type'] ?? '') !== 'text') continue;
                $from = $m['from']; // e.g. 919876543210
                $text = $m['text']['body'] ?? '';
                if (!$text) continue;

                $reply = processMessage('meta:' . $from, $text);

                // Send reply via Meta API
                if ($accessToken && $phoneId) {
                    $payload = json_encode([
                        'messaging_product' => 'whatsapp',
                        'to'                => $from,
                        'type'              => 'text',
                        'text'              => ['body' => $reply],
                    ]);
                    $ch = curl_init("https://graph.facebook.com/v19.0/{$phoneId}/messages");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $payload,
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            "Authorization: Bearer {$accessToken}",
                        ],
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
        }
    }
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

/* ── Twilio WhatsApp (form POST) ── */
$from = trim($_POST['From'] ?? '');
$body = trim($_POST['Body'] ?? '');

if ($from && $body) {
    $reply = processMessage($from, $body);
    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response><Message>' . htmlspecialchars($reply, ENT_XML1, 'UTF-8') . '</Message></Response>';
    exit;
}

http_response_code(400);
echo 'Bad Request';
