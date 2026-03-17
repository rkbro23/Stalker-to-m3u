<?php
// Configuration for main.light-ott.net
$stalkerCredentials = [
    'host'      => 'main.light-ott.net',
    'mac'       => '00:1A:79:66:94:44',
    'base_path' => '/c',               // from http://main.light-ott.net/c/
    'api_file'  => 'portal.php',        // the API endpoint
    'stb_type'  => 'MAG270'             // as seen in debug
];

/**
 * Generate device hashes exactly like the scanner (Ultima_SpeedX.py)
 */
function generateDeviceHashes($mac) {
    $mac_clean = strtoupper($mac);
    
    // Full MD5 of MAC (uppercase)
    $sn_full = strtoupper(md5($mac_clean));
    // SN cut: first 13 chars (what the portal expects)
    $sn_cut = substr($sn_full, 0, 13);
    
    // Device ID 1: SHA256 of MAC
    $dev_id = strtoupper(hash('sha256', $mac_clean));
    
    // Device ID 2: SHA256 of sn_cut
    $dev_id2 = strtoupper(hash('sha256', $sn_cut));
    
    // Signature: SHA256 of sn_cut . mac
    $signature = strtoupper(hash('sha256', $sn_cut . $mac_clean));
    
    return [
        'sn_cut'    => $sn_cut,
        'dev_id'    => $dev_id,
        'dev_id2'   => $dev_id2,
        'signature' => $signature
    ];
}

/**
 * Build headers with device spoofing (MAG270)
 */
function generateDeviceHeaders($mac, $token = '') {
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

/**
 * Perform handshake and return token
 */
function handshake($host, $mac, $forceRegenerate = false) {
    static $tokenCache = null;
    
    if (!$forceRegenerate && $tokenCache) {
        return $tokenCache;
    }
    
    global $stalkerCredentials;
    $hashes = generateDeviceHashes($mac);
    
    $timestamp = time();
    $random = rand(100000, 999999);
    
    // Metrics JSON (same as scanner)
    $metrics = json_encode([
        'mac'    => $mac,
        'sn'     => $hashes['sn_cut'],
        'model'  => $stalkerCredentials['stb_type'],
        'type'   => 'STB',
        'uid'    => $hashes['dev_id'],
        'random' => $random
    ]);
    
    // Build API URL – now using /c/portal.php
    $url = "http://{$host}{$stalkerCredentials['base_path']}/{$stalkerCredentials['api_file']}";
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
