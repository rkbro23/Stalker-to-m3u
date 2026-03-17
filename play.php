<?php
require_once 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
set_time_limit(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(204);
}

if (empty($_GET['id'])) {
    exit("Error: Missing 'id' parameter");
}

$id = preg_replace('/\.m3u8$/', '', $_GET['id']);
$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];

// Get fresh token
$token = generate_token();
if (!$token) {
    exit("Error: Failed to authenticate with portal");
}

// Create stream link
$timestamp = time();
$sn = $stalkerCredentials['sn'];
$deviceId = $stalkerCredentials['device_id1'];

$metrics = json_encode([
    'mac' => $mac,
    'sn' => $sn,
    'model' => $stalkerCredentials['stb_type'],
    'type' => 'STB',
    'uid' => $deviceId,
    'random' => rand(100000, 999999)
]);

$url = "http://{$host}/stalker_portal/server/load.php";
$params = [
    'type' => 'itv',
    'action' => 'create_link',
    'cmd' => 'ffrt http://localhost/ch/' . $id,
    'JsHttpRequest' => '1-xml',
    'sn' => $sn,
    'device_id' => $deviceId,
    'device_id2' => $stalkerCredentials['device_id2'],
    'signature' => $stalkerCredentials['signature'],
    'timestamp' => $timestamp,
    'metrics' => $metrics
];

$fullUrl = $url . '?' . http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, generateDeviceHeaders($mac, $token));
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

if ($httpCode != 200) {
    // Try once more with new token
    $token = generate_token(true);
    if (!$token) {
        exit("Error: Authentication failed");
    }
    
    // Retry with new token...
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, generateDeviceHeaders($mac, $token));
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    if ($httpCode != 200) {
        exit("Error: Failed to get stream URL (HTTP $httpCode)");
    }
}

$data = json_decode($body, true);
$streamUrl = $data['js']['cmd'] ?? '';

if (empty($streamUrl)) {
    exit("Error: No stream URL in response");
}

// Clean up stream URL
$streamUrl = str_replace('ffrt ', '', $streamUrl);
$streamUrl = str_replace('ffmpeg ', '', $streamUrl);
$streamUrl = trim($streamUrl, '"');

// Proxy the stream
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $streamUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG270 stbapp ver: 2 rev: 250 Safari/533.3",
    "Accept: */*"
]);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headersReceived = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

// Process M3U8 and fix relative paths
if (strpos($body, '#EXTM3U') !== false) {
    $baseUrl = parse_url($finalUrl, PHP_URL_SCHEME) . '://' . parse_url($finalUrl, PHP_URL_HOST);
    if ($port = parse_url($finalUrl, PHP_URL_PORT)) {
        $baseUrl .= ":$port";
    }
    $basePath = dirname(parse_url($finalUrl, PHP_URL_PATH));
    
    $lines = explode("\n", $body);
    $processed = [];
    foreach ($lines as $line) {
        if (preg_match('/\.(ts|m3u8|m3u)(\?.*)?$/i', trim($line)) && !filter_var($line, FILTER_VALIDATE_URL)) {
            $line = ltrim($line, '/');
            $processed[] = $baseUrl . $basePath . '/' . $line;
        } else {
            $processed[] = $line;
        }
    }
    $body = implode("\n", $processed);
}

header('Content-Type: application/vnd.apple.mpegurl');
header('Content-Disposition: inline; filename="stream.m3u8"');
header('Cache-Control: no-cache');
echo $body;
?>
