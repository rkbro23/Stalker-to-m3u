<?php
require_once 'config.php';

echo "🔍 RK Debug Tool\n\n";
echo "Host: {$stalkerCredentials['host']}\n";
echo "MAC: {$stalkerCredentials['mac']}\n";

$hashes = generateDeviceHashes($stalkerCredentials['mac']);
echo "SN (cut): {$hashes['sn_cut']}\n";
echo "Device ID: " . substr($hashes['dev_id'], 0, 16) . "...\n\n";

// Test 1: Check portal reachability
echo "Test 1: Checking if portal is reachable...\n";
$ch = curl_init("http://{$stalkerCredentials['host']}{$stalkerCredentials['base_path']}/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $httpCode\n";
echo "Response length: " . strlen($response) . " chars\n\n";

// Test 2: Handshake
echo "Test 2: Attempting handshake...\n";
$token = handshake($stalkerCredentials['host'], $stalkerCredentials['mac'], true);
if ($token) {
    echo "✅ Token received: " . substr($token, 0, 20) . "...\n";
} else {
    echo "❌ Handshake failed\n";
    
    // Show raw handshake attempt
    $hashes = generateDeviceHashes($stalkerCredentials['mac']);
    $timestamp = time();
    $random = rand(100000, 999999);
    $metrics = json_encode([
        'mac'    => $stalkerCredentials['mac'],
        'sn'     => $hashes['sn_cut'],
        'model'  => $stalkerCredentials['stb_type'],
        'type'   => 'STB',
        'uid'    => $hashes['dev_id'],
        'random' => $random
    ]);
    
    $url = "http://{$stalkerCredentials['host']}{$stalkerCredentials['base_path']}/{$stalkerCredentials['api_file']}";
    $params = [
        'type'      => 'stb',
        'action'    => 'handshake',
        'token'     => '',
        'JsHttpRequest' => '1-xml',
        'sn'        => $hashes['sn_cut'],
        'stb_type'  => $stalkerCredentials['stb_type'],
        'client_type' => 'STB',
        'device_id' => $hashes['dev_id'],
        'device_id2'=> $hashes['dev_id2'],
        'signature' => $hashes['signature'],
        'timestamp' => $timestamp,
        'metrics'   => $metrics
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    echo "\nRaw handshake attempt:\nURL: $fullUrl\n\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, generateDeviceHeaders($stalkerCredentials['mac']));
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    
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
