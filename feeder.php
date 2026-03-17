<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'URL parameter is missing']);
    exit;
}

$targetUrl = $_GET['url'];
$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$token = generate_token();

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Failed to authenticate with portal']);
    exit;
}

// Parse the target URL to extract action and type
$parsedUrl = parse_url($targetUrl);
parse_str($parsedUrl['query'] ?? '', $queryParams);

// Build proper request with device signatures
$timestamp = time();
$sn = $stalkerCredentials['sn'];
$deviceId = $stalkerCredentials['device_id1'];
$deviceId2 = $stalkerCredentials['device_id2'];
$signature = $stalkerCredentials['signature'];
$stbType = $stalkerCredentials['stb_type'];

// Add device params to URL if they're not already there
if (strpos($targetUrl, 'sn=') === false) {
    $metrics = json_encode([
        'mac' => $mac,
        'sn' => $sn,
        'model' => $stbType,
        'type' => 'STB',
        'uid' => $deviceId,
        'random' => rand(100000, 999999)
    ]);
    
    $separator = (strpos($targetUrl, '?') === false) ? '?' : '&';
    $targetUrl .= $separator . http_build_query([
        'sn' => $sn,
        'stb_type' => $stbType,
        'client_type' => 'STB',
        'device_id' => $deviceId,
        'device_id2' => $deviceId2,
        'signature' => $signature,
        'timestamp' => $timestamp,
        'metrics' => $metrics
    ]);
}

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
$headersReceived = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => "Failed to fetch data: $error"]);
    exit;
}

if ($httpCode >= 400) {
    // Try once more with fresh token
    $token = generate_token(true);
    
    if ($token) {
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
        $headersReceived = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);
    }
    
    if ($httpCode >= 400) {
        http_response_code($httpCode);
        echo json_encode([
            'error' => "Server returned error: HTTP $httpCode",
            'response' => $body
        ]);
        exit;
    }
}

// Try to decode JSON response
$decoded = json_decode($body, true);
if ($decoded !== null) {
    echo json_encode($decoded);
} else {
    // If not JSON, return as is (might be XML or plain text)
    echo $body;
}
?>
