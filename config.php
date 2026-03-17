<?php
// Configuration file for Stalker Portal credentials
$stalkerCredentials = [
    'host' => 'main.light-ott.net',
    'mac' => '00:1A:79:66:94:44'
];

/**
 * Generate dynamic device hashes from MAC (same as Python script)
 */
function generateDeviceHashes($mac) {
    $mac_clean = strtoupper($mac);
    
    // SN: first 13 chars of MD5 (uppercase)
    $sn = strtoupper(substr(md5($mac_clean), 0, 13));
    
    // Device ID 1: SHA256 of MAC
    $dev_id = strtoupper(hash('sha256', $mac_clean));
    
    // Device ID 2: SHA256 of MAC + 'mag250'
    $dev_id2 = strtoupper(hash('sha256', $mac_clean . 'mag250'));
    
    // Signature: SHA256 of MAC + 'signature'
    $signature = strtoupper(hash('sha256', $mac_clean . 'signature'));
    
    return [
        'sn' => $sn,
        'dev_id' => $dev_id,
        'dev_id2' => $dev_id2,
        'signature' => $signature
    ];
}

/**
 * Build headers with device spoofing (MAG250)
 */
function generateDeviceHeaders($mac, $token = '') {
    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG250 stbapp ver: 4 rev: 250 Safari/533.3",
        "X-User-Agent: Model: MAG250; Link: Ethernet",
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

/**
 * Perform handshake and return token
 */
function handshake($host, $mac, $forceRegenerate = false) {
    static $tokenCache = null;
    
    if (!$forceRegenerate && $tokenCache) {
        return $tokenCache;
    }
    
    $hashes = generateDeviceHashes($mac);
    $sn = $hashes['sn'];
    $dev_id = $hashes['dev_id'];
    $dev_id2 = $hashes['dev_id2'];
    $signature = $hashes['signature'];
    
    $timestamp = time();
    $random = rand(100000, 999999);
    
    // Metrics JSON (same as Python)
    $metrics = json_encode([
        'mac' => $mac,
        'sn' => $sn,
        'model' => 'MAG250',
        'type' => 'STB',
        'uid' => $dev_id,
        'random' => $random
    ]);
    
    $url = "http://{$host}/stalker_portal/server/load.php";
    $params = [
        'type' => 'stb',
        'action' => 'handshake',
        'token' => '',
        'JsHttpRequest' => '1-xml',
        'sn' => $sn,
        'stb_type' => 'MAG250',
        'client_type' => 'STB',
        'device_id' => $dev_id,
        'device_id2' => $dev_id2,
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

/**
 * Get token (cached)
 */
function generate_token($forceRegenerate = false) {
    global $stalkerCredentials;
    return handshake($stalkerCredentials['host'], $stalkerCredentials['mac'], $forceRegenerate);
}
?>
