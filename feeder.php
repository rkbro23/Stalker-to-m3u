<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'URL parameter missing']);
    exit;
}

$targetUrl = $_GET['url'];
$host      = $stalkerCredentials['host'];
$mac       = $stalkerCredentials['mac'];
$basePath  = $stalkerCredentials['base_path'];
$apiFile   = $stalkerCredentials['api_file'];

$token = generate_token();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// Relative URL (sirf parameters) → full URL banao
if (!preg_match('/^https?:\/\//', $targetUrl)) {
    $targetUrl = "http://{$host}{$basePath}/{$apiFile}?" . ltrim($targetUrl, '?');
}

// Device signatures add karo
$hashes    = generateDeviceHashes($mac);
$timestamp = time();
$random    = rand(100000, 999999);
$metrics   = json_encode([
    'mac'    => $mac,
    'sn'     => $hashes['sn_cut'],
    'model'  => $stalkerCredentials['stb_type'],
    'type'   => 'STB',
    'uid'    => $hashes['dev_id'],
    'random' => $random
]);

$separator  = (strpos($targetUrl, '?') === false) ? '?' : '&';
$targetUrl .= $separator . http_build_query([
    'sn'          => $hashes['sn_cut'],
    'stb_type'    => $stalkerCredentials['stb_type'],
    'client_type' => 'STB',
    'device_id'   => $hashes['dev_id'],
    'device_id2'  => $hashes['dev_id2'],
    'signature'   => $hashes['signature'],
    'timestamp'   => $timestamp,
    'metrics'     => $metrics
]);

function doRequest($url, $headers) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => 'gzip'
    ]);
    $response   = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body       = substr($response, $headerSize);
    curl_close($ch);
    return [$httpCode, $body];
}

$headers           = generateDeviceHeaders($mac, $token);
[$httpCode, $body] = doRequest($targetUrl, $headers);

// 4xx/5xx pe fresh token se retry
if ($httpCode >= 400) {
    $token = generate_token(true);
    if ($token) {
        $headers           = generateDeviceHeaders($mac, $token);
        [$httpCode, $body] = doRequest($targetUrl, $headers);
    }
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode(['error' => "HTTP $httpCode", 'response' => $body]);
    exit;
}

$decoded = json_decode($body, true);
echo ($decoded !== null) ? json_encode($decoded) : $body;
?>
