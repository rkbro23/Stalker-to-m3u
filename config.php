<?php
// Configuration file for Stalker Portal credentials
$stalkerCredentials = [
    'host' => 'main.light-ott.net',
    'mac' => '00:1A:79:66:94:44',
    'sn' => '9511a526a3131cbba83231cf0249a86a',
    'device_id1' => 'A5C727E9313AB820B34764A3EB9A8CB9EC338BD5644D64C5D1329271730B8439',
    'device_id2' => 'A5C727E9313AB820B34764A3EB9A8CB9EC338BD5644D64C5D1329271730B8439',
    'signature' => 'B6FF5587BDC130BDE950A6DBEE5117CD0719441CD644C967E9F784B962A9FE5C',
    'stb_type' => 'MAG270'
];

function generateDeviceHeaders($mac, $token = '') {
    global $stalkerCredentials;
    
    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG270 stbapp ver: 2 rev: 250 Safari/533.3",
        "X-User-Agent: Model: MAG270; Link: Ethernet",
        "Cookie: mac={$mac}; stb_lang=en; timezone=GMT",
        "Accept: application/json, application/javascript, text/javascript, text/html",
        "Accept-Encoding: gzip, deflate",
        "Connection: Keep-Alive"
    ];
    
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    
    return $headers;
}

function handshake($host, $mac, $forceRegenerate = false) {
    static $tokenCache = null;
    
    if (!$forceRegenerate && $tokenCache) {
        return $tokenCache;
    }
    
    global $stalkerCredentials;
    
    // Build handshake URL with device signatures
    $timestamp = time();
    $sn = $stalkerCredentials['sn'];
    $deviceId = $stalkerCredentials['device_id1'];
    $deviceId2 = $stalkerCredentials['device_id2'];
    $signature = $stalkerCredentials['signature'];
    $stbType = $stalkerCredentials['stb_type'];
    
    $metrics = json_encode([
        'mac' => $mac,
        'sn' => $sn,
        'model' => $stbType,
        'type' => 'STB',
        'uid' => $deviceId,
        'random' => rand(100000, 999999)
    ]);
    
    $protocol = 'http'; // Change to 'https' if needed
$url = "{$protocol}://{$host}/stalker_portal/server/load.php";
    $params = [
        'type' => 'stb',
        'action' => 'handshake',
        'token' => '',
        'JsHttpRequest' => '1-xml',
        'sn' => $sn,
        'stb_type' => $stbType,
        'client_type' => 'STB',
        'device_id' => $deviceId,
        'device_id2' => $deviceId2,
        'signature' => $signature,
        'timestamp' => $timestamp,
        'metrics' => $metrics
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, generateDeviceHeaders($mac));
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        error_log("Handshake failed: HTTP $httpCode - Body: $body");
        return null;
    }
    
    $data = json_decode($body, true);
    $token = $data['js']['token'] ?? null;
    
    if ($token) {
        $tokenCache = $token;
        return $token;
    }
    
    error_log("No token in response: $body");
    return null;
}

function generate_token($forceRegenerate = false) {
    global $stalkerCredentials;
    return handshake($stalkerCredentials['host'], $stalkerCredentials['mac'], $forceRegenerate);
}
?>
