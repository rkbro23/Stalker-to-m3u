<?php
require_once 'config.php';

echo "🔍 RK Debug Tool\n\n";

$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];

echo "Host: $host\n";
echo "MAC: $mac\n";
echo "SN: {$stalkerCredentials['sn']}\n\n";

// Test 1: Basic connection
echo "Test 1: Checking if portal is reachable...\n";
$ch = curl_init("http://$host/stalker_portal/c/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response length: " . strlen($response) . " chars\n\n";

// Test 2: Try handshake manually
echo "Test 2: Attempting handshake...\n";
$token = handshake($host, $mac, true);
if ($token) {
    echo "✅ Token received: " . substr($token, 0, 20) . "...\n";
} else {
    echo "❌ Handshake failed\n";
    
    // Try with curl directly to see raw response
    echo "\nRaw handshake attempt:\n";
    
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
        'type' => 'stb',
        'action' => 'handshake',
        'token' => '',
        'JsHttpRequest' => '1-xml',
        'sn' => $sn,
        'stb_type' => $stalkerCredentials['stb_type'],
        'device_id' => $deviceId,
        'device_id2' => $stalkerCredentials['device_id2'],
        'signature' => $stalkerCredentials['signature'],
        'timestamp' => $timestamp,
        'metrics' => $metrics
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    echo "URL: $fullUrl\n\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, generateDeviceHeaders($mac));
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    echo "HTTP Code: $httpCode\n";
    echo "Headers:\n$headers\n";
    echo "Body:\n$body\n";
}
?>
