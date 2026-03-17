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
$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$token = generate_token();

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// If the target URL is relative (e.g., just parameters), build full API URL
if (!preg_match('/^https?:\/\//', $targetUrl)) {
    $targetUrl = "http://{$host}{$stalkerCredentials['base_path']}/{$stalkerCredentials['api_file']}?" . ltrim($targetUrl, '?');
}

// Add device signatures if not already present
$hashes = generateDeviceHashes($mac);
$timestamp = time();
$random = rand(100000, 999999);
$metrics = json_encode([
    'mac'    => $mac,
    'sn'     => $hashes['sn_cut'],
    'model'  => $stalkerCredentials['stb_type'],
    'type'   => 'STB',
    'uid'    => $hashes['dev_id'],
    'random' => $random
]);

$separator = (strpos($targetUrl, '?') === false) ? '?' : '&';
$targetUrl .= $separator . http_build_query([
    'sn'        => $hashes['sn_cut'],
    'stb_type'  => $stalkerCredentials['stb_type'],
    'client_type' => 'STB',
    'device_id' => $hashes['dev_id'],
    'device_id2'=> $hashes['dev_id2'],
    'signature' => $hashes['signature'],
    'timestamp' => $timestamp,
    'metrics'   => $metrics
]);

$headers = generateDeviceHeaders($mac, $token);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

if ($httpCode >= 400) {
    // Retry with fresh token
    $token = generate_token(true);
    if ($token) {
        $headers = generateDeviceHeaders($mac, $token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        curl_close($ch);
    }
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode(['error' => "HTTP $httpCode", 'response' => $body]);
    exit;
}

// Try to decode JSON
$decoded = json_decode($body, true);
if ($decoded !== null) {
    echo json_encode($decoded);
} else {
    echo $body;
}
?>
