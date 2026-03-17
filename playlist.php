<?php
require_once 'config.php';

header('Content-Type: audio/x-mpegurl');
header('Access-Control-Allow-Origin: *');

$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$basePath = $stalkerCredentials['base_path'];
$apiFile = $stalkerCredentials['api_file'];

$token = generate_token();
if (!$token) {
    http_response_code(500);
    die("# Failed to authenticate with portal");
}

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

$url = "http://{$host}{$basePath}/{$apiFile}";
$params = [
    'type'      => 'itv',
    'action'    => 'get_all_channels',
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
    die("# Failed to fetch channels: HTTP $httpCode\n$body");
}

$data = json_decode($body, true);
$channels = $data['js']['data'] ?? [];

if (empty($channels)) {
    die("# No channels found");
}

// Get categories for group titles
$catParams = $params;
$catParams['type'] = 'itv';
$catParams['action'] = 'get_genres';
$catUrl = $url . '?' . http_build_query($catParams);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $catUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, generateDeviceHeaders($mac, $token));
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
$catResponse = curl_exec($ch);
curl_close($ch);

$categoryMap = [];
if ($catResponse) {
    $catData = json_decode($catResponse, true);
    if (isset($catData['js']) && is_array($catData['js'])) {
        foreach ($catData['js'] as $cat) {
            if ($cat['id'] !== '*') {
                $categoryMap[$cat['id']] = $cat['title'] ?? 'Unknown';
            }
        }
    }
}

// Build base URL for play.php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/play.php?id=';

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
    $catId = $channel['tv_genre_id'] ?? '';
    $group = $categoryMap[$catId] ?? 'Uncategorized';
    
    // Build full logo URL
    if ($logo && !preg_match('/^https?:\/\//', $logo)) {
        $logo = "http://$host{$basePath}/misc/logos/320/$logo";
    }
    
    echo "#EXTINF:-1 tvg-id=\"$id\" tvg-name=\"$name\" tvg-logo=\"$logo\" group-title=\"$group\",$name\n";
    echo $baseUrl . $id . ".m3u8\n";
}
?>
