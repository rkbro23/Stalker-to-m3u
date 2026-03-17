<?php
require_once 'config.php';

header('Content-Type: audio/x-mpegurl');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$apiFile = $stalkerCredentials['api_file'];

$token = generate_token();
if (!$token) {
    http_response_code(500);
    die("# Failed to authenticate with portal\n");
}

$hashes = generateDeviceHashes($mac);

// Build base URL for play.php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/play.php?id=';

// Get selected categories from query parameter
$selectedCategories = [];
if (isset($_GET['categories']) && !empty($_GET['categories'])) {
    $selectedCategories = explode(',', $_GET['categories']);
    $selectedCategories = array_map('trim', $selectedCategories);
}

// Fetch all channels
$params = [
    'type' => 'itv',
    'action' => 'get_all_channels',
    'JsHttpRequest' => '1-xml',
    'mac' => $mac,
    'sn' => $hashes['sn'],
    'device_id' => $hashes['dev_id'],
    'device_id2' => $hashes['dev_id2'],
    'signature' => $hashes['signature']
];

$url = "http://{$host}/{$apiFile}?" . http_build_query($params);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => generateDeviceHeaders($mac, $token),
    CURLOPT_ENCODING => 'gzip',
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || !$response) {
    http_response_code(500);
    die("# Failed to fetch channels: HTTP $httpCode\n");
}

$data = json_decode($response, true);
$channels = $data['js']['data'] ?? [];

if (empty($channels)) {
    die("# No channels found\n");
}

// ✅ Extract category map from channels (no separate API call)
$categoryMap = [];
foreach ($channels as $channel) {
    $catId = $channel['tv_genre_id'] ?? '';
    $catName = $channel['tv_genre_title'] ?? '';
    
    // If no title, create a nice name from ID
    if (empty($catName)) {
        $catName = "Category $catId";
    }
    
    if ($catId && !isset($categoryMap[$catId])) {
        $categoryMap[$catId] = $catName;
    }
}

// Start M3U output
echo "#EXTM3U\n";
$streamCount = 0;

foreach ($channels as $channel) {
    // Extract channel ID from cmd
    $cmd = $channel['cmd'] ?? '';
    $id = '';
    
    if (preg_match('/ch\/(\d+)/', $cmd, $matches)) {
        $id = $matches[1];
    } elseif (preg_match('/\/(\d+)(\.ts|\?|$)/', $cmd, $matches)) {
        $id = $matches[1];
    }
    
    if (empty($id)) continue;
    
    $name = $channel['name'] ?? 'Unknown Channel';
    $catId = $channel['tv_genre_id'] ?? '';
    
    // Filter by selected categories if provided
    if (!empty($selectedCategories) && !in_array($catId, $selectedCategories)) {
        continue;
    }
    
    $group = $categoryMap[$catId] ?? 'Uncategorized';
    
    // Handle logo
    $logo = $channel['logo'] ?? '';
    if (!empty($logo) && !preg_match('/^https?:\/\//', $logo)) {
        $logo = "http://$host/stalker_portal/misc/logos/320/$logo";
    }
    if (empty($logo)) {
        $logo = "https://i.ibb.co/39Nz2wg/stalker.png";
    }
    
    // Output channel
    echo "#EXTINF:-1 tvg-id=\"$id\" tvg-name=\"" . addcslashes($name, "\"") . "\" tvg-logo=\"$logo\" group-title=\"" . addcslashes($group, "\"") . "\"," . $name . "\n";
    echo $baseUrl . $id . ".m3u8\n";
    
    $streamCount++;
}

// If no streams after filtering
if ($streamCount === 0 && !empty($selectedCategories)) {
    // Add a comment so player knows it's empty but not broken
    echo "# No channels found for selected categories\n";
}

// Optional: Add stats as comments (players ignore comments)
echo "# Total channels: $streamCount\n";
if (!empty($selectedCategories)) {
    echo "# Filtered by categories: " . implode(', ', array_map(function($id) use ($categoryMap) {
        return $categoryMap[$id] ?? $id;
    }, $selectedCategories)) . "\n";
}

exit();
?>
