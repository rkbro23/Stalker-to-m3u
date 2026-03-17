<?php
require_once 'config.php';

header('Content-Type: audio/x-mpegurl');
header('Access-Control-Allow-Origin: *');

$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$token = generate_token();

if (!$token) {
    http_response_code(500);
    die("# Failed to authenticate with portal");
}

// Get channels
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
    'action' => 'get_all_channels',
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
    http_response_code(500);
    die("# Failed to fetch channels: HTTP $httpCode");
}

$data = json_decode($body, true);
$channels = $data['js']['data'] ?? [];

if (empty($channels)) {
    die("# No channels found");
}

// Build base URL for play.php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/play.php?id=';

// Generate M3U
echo "#EXTM3U\n";

foreach ($channels as $channel) {
    $cmd = $channel['cmd'] ?? '';
    $id = '';
    
    if (preg_match('/ch\/(\d+)/', $cmd, $matches)) {
        $id = $matches[1];
    }
    
    if (empty($id)) continue;
    
    $name = $channel['name'] ?? 'Unknown';
    $logo = $channel['logo'] ?? '';
    $genre = $channel['tv_genre_id'] ?? '0';
    
    // Get category name (simplified - you can fetch genres too)
    $group = "Category $genre";
    
    echo "#EXTINF:-1 tvg-id=\"$id\" tvg-name=\"$name\" tvg-logo=\"http://$host/stalker_portal/misc/logos/320/$logo\" group-title=\"$group\",$name\n";
    echo $baseUrl . $id . ".m3u8\n";
}
?>
