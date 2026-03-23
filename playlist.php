<?php
require_once 'config.php';

header('Content-Type: audio/x-mpegurl');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$host     = $stalkerCredentials['host'];
$mac      = $stalkerCredentials['mac'];
$basePath = $stalkerCredentials['base_path'];
$apiFile  = $stalkerCredentials['api_file'];

$token = generate_token();
if (!$token) {
    http_response_code(500);
    die("# Failed to authenticate with portal\n");
}

$hashes = generateDeviceHashes($mac);

// play.php ka base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl  = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/play.php?id=';

// Selected categories (filter se aayengi)
$selectedCategories = [];
if (!empty($_GET['categories'])) {
    $selectedCategories = array_map('trim', explode(',', $_GET['categories']));
}

// Channels fetch karo
$params = [
    'type'          => 'itv',
    'action'        => 'get_all_channels',
    'JsHttpRequest' => '1-xml',
    'mac'           => $mac,
    'sn'            => $hashes['sn'],
    'device_id'     => $hashes['dev_id'],
    'device_id2'    => $hashes['dev_id2'],
    'signature'     => $hashes['signature']
];

// ✅ Correct URL: host + base_path + api_file
$url = "http://{$host}{$basePath}/{$apiFile}?" . http_build_query($params);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => generateDeviceHeaders($mac, $token),
    CURLOPT_ENCODING       => 'gzip',
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HEADER         => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || !$response) {
    http_response_code(500);
    die("# Failed to fetch channels: HTTP $httpCode\n");
}

$data     = json_decode($response, true);
$channels = $data['js']['data'] ?? [];

if (empty($channels)) {
    die("# No channels found\n");
}

// Category map channels se hi extract karo (alag API call ki zarurat nahi)
$categoryMap = [];
foreach ($channels as $ch) {
    $catId   = $ch['tv_genre_id']    ?? '';
    $catName = $ch['tv_genre_title'] ?? '';
    if (empty($catName)) {
        $catName = $catId ? "Category $catId" : 'Uncategorized';
    }
    if ($catId && !isset($categoryMap[$catId])) {
        $categoryMap[$catId] = $catName;
    }
}

// M3U output
echo "#EXTM3U\n";
$streamCount = 0;

foreach ($channels as $channel) {
    // Channel ID direct extract karo (Stalker id field deta hai)
    $id = $channel['id'] ?? '';
    if (empty($id)) {
        continue;
    }
    

    $name  = $channel['name']        ?? 'Unknown Channel';
    $catId = $channel['tv_genre_id'] ?? '';

    // Category filter
    if (!empty($selectedCategories) && !in_array($catId, $selectedCategories)) {
        continue;
    }

    $group = $categoryMap[$catId] ?? 'Uncategorized';

    // Logo
    $logo = $channel['logo'] ?? '';
    if (!empty($logo) && !preg_match('/^https?:\/\//', $logo)) {
        $logo = "http://$host/stalker_portal/misc/logos/320/$logo";
    }
    if (empty($logo)) {
        $logo = "https://i.ibb.co/39Nz2wg/stalker.png";
    }

    echo "#EXTINF:-1 tvg-id=\"$id\" tvg-name=\"" . addcslashes($name, '"') . "\" tvg-logo=\"$logo\" group-title=\"" . addcslashes($group, '"') . "\"," . $name . "\n";
    echo $baseUrl . $id . ".m3u8\n";
    $streamCount++;
}

if ($streamCount === 0 && !empty($selectedCategories)) {
    echo "# No channels found for selected categories\n";
}

echo "# Total channels: $streamCount\n";
if (!empty($selectedCategories)) {
    $names = array_map(fn($id) => $categoryMap[$id] ?? $id, $selectedCategories);
    echo "# Filtered by: " . implode(', ', $names) . "\n";
}
exit();
?>
