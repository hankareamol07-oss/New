<?php
require_once __DIR__ . '/config.php';

// Greeting patterns Gemini sometimes adds despite instructions
define('GEMINI_GREETING_PATTERN',
    '/^(नमस्कार[^\n]*|Hello[^\n]*|Hi[^\n]*|Sure[^\n]*|Certainly[^\n]*|Of course[^\n]*|Great[^\n]*|Okay[^\n]*|अर्थात[^\n]*|बालमित्रांनो[^\n]*)\n+/ui'
);

function callGemini(string $prompt, int $maxTokens = 4096, int $retries = 2): string {
    if (!GEMINI_API_KEY || !GEMINI_BASE_URL) return '';

    // Gemini 2.5 Flash uses ~1500 thinking tokens internally; add headroom on top of requested output
    $totalTokens = max($maxTokens, 512) + 2048;

    $url  = GEMINI_BASE_URL . '/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
    $body = json_encode([
        'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => $totalTokens],
    ]);

    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $resp = curl_exec($ch);
        $err  = curl_errno($ch);
        curl_close($ch);

        if ($resp && !$err) {
            $json = json_decode($resp, true);
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ($text !== '') {
                // Strip any greeting/preamble Gemini adds despite instructions
                $text = preg_replace(GEMINI_GREETING_PATTERN, '', $text);
                return ltrim($text);
            }
        }

        // Brief pause before retry
        if ($attempt < $retries) usleep(500000);
    }

    return '';
}
