<?php
// Temporary check: can this host call the Claude API? Delete this file after testing.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

// Key lives OUTSIDE the public web folders: <webspace root>/private/anthropic.key
$keyFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/anthropic.key';

// Throttle: at most one call per 30 seconds, so a stray visitor cannot run up a bill.
$stamp = sys_get_temp_dir() . '/apitest.stamp';
if (is_file($stamp) && time() - filemtime($stamp) < 30) {
    http_response_code(429);
    exit("throttled: try again in 30 seconds\n");
}
touch($stamp);

$key = is_readable($keyFile) ? trim((string) file_get_contents($keyFile)) : '';
if ($key === '') {
    exit("no-key: could not read the key file (check the folder name, file name, and permissions)\n");
}

$body = json_encode([
    'model' => 'claude-haiku-5-5',
    'max_tokens' => 60,
    'messages' => [[
        'role' => 'user',
        'content' => 'In one short sentence, greet visitors to skeletonbelly.com like a friendly skeleton.',
    ]],
]);

$t0 = microtime(true);
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'content-type: application/json',
        'anthropic-version: 2023-06-01',
        'x-api-key: ' . $key,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$ms = (int) round((microtime(true) - $t0) * 1000);

echo "php: " . PHP_VERSION . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "elapsed_ms: $ms\n";
if ($resp === false) {
    exit("curl-error: $err\n");   // e.g. outbound connections blocked
}
echo "http_status: $code\n";
$data = json_decode($resp, true);
if ($code === 200 && isset($data['content'][0]['text'])) {
    echo "reply: " . $data['content'][0]['text'] . "\n";
} else {
    echo "error_type: " . ($data['error']['type'] ?? 'unknown') . "\n";
    echo "error_message: " . ($data['error']['message'] ?? substr((string) $resp, 0, 200)) . "\n";
}
